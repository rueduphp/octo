<?php
    namespace Octo\Mongo;

    class Redis
    {
        public static $instance;

        public function __construct($db, $table)
        {
            self::$instance = Db::instance($db, $table)->inCache(false);
        }

        public function exec($object = false, $count = false, $first = false)
        {
            if (!$object) {
                $hash   = self::$instance->getHash($object, $count, $first);
                $ageDb  = self::$instance->getAge();

                $key    = 'dbredis.exec.' . $hash . '.' . $ageDb;

                $cache = fmr()->get($key);

                if ($cache) {
                    self::$instance->reset();

                    return unserialize($cache);
                }

                $collection = call_user_func_array([static::$instance, 'exec'], func_get_args());

                fmr()->set($key, serialize($collection));

                return $collection;
            } else {
                return call_user_func_array([static::$instance, 'exec'], func_get_args());
            }
        }

        public static function __callStatic($method, $args)
        {
            return call_user_func_array([static::$instance, $method], $args);
        }
    }
