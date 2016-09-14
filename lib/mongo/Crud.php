<?php
    namespace Octo\Mongo;

    use Octo\Exception;
    use Octo\Instance;
    use Octo\File;
    use Octo\Container;
    use Octo\Inflector;
    use Octo\Mongo\Db as Database;
    use Octo\Arrays;
    use Octo\Paginator;

    class Crud
    {
        private $model, $config;

        public function __construct(Database $model)
        {
            $this->model = $model->inCache(false);

            $fileConfig = APPLICATION_PATH . DS . 'models' . DS . 'CrudRedis' . DS . ucfirst(Inflector::camelize($model->db)) . DS . ucfirst(Inflector::camelize($model->table)) . '.php';

            if (File::exists($fileConfig)) {
                $this->config = include($fileConfig);
            } else {
                $this->config = [];
            }

            if (!empty($this->config)) {
                $this->prepareFields();
            }

            if (get_magic_quotes_gpc()) {
                $_REQUEST = $this->stripslashes($_REQUEST);
            }
        }

        public static function instance(Database $model)
        {
            $key    = sha1($model->db . $model->table);
            $has    = Instance::has('DbredisCrud', $key);

            if (true === $has) {
                return Instance::get('DbredisCrud', $key);
            } else {
                return Instance::make('DbredisCrud', $key, new self($model));
            }
        }

        public function config()
        {
            return $this->config;
        }

        private function prepareFields()
        {
            $fields         = $this->fields();
            $configFields   = isAke($this->config, 'fields', false);

            if (!$configFields) {
                $this->config['fields'] = [];
            }

            foreach ($fields as $field) {
                $settings   = isAke($configFields, $field, false);
                $foreign    = substr($field, -3) == '_id';

                if (false === $settings && true === $foreign && $field != $this->pk()) {
                    $this->config['fields'][$field] = [];
                    $label = substr($field, 0, -3);
                    $this->config['fields'][$field]['label'] = ucfirst($label);
                }
            }
        }

        public function create()
        {
            if (true === context()->isPost()) {
                $this->model->post(true);

                return true;
            }

            return false;
        }

        public function read($id, $object = true)
        {
            try {
                $row = $this->model->findOrFail($id, $object);
            } catch (Exception $e) {
                $row = $this->model->create();
            }

            return $row;
        }

        public function update($id = false)
        {
            $pk = $this->model->pk();
            $id = false === $id ? isAke($_POST, $pk, false) : $id;

            if (false !== $id && true === context()->isPost()) {
                $this->model->post()->save();

                return true;
            }

            return false;
        }

        public function delete($id = false)
        {
            $pk = $this->model->pk();
            $id = false === $id ? isAke($_POST, $pk, false) : $id;

            if (false !== $id) {
                $this->model->delete($id);

                return true;
            }

            return false;
        }

        public function newRow()
        {
            return $this->model->create();
        }

        public function fields($keys = true)
        {
            $configFields   = isAke($this->config, 'fields', false);

            if (false === $configFields) {
                $row = $this->model->first(false, false);

                if (!empty($row)) {
                    unset($row['created_at']);
                    unset($row['updated_at']);
                    ksort($row);

                    return array_keys($row);
                }

                return ['id'];
            } else {
                $ignoreFields = isAke($this->config, 'ignore_fields', false);

                if (false === $ignoreFields) {
                    return array_merge(['id'], array_keys($configFields));
                } else {
                    $fields     = array_merge(['id'], array_keys($configFields));
                    $collection = [];

                    if (!Arrays::is($ignoreFields)) {
                        return $fields;
                    }

                    if (!count($ignoreFields)) {
                        return $fields;
                    }

                    foreach ($fields as $field) {
                        if (!Arrays::in($field, $ignoreFields)) {
                            array_push($collection, $field);
                        }
                    }

                    return $collection;
                }
            }
        }

        public function listing($customFields = false)
        {
            $fields         = $this->fields();
            $fieldInfos     = isAke($this->config, 'fields');
            $before_list    = isAke($this->config, 'before_list', false);

            if (false !== $before_list) {
                $before_list([]);
            }

            $fieldsSettings = Db::instance('core', 'datafieldssettings')
            ->where("table = " . $this->model->table)
            ->where("database = " . $this->model->db)
            ->where('user_id = ' . auth()->user()->getId())
            ->exec();

            $userSettings = [];

            if (count($fieldsSettings)) {
                foreach($fieldsSettings as $fieldSettings) {
                    foreach ($fieldSettings as $k => $v) {
                        if (strstr($k, 'is_')) {
                            $userSettings[$fieldSettings['field']][$k] = 1 == $v ? true : false;
                        }
                    }
                }
            }

            $tableSettings = Db::instance('core', 'datatablesettings')
            ->where("table = " . $this->model->table)
            ->where('database = ' . $this->model->db)
            ->where('user_id = ' . auth()->user()->getId())
            ->first();

            if (request()->getKill() == 1) {
                session('dataTableCrudRedis::' . $this->model->table)
                ->setPage(null)
                ->setOrder(null)
                ->setOrderDirection(null)
                ->setWhere(null);
            }

            $limit          = isAke($tableSettings, 'rows', isAke($this->config, 'items_by_page', Config::get('crud.items.number', 25)));

            $defaultOrder   = isAke($tableSettings, 'sort', isAke($this->config, 'default_order', $this->model->pk()));

            $defaultDir     = isAke($this->config, 'default_order_direction', 'ASC');
            $many           = isAke($this->config, 'many');
            $plus           = isAke($this->config, 'options_form', '');

            $readable       = isAke($this->config, 'readable', true);
            $updatable      = isAke($this->config, 'updatable', true);
            $duplicatable   = isAke($this->config, 'duplicatable', true);
            $deletable      = isAke($this->config, 'deletable', true);

            $optionsConfig  = ['readable' => $readable, 'updatable' => $updatable, 'duplicatable' => $duplicatable, 'deletable' => $deletable];


            $where          = isAke($_REQUEST, 'crud_where', null);
            $page           = isAke($_REQUEST, 'crud_page', 1);
            $order          = isAke($_REQUEST, 'crud_order', $defaultOrder);
            $orderDirection = isAke($_REQUEST, 'crud_order_direction', $defaultDir);
            $export         = isAke($_REQUEST, 'crud_type_export', false);

            $updated_at     = isAke($this->config, 'updated_at', false);
            $created_at     = isAke($this->config, 'created_at', false);

            $export = !strlen($export) ? false : $export;

            $offset = ($page * $limit) - $limit;

            if (!count($_POST)) {
                $sessionWhere           = session('dataTableCrudRedis::' . $this->model->table)->getWhere();
                $sessionPage            = session('dataTableCrudRedis::' . $this->model->table)->getPage();
                $sessionOrder           = session('dataTableCrudRedis::' . $this->model->table)->getOrder();
                $sessionOrderDirection  = session('dataTableCrudRedis::' . $this->model->table)->getOrderDirection();

                $where = !strlen($sessionWhere)
                ? $where
                : $sessionWhere;

                $page = !strlen($sessionPage)
                ? $where
                : $sessionPage;

                $order = !strlen($sessionOrder)
                ? $order
                : $sessionOrder;

                $orderDirection = !strlen($sessionOrderDirection)
                ? $orderDirection
                : $sessionOrderDirection;
            }

            $page = !is_numeric($page) ? 1 : $page;

            session('dataTableCrudRedis::' . $this->model->table)
            ->setPage($page)
            ->setOrder($order)
            ->setOrderDirection($orderDirection)
            ->setWhere($where);

            $whereData = '';

            if (!empty($where)) {
                $whereData = $this->parseQuery($where);
            }

            $db = call_user_func_array(['\\Dbredis\\Db', 'instance'], [$this->model->db, $this->model->table]);

            if (strstr($whereData, ' && ') || strstr($whereData, ' || ')) {
                $db = $this->model->query($whereData);
            } else {
                if (strlen($whereData)) {
                    $db = $this->model->where($whereData);
                } else {
                    $db = $this->model->full();
                }
            }

            $results    = $db->order($order, $orderDirection)->exec();

            if (count($results) < 1) {
                if (strlen($where)) {
                    return '<div class="alert alert-danger col-md-4 col-md-pull-4 col-md-push-4">La requête ne remonte aucun résultat.</div>';
                } else {
                    return '<div class="alert alert-info col-md-4 col-md-pull-4 col-md-push-4">Aucune donnée à afficher..</div>';
                }
            }

            if (false !== $export) {
                return $this->export($export, $results);
            }

            $total      = count($results);
            $last       = ceil($total / $limit);
            $paginator  = new Paginator($results, $page, $total, $limit, $last);
            $data       = $paginator->getItemsByPage();
            $pagination = $paginator->links();

            $start = ($limit * $page) - ($limit - 1);
            $end = $limit * $page;

            $end = $end > $total ? $total : $end;

            if (strlen($pagination)) {
                $pagination = '<div class="row">
                <div class="col-md-3">
                Enregistrements ' . $start . ' à ' . $end . ' sur ' . $total . '
                </div>
                <div class="col-md-9">
                ' . $pagination . '
                </div>
                </div>';
            } else {
                $pagination = '<div class="row"><div class="col-md-12">' . $total . ' enregistrements<br /><br /></div></div>';
            }

            $html = $pagination . '<div class="row"><div class="col-md-12"><form action="' . urlAction('list') . '/table/' . $this->model->table . '/database/' . $this->model->db . '" id="listForm" method="post">
            <input type="hidden" name="crud_page" id="crud_page" value="' . $page . '" /><input type="hidden" name="crud_order" id="crud_order" value="' . $order . '" /><input type="hidden" name="crud_order_direction" id="crud_order_direction"  value="' . $orderDirection . '" /><input type="hidden" id="crud_where" name="crud_where" value="' . \Thin\Crud::checkEmpty('crud_where') . '" /><input type="hidden" id="crud_type_export" name="crud_type_export" value="" />';

            if ($order != 'updated_at') {
                $html .= '<a rel="tooltip" title="Classer du plus récent au plus ancien" href="#" class="btn btn-default" onclick="recent(); return false;"><i class="fa fa-plus"></i> <i class="fa fa-clock-o"></i></a>&nbsp;&nbsp;<a rel="tooltip" title="Classer du plus ancien au plus récent" href="#" class="btn btn-default" onclick="old(); return false;"><i class="fa fa-minus"></i> <i class="fa fa-clock-o"></i></a><br><br>';
            } else {
                if ($orderDirection == 'ASC') {
                    $html .= '<a rel="tooltip" title="Classer du plus récent au plus ancien" href="#" class="btn btn-default" onclick="recent(); return false;"><i class="fa fa-plus"></i> <i class="fa fa-clock-o"></i></a><br><br>';
                } else {
                    $html .= '<a rel="tooltip" title="Classer du plus ancien au plus récent" href="#" class="btn btn-default" onclick="old(); return false;"><i class="fa fa-minus"></i> <i class="fa fa-clock-o"></i></a><br><br>';
                }
            }

            $html .= '<table style="clear: both;" class="table table-striped tablesorter table-bordered table-condensed table-hover">
                        <thead>
                        <tr>';

            if (Arrays::is($created_at)) {
                $label = isAke($created_at, 'label', 'Créé');

                if ('created_at' == $order) {
                    $directionJs = ('ASC' == $orderDirection) ? 'DESC' : 'ASC';
                    $js = 'orderGoPage(\'created_at\', \'' . $directionJs . '\');';
                    $html .= '<th><div onclick="' . $js . '" class="text-left field-sorting ' . Inflector::lower($orderDirection) . '" rel="created_at">'. \Thin\Html\Helper::display($label) . '</div></th>';
                } else {
                    $js = 'orderGoPage(\'created_at\', \'ASC\');';
                    $html .= '<th><div onclick="' . $js . '" class="text-left field-sorting" rel="created_at">'. \Thin\Html\Helper::display($label) . '</div></th>';
                }
            }

            if (Arrays::is($updated_at)) {
                $label = isAke($updated_at, 'label', 'MAJ');

                if ('updated_at' == $order) {
                    $directionJs = ('ASC' == $orderDirection) ? 'DESC' : 'ASC';
                    $js = 'orderGoPage(\'updated_at\', \'' . $directionJs . '\');';
                    $html .= '<th><div onclick="' . $js . '" class="text-left field-sorting ' . Inflector::lower($orderDirection) . '" rel="updated_at">'. \Thin\Html\Helper::display($label) . '</div></th>';
                } else {
                    $js = 'orderGoPage(\'updated_at\', \'ASC\');';
                    $html .= '<th><div onclick="' . $js . '" class="text-left field-sorting" rel="updated_at">'. \Thin\Html\Helper::display($label) . '</div></th>';
                }
            }

            foreach ($fields as $field) {
                $userInfos      = isAke($userSettings, $field, []);
                $fieldSettings  = isAke($fieldInfos, $field);
                $listable       = isAke($userInfos, 'is_listable', isAke($fieldSettings, 'is_listable', false));
                $sortable       = isAke($userInfos, 'is_sortable', isAke($fieldSettings, 'is_sortable', false));
                $fieldSettings  = isAke($fieldInfos, $field);
                $label          = isAke($fieldSettings, 'label', ucfirst($field));

                if (!$listable || $field == $this->model->pk()) {
                    continue;
                }

                if (!$sortable) {
                    $html .= '<th>'. \Thin\Html\Helper::display($label) . '</th>';
                } else {
                    if ($field == $order) {
                        $directionJs = ('ASC' == $orderDirection) ? 'DESC' : 'ASC';
                        $js = 'orderGoPage(\'' . $field . '\', \'' . $directionJs . '\');';
                        $html .= '<th><div onclick="' . $js . '" class="text-left field-sorting ' . Inflector::lower($orderDirection) . '" rel="' . $field . '">'. \Thin\Html\Helper::display($label) . '</div></th>';
                    } else {
                        $js = 'orderGoPage(\'' . $field . '\', \'ASC\');';
                        $html .= '<th><div onclick="' . $js . '" class="text-left field-sorting" rel="' . $field . '">'. \Thin\Html\Helper::display($label) . '</div></th>';
                    }
                }
            }

            if (true === $customFields) {
                $html .= '<th style="text-align: center;">Attr.</th>';
            }

            if (count($many)) {
                $html .= '<th style="text-align: center;">Rel.</th>';
            }

            $html .= '<th style="text-align: center;">Action</th></tr></thead><tbody>';

            foreach ($data as $item) {
                $id = isAke($item, $this->model->pk(), null);
                $html .= '<tr ondblclick="document.location.href = \'' . urlAction('update') . '/table/' . $this->model->table . '/database/' . $this->model->db . '/id/' . $id . '\';">';

                if (Arrays::is($created_at)) {
                    $format = isAke($created_at, 'format', 'd/m/Y H:i:s');
                    $value = date($format, isAke($item, 'created_at', time()));
                    $html .= '<td>'. \Thin\Html\Helper::display($value) . '</td>';
                }

                if (Arrays::is($updated_at)) {
                    $format = isAke($updated_at, 'format', 'd/m/Y H:i:s');
                    $value = date($format, isAke($item, 'updated_at', time()));
                    $html .= '<td>'. \Thin\Html\Helper::display($value) . '</td>';
                }

                foreach ($fields as $field) {
                    $userInfos      = isAke($userSettings, $field, []);
                    $fieldSettings  = isAke($fieldInfos, $field);
                    $listable       = isAke($userInfos, 'is_listable', isAke($fieldSettings, 'is_listable', false));
                    $languages      = isAke($fieldSettings, 'languages');

                    if (!$listable || $field == $this->model->pk()) {
                        continue;
                    }

                    $value = !count($languages) ? isAke($item, $field, null) : isAke($item, $field . '_' . Arrays::first($languages), null);

                    $closure = isAke($fieldSettings, 'content_view', false);

                    if (false === $closure || !is_callable($closure)) {
                        $continue = true;

                        if (ake('form_type', $fieldSettings)) {

                            if ($fieldSettings['form_type'] == 'image' && strlen($value)) {
                                $html .= '<td><img src="' . $value . '" style="max-width: 200px;" /></td>';
                                $continue = false;
                            }

                            if ($fieldSettings['form_type'] == 'email' && strlen($value)) {
                                $html .= '<td><a href="mailto:' . $value . '">'. \Thin\Html\Helper::display($this->truncate($value)) . '</a></td>';
                                $continue = false;
                            }

                            if ($fieldSettings['form_type'] == 'file' && strlen($value)) {
                                $html .= '<td><a class="btn btn-small btn-success" href="' . $value . '"><i class="fa fa-download"></i></td>';
                                $continue = false;
                            }
                        }

                        if (true === $continue) {
                            if ('email' == $field) {
                                $html .= '<td><a href="mailto:' . $value . '">'. \Thin\Html\Helper::display($this->truncate($value)) . '</a></td>';
                            } else {
                                $html .= '<td>';

                                if (is_array($value)) {
                                    $value = 'array';
                                }

                                if (strlen($value) >= 20) {
                                    $html .= '<span rel="tooltip" title="' . \Thin\Html\Helper::display($value) . '">';
                                }

                                $html .= \Thin\Html\Helper::display($this->truncate($value));

                                if (strlen($value) >= 20) {
                                    $html .= '</span>';
                                }

                                $html .= '</td>';
                            }
                        }
                    } else {
                        $value = call_user_func_array($closure, array($item));
                        $html .= '<td>'. \Thin\Html\Helper::display($value) . '</td>';
                    }
                }

                if (true === $customFields) {
                    $html .= '<td style="text-align: center;"><a href="' . urlAction('customfields') . '/type/' . $this->model->table . '/row_id/' . $id . '" target="_blank" rel="tooltip" title="Gestion des attributs supplémentaires" class="btn btn-success"><i class="fa fa-tags"></i></a></td>';
                }

                if (count($many)) {
                    $html .= '<td style="text-align: center;"><ul class="list-inline">';

                    foreach ($many as $rel) {
                        $foreignCrud = new self(Db::instance($this->model->db, $rel));
                        $nameRel   = isAke($foreignCrud->config(), 'plural', $rel . 's');
                        $html .= '<li style="margin-right: 5px;"><a rel="tooltip" title="Afficher les ' . strtolower($nameRel) . ' en relation" class="btn btn-primary" target="_blank" href="' . urlAction('many') . '/table/' . $rel . '/foreign/' . $this->model->table . '_id/id/' . $id . '/database/' . $this->model->db . '"><i class="fa fa-chain"></i></a></li>';
                    }

                    $html .= '</ul></td>';
                }

                $html .= $this->options($id, $optionsConfig, $plus);
                $html .= '</tr>';
            }

            $html .= '</tbody></table></form>' . $pagination . '</div></div>';

            return $html;
        }

        private function truncate($str, $length = 20)
        {
            if (strlen($str) > $length) {
                $seg = substr($str, 0, $length);
                $str = $seg . '&hellip;';
            }

            return $str;
        }

        public function makeSearch()
        {
            $fieldsSettings = Db::instance('core', 'datafieldssettings')
            ->where("table = " . $this->model->table)
            ->where('database = ' . $this->model->db)
            ->where('user_id = ' . auth()->user()->getId())
            ->exec();

            $userSettings = [];

            if (count($fieldsSettings)) {
                foreach($fieldsSettings as $fieldSettings) {
                    foreach ($fieldSettings as $k => $v) {
                        if (strstr($k, 'is_')) {
                            $userSettings[$fieldSettings['field']][$k] = 1 == $v ? true : false;
                        }
                    }
                }
            }

            $fields         = $this->fields();
            $fieldInfos     = isAke($this->config, 'fields');
            $where          = isAke($_REQUEST, 'crud_where', session('dataTableCrudRedis::' . $this->model->table)->getWhere());

            $search         = '<div class="row"><div class="col-md-10">' . NL;

            $queriesRecorded = Db::instance('core', 'dataquery')
            ->where('user_id = ' . auth()->user()->getId())
            ->where('table = ' . $this->model->table)
            ->where('database = ' . $this->model->db)
            ->exec();

            if (count($queriesRecorded)) {
                $search .= '<div><a rel="tooltip" href="' . urlAction('saveddataqueries') . '/table/' . $this->model->table . '/database/' . $this->model->db . '" title="Accéder aux recherches enregistrées" class="btn btn-primary"><i class="fa fa-database"></i> <i class="fa fa-search"></i></a></div><br />';
            }

            if (!empty($where)) {
                $queryRecorded = Db::instance('core', 'dataquery')
                ->where('user_id = ' . auth()->user()->getId())
                ->where('table = ' . $this->model->table)
                ->where('database = ' . $this->model->db)
                ->where('clause = ' . base64_encode($where))
                ->first(true);

                $whereReadable = $this->readableQuery($where);

                $search .= '<span class="badge badge-lg alert-success">Recherche en cours : ' . $whereReadable . '</span>';

                $exists = !is_null($queryRecorded);

                if (!$exists) {
                    $search .= '&nbsp;&nbsp;<a rel="tooltip" title="Sauvegarder cette recherche" class="btn btn-primary" href="' . urlAction('savedataquery') . '/table/' . $this->model->table . '/database/' . $this->model->db . '/query/' . base64_encode($where) . '"><i class="fa fa-save"></i></a>&nbsp;&nbsp;';
                }

                $search .= '&nbsp;&nbsp;<a class="btn btn-warning" href="#" onclick="document.location.href = \'' . urlAction('list') . '/table/' . $this->model->table . '/database/' . $this->model->db . '/kill/1\'"><i class="fa fa-trash-o icon-white"></i> Supprimer cette recherche</a>&nbsp;&nbsp;';
            }

            $search .= '<button id="newCrudSearch" type="button" class="btn btn-info" onclick="$(\'#crudSearchDiv\').slideDown();$(\'#newCrudSearch\').hide();$(\'#hideCrudSearch\').show();"><i class="fa fa-search fa-white"></i> Effectuer une nouvelle recherche</button>';
            $search .= '&nbsp;&nbsp;<button id="hideCrudSearch" type="button" style="display: none;" class="btn btn-danger" onclick="$(\'#crudSearchDiv\').slideUp();$(\'#newCrudSearch\').show();$(\'#hideCrudSearch\').hide();"><i class="fa fa-power-off fa-white"></i> Masquer la recherche</button>';
            $search .= '<fieldset id="crudSearchDiv" style="display: none;">' . NL;

            $search .= '<hr />' . NL;

            $i = 0;
            $fieldsJs = [];
            $js = '<script type="text/javascript">' . NL;

            $searchFields = ['created_at' => 'Date de création', 'updated_at' => 'Date de mise à jour'];

            foreach ($searchFields as $field => $label) {
                $fieldsJs[] = "'$field'";
                $search .= '<div class="control-group">' . NL;
                $search .= '<label class="control-label">' . \Thin\Html\Helper::display($label) . '</label>' . NL;
                $search .= '<div class="controls" id="crudControl_' . $i . '">' . NL;
                $search .= '<select id="crudSearchOperator_' . $i . '">
                <option value="=">=</option>
                <option value="<">&lt;</option>
                <option value=">">&gt;</option>
                <option value="<=">&le;</option>
                <option value=">=">&ge;</option>
                </select>' . NL;

                if ($field == 'id') {
                    $search .= '<input pattern="\\\d*" style="width: 150px;" type="text" id="crudSearchValue_' . $i . '" value="" />';
                } elseif ($field == 'created_at' || $field == 'updated_at') {
                    $search .= '<input class="crudDate" data-date-clear-btn="true" data-date-format="dd/mm/yyyy" style="width: 150px;" type="text" id="crudSearchValue_' . $i . '" value="" />';
                }

                $search .= '&nbsp;&nbsp;<span class="btn btn-success" href="#" onclick="addRowSearch(\'' . $field . '\', ' . $i . '); return false;"><i class="fa fa-plus"></i></span>';
                $search .= '</div>' . NL;
                $search .= '</div><hr>' . NL;
                $i++;
            }

            foreach ($fields as $field) {
                $userInfos      = isAke($userSettings, $field, []);
                $fieldSettings  = isAke($fieldInfos, $field);
                $searchable     = isAke($userInfos, 'is_searchable', isAke($fieldSettings, 'is_listable', true));
                $label          = isAke($fieldSettings, 'label', ucfirst($field));

                $type           = isAke($fieldSettings, 'type', 'text');
                $closure        = isAke($fieldSettings, 'content_search', false);

                if (true === $searchable) {
                    $fieldsJs[] = "'$field'";
                    $search .= '<div class="control-group">' . NL;
                    $search .= '<label class="control-label">' . \Thin\Html\Helper::display($label) . '</label>' . NL;
                    $search .= '<div class="controls" id="crudControl_' . $i . '">' . NL;
                    $search .= '<select id="crudSearchOperator_' . $i . '">
                    <option value="=">=</option>
                    <option value="LIKE">Contient</option>
                    <option value="NOT LIKE">Ne contient pas</option>
                    <option value="START">Commence par</option>
                    <option value="END">Finit par</option>
                    <option value="<">&lt;</option>
                    <option value=">">&gt;</option>
                    <option value="<=">&le;</option>
                    <option value=">=">&ge;</option>
                    </select>' . NL;

                    if (!$closure) {
                        $search .= '<input style="width: 150px;" type="text" id="crudSearchValue_' . $i . '" value="" />';
                    } else {
                        if (is_callable($closure)) {
                            $customSearch = call_user_func_array($closure, array('crudSearchValue_' . $i));
                            $search  .= $customSearch;
                        } else {
                            $search .= '<input style="150px;" type="text" id="crudSearchValue_' . $i . '" value="" />';
                        }
                    }

                    $search .= '&nbsp;&nbsp;<span class="btn btn-success" href="#" onclick="addRowSearch(\'' . $field . '\', ' . $i . '); return false;"><i class="fa fa-plus"></i></span>';
                    $search .= '</div>' . NL;
                    $search .= '</div>' . NL;
                    $i++;
                }
            }

            $js .= 'var searchFields = [' . implode(', ', $fieldsJs)  . ']; var numFieldsSearch = ' . ($i - 1) . ';';
            $js .= '</script>' . NL;
            $search .= '<div class="control-group">
                <div class="controls">
                    <button type="submit" class="btn btn-primary" name="Rechercher" onclick="makeCrudSearch();">Rechercher</button>
                </div>
            </div>' . NL;

            $search .= '</fieldset>' . NL;
            $search .= '</div><div class="col-md-2 clear"></div>' . NL . $js . NL;

            return $search . '</div></div><div class="wrapper">';
        }

        private function options($id, $config, $plus = '')
        {
            if (is_callable($plus)) {
                $plus = call_user_func_array($plus, [$id]);
            }

            $options = '';

            $deletable      = isAke($config, 'deletable', true);
            $updatable      = isAke($config, 'updatable', true);
            $readable       = isAke($config, 'readable', true);
            $duplicatable   = isAke($config, 'duplicatable', true);

            $options .= '<td style="text-align: center;"><select class="form-control input-sm m-bot15" onchange="if ($(this).val().length > 0) { if ($(this).val().match(\'delete\')) {if (confirm(\'Confirmez-vous la suppression de cet élément ?\')) document.location.href = $(this).val();} else {document.location.href = $(this).val();}}"><option value="">Choisir</option>';

            if (true === $updatable) {
                $options .= '<option value="' . urlAction('update') . '/table/' . $this->model->table . '/database/' . $this->model->db . '/id/' . $id . '">Mettre à jour</option>';
            }

            if (true === $duplicatable) {
                $options .= '<option value="' . urlAction('duplicate') . '/table/' . $this->model->table . '/database/' . $this->model->db . '/id/' . $id . '">Dupliquer</option>';
            }

            if (true === $deletable) {
                $options .= '<option value="' . urlAction('delete') . '/table/' . $this->model->table . '/database/' . $this->model->db . '/id/' . $id . '">Effacer</option>';
            }

            if (true === $readable) {
                $options .= '<option value="' . urlAction('read') . '/table/' . $this->model->table . '/database/' . $this->model->db . '/id/' . $id . '">Voir</option>';
            }

            $options .= '</select>' . $plus . '</td>';

            return $options;
        }

        private function parseQuery($queryJs)
        {
            $queryJs = substr($queryJs, 0, -2);

            $query = str_replace('##', ' && ', $queryJs);
            $query = str_replace('||', ' || ', $query);
            $query = str_replace('%%', ' ', $query);
            $query = str_replace('LIKESTART', 'LIKE', $query);
            $query = str_replace('LIKEEND', 'LIKE', $query);
            $query = str_replace("'", '', $query);

            return $query;
        }

        public function readableQuery($query)
        {
            $query = substr($query, 0, -2);
            $query = str_replace('##', ' AND ', $query);
            $query = str_replace('||', ' OR ', $query);
            $query = str_replace('%%', ' ', $query);

            $query = str_replace(' NOT LIKE ', ' ne contient pas ', $query);
            $query = str_replace(' LIKESTART ', ' commence par ', $query);
            $query = str_replace(' LIKEEND ', ' finit par ', $query);
            $query = str_replace(' LIKE ', ' contient ', $query);
            $query = str_replace(' IN ', ' compris dans ', $query);
            $query = str_replace(' NOT IN ', ' non compris dans ', $query);
            $query = str_replace('%', '', $query);
            $query = str_replace(' >= ', ' plus grand ou vaut ', $query);
            $query = str_replace(' <= ', ' plus petit ou vaut ', $query);
            $query = str_replace(' = ', ' vaut ', $query);
            $query = str_replace(' < ', ' plus petit que ', $query);
            $query = str_replace(' > ', ' plus grand que ', $query);
            $query = str_replace(' AND ', ' et ', $query);
            $query = str_replace(' OR ', ' ou ', $query);
            $query = str_replace(" '", ' ', $query);
            $query = str_replace("'", '', $query);

            $tab = explode(' ', $query);
            $val = $tab[2];

            if ($tab[0] != 'id' && $tab[0] != 'created_at' && $tab[0] != 'updated_at') {
                foreach ($this->config['fields'] as $f => $settings) {
                    if ($f == $tab[0]) {
                        $label  = isAke($settings, 'label', $f);
                        $view   = isAke($settings, 'content_view', false);

                        if (false !== $view) {
                            $obj = $this->model->where($f . ' = ' . $val)->first();

                            if (!empty($obj)) {
                                $val = call_user_func_array($view, [$obj]);
                            }
                        }
                    }
                }
            } else {
                $label = $tab[0];
            }

            $and = explode(' et ', $query);

            $query = str_replace(
                $tab[0] . ' ' . $tab[1] . ' ' . $tab[2],
                '<span class="badge alert-warning">' . $label . '</span> ' . $tab[1] . ' <span class="badge alert-danger">' . $val . '</span>',
                $query
            );

            if (count($and) > 1) {
                array_shift($and);

                foreach ($and as $row) {
                    $tab = explode(' ', $row);
                    $val = $tab[2];

                    if ($tab[0] != 'id' && $tab[0] != 'created_at' && $tab[0] != 'updated_at') {
                        foreach ($this->config['fields'] as $f => $settings) {
                            if ($f == $tab[0]) {
                                $label  = isAke($settings, 'label', $f);
                                $view   = isAke($settings, 'content_view', false);

                                if (false !== $view) {
                                    $obj = $this->model->where($f . ' = ' . $val)->first();

                                    if (!empty($obj)) {
                                        $val = call_user_func_array($view, [$obj]);
                                    }
                                }
                            }
                        }
                    } else {
                        $label = $tab[0];
                    }

                    $query = str_replace(
                        $tab[0] . ' ' . $tab[1] . ' ' . $tab[2],
                        '<span class="badge alert-warning">' . $label . '</span> ' . $tab[1] . ' <span class="badge alert-danger">' . $val . '</span>',
                        $query
                    );
                }
            }

            $or = explode(' ou ', $query);

            if (count($or) > 1) {
                array_shift($or);

                foreach ($or as $row) {
                    $tab = explode(' ', $row);
                    $val = $tab[2];

                    if ($tab[0] != 'id' && $tab[0] != 'created_at' && $tab[0] != 'updated_at') {
                        foreach ($this->config['fields'] as $f => $settings) {
                            if ($f == $tab[0]) {
                                $label  = isAke($settings, 'label', $f);
                                $view   = isAke($settings, 'content_view', false);

                                if (false !== $view) {
                                    $obj = $this->model->where($f . ' = ' . $val)->first();

                                    if (!empty($obj)) {
                                        $val = call_user_func_array($view, [$obj]);
                                    }
                                }
                            }
                        }
                    } else {
                        $label = $tab[0];
                    }

                    $query = str_replace(
                        $tab[0] . ' ' . $tab[1] . ' ' . $tab[2],
                        '<span class="badge alert-warning">' . $label . '</span> ' . $tab[1] . ' <span class="badge alert-danger">' . $val . '</span>',
                        $query
                    );
                }
            }

            $query = str_replace(
                [
                    '<span</span> class="badge alert-danger">',
                    '</span<>'
                ],
                '',
                $query
            );

            return $query;
        }

        private function export($type, $rows)
        {
            $fieldInfos = isAke($this->config, 'fields');
            $fields     = $this->fields();

            if ('excel' == $type) {
                $excel = '<html xmlns:o="urn:schemas-microsoft-com:office:office"
        xmlns:x="urn:schemas-microsoft-com:office:excel"
        xmlns="http://www.w3.org/TR/REC-html40">

            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                <meta name="ProgId" content="Excel.Sheet">
                <meta name="Generator" content="Microsoft Excel 11">
                <style id="Classeur1_17373_Styles">
                <!--table
                    {mso-displayed-decimal-separator:"\,";
                    mso-displayed-thousand-separator:" ";}
                .xl1517373
                    {padding-top:1px;
                    padding-right:1px;
                    padding-left:1px;
                    mso-ignore:padding;
                    color:windowtext;
                    font-size:10.0pt;
                    font-weight:400;
                    font-style:normal;
                    text-decoration:none;
                    font-family:Arial;
                    mso-generic-font-family:auto;
                    mso-font-charset:0;
                    mso-number-format:General;
                    text-align:general;
                    vertical-align:bottom;
                    mso-background-source:auto;
                    mso-pattern:auto;
                    white-space:nowrap;}
                .xl2217373
                    {padding-top:1px;
                    padding-right:1px;
                    padding-left:1px;
                    mso-ignore:padding;
                    color:#FFFF99;
                    font-size:10.0pt;
                    font-weight:700;
                    font-style:normal;
                    text-decoration:none;
                    font-family:Arial, sans-serif;
                    mso-font-charset:0;
                    mso-number-format:General;
                    text-align:center;
                    vertical-align:bottom;
                    background:#003366;
                    mso-pattern:auto none;
                    white-space:nowrap;}
                -->
                </style>
            </head>

                <body>
                <!--[if !excel]>&nbsp;&nbsp;<![endif]-->

                <div id="Classeur1_17373" align="center" x:publishsource="Excel">

                <table x:str border="0" cellpadding="0" cellspacing="0" width=640 style="border-collapse:
                 collapse; table-layout: fixed; width: 480pt">
                 <col width="80" span=8 style="width: 60pt">
                 <tr height="17" style="height:12.75pt">
                  ##headers##
                 </tr>
                 ##content##
                </table>
                </div>
            </body>
        </html>';
                $tplHeader = '<td class="xl2217373">##value##</td>';
                $tplData = '<td>##value##</td>';

                $headers = [];

                foreach ($fields as $field) {
                    $fieldSettings  = isAke($fieldInfos, $field);
                    $exportable     = isAke($fieldSettings, 'is_exportable', true);
                    $label          = isAke($fieldSettings, 'label', ucfirst($field));

                    if (true === $exportable) {
                        $headers[] = \Thin\Html\Helper::display($label);
                    }
                }

                $xlsHeader = '';

                foreach ($headers as $header) {
                    $xlsHeader .= str_replace('##value##', $header, $tplHeader);
                }

                $excel = str_replace('##headers##', $xlsHeader, $excel);

                $xlsContent = '';

                foreach ($rows as $item) {
                    $xlsContent .= '<tr>';

                    foreach ($fields as $field) {
                        $fieldSettings  = isAke($fieldInfos, $field);
                        $exportable     = isAke($fieldSettings, 'is_exportable', true);

                        if (true === $exportable) {
                            $value = isAke($item, $field, '&nbsp;');

                            if (Arrays::exists('content_list', $fieldSettings)) {
                                $closure = $fieldSettings['content_list'];

                                if (is_callable($closure)) {
                                    $value = call_user_func_array($closure, array($item));
                                }
                            }

                            if (empty($value)) {
                                $value = '&nbsp;';
                            }
                            $xlsContent .= str_replace('##value##', \Thin\Html\Helper::display($value), $tplData);
                        }
                    }

                    $xlsContent .= '</tr>';
                }

                $excel = str_replace('##content##', $xlsContent, $excel);

                $name = 'extraction_' . $this->model->db . '_' . $this->model->table . '_' . date('d_m_Y_H_i_s') . '.xlsx';

                $file = TMP_PUBLIC_PATH . DS . $name;

                File::delete($file);
                File::put($file, $excel);
                redirect(URLSITE . '/tmp/' . $name);
            } elseif ('pdf' == $type) {
                $pdf = '<html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <link href="//fonts.googleapis.com/css?family=Abel" rel="stylesheet" type="text/css" />
                <title>Extraction ' . $this->model->db . ' - ' . $this->model->table . '</title>
                <style>
                    *
                    {
                        font-family: Abel, ubuntu, verdana, tahoma, arial, sans serif;
                        font-size: 11px;
                    }
                    h1
                    {
                        text-transform: uppercase;
                        font-size: 135%;
                    }
                    th
                    {
                        font-size: 120%;
                        color: #fff;
                        background-color: #394755;
                        text-transform: uppercase;
                    }
                    td
                    {
                        border: solid 1px #394755;
                    }

                    a, a:visited, a:hover
                    {
                        color: #000;
                        text-decoration: underline;
                    }
                </style>
            </head>
            <body>
                <center><h1>Extraction &laquo ' . $this->model->db . ' - ' . $this->model->table . ' &raquo;</h1></center>
                <p></p>
                <table width="100%" cellpadding="5" cellspacing="0" border="0">
                <tr>
                    ##headers##
                </tr>
                ##content##
                </table>
                <p>&copy; GP 1996 - ' . date('Y') . ' </p>
            </body>
            </html>';

                $tplHeader = '<th>##value##</th>';
                $tplData = '<td>##value##</td>';

                $headers = [];

                foreach ($fields as $field) {
                    $fieldSettings  = isAke($fieldInfos, $field, []);
                    $exportable     = isAke($fieldSettings, 'is_exportable', true);

                    if (true === $exportable) {
                        $label = isAke($fieldSettings, 'label', ucfirst($field));
                        $headers[] = \Thin\Html\Helper::display($label);
                    }
                }

                $pdfHeader = '';

                foreach ($headers as $header) {
                    $pdfHeader .= str_replace('##value##', $header, $tplHeader);
                }

                $pdf = str_replace('##headers##', $pdfHeader, $pdf);

                $pdfContent = '';

                foreach ($rows as $item) {
                    $pdfContent .= '<tr>';

                    foreach ($fields as $field) {
                        $fieldSettings  = isAke($fieldInfos, $field, []);
                        $exportable     = isAke($fieldSettings, 'is_exportable', true);

                        if (true === $exportable) {
                            $value = isAke($item, $field, '&nbsp;');

                            if (Arrays::exists('content_list', $fieldSettings)) {
                                $closure = $fieldSettings['content_list'];

                                if (is_callable($closure)) {
                                    $value = call_user_func_array($closure, array($item));
                                }
                            }

                            if (empty($value)) {
                                $value = '&nbsp;';
                            }

                            $pdfContent .= str_replace('##value##', \Thin\Html\Helper::display($value), $tplData);
                        }
                    }

                    $pdfContent .= '</tr>';
                }

                $pdf = str_replace('##content##', $pdfContent, $pdf);

                return \Thin\Pdf::make($pdf, "extraction_" . $this->model->db . "_" . $this->model->table . "_" . date('d_m_Y_H_i_s'), false);
            }
        }

        public function pk()
        {
            return $this->model->pk();
        }

        public function form()
        {
            $MAX_FILE_SIZE = isAke($_POST, 'MAX_FILE_SIZE', null);

            if (!is_null($MAX_FILE_SIZE)) {
                unset($_POST['MAX_FILE_SIZE']);
            }

            $pk = $this->model->pk();

            $this->treatCast();

            $action = false !== isAke($_POST, $pk, false) ? 'updating' : 'creating';

            return $this->$action();
        }

        private function treatCast()
        {
            if (!empty($_POST)) {
                foreach ($_POST as $k => $v) {
                    if (fnmatch('*_id', $k) && !empty($v)) {
                        if (is_numeric($v)) {
                            $_POST[$k] = (int) $v;
                        }
                    }
                }
            }
        }

        private function updating()
        {
            $pk = $this->model->pk();
            $id = isAke($_POST, $pk, false);

            if (false !== $id && count($_POST)) {
                $old        = $this->model->find($id);
                $fieldInfos = isAke($this->config, 'fields');
                $fields     = $this->fields();
                $closure    = isAke($this->config, 'before_update', false);

                if (false !== $closure && is_callable($closure)) {
                    $closure($_POST);
                }

                foreach ($fields as $field) {
                    $settings   = isAke($fieldInfos, $field);
                    $type       = isAke($settings, 'form_type', false);

                    if ($type == 'file' || $type == 'image') {
                        $upload = $this->upload($field);

                        if (!is_null($upload)) {
                            $_POST[$field] = $upload;
                        }
                    }
                }

                $record     = $old->hydrate()->save();
                $closure    = isAke($this->config, 'after_update', false);

                if (false !== $closure && is_callable($closure)) {
                    $closure($record);
                }

                return true;
            }

            return false;
        }

        private function creating()
        {
            $pk = $this->model->pk();
            $id = isAke($_POST, 'duplicate_id', false);

            if (count($_POST)) {
                $new        = $this->model->create();
                $fieldInfos = isAke($this->config, 'fields');
                $fields     = $this->fields();
                $closure    = isAke($this->config, 'before_create', false);

                if (false !== $closure && is_callable($closure)) {
                    $closure($_POST);
                }

                foreach ($fields as $field) {
                    $settings   = isAke($fieldInfos, $field);
                    $type       = isAke($settings, 'form_type', false);

                    if ($type == 'file' || $type == 'image') {
                        $upload = $this->upload($field);

                        if (!is_null($upload)) {
                            $_POST[$field] = $upload;
                        } else {
                            if (false !== $id) {
                                $old = $this->model->find($id);
                                $_POST[$field] = $old->$field;
                            }
                        }
                    }
                }

                if (false !== $id) {
                    unset($_POST['duplicate_id']);
                }

                $record = $new->hydrate()->save();
                $closure = isAke($this->config, 'after_create', false);

                if (false !== $closure && is_callable($closure)) {
                    if (false !== $id) {
                        $_REQUEST['duplicate_id'] = $id;
                    }

                    $closure($record);
                }

                return true;
            }

            return false;
        }

        private function upload($field)
        {
            $bucket = container()->bucket();

            if (Arrays::exists($field, $_FILES)) {
                $fileupload         = $_FILES[$field]['tmp_name'];
                $fileuploadName     = $_FILES[$field]['name'];

                if (strlen($fileuploadName)) {
                    $tab    = explode(".", $fileuploadName);
                    $data   = fgc($fileupload);

                    if (!strlen($data)) {
                        return null;
                    }

                    $ext = Inflector::lower(Arrays::last($tab));
                    $res = $bucket->data($data, $ext);

                    return $res;
                }
            }
            return null;
        }

        private function stripslashes($val)
        {
            return Arrays::is($val)
            ? array_map(
                array(
                    __NAMESPACE__ . '\\Tools',
                    'stripslashes'
                ),
                $val
            )
            : stripslashes($val);
        }

        public static function __callStatic($method, $args)
        {
            return call_user_func_array(array(__NAMESPACE__ . '\\Tools', $method), $args);
        }
    }
