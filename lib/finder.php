<?php
    namespace Octo;

    use Countable;
    use Iterator;

    class Finder implements Countable, Iterator
    {
        private $instance, $dir, $pattern = '*', $contains, $position = 0, $recursive = true, $made = false;

        public function __construct()
        {
            $this->instance = token();
        }

        public static function create()
        {
            return new self;
        }

        public function in($dir)
        {
            if (!is_dir($dir)) {
                throw new Exception("$dir is not a valid directory.");
            }

            $this->dir = $dir;

            return $this;
        }

        public function only($dir)
        {
            $this->in($dir);

            $this->recursive = false;

            return $this;
        }

        public function match($pattern = '*')
        {
            $this->pattern = $pattern;

            return $this;
        }

        public function extension($extension)
        {
            $this->pattern = '*.' . $extension;

            return $this;
        }

        public function contains($pattern = '*')
        {
            $this->contains = $pattern;

            return $this;
        }

        private function getCacheKey()
        {
            return sha1($this->instance . $this->recursive . $this->contains . $this->pattern . $this->dir);
        }

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

        public static function getInfo($file)
        {
            $info = o([]);

            $info->real_path    = $file;
            $info->file_name    = Arrays::last(explode(DS, $file));
            $info->extension    = Strings::lower(Arrays::last(explode('.', $file)));
            $info->name         = str_replace('.' . $info->extension, '', $info->file_name);
            $info->age          = filemtime($file);

            return $info;
        }

        public function getNext()
        {
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                return self::getInfo($cursor[$this->position]);
            }

            return false;
        }

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

        public function seek($pos = 0)
        {
            $this->position = $pos;

            return $this;
        }

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

        public function key()
        {
            return $this->position;
        }

        public function next()
        {
            ++$this->position;
        }

        public function valid()
        {
            $cursor = $this->getIterator();

            return isset($cursor[$this->position]);
        }

        public function each(callable $closure)
        {
            $row = $this->getNext();

            if ($row) {
                return $closure($row);
            }

            return false;
        }

        public function first($model = false)
        {
            return self::getInfo(current($this->getIterator()));
        }

        public function last($model = false)
        {
            $i  =  $this->getIterator();

            return self::getInfo(end($i));
        }

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

        public function count()
        {
            return count($this->getIterator());
        }

        protected function glob($pattern, $flags = 0)
        {
            $files = glob($pattern, $flags);

            foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
                $files = array_merge($files, $this->glob($dir . DS . basename($pattern), $flags));
            }

            return $files;
        }

        public function fresh()
        {
            return new self;
        }
    }
