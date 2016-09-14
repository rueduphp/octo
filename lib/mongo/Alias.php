<?php
    namespace Octo\Mongo;

    use Octo\Arrays;

    class Alias
    {
        public static $db;
        public $model;

        public function __construct()
        {
            $class = get_called_class();

            if (strstr($class, '\\')) {
                $class = Arrays::last(explode('\\', $class));
                $class = str_replace('Model_', '', $class);
            }

            if (fnmatch('*_*', $class)) {
                list($database, $table) = explode('_', $class, 2);
            } else {
                $database   = SITE_NAME;
                $table      = $class;
            }

            self::$db = Db::instance($database, $table);

            $this->model = self::$db->model(func_get_args());
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->model, $m], $a);
        }

        public static function __callStatic($m, $a)
        {
            return call_user_func_array([self::$db, $m], $a);
        }
    }
