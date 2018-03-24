<?php
    namespace Octo;

    use Countable;
    use Exception as NativeException;
    use Iterator;

    class Finder implements Countable, Iterator
    {
        private
            $instance,
            $dir,
            $date = [],
            $pattern = '*',
            $contains,
            $position = 0,
            $recursive = true,
            $made = false
        ;

        public function __construct()
        {
            $this->instance = token();
        }

        /**
         * @return Finder
         */
        public static function create(): self
        {
            return new self;
        }

        /**
         * @param string $dir
         * @return Finder
         *
         * @throws NativeException
         */
        public function in(string $dir): self
        {
            if (!is_dir($dir)) {
                throw new NativeException("$dir is not a valid directory.");
            }

            $this->dir = $dir;

            return $this;
        }

        /**
         * @param $dir
         * @return Finder
         * @throws NativeException
         */
        public function only($dir): self
        {
            $this->in($dir);

            $this->recursive = false;

            return $this;
        }

        /**
         * @param string $pattern
         * @return Finder
         */
        public function match($pattern = '*'): self
        {
            $this->pattern = $pattern;

            return $this;
        }

        /**
         * @param $extension
         * @return Finder
         */
        public function extension($extension): self
        {
            $this->pattern = '*.' . $extension;

            return $this;
        }

        /**
         * @param string $pattern
         * @return Finder
         */
        public function contains($pattern = '*'): self
        {
            $this->contains = $pattern;

            return $this;
        }

        /**
         * @param string $test
         * @return Finder
         */
        public function date(string $test): self
        {
            if (!preg_match('#^\s*(==|!=|[<>]=?|after|since|before|until)?\s*(.+?)\s*$#i', $test, $matches)) {
                throw new \InvalidArgumentException(sprintf('Don\'t understand "%s" as a date test.', $test));
            }

            try {
                $date = new \DateTime($matches[2]);
                $target = $date->format('U');
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a valid date.', $matches[2]));
            }

            $operator = isset($matches[1]) ? $matches[1] : '==';

            if ('since' === $operator || 'after' === $operator) {
                $operator = '>';
            }

            if ('until' === $operator || 'before' === $operator) {
                $operator = '<';
            }

            $this->date[] = [$operator, $target];

            return $this;
        }

        /**
         * @return string
         */
        private function getCacheKey(): string
        {
            return sha1(
                $this->instance .
                $this->recursive .
                serialize($this->date) .
                $this->contains .
                $this->pattern .
                $this->dir
            );
        }

        /**
         * @param $fileinfo
         * @return bool
         */
        protected function checkdate($fileinfo)
        {
            $date = current($this->date);

            return compare($fileinfo->getAge(), current($date), end($date));
        }

        /**
         * @return array
         */
        public function getIterator()
        {
            $key = $this->getCacheKey();

            if (true === $this->made) {
                return Registry::get('finder.' . $key, []);
            }

            $collection = [];

            if (is_dir($this->dir)) {
                if (true === $this->recursive) $all = $this->glob($this->dir . DS . $this->pattern);
                else $all = glob($this->dir . DS . $this->pattern);

                foreach ($all as $item) {
                    if (!is_dir($item)) {
                        $collection[] = $item;
                    }
                }

                if (!empty($this->date)) {
                    $newCollection = [];

                    foreach ($collection as $file) {
                        if (true === $this->checkdate(self::getInfo($file))) $newCollection[] = $file;
                    }

                    $collection = $newCollection;
                }

                if (!empty($this->contains)) {
                    $newCollection = [];

                    foreach ($collection as $file) {
                        $content = File::read($file);

                        if (preg_match($this->contains, $content))  $newCollection[] = $file;
                    }

                    $collection = $newCollection;
                }
            }

            Registry::set('finder.' . $key, $collection);

            $this->made = true;

            return $collection;
        }

        /**
         * @return \Generator
         */
        public function get()
        {
            foreach ($this->getIterator() as $row) {
                yield $row;
            }
        }

        /**
         * @param string $file
         *
         * @return Objet
         */
        public static function getInfo(string $file)
        {
            $info = o([]);

            $info->real_path    = $file;
            $info->file_name    = Arrays::last(explode(DS, $file));
            $info->extension    = Strings::lower(Arrays::last(explode('.', $file)));
            $info->name         = str_replace('.' . $info->extension, '', $info->file_name);
            $info->age          = filemtime($file);

            return $info;
        }

        /**
         * @return bool|Objet
         */
        public function getNext()
        {
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                return self::getInfo($cursor[$this->position]);
            }

            return false;
        }

        /**
         * @return bool|Objet
         */
        public function getPrev()
        {
            $cursor = $this->getIterator();
            $this->position--;

            if (isset($cursor[$this->position])) {
                $this->position++;

                return self::getInfo($cursor[$this->position]);
            }

            return false;
        }

        /**
         * @param int $pos
         * @return Finder
         */
        public function seek(int $pos = 0): self
        {
            $this->position = $pos;

            return $this;
        }

        /**
         * @param bool $model
         * @return bool|mixed|Objet
         */
        public function one($model = false)
        {
            return $this->seek()->current($model);
        }

        public function current($model = false)
        {
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                return self::getInfo($cursor[$this->position]);
            }

            return false;
        }

        public function rewind()
        {
            $this->position = 0;
        }

        /**
         * @return int|mixed
         */
        public function key()
        {
            return $this->position;
        }

        public function next()
        {
            ++$this->position;
        }

        /**
         * @return bool
         */
        public function valid()
        {
            $cursor = $this->getIterator();

            return isset($cursor[$this->position]);
        }

        /**
         * @param callable $closure
         * @return mixed
         */
        public function each(callable $closure)
        {
            $row = $this->getNext();

            if ($row) {
                return $closure($row);
            }

            return false;
        }

        /**
         * @return Objet
         */
        public function first()
        {
            return self::getInfo(current($this->getIterator()));
        }

        /**
         * @return Objet
         */
        public function last()
        {
            $i  =  $this->getIterator();

            return self::getInfo(end($i));
        }

        /**
         * @return Objet
         */
        public function youngest()
        {
            $files = $this->getIterator();

            $collection = [];

            foreach ($files as $file) {
                $collection[] = ['path' => $file, 'age' => filemtime($file)];
            }

            $row = coll($collection)->sortByDesc('age')->first();

            return self::getInfo($row['path']);
        }

        /**
         * @return Objet
         */
        public function oldest()
        {
            $files = $this->getIterator();

            $collection = [];

            foreach ($files as $file) {
                $collection[] = ['path' => $file, 'age' => filemtime($file)];
            }

            $row = coll($collection)->sortBy('age')->first();

            return self::getInfo($row['path']);
        }

        /**
         * @return bool
         */
        public function exists(): bool
        {
            return $this->count() > 0;
        }

        public function count()
        {
            return count($this->getIterator());
        }

        /**
         * @param $pattern
         * @param int $flags
         *
         * @return array
         */
        protected function glob($pattern, $flags = 0): array
        {
            $files = glob($pattern, $flags);

            foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
                $files = array_merge($files, $this->glob($dir . DS . basename($pattern), $flags));
            }

            return $files;
        }

        /**
         * @return Finder
         */
        public function fresh(): self
        {
            return new self;
        }
    }
