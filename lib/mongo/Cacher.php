<?php
    namespace Octo\Mongo;

    use Octo\Inflector;
    use Octo\Arrays;

    class Cacher
    {
        public static function __callStatic($method, $args)
        {
            $db = Inflector::uncamelize($method);

            if (fnmatch('*_*', $db)) {
                list($database, $table) = explode('_', $db, 2);
            } else {
                $database   = SITE_NAME;
                $table      = $db;
            }

            if (empty($args)) {
                return new Redis($database, $table);
            } elseif (count($args) == 1) {
                $id = Arrays::first($args);

                if (is_numeric($id)) {
                    return with(new Redis($database, $table))->find($id);
                }
            }
        }
    }
