<?php
    namespace Octo;

    class OctaliaHelpers
    {
        public static function rename($db, $newName)
        {
            $tables = System::Table()->where(['db_id', '=', (int) $db['id']]);

            if ($tables->count() > 0) {
                foreach ($tables->get() as $table) {
                    $tmp = odb($db['name'], $table['name']);
                    $tmp->rename($newName . '.' . $table['name']);
                }
            }
        }

        public static function countTables($id)
        {
            return System::Table()->where(['db_id', '=', (int) $id])->count();
        }

        public static function countFields($id)
        {
            return System::Field()->where(['table_id', '=', (int) $id])->count() + 3;
        }

        public static function getDbName($id)
        {
            $db = System::Db()->row((int) $id);

            if ($db) {
                return $db['name'];
            }

            return null;
        }

        public static function countRecords($table)
        {
            $db = System::Db()->find((int) $table['db_id']);

            if ($db) {
                return fmr('core')->until(
                    'countRecords.' . $db->name . '.' . $table['name'],
                    function () use ($db, $table) {
                        return odb($db->name, $table['name'])->count();
                    },
                    odb($db->name, $table['name'])->age()
                );
            }

            return 0;
        }

        public static function renameTable($t, $newName)
        {
            $table = System::Table()->find((int) $t['id']);
            $oldName = self::getDbName($t['db_id']);

            $old = odb($oldName, $table['name']);
            $old->rename($oldName . '.' . $newName);
        }

        public static function deleteDb($db)
        {
            $dbTable    = System::Table();
            $dbField    = System::Field();
            $dbSchema   = System::Schema();
            $tables     = $dbTable->where(['db_id', '=', (int) $db['id']]);

            if ($tables->count() > 0) {
                foreach ($tables->get() as $table) {
                    self::deleteTable((int) $table['id']);
                }
            }

            $row = System::Db()->find((int) $db['id']);

            if ($row) {
                $row->delete();
            }
        }

        public static function deleteTable($id)
        {
            $dbTable    = System::Table();
            $dbField    = System::Field();
            $dbSchema   = System::Schema();

            $table = $dbTable->find((int) $id, false);

            if ($table) {
                $records = odb(self::getDbName($table['db_id']), $table['name']);

                odb(self::getDbName($table['db_id']), $table['name'])->drop();

                $fields = $dbField->where(['table_id', '=', (int) $table['id']]);

                if ($fields->count() > 0) {
                    foreach ($fields->get() as $field) {
                        self::deleteField((int) $field['id']);
                    }
                }

                $schema = $dbSchema->where(['table_id', '=', (int) $table['id']])->first(true);

                if (is_object($schema)) {
                    $schema->delete();
                }

                $dbTable->findOrFail((int) $table['id'])->delete();
            }
        }

        public static function deleteField($id)
        {
            $dbField = System::Field();

            $field = $dbField->find((int) $id, false);

            if ($field) {
                $field->delete();
            }
        }

        public static function clean($str)
        {
            $str = @trim($str);

            if (get_magic_quotes_gpc()) {
                $str = stripslashes($str);
            }

            $search     = array('&' , '"' , "'" , '<' , '>');
            $replace    = array('&amp;', '&quot;', '&#39;', '&lt;', '&gt;');
            $str        = str_replace($search, $replace, $str);

            return $str;
        }

        public static function paginate($paginator)
        {
            $links = $paginator->links();

            return str_replace(['&larr;', '&rarr;', '<li><a href="#" onclick'], ['<i class="material-icons">chevron_left</i>', '<i class="material-icons">chevron_right</i>', '<li class="waves-effect"><a href="#" onclick'], $links);
        }

        public static function fieldtypes()
        {
            return [
                'varchar',
                'text',
                'foreign_key',
                'select',
                'select_multiple',
                'checkbox',
                'radio',
                'switch',
                'file',
                'image',
                'video',
                'audio',
                'int',
                'float',
                'boolean',
                'datetime',
                'date',
                'time',
                'timestamp',
                'day',
                'month',
                'year',
                'currency',
                'password',
                'phone',
                'hyperlink',
                'email',
                'html',
                'php',
                'js',
                'css',
                'latitude',
                'longitude',
                'barcode',
                'sha1',
                'md5',
            ];
        }

        public static function cut($str, $length = 30, $end = '&hellip;')
        {
            return Inflector::limit($str, $length, $end);
        }

        public static function __callStatic($method, $args)
        {
            if ('include' == $method) {
                $partial    = array_shift($args);
                $a          = array_shift($args);

                if (!$a) $a = [];

                $file = path('views') . DS . $partial . '.phtml';

                if (is_file($file)) {
                    extract($a);
                    $content    = File::read($file);
                    $controller = Registry::get('app.controller');

                    $content = str_replace(
                        '$this->',
                        '$controller->',
                        $content
                    );

                    $content = str_replace(['{{', '}}'], ['<?php $controller->e("', '");?>'], $content);
                    $content = str_replace(['[[', ']]'], ['<?php $controller->trad("', '");?>'], $content);

                    ob_start();

                    eval(' namespace Octo; ?>' . $content . '<?php ');

                    $html = ob_get_contents();

                    ob_end_clean();

                    echo $html;
                }

                return;
            }
        }
    }
