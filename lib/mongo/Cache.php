<?php
    namespace Octo\Mongo;

    use Octo\File;
    use Octo\Inflector;
    use Octo\Arrays;
    use Octo\Exception;

    class Cache
    {
        private static $dir;

        public function __construct()
        {
            /* CLI case */
            defined('APPLICATION_ENV') || define('APPLICATION_ENV', 'production');

            self::init();
        }

        private function readFile($file)
        {
            $data = File::read($file);

            return strlen($data) > 0 ? $data : null;
        }

        private function writeFile($file, $data)
        {
            $fp = fopen($file, 'w');

            if (!flock($fp, LOCK_EX)) {
                throw new Exception("The file '$file' can not be locked.");
            }

            $result = fwrite($fp, $data);

            flock($fp, LOCK_UN);

            fclose($fp);

            umask(0000);

            chmod($file, 0777);

            return $result !== false;
        }

        private function deleteFile($file)
        {
            return File::delete($file);
        }

        public static function init()
        {
            self::$dir = Config::get('directory.store', STORAGE_PATH) . DS . 'redisCache_' . APPLICATION_ENV;

            if (!is_dir(self::$dir)) {
                umask(0000);
                mkdir(self::$dir, 0777);
            }
        }

        public function set($key, $value)
        {
            $file   = self::$dir . DS . $key . '.cache';
            $ttl    = self::$dir . DS . $key . '.ttl';

            $this->deleteFile($file);
            $this->deleteFile($ttl);

            $this->writeFile($file, serialize($value));

            return $this;
        }

        public function get($key, $default = null)
        {
            $file   = self::$dir . DS . $key . '.cache';
            $ttl    = self::$dir . DS . $key . '.ttl';

            $dataFile   = $this->readFile($file);
            $dataTtl    = $this->readFile($ttl);

            if (strlen($dataFile)) {
                if (strlen($dataTtl)) {
                    $diff = $dataTtl - time();

                    if ($diff < 0) {
                        $this->deleteFile($file);
                        $this->deleteFile($ttl);

                        return $default;
                    }
                }

                $data = unserialize($dataFile);

                return $data;
            }

            return $default;
        }

        public function expire($key, $ttl = 3600)
        {
            $ttl = self::$dir . DS . $key . '.ttl';
            $this->deleteFile($ttl);

            $this->writeFile($ttl, time() + $ttl);
        }

        public function del($key)
        {
            return $this->delete($key);
        }

        public function delete($key)
        {
            $file   = self::$dir . DS . $key . '.cache';
            $ttl    = self::$dir . DS . $key . '.ttl';

            $this->deleteFile($file);
            $this->deleteFile($ttl);

            return $this;
        }

        public function has($key)
        {
            $check = $this->get($key, false);

            return $check !== false;
        }

        public function keys($pattern = '*')
        {
            return glob(self::$dir . DS . $pattern . '.cache', GLOB_NOSORT);
        }

        public function incr($key, $by = 1)
        {
            $val = $this->get($key, 0);

            $val = (int) $val;
            $val += $by;

            $this->set($key, $val);

            return $val;
        }

        public function decr($key, $by = 1)
        {
            $val = $this->get($key, 1);

            $val = (int) $val;
            $val -= $by;
            $val = 0 > $val ? 0 : $val;

            $this->set($key, $val);

            return $val;
        }

        public function hset($h, $key, $value)
        {
            $dirH = self::$dir . DS . $h;

            if (!is_dir($dirH)) {
                umask(0000);
                mkdir($dirH, 0777);
            }

            return $this->set($h . DS . $key, $value);
        }

        public function hget($h, $key, $default = null)
        {
            $dirH = self::$dir . DS . $h;

            if (!is_dir($dirH)) {
                umask(0000);
                mkdir($dirH, 0777);
            }

            return $this->get($h . DS . $key, $default);
        }

        public function hgetall($h)
        {
            $dirH = self::$dir . DS . $h;

            if (!is_dir($dirH)) {
                umask(0000);
                mkdir($dirH, 0777);
            }

            $keys = glob($dirH . DS . '*', GLOB_NOSORT);

            $collection = [];

            if (count($keys)) {
                foreach ($keys as $cache) {
                    $k = str_replace('.cache', '', Arrays::last(explode(DS, $cache)));
                    $collection[$k] = unserialize(File::read($cache));
                }
            }

            return $collection;
        }

        public function hexpire($h, $key, $ttl = 3600)
        {
            $dirH = self::$dir . DS . $h;

            if (!is_dir($dirH)) {
                umask(0000);
                mkdir($dirH, 0777);
            }

            return $this->expire($h . DS . $key, $ttl);
        }

        public function hdel($h, $key)
        {
            return $this->hdelete($h, $key);
        }

        public function hdelete($h, $key)
        {
            $dirH = self::$dir . DS . $h;

            if (is_dir($dirH)) {
                return $this->delete($h . DS . $key);
            }

            return $this;
        }

        public function hincr($h, $key, $by = 1)
        {
            $dirH = self::$dir . DS . $h;

            if (!is_dir($dirH)) {
                umask(0000);
                mkdir($dirH, 0777);
            }

            return $this->incr($h . DS . $key, $by);
        }

        public function hdecr($h, $key, $by = 1)
        {
            $dirH = self::$dir . DS . $h;

            if (!is_dir($dirH)) {
                umask(0000);
                mkdir($dirH, 0777);
            }

            return $this->decr($h . DS . $key, $by);
        }

        public function hkeys($h, $pattern = '*')
        {
            return glob(self::$dir . DS . $h . DS . $pattern . '.cache', GLOB_NOSORT);
        }

        public function hhas($h, $key)
        {
            $check = $this->get($h . DS . $key, false);

            return $check !== false;
        }

        public function callback(\Closure $closure, $args = [], $ttl = 0)
        {
            $ref    = new \ReflectionFunction($closure);
            $key    = 'callback::' . sha1($ref->getFileName() . $ref->getStartLine() . serialize($args));

            $value = $this->get($key);

            if (!strlen($value)) {
                $value = call_user_func_array($closure, $args);

                $this->set($key, $value);

                if (0 < $ttl) {
                    $this->expire($key, $ttl);
                }
            }

            return $value;
        }
    }
