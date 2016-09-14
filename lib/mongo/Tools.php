<?php
    namespace Octo\Mongo;

    use Octo\Exception;
    use Octo\File;
    use Octo\Inflector;
    use Octo\Mongo\Db as Database;
    use Octo\Arrays;
    use Closure;

    class Tools
    {
        public static function row(array $row, $table, $fields, $foreign = null, $link = true, $database = null)
        {
            /* polymorphism */
            $fields = !Arrays::is($fields)
            ? strstr($fields, ',')
                ? explode(',', repl(' ', '', $fields))
                : [$fields]
            : $fields;

            if (fnmatch('*:*', $table)) {
                list($database, $table) = explode(':', $table, 2);
            } else {
                $database = is_null($database) ? SITE_NAME : $database;
            }

            $foreign = !strlen($foreign) ? $table . '_id' : $foreign;

            $val = isAke($row, $foreign, false);

            if (!Arrays::is($val) && (false === $val || strlen($val) == 0)) {
                return '';
            }

            if (!Arrays::is($val)) {
                $db     = Db::instance($database, $table);
                $target = $db->find($val, false);
                $value  = [];

                foreach ($fields as $field) {
                    if (!$field instanceof Closure) {
                        $value[] = isAke($target, $field, $field);
                    } else {
                        $value[] = $field($target);
                    }
                }

                $value = implode(' ', $value);

                if (true === $link) {
                    return '<a target="_blank" href="' . urlAction('update') . '/database/' . $database . '/table/' . $table . '/id/' . $val . '">' . $value . '</a>';
                } else {
                    return $value;
                }
            } else {
                $vals = $val;

                $return = [];

                foreach ($vals as $val) {
                    $db     = Db::instance($database, $table);
                    $target = $db->find($val, false);
                    $value  = [];

                    foreach ($fields as $field) {
                        if (!$field instanceof Closure) {
                            $value[] = isAke($target, $field, $field);
                        } else {
                            $value[] = $field($target);
                        }
                    }

                    $value = implode(' ', $value);

                    if (true === $link) {
                        $return[] = '<a target="_blank" href="' . urlAction('update') . '/database/' . $database . '/table/' . $table . '/id/' . $val . '">' . $value . '</a>';
                    } else {
                        $return[] = $value;
                    }
                }

                return implode(' <i class="fa fa-caret-right"></i> ', $return);
            }
        }

        public static function rows($idField, $table, $fields, $order = null, $data = null, $sort = 'ASC', $database = null)
        {
            /* polymorphism */
            $fields = !Arrays::is($fields)
            ? strstr($fields, ',')
                ? explode(',', repl(' ', '', $fields))
                : [$fields]
            : $fields;

            if (fnmatch('*:*', $table)) {
                list($database, $table) = explode(':', $table, 2);
            } else {
                $database = is_null($database) ? SITE_NAME : $database;
            }

            $db = Db::instance($database, $table);

            $html = '<select id="' . $idField . '">' . NL;
            $html .= '<option value="">Choisir</option>' . NL;

            $order = is_null($order) ? $db->pk() : $order;

            $data = is_null($data) ? $db->full()->order($order, $sort)->exec() : $data();

            if (count($data)) {
                foreach ($data as $target) {
                    $value = [];
                    $id = $target[$db->pk()];

                    foreach ($fields as $field) {
                        if (!$field instanceof Closure) {
                            $value[] = isAke($target, $field, $field);
                        } else {
                            $value[] = $field($target);
                        }
                    }

                    $value = implode(' ', $value);
                    $html .= '<option value="' . $id . '">' . \Thin\Html\Helper::display($value) . '</option>' . NL;
                }
            }

            $html .= '</select>';

            return $html;
        }

        public static function rowsForm($idField, $table, $fields, $order = null, $valueField = null, $required = true, $default = null, $data = null, $sort = 'ASC', $database = null)
        {
            /* polymorphism */
            $fields = !Arrays::is($fields)
            ? strstr($fields, ',')
                ? explode(',', repl(' ', '', $fields))
                : [$fields]
            : $fields;

            if (fnmatch('*:*', $table)) {
                list($database, $table) = explode(':', $table, 2);
            } else {
                $database = is_null($database) ? SITE_NAME : $database;
            }

            $default = null !== request()->$idField ? request()->$idField : $default;

            if ($default instanceof \Closure) {dd('ici');
                $default = $default();
            }

            $defined = !is_null($default) && null !== request()->$idField;

            $db = Db::instance($database, $table);

            $require = $required ? 'required ' : '';

            $multiple = $idField == request()->is_multiple ? 'multiple ' : '';

            $class      = strlen($multiple) ? 'multi-select' : 'form-control';
            $inputName  = strlen($multiple) ? $idField . '[]' : $idField;

            $html = '<span id="span_' . $idField . '"><select ' . $multiple . $require . 'class="' . $class . '" name="' . $inputName . '" id="' . $idField . '">' . NL;

            if (false === $defined && !strlen($multiple)) {
                $html .= '<option value="">Choisir</option>' . NL;
            }

            $order = is_null($order) ? 'id' : $order;

            $data = is_null($data) ? $db->where(['id', '>', 0])->order($order, $sort)->exec() : $data();

            if (count($data)) {
                foreach ($data as $target) {
                    $value = [];
                    $id = $target['id'];

                    foreach ($fields as $field) {
                        if (!$field instanceof Closure) {
                            $value[] = isAke($target, $field, $field);
                        } else {
                            $value[] = $field($target);
                        }
                    }

                    $value = implode(' ', $value);

                    if (strstr($_SERVER['REQUEST_URI'], '/create/')) {
                        $valueField = empty($valueField) ? $default : $valueField;
                    }

                    if (!Arrays::is($valueField)) {
                        $selected = ($valueField == $id) ? 'selected ' : '';
                    } else {
                        $selected = Arrays::in($id, $valueField) ? 'selected ' : '';
                    }

                    if (false === $defined) {
                        $html .= '<option ' . $selected . 'value="' . $id . '">' . \Thin\Html\Helper::display($value) . '</option>' . NL;
                    } else {
                        if (!strlen($selected)) {
                            $html .= '<option disabled value="' . $id . '">' . \Thin\Html\Helper::display($value) . '</option>' . NL;
                        } else {
                            $html .= '<option selected value="' . $id . '">' . \Thin\Html\Helper::display($value) . '</option>' . NL;
                        }
                    }
                }
            }

            $html .= '</select></span>';

            if (strlen($multiple)) {
                $css = '<style> .ms-container{
  background: transparent url(\'/crud/assets/img/switch.png\') no-repeat 50% 50%;
  width: 100%;
}

.ms-container:after{
  content: ".";
  display: block;
  height: 0;
  line-height: 0;
  font-size: 0;
  clear: both;
  min-height: 0;
  visibility: hidden;
}

.ms-container .ms-selectable, .ms-container .ms-selection{
  background: #fff;
  color: #555555;
  float: left;
  width: 45%;
  font-size: 10px;
}
.ms-container .ms-selection{
  float: right;
}

.ms-container .ms-list{
  -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
  -moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
  box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
  -webkit-transition: border linear 0.2s, box-shadow linear 0.2s;
  -moz-transition: border linear 0.2s, box-shadow linear 0.2s;
  -ms-transition: border linear 0.2s, box-shadow linear 0.2s;
  -o-transition: border linear 0.2s, box-shadow linear 0.2s;
  transition: border linear 0.2s, box-shadow linear 0.2s;
  border: 1px solid #ccc;
  -webkit-border-radius: 3px;
  -moz-border-radius: 3px;
  border-radius: 3px;
  position: relative;
  height: 200px;
  padding: 0;
  overflow-y: auto;
}

.ms-container .ms-list.ms-focus{
  border-color: rgba(82, 168, 236, 0.8);
  -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(82, 168, 236, 0.6);
  -moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(82, 168, 236, 0.6);
  box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(82, 168, 236, 0.6);
  outline: 0;
  outline: thin dotted \\9;
}

.ms-container ul{
  margin: 0;
  list-style-type: none;
  padding: 0;
}

.ms-container .ms-optgroup-container {
  width: 100%;
}

.ms-container .ms-optgroup-label {
  margin: 0;
  padding: 5px 0px 0px 5px;
  cursor: pointer;
  color: #999;
}

.ms-container .ms-selectable li.ms-elem-selectable,
.ms-container .ms-selection li.ms-elem-selection {
  border-bottom: 1px #eee solid;
  padding: 2px 10px;
  color: #555;
  font-size: 10px;
}

.ms-container .ms-selectable li.ms-hover,
.ms-container .ms-selection li.ms-hover {
  cursor: pointer;
  color: #fff;
  text-decoration: none;
  background-color: #08c;
}

.ms-container .ms-selectable li.disabled,
.ms-container .ms-selection li.disabled {
  background-color: #eee;
  color: #aaa;
  cursor: text;
}</style>';
                $js = "<script>$('#$idField').multiSelect();</script>";
                $html .= $css . $js;
            }

            return $html;
        }

        public static function vocabulary($id, $vocables)
        {
            /* polymorphism */
            $vocables = !Arrays::is($vocables)
            ? strstr($vocables, ',')
                ? explode(',', repl(' ', '', $vocables))
                : [$vocables]
            : $vocables;

            /* start to index 1 */
            $search = [];
            $i = 1;

            foreach ($vocables as $vocable) {
                $search[$i] = $vocable;
                $i++;
            }

            return isAke($search, $id, ' ');
        }

        public static function vocabularies($idField, $data)
        {
            /* polymorphism */
            $data = !Arrays::is($data)
            ? strstr($data, ',')
                ? explode(',', repl(' ', '', $data))
                : [$data]
            : $data;

            $html = '<select id="' . $idField . '">' . NL;
            $html .= '<option value="">Choisir</option>' . NL;

            /* start to index 1 */
            $vocables = [];
            $i = 1;

            foreach ($data as $vocable) {
                $vocables[$i] = $vocable;
                $i++;
            }

            if (count($vocables)) {
                foreach ($vocables as $id => $vocable) {
                    if (1 > $id) continue;

                    $value = isAke($vocables, $id, ' ');
                    $html .= '<option value="' . $id . '">' . \Thin\Html\Helper::display($value) . '</option>' . NL;
                }
            }

            $html .= '</select>';

            return $html;
        }

        public static function vocabulariesForm($idField, $data, $valueField = null, $required = true)
        {
            /* polymorphism */
            $data = !Arrays::is($data)
            ? strstr($data, ',')
                ? explode(',', repl(' ', '', $data))
                : [$data]
            : $data;

            $require = $required ? 'required ' : '';

            $html = '<select ' . $require . 'class="form-control" name="' . $idField . '" id="' . $idField . '">' . NL;
            $html .= '<option value="">Choisir</option>' . NL;

            /* start to index 1 */
            $vocables = [];
            $i = 1;

            foreach ($data as $vocable) {
                $vocables[$i] = $vocable;
                $i++;
            }

            if (count($vocables)) {
                foreach ($vocables as $id => $vocable) {
                    if (1 > $id) continue;
                    $selected = ($valueField == $id) ? 'selected ' : '';

                    $value = isAke($vocables, $id, ' ');
                    $html .= '<option ' . $selected . 'value="' . $id . '">' . \Thin\Html\Helper::display($value) . '</option>' . NL;
                }
            }

            $html .= '</select>';

            return $html;
        }

        public static function generate($database, $model, $fields = [], $overwrite = false)
        {
            if(!is_dir(APPLICATION_PATH . DS . 'models' . DS . 'CrudRedis')) {
                File::mkdir(APPLICATION_PATH . DS . 'models' . DS . 'CrudRedis');
            }

            $file = APPLICATION_PATH . DS . 'models' . DS . 'CrudRedis' . DS . ucfirst(Inflector::camelize($database)) . DS . ucfirst(Inflector::camelize($model)) . '.php';

            if (!is_dir(APPLICATION_PATH . DS . 'models' . DS . 'CrudRedis' . DS . ucfirst(Inflector::camelize($database)))) {
                File::mkdir(APPLICATION_PATH . DS . 'models' . DS . 'CrudRedis' . DS . ucfirst(Inflector::camelize($database)));
            }

            if (!File::exists($file) || $overwrite) {
                $db     = Db::instance($database, $model);
                $crud   = Crud::instance($db);

                File::delete($file);

                $tplModel       = File::read(__DIR__ . DS . 'Model.tpl');
                $tplField       = File::read(__DIR__ . DS . 'Field.tpl');

                $fields         = empty($fields) ? $crud->fields() : $fields;

                $singular       = ucfirst($model);
                $plural         = $singular . 's';
                $default_order  = $crud->pk();

                $tplModel       = str_replace(
                    [
                        '##singular##',
                        '##plural##',
                        '##default_order##',
                        '##foreigns##',
                        '##uniques##',
                        '##soft_delete##',
                        '##before_create##',
                        '##after_create##',
                        '##before_update##',
                        '##after_update##',
                        '##before_read##',
                        '##after_read##',
                        '##before_delete##',
                        '##after_delete##',
                        '##before_list##',
                        '##after_list##'
                    ],
                    [
                        $singular,
                        $plural,
                        $default_order,
                        '[]',
                        '[]',
                        'false',
                        'false',
                        'false',
                        'false',
                        'false',
                        'false',
                        'false',
                        'false',
                        'false',
                        'false',
                        'false'
                    ],
                    $tplModel
                );

                $fieldsSection = '';

                foreach ($fields as $field) {
                    if ($field != $crud->pk()) {
                        $label = substr($field, -3) == '_id'
                        ? ucfirst(
                            str_replace(
                                '_id',
                                '',
                                $field
                            )
                        )
                        : ucfirst(Inflector::camelize($field));

                        $fieldsSection .= str_replace(
                            [
                                '##field##',
                                '##form_type##',
                                '##helper##',
                                '##required##',
                                '##form_plus##',
                                '##length##',
                                '##is_listable##',
                                '##is_exportable##',
                                '##is_searchable##',
                                '##is_sortable##',
                                '##is_readable##',
                                '##is_creatable##',
                                '##is_updatable##',
                                '##is_deletable##',
                                '##content_view##',
                                '##content_list##',
                                '##content_search##',
                                '##content_create##',
                                '##label##'
                            ],
                            [
                                $field,
                                'text',
                                'false',
                                'true',
                                'false',
                                'false',
                                'true',
                                'true',
                                'true',
                                'true',
                                'true',
                                'true',
                                'true',
                                'true',
                                'false',
                                'false',
                                'false',
                                'false',
                                $label
                            ],
                            $tplField
                        );
                    }
                }

                $tplModel = str_replace(
                    '##fields##',
                    $fieldsSection,
                    $tplModel
                );

                File::put($file, $tplModel);
            }
        }
    }

