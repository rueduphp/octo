<?php
    namespace Octo;

    class Entitykv
    {
        public static function __callStatic($method, $args)
        {
            $driver = Config::get('octalia.driver', 'sql') == 'sql' ? 'ldb' : 'odb';

            $db = Inflector::uncamelize($method);

            if (fnmatch('*_*', $db)) {
                list($database, $table) = explode('_', $db, 2);
            } else {
                $database   = def('SITE_NAME', 'core');
                $table      = $db;
            }

            if (empty($args)) {
                return $driver($database, $table);
            } elseif (count($args) == 1) {
                $id = array_shift($args);

                if (is_numeric($id)) {
                    return $driver($database, $table)->find($id);
                }
            }
        }
    }
