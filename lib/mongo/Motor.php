<?php
    namespace Octo\Mongo;

    use Octo\Arrays;
    use Octo\Exception;
    use Octo\Instance;
    use Octo\Inflector;

    class Motor
    {
        private $collection, $path;

        public function __construct($collection)
        {
            $this->collection   = $collection;
        }

        public function getPath()
        {
            return $this->collection;
        }

        public static function instance($collection)
        {
            $args   = func_get_args();
            $key    = sha1(serialize($args));
            $has    = Instance::has('redisMotor', $key);

            if (true === $has) {
                return Instance::get('redisMotor', $key);
            } else {
                return Instance::make('redisMotor', $key, new self($collection));
            }
        }

        public function write($name, $data = [])
        {
            $file = $this->getFile($name);

            $this->cache()->del($file);

            $data = is_array($data) ? var_export($data, 1) : var_export([$data], 1);
            $res = $this->cache()->set($file, "return " . $data . ';');

            return $this;
        }

        public function count($dir)
        {
            $path = $this->collection . '.' . $dir;

            $files = $this->cache()->keys($path . '.*');

            return count($files);
        }

        public function ids($dir)
        {
            return array_keys($this->all($dir));
        }

        public function all($dir)
        {
            $path = $this->collection . '.' . $dir;

            $files = $this->cache()->keys($path . '.*');

            $collection = [];

            foreach ($files as $file) {
                $content = $this->import($file);

                if (!Arrays::isAssoc($content)) {
                    $content = current($content);
                }

                $id = (int) Arrays::last(explode('.', $file));

                $collection[$id] = $content;
            }

            return $collection;
        }

        public function read($name, $default = null)
        {
            $file = $this->getFile($name);

            $content = $this->import($file);

            if ($content) {
                if (Arrays::isAssoc($content)) {
                    return $content;
                }

                return current($content);
            }

            return $default;
        }

        public function remove($name)
        {
            $file = $this->getFile($name);

            $this->cache()->del($file);

            return $this;
        }

        public function getFile($name)
        {
            $path = $this->collection;

            $tab = $tmp = explode('.', $name);

            $fileName = end($tmp);

            array_pop($tab);

            foreach ($tab as $subPath) {
                $path .= '.' . $subPath;
            }

            return $path . '.' . $fileName;
        }

        public function import($file)
        {
            $content = $this->cache()->get($file);

            if (!$content) {
                $content = 'return null;';
            }

            return eval($content);
        }

        public function cache()
        {
            if (is_null($this->clientCache)) {
                $this->clientCache =  lib('redys', ['motor.' . $this->collection]);
            }

            return $this->clientCache;
        }
    }
