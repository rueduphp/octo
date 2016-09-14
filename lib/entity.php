<?php
    namespace Octo;

    class Entity
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
                return db($database, $table);
            } elseif (count($args) == 1) {
                $id = array_shift($args);

                if (is_numeric($id)) {
                    return db($database, $table)->find($id);
                }
            }
        }
    }
