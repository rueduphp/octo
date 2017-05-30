<?php
    namespace Octo;

    use SplFixedArray;

    class Octalia
    {
        use Notifiable;

        public $path, $ns, $db, $table, $query;

        public function __construct($db = 'core', $table = 'core', $driver = null, $dir = null)
        {
            $dir            = empty($dir) ? Config::get('octalia.dir', session_save_path()) : $dir;
            $driver         = empty($driver) ? fmr("odb.$db.$table", $dir) : $driver;

            $this->ns       = "$db.$table";
            $this->path     = "$db.$table";

            if ($driver instanceof Cache || $driver instanceof Cachelite) {
                $this->ns .= str_replace(DS, '.', $driver->getDirectory());
            }

            $this->driver   = $driver;
            $this->table    = $table;
            $this->db       = $db;
            $this->query    = [];
            $this->ids      = $this->ids();
            $this->instance = token();

            Registry::set('octalia.start', microtime(true));
            Registry::set('octalia.instance', $this);

            $this->_events();
        }

        public function validator()
        {
            return validator('model.' . $this->db . '.' . $this->table);
        }

        public function reset()
        {
            $queries    = Registry::get('octalia.queries', []);
            $queries[]  = ['time' => microtime(true) - Registry::get('octalia.start', microtime(true))];

            Registry::set('octalia.queries', $queries);

            return $this;
        }

        public function instance()
        {
            return $this;
        }

        public function tableExists()
        {
            $file = path('factories') .
            DS .
            $this->db .
            DS .
            $this->table . '.php';

            return File::exists($file);
        }

        public function make()
        {
            $file = path('factories') .
            DS .
            $this->db .
            DS .
            $this->table . '.php';

            File::delete($file);

            return $this;
        }

        public function setDirectory($dir)
        {
            if ($this->driver instanceof Cache) {
                $this->driver->setDirectory($dir);
            }

            return $this;
        }

        public function getDirectory()
        {
            if ($this->driver instanceof Cache) {
                return $this->driver->getDirectory();
            }

            return null;
        }

        public function __get($k)
        {
            if ($k == 'ids') {
                return Registry::get('Octalia.' . sha1($this->ns) . '.ids', []);
            } elseif ($k == 'driver') {
                return Registry::get('Octalia.' . sha1($this->ns) . '.driver');
            } elseif ($k == 'kh') {
                return Registry::get('Octalia.' . sha1($this->ns) . '.kh');
            } elseif ($k == 'optimized') {
                return Registry::get('Octalia.' . sha1($this->ns) . '.optimized', Config::get('octalia.optimized', true));
            } elseif ($k == 'instance') {
                return Registry::get('Octalia.' . sha1($this->ns) . '.instance', token());
            } elseif ($k == 'modeler') {
                return Registry::get('Octalia.' . sha1($this->ns) . '.modeler', 'Object');
            } else {
                return $this->$k;
            }
        }

        public function __set($k, $v)
        {
            if ($k == 'ids') {
                return Registry::set('Octalia.' . sha1($this->ns) . '.ids', $v);
            } elseif ($k == 'driver') {
                return Registry::set('Octalia.' . sha1($this->ns) . '.driver', $v);
            } elseif ($k == 'kh') {
                return Registry::set('Octalia.' . sha1($this->ns) . '.kh', $v);
            } elseif ($k == 'optimized') {
                return Registry::set('Octalia.' . sha1($this->ns) . '.optimized', $v);
            } elseif ($k == 'modeler') {
                return Registry::set('Octalia.' . sha1($this->ns) . '.modeler', $v);
            } elseif ($k == 'instance') {
                Registry::set('Octalia.' . $v, $this);

                return Registry::set('Octalia.' . sha1($this->ns) . '.instance', $v);
            } else {
                return $this->$k = $v;
            }
        }

        public function import(array $data, $tuples = true, $id = true)
        {
            foreach ($data as $row) {
                if (is_array($row)) {
                    if (!$id) {
                        unset($row['id']);
                    }

                    if ($tuples) {
                        $this->create($row)->save();
                    } else {
                        $this->firstOrCreate($row);
                    }
                }
            }

            return $this;
        }

        public function makeId()
        {
            $id = $this->driver->incr('ids');
            $this->driver->set('lastid', $id);

            return $id;
        }

        public function forget()
        {
            $this->find($this->lastid())->delete();
            $id = $this->driver->decr('ids');
            $this->driver->set('lastid', $id);

            return true;
        }

        public function lastid()
        {
            return $this->driver->get('lastid', 1);
        }

        public function memory()
        {
            $entity = Inflector::camelize($this->db . '_' . $this->table);

            $db = dbMemory($entity);

            foreach ($this->get() as $row) {
                $db->add($row);
            }

            return $db;
        }

        public function instanciate($db = null, $table = null, $driver = null)
        {
            $db     = is_null($db) ? $this->db : $db;
            $table  = is_null($table) ? $this->table : $table;
            $driver = is_null($driver) ? $this->driver : $driver;

            return new self($db, $table, $driver);
        }

        public function age($t = null)
        {
            if (empty($t)) {
                $t = $this->driver->getOr('age', function () {
                    return microtime(true);
                });
            } else {
                $this->driver->set('age', $t);
            }

            return $t;
        }

        public function fresh()
        {
            return $this->age(microtime(true));
        }

        public function __destruct()
        {
            $cbs = Registry::get('octalia.listen', []);

            if (!empty($cbs)) {
                foreach ($cbs as $cb) {
                    call_user_func_array($cb, [$this]);
                }
            }
        }

        public function data($rows = null)
        {
            if (is_array($rows)) {
                $this->ids = coll($rows)->fetch('id')->toArray();
            } else {
                $data = Registry::get('rows.' . sha1($this->ns), 'dummy');

                if (!is_array($data)) {
                    $data = $this->driver->get('rows', []);
                }

                return $data;
            }
        }

        public function optimize()
        {
            $cb = function ($database, $table) {
                $db = $this->newQuery();

                foreach ($db->fields() as $field) {
                    $db->select($field);
                }
            };

            lib('later')->set('optimize.' . $this->ns . '.' . token(), $cb, [$this->db, $this->table]);
            lib('later')->background();
        }

        public function ids()
        {
            $keyCache = sha1($this->ns . '.ids');

            return $this->driver->until($keyCache, function () {
                $ids = array_keys($this->data());
                asort($ids);

                return $ids;
            }, $this->age());
        }

        public function iterator($ids = null)
        {
            if (!is_null($ids)) {
                $this->ids = SplFixedArray::fromArray($ids);
            } else {
                if (!count($this->ids) && empty($this->query)) {
                    $this->ids = SplFixedArray::fromArray($this->ids());
                }
            }

            return $this->ids;
        }

        public function count()
        {
            $this->reset();

            return $this->fire('count', count($this->iterator()));
        }

        private function add($row, $fire = true)
        {
            $id = isAke($row, 'id', null);

            if ($id) {
                $this->driver->set($id, $row);

                $rows = $this->data();

                $rows[$id] = $row;

                $this->data($rows);

                $this->driver->set('rows', $rows);

                $this->age(microtime(true));

                if ($fire) {
                    $this->fire('added', $row);
                }
            }

            return $this;
        }

        public function push($row)
        {
            return $this->add($row);
        }

        public function createMany(array $rows = [], $return = false)
        {
            if ($return) {
                $collection = [];
            }

            foreach ($rows as $data) {
                $new = $this->model($data);

                if ($return) {
                    $collection[] = $new;
                } else {
                    $new->save();
                }
            }

            return $return ? $collection : $this;
        }

        public function many(array $rows = [])
        {
            foreach ($rows as $row) {
                $this->save($row);
            }
        }

        public function create($data = [])
        {
            $data = is_object($data) ? $data->toArray() : $data;

            return $this->model($data);
        }

        public function item($data = [])
        {
            return item($data);
        }

        public function record($data = [])
        {
            return item($data);
        }

        public function store($data = [])
        {
            $data = is_object($data) ? $data->toArray() : $data;

            return $this->model($data)->save();
        }

        public function persist($data = [])
        {
            return $this->store($data);
        }

        public function createIfNotExists(array $data = [])
        {
            $data = is_object($data) ? $data->toArray() : $data;

            return $this->firstOrCreate($data);
        }

        public function save($data, $model = true)
        {
            $data = is_object($data) ? $data->toArray() : $data;

            $this->reset();

            if ($this->fire('saving', $data) === false) return false;

            $id = isAke($data, 'id', null);

            if ($id && is_int($id)) {
                $saved = $this->modify($data, $model);
            } else {
                $data['id']         = $this->makeId();
                $data['created_at'] = $data['updated_at'] = time();

                $saved = $this->insert($data, $model);
            }

            if ($saved) {
                $saved = $this->fire('saved', $saved);
            }

            return $saved;
        }

        private function insert(array $data, $model = true)
        {
            if ($this->fire('creating', $data) === false) return false;

            $this->add($data, false);

            return $this->fire('created', $model ? $this->model($data) : $data);
        }

        private function modify(array $data, $model = true)
        {
            if ($this->fire('updating', $data) === false) return false;

            $data['updated_at'] = time();

            $old = $this->row($data['id']);

            if (empty($old)) {
                $old = [];
            }

            $data = array_merge($old, $data);

            $this->delete($data['id'], false, false);

            $this->add($data, false);

            return $this->fire('updated', $model ? $this->model($data) : $data);
        }

        public function delete($id = null, $soft = false, $fire = true)
        {
            if (is_null($id)) {
                return $this->deletes();
            }

            $row = $this->row($id);

            $exists = !is_null($row);

            if ($exists) {
                if ($fire && $this->fire('deleting', $row) === false) return false;

                if ($soft) {
                    $row['deleted_at'] = time();
                    $this->modify($row);
                } else {
                    $this->driver->delete($id);

                    $rows = $this->driver->get('rows', []);

                    unset($rows[$id]);

                    $this->driver->set('rows', $rows);

                    $this->age(microtime(true));
                }

                return $fire ? $this->fire('deleted', $row) : $exists;
            }

            return false;
        }

        public function drop()
        {
            $this->reset();

            $this->age(microtime(true));

            $this->all()->delete();

            if ($this->driver instanceof Cache) {
                return File::rmdir($this->getDirectory());
            }

            return $this->driver->flush();
        }

        public function findAll($model = true)
        {
            return $this->newQuery()->get($model);
        }

        public function find($id = null, $model = true)
        {
            if (is_null($id)) {
                return $this->get(true);
            }

            if (is_array($id)) {
                return $this->newQuery()->whereIn('id', $id)->get(true);
            }

            $row = $this->driver->get($id);

            if (!$row) {
                return null;
            }

            $row = $this->read($row);

            $this->reset();

            return $model ? $this->model($row) : $row;
        }

        public function findMany(array $ids)
        {
            return $this->where(['id', 'IN', $ids]);
        }

        public function findAndDelete($id, $model = true)
        {
            $row = $this->find($id, $model);

            if ($row) {
                $this->delete($id);
            }

            return $row;
        }

        public function row($id)
        {
            $this->reset();

            return $this->driver->get($id);
        }

        public function rowAndDelete($id)
        {
            $row = $this->row($id);

            if ($row) {
                $this->delete($id);
            }

            return $this->read($row);
        }

        public function model($row = [])
        {
            $row = is_object($row) ? $row->toArray() : $row;

            $class = Strings::camelize($this->db . '_' . $this->table . '_model');

            $file = path('models') . '/' . $this->db . '/' . $this->table . '.php';

            $row = treatCast($row);

            if (file_exists($file)) {
                $cbs = require_once $file;

                $fns    = isAke($cbs, 'scopes', []);
                $hooks  = isAke($cbs, 'hooks', []);

                foreach ($fns as $cbname => $cb) {
                    if (is_callable($cb)) {
                        $model->fn($cbname, $cb);
                    }
                }

                foreach ($hooks as $when => $cb) {
                    if (is_callable($cb)) {
                        Arrays::set($model->hooks, $when, $cb);
                    } else {
                        if (is_array($cb)) {
                            foreach ($cb as $action => $c) {
                                Arrays::set($model->hooks, $when . '.' . $action, $c);
                            }
                        }
                    }
                }
            } else {
                if (!is_dir(path('models'))) {
                    File::mkdir(path('models'));
                }

                if (!is_dir(path('models') . '/' . $this->db)) {
                    File::mkdir(path('models') . '/' . $this->db);
                }

                $fks = array_keys(Arrays::pattern($row, '*_id'));

                $indices = '["id", ';

                foreach ($fks as $fk) {
                    $indices .= '"' . $fk . '", ';
                }

                $indices = substr($indices, 0, -2) . ']';

                $fields = ['id', 'created_at', 'updated_at'];

                $rowFields = array_keys($row);

                foreach ($rowFields as $f) {
                    if (!in_array($f, $fields)) {
                        $fields[] = $f;
                    }
                }

                $fs = '[' . "\n\t\t\t";

                foreach ($fields as $field) {
                    if ($field == 'year' || $field == 'age' || $field == 'number' || $field == 'quantity' || $field == 'id' || $field == 'created_at' || $field == 'updated_at' || $field == 'deleted_at' || $field == 'created_by' || $field == 'updated_by' || $field == 'deleted_by' || fnmatch('*_id', $field)) {
                        $type = 'integer';
                    } elseif (in_array(
                        $field, [
                            'duration',
                            'price',
                            'size',
                            'length',
                            'width',
                            'height',
                            'depth'
                        ]
                    )) {
                        $type = 'float';
                    } else {
                        $type = gettype($row[$field]);
                    }

                    $fs .= '"' . $field . '" => ["type" => "' . $type . '"], ';
                }

                $fs = substr($fs, 0, -2);

                $tab = explode(', ', $fs);

                $fs = implode(",\n\t\t\t", $tab);

                $fs .= "\n\t\t" . ']';

                $modeler = $this->modeler;

                File::put($file, '<?' . 'php' . "\n\t" . 'namespace Octo;' . "\n\n\t" . 'if (!class_exists("Octo\\' . $class . '")):' . "\n\n\t" . 'class ' . $class . " extends $modeler\n\t" .  '{' . "\n\t\t" .  'public function __construct(array $model)' . "\n\t\t" .  '{' . "\n\t\t\t" .  'parent::__construct($model);' . "\n\t\t" .  '}' . "\n\t" .  '}' . "\n\n\t" . 'endif;' . "\n\n\t" . 'return [' . "\n\t\t" . '"fields" => ' . $fs . ',' . "\n\t\t" . '"scopes" => [],' . "\n\t\t" . '"hooks" => [' . "\n\t\t\t" . '"validate" => null,' . "\n\t\t\t" . '"before" => [' . "\n\t\t\t\t" . '"create" => null,' . "\n\t\t\t\t" . '"read" => null,' . "\n\t\t\t\t" . '"update" => null,' . "\n\t\t\t\t" . '"delete" => null' . "\n\t\t\t" . '],' . "\n\t\t\t" . '"after" => [' . "\n\t\t\t\t" . '"create" => null,' . "\n\t\t\t\t" . '"read" => null,' . "\n\t\t\t\t" . '"update" => null,' . "\n\t\t\t\t" . '"delete" => null' . "\n\t\t\t" . ']' . "\n\t\t" . '],' . "\n\t\t" . '"indices" => ' . $indices . "\n\t" . '];');

                require $file;
            }

            $dir = path('factories');

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . $this->db;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $file = $dir . DS . $this->table . '.php';

            if (!file_exists($file)) {
                $fieldsOmitted = ['id', 'created_at', 'updated_at'];

                $rowFields = array_keys($row);

                $fields = [];

                foreach ($rowFields as $f) {
                    if (!in_array($f, $fieldsOmitted)) {
                        $fields[] = $f;
                    }
                }

                $code = '<?' . 'php' . "\n\tnamespace Octo;\n\n\t";
                $code .= 'return function (\Faker\Generator $faker){' . "\n\t\t" . 'return [' . "\n\t\t\t";

                $n = 0;

                foreach ($fields as $field) {
                    $val = 'Strings::random(10)';

                    if ($field == 'password') {
                        $val = '$faker->password';
                    } elseif ($field == 'email') {
                        $val = '$faker->safeEmail';
                    } elseif ($field == 'username' || $field == 'login') {
                        $val = '$faker->username';
                    } elseif ($field == 'name' || $field == 'lastname') {
                        $val = '$faker->lastName';
                    } elseif ($field == 'firstname') {
                        $val = '$faker->firstName';
                    } elseif ($field == 'phone' || $field == 'tel' || $field == 'mobile' || $field == 'cellular' || $field == 'fax') {
                        $val = '$faker->phoneNumber';
                    } elseif ($field == 'latitude' || $field == 'lat') {
                        $val = '$faker->latitude';
                    } elseif ($field == 'longitude' || $field == 'lng') {
                        $val = '$faker->longitude';
                    } elseif ($field == 'city') {
                        $val = '$faker->city';
                    } elseif ($field == 'country') {
                        $val = '$faker->country';
                    } elseif ($field == 'address') {
                        $val = '$faker->streetAddress';
                    } elseif ($field == 'zip' || $field == 'postcode') {
                        $val = '$faker->postcode';
                    } elseif ($field == 'color') {
                        $val = '$faker->colorName';
                    } elseif ($field == 'company') {
                        $val = '$faker->company';
                    } elseif ($field == 'ip') {
                        $val = '$faker->ipv4';
                    } elseif ($field == 'url' || $field == 'website') {
                        $val = '$faker->url';
                    } elseif ($field == 'slug') {
                        $val = '$faker->slug';
                    } elseif ($field == 'barcode' || $field == 'ean13') {
                        $val = '$faker->ean13';
                    } elseif ($field == 'date' || $field == 'birthdate' || $field == 'deathdate') {
                        $val = '$faker->date';
                    } elseif ($field == 'time') {
                        $val = '$faker->time';
                    } elseif ($field == 'uuid') {
                        $val = 'uuid()';
                    } elseif ($field == 'token') {
                        $val = 'token()';
                    } elseif (in_array(
                        $field, [
                            'created_by',
                            'updated_by',
                            'deleted_by',
                            'year',
                            'age',
                            'price',
                            'distance',
                            'weight',
                            'size',
                            'width',
                            'height',
                            'length',
                            'depth',
                            'quantity',
                            'number'
                        ]
                    )) {
                        $val = '$faker->numberBetween(15, 85)';
                    } elseif (fnmatch('*_id', $field)) {
                        $em = Strings::camelize($this->db . '_' . str_replace('_id', '', $field));
                        $val = 'em("' . $em . '")->createFake()->id';
                    }

                    if ($n < count($fields) - 1) {
                        $code .= '"' . $field . '" => ' . $val . ',' . "\n\t\t\t";
                    } else {
                        $code .= '"' . $field . '" => ' . $val . '' . "\n\t\t";
                    }

                    $n++;
                }

                $code .= '];' . "\n\t";
                $code .= '};';

                File::put($file, $code);
            }

            $class  = '\\Octo\\' . $class;
            $model  = new $class($row);
            $self   = $this;

            $model->fn('save', function ($event = null) use ($model) {
                if ($model->exists() && !$model->isDirty()) {
                    return $model;
                }

                $check = $this->fire('validate', $model->toArray());

                if ($check != $model->toArray()) {
                    exception('model', $check);
                }

                if ($model) {
                    $row =  $this->save($model->toArray());

                    return $row;
                }

                return $model;
            })->fn('delete', function ($event = null) use ($row, $model) {
                if (isset($row['id'])) {
                    if ($model) {
                        $status = $this->delete($row['id']);

                        return $status;
                    }
                }

                return false;
            })->fn('post', function (array $data = [], $save = false) use ($model) {
                $data = empty($data) ? $_POST : $data;

                if (Arrays::isAssoc($data)) {
                    foreach ($data as $k => $v) {
                        if ($k != 'id') {
                            if ('true' == $v) {
                                $v = true;
                            } elseif ('false' == $v) {
                                $v = false;
                            } elseif ('null' == $v) {
                                $v = null;
                            }

                            $continue = true;

                            if (fnmatch('*_id', $k)) {
                                if (is_numeric($v)) {
                                    $v = (int) $v;
                                } elseif (is_object($v)) {
                                    $model->set($k, $v->id);
                                    $model->set(str_replace('_id', '', $k), $v->toArray());
                                    $continue = false;
                                }
                            }

                            if (true === $continue) $model->set($k,  $v);
                        }
                    }
                }

                return !$save ? $model : $model->save();
            })->fn('copy', function ($create = true) use ($row) {
                unset($row['id']);
                unset($row['created_at']);
                unset($row['updated_at']);

                $record = $this->create($row);

                return $create ? $record->save() : $record;
            })->fn('table', function () {
                return $this->table;
            })->fn('db', function () {
                return $this->db;
            })->fn('em', function () {
                return $this;
            })->fn('entityName', function () {
                $database   = $this->db;
                $table      = $this->table;

                return Strings::camelize($database . "_" . $table);
            })->fn('entity', function () {
                $database   = $this->db;
                $table      = $this->table;

                return actual("entity.$database.$table");
            })->fn('instance', function () {
                return $this;
            })->fn('driver', function () {
                return $this->driver;
            })->fn('has', function ($what) use ($model) {
                $m = $what . 's';

                return $model->$m()->count() > 0;
            })->fn('count', function ($what) use ($model) {
                $m = $what . 's';

                return $model->$m()->count();
            })->fn('owns', function (Object $object) use ($row) {
                $field = $object->table() . '_id';

                return $object->getId() == isAke($row, $field, 0);
            })->fn('savePost', function ($data = null) use ($model) {
                $data = is_null($data) ? $_POST : $data;

                foreach ($_POST as $k => $v) {
                    $setter = setter($k);
                    $model->$setter($v);
                }

                return $model->save();
            })->fn('storePost', function ($only = []) use ($model) {
                foreach ($_POST as $k => $v) {
                    if (!empty($only) && !in_array($k, $only)) {
                        continue;
                    }

                    $setter = setter($k);
                    $model->$setter($v);
                }

                return $model->save();
            });

            $traits = class_uses($model);

            if (!empty($traits)) {
                foreach ($traits as $trait) {
                    $tab = explode('\\', $trait);
                    $traitName = Strings::lower(end($tab));
                    $method = lcfirst(Strings::camelize('boot_' . $traitName . '_trait'));

                    $methods = get_class_methods($model);

                    if (in_array($method, $methods)) {
                        call_user_func_array([$model, $method], []);
                    }
                }
            }

            return $this->fire('model', $model);
        }

        public function createFake()
        {
            return $this->fake(1, false)
            ->create(true)
            ->lastFake();
        }

        public function fake($amount = 1, $create = true)
        {
            if (!is_int($amount)) {
                $amount = 1;
            }

            $file = path('factories') .
            DS .
            $this->db .
            DS .
            $this->table . '.php';

            if (!file_exists($file)) {
                exception("octalia", "The factory file does not exist.");
            }

            $resolver = include $file;

            $faker  = faker();
            $coll   = coll();

            for ($i = 0; $i < $amount; $i++) {
                $row = $resolver($faker);

                $row['is_fake'] = true;

                $coll[] = $this->model($row);
            }

            return $create ? $coll->create() : $coll;
        }

        public function fields()
        {
            $keyCache = sha1('fields.' . $this->ns);

            return $this->driver->until($keyCache, function () {
                $rows = $this->data();

                if (!empty($rows)) {
                    $id = current(array_keys($rows));

                    return array_keys($this->row($id));
                }

                return [];
            }, $this->age());
        }

        public function select($fields = null)
        {
            if (is_null($fields)) {
                $fields = $this->fields();
            }

            if (is_string($fields)) {
                if (fnmatch('*,*', $fields)) {
                    if (fnmatch('* *', $fields)) {
                        $fields = str_replace(' ', '', $fields);
                    }

                    $fields = explode(',', $fields);
                } else {
                    $fields = [$fields];
                }
            }

            if (!in_array('id', $fields)) {
                $fields[] = 'id';
            }

            $key = 'sf.' . sha1(serialize($fields) . serialize($this->ids));

            return $this->driver->until($key, function () use ($fields) {
                $data = [];

                foreach ($this->ids as $id) {
                    $data[$id] = [];
                    $row = $this->row($id);

                    foreach ($fields as $field) {
                        $data[$id][$field] = isAke($row, $field, null);
                    }
                }

                return $data;
            }, $this->age());
        }

        private function merge($tab1, $tab2)
        {
            return array_unique(
                array_merge(
                    $tab1,
                    $tab2
                )
            );
        }

        public function __call($m, $a)
        {
            if ($m == 'is' && count($a) == 2) {
                return $this->where([
                    current($a),
                    '=',
                    end($a)
                ]);
            }

            if ($m == 'or') {
                if (empty($this->query)) {
                    exception('octalia', 'You must have at least one where clause before using the method or.');
                }

                $oldIds = $this->ids;

                $this->iterator($this->ids());

                $this->query[] = 'OR';

                call_user_func_array([$this, 'where'], $a);

                $merged = $this->merge((array) $oldIds, (array) $this->ids);

                $this->iterator(array_values($merged));

                return $this;
            }

            if ($m == 'xor') {
                if (empty($this->query)) {
                    exception('octalia', 'You must have at least one where clause before using the method xor.');
                }

                $oldIds = $this->iterator();

                $this->iterator($this->ids());

                $this->query[] = 'XOR';

                call_user_func_array([$this, 'where'], $a);

                $results = array_merge(array_diff($oldIds, $this->ids), array_diff($this->ids, $oldIds));

                $this->iterator(array_values($results));

                return $this;
            }

            if ($m == 'rawOr') {
                if (empty($this->query)) {
                    exception('octalia', 'You must have at least one where clause before using the method rawOr.');
                }

                $oldIds = $this->ids;

                $this->iterator($this->ids());

                $this->query[] = 'OR';

                call_user_func_array([$this, 'raw'], $a);

                $merged = $this->merge((array) $oldIds, (array) $this->ids);

                $this->iterator(array_values($merged));

                return $this;
            }

            if ($m == 'rawXor') {
                if (empty($this->query)) {
                    exception('octalia', 'You must have at least one where clause before using the method rawXor.');
                }

                $oldIds = $this->iterator();

                $this->iterator($this->ids());

                $this->query[] = 'XOR';

                call_user_func_array([$this, 'raw'], $a);

                $results = array_merge(array_diff($oldIds, $this->ids), array_diff($this->ids, $oldIds));

                $this->iterator(array_values($results));

                return $this;
            }

            if ($m == 'and') {
                return call_user_func_array([$this, 'where'], $a);
            }

            if ($m == 'new') {
                $this->iterator(current($a));

                return $this;
            }

            if ($m == 'list') {
                $field = array_shift($a);

                if (!$field) {
                    $field = ['id'];
                } elseif (fnmatch('*|*', $field)) {
                    $field = explode('|', str_replace(" ", "", $field));
                } elseif (fnmatch('*,*', $field)) {
                    $field = explode(',', str_replace(" ", "", $field));
                }

                if (!is_array($field)) {
                    $field = [$field];
                }

                $list = [];
                $rows = $this->get();

                foreach ($rows as $row) {
                    $value = ['id' => $row['id']];

                    foreach ($field as $f) {
                        $value[$f] = isAke($row, $f, null);
                    }

                    $list[] = $value;
                }

                return coll($list);
            }

            if (fnmatch('findBy*', $m) && strlen($m) > 6) {
                $field = callField($m, 'findBy');

                $op = '=';

                if (count($a) == 2) {
                    $op     = array_shift($a);
                    $value  = array_shift($a);
                } else {
                    $value  = array_shift($a);
                }

                return $this->where([$field, $op, $value]);
            }

            if (fnmatch('getBy*', $m) && strlen($m) > 5) {
                $field = callField($m, 'getBy');

                $op = '=';

                if (count($a) == 2) {
                    $op     = array_shift($a);
                    $value  = array_shift($a);
                } else {
                    $value  = array_shift($a);
                }

                return $this->where([$field, $op, $value]);
            }

            if (fnmatch('where*', $m) && strlen($m) > 5) {
                $field = callField($m, 'where');

                $op = '=';

                if (count($a) == 2) {
                    $op     = array_shift($a);
                    $value  = array_shift($a);
                } else {
                    $value  = array_shift($a);
                }

                return $this->where([$field, $op, $value]);
            }

            if (fnmatch('by*', $m) && strlen($m) > 2) {
                $field = callField($m, 'by');
                $value = array_shift($a);

                return $this->where([$field, '=', $value]);
            }

            if (fnmatch('sortWith*', $m)) {
                $field = callField($m, 'sortWith');

                return $this->sortBy($field);
            }

            if (fnmatch('asortWith*', $m)) {
                $field = callField($m, 'sortWith');

                return $this->sortByDesc($field);
            }

            if (fnmatch('sortDescWith*', $m)) {
                $field = callField($m, 'sortDescWith');

                return $this->sortByDesc($field);
            }

            if (fnmatch('firstBy*', $m) && strlen($m) > 7) {
                $field = callField($m, 'firstBy');
                $value = array_shift($a);

                $model = array_shift($a);

                if (is_null($model)) {
                    $model = true;
                }

                return $this->firstBy($field, $value, $model);
            }

            if (fnmatch('lastBy*', $m) && strlen($m) > 6) {
                $field = callField($m, 'lastBy');
                $value = array_shift($a);

                $model = array_shift($a);

                if (is_null($model)) {
                    $model = true;
                }

                return $this->lastBy($field, $value, $model);
            }

            if (count($a) == 1) {
                $o = array_shift($a);

                if ($o instanceof Object) {
                    $fk = Strings::uncamelize($m) . '_id';

                    return $this->where([$fk, '=', (int) $o->id]);
                }
            }

            $file = path('models') . DS . $this->db . DS . $this->table . '.php';

            if (file_exists($file)) {
                $cbs = require_once $file;

                $scopes = isAke($cbs, 'scopes', []);
                $cb     = isAke($scopes, $m, null);

                if ($cb && is_callable($cb)) {
                    $args = array_merge([$this], $a);

                    return call_user_func_array($cb, $args);
                }
            }

            $data = $this->data();

            return call_user_func_array([coll($data), $m], $a);
        }

        public function first($model = true)
        {
            $i  = $this->iterator();
            $id = current($i);

            if (!$id) return null;

            return $this->find($id, $model);
        }

        public function last($model = true)
        {
            $i  = $this->iterator();
            $id = end($i);

            if (!$id) return null;

            return $this->find($id, $model);
        }

        public function takeFisrt($limit = 1, $model = true)
        {
            return $this->sortBy('id')->take($limit)->get($model);
        }

        public function takeLast($limit = 1, $model = true)
        {
            return $this->sortByDesc('id')->take($limit)->get($model);
        }

        public function slice($offset, $length = null)
        {
            $ids        = array_values(array_slice((array) $this->iterator(), $offset, $length, true));
            $this->ids  = SplFixedArray::fromArray($ids);

            return $this;
        }

        public function sum($field)
        {
            $this->query[] = ['sum' => $field];

            $keyCache = sha1('sum.' . $this->ns . $field . serialize($this->query));

            $this->reset();

            return $this->driver->until($keyCache, function () use ($field) {
                return coll($this->select($field))->sum($field);
            }, $this->age());
        }

        public function min($field)
        {
            $this->query[] = ['min' => $field];

            $keyCache = sha1('min.' . $this->ns . $field . serialize($this->query));

            $this->reset();

            return $this->driver->until($keyCache, function () use ($field) {
                return coll($this->select($field))->min($field);
            }, $this->age());
        }

        public function max($field)
        {
            $this->query[] = ['max' => $field];

            $keyCache = sha1('max.' . $this->ns . $field . serialize($this->query));

            $this->reset();

            return $this->driver->until($keyCache, function () use ($field) {
                return coll($this->select($field))->max($field);
            }, $this->age());
        }

        public function avg($field)
        {
            $this->query[] = ['avg' => $field];

            $keyCache = sha1('avg.' . $this->ns . $field . serialize($this->query));

            $this->reset();

            return $this->driver->until($keyCache, function () use ($field) {
                return coll($this->select($field))->avg($field);
            }, $this->age());
        }

        public function multisort($criteria)
        {
            $this->query[] = ['multisort' => serialize($criteria)];

            $keyCache = sha1('multisort.' . $this->ns . serialize($criteria) . serialize($this->query));

            $ids =  $this->driver->until($keyCache, function () use ($criteria) {
                $results = coll($this->select(array_keys($criteria)))->multisort($criteria);

                return array_values($results->fetch('id')->toArray());
            }, $this->age());

            $this->ids = SplFixedArray::fromArray($ids);

            return $this;
        }

        public function groupBy($field)
        {
            $this->query[] = ['groupBy' => $field];

            $keyCache = sha1('groupBy.' . $this->dir . $field . serialize($this->query));

            $ids =  $this->driver->until($keyCache, function () use ($field) {
                $results = coll($this->select($field))->groupBy($field);

                return array_values($results->fetch('id')->toArray());
            }, $this->age());

            $this->ids = SplFixedArray::fromArray($ids);

            return $this;
        }

        public function sortBy($field)
        {
            $this->query[] = ['sortBy' => $field];

            $keyCache = sha1('sortBy.' . $this->ns . $field . serialize($this->query));

            $ids =  $this->driver->until($keyCache, function () use ($field) {
                $results = coll($this->select($field))->sortBy($field);

                return array_values($results->fetch('id')->toArray());
            }, $this->age());

            $this->ids = SplFixedArray::fromArray($ids);

            return $this;
        }

        public function sortByDesc($field)
        {
            $this->query[] = ['sortByDesc' => $field];

            $keyCache = sha1('sortByDesc.' . $this->ns . $field . serialize($this->query));

            $ids =  $this->driver->until($keyCache, function () use ($field) {
                $results = coll($this->select($field))->sortByDesc($field);

                return array_values($results->fetch('id')->toArray());
            }, $this->age());

            $this->ids = SplFixedArray::fromArray($ids);

            return $this;
        }

        public function lite($key, $operator, $value)
        {
            $liteTable = str_replace('.', '', $this->path);
            Wrapper::sql("DROP TABLE IF EXISTS $liteTable");

            $fields = $this->fields();

            if (!in_array($key, $fields)) {
                $fields[] = $key;
            }

            $sql = 'CREATE TABLE "' . $liteTable . '" (';
            $sql .= '"id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,';
            $sql .= '"row_id" integer NOT NULL DEFAULT \'0\',';
            $sql .= '"created_at" integer NOT NULL DEFAULT \'0\',';
            $sql .= '"updated_at" integer NOT NULL DEFAULT \'0\',';

            foreach ($fields as $field) {
                if (in_array($field, ['id', 'created_at', 'updated_at'])) {
                    continue;
                }

                if (fnmatch('*_id', $field) || ($key == $field && in_array($operator, ['<', '>', '<=', '>=', '=']))) {
                    $sql .= '"' . $field . '" integer NOT NULL DEFAULT \'0\',';
                } else {
                    $sql .= '"' . $field . '" text NULL,';
                }
            }

            $sql = substr($sql, 0, -1);
            $sql .= ');';
            $sql .= 'CREATE INDEX "' . $liteTable . '_row_id" ON "' . $liteTable . '" ("row_id");';

            foreach ($fields as $field) {
                if (fnmatch('*_id', $field)) {
                    $sql .= 'CREATE INDEX "' . $liteTable . '_' . $field . '" ON "' . $liteTable . '" ("' . $field . '");';
                }
            }

            Wrapper::sql($sql);

            $data = $this->select($key);

            foreach ($data as $id => $row) {
                $sql = 'INSERT INTO ' . $liteTable . ' (row_id, ' . $key . ') VALUES (\'' . \SQLite3::escapeString($id) . '\', \'' . \SQLite3::escapeString(isAke($row, $key, null)) . '\');';
                Wrapper::sql($sql);
            }
        }

        public function whereSQL($key, $operator = null, $value = null)
        {
            if (!empty($this->query)) {
                $last = end($this->query);

                if (is_array($last)) {
                    $this->query[] = 'AND';
                }
            }

            if (func_num_args() == 1) {
                if (is_array($key)) {
                    if (count($key) == 1) {
                        $operator   = '=';
                        $value      = array_values($key);
                        $key        = array_keys($key);
                    } elseif (count($key) == 3) {
                        list($key, $operator, $value) = $key;
                    }
                }
            }

            if (func_num_args() == 2) {
                list($value, $operator) = [$operator, '='];
            }

            $operator = strtolower($operator);

            $liteTable = str_replace('.', '', $this->path);

            $this->query[] = [$key, $operator, $value];

            $insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

            if ($insensitive) {
                $operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
            }

            $keyCache = 'whqsql.' . sha1(serialize($this->query) . $this->ns);

            $ids = $this->driver->until($keyCache, function () use ($key, $operator, $value, $liteTable) {
                $this->lite($key, $operator, $value);

                $sql = "SELECT row_id as id FROM $liteTable WHERE $key ";

                switch ($operator) {
                    case '<':
                    case '>':
                    case '<=':
                    case '>=':
                    case '=':
                        if (is_numeric($value)) {
                            $sql .= $operator . ' ' . $value;
                            break;
                        }
                    case 'is':
                    case 'is not':
                        $sql .= $operator . ' NULL';
                        break;
                    case 'between':
                    case 'not between':
                        $sql .= $operator . ' ' . $value[0] . ' AND ' . $value[1];
                        break;
                    case 'like':
                    case 'not like':
                        $sql .= $operator . ' \'' . \SQLite3::escapeString(str_replace('*', '%', $value)) . '\'';
                        break;
                    default:
                        $sql .= $operator . ' \'' . \SQLite3::escapeString($value) . '\'';
                }

                if ($insensitive) {
                    $sql .= ' COLLATE NOCASE';
                }

                $res = Wrapper::sql($sql);

                return array_values(coll($res)->fetch('id')->toArray());
            }, $this->age());

            $this->ids = SplFixedArray::fromArray($ids);

            return $this;
        }

        public function raw($key, $operator = null, $value = null)
        {
            if (!empty($this->query)) {
                $last = end($this->query);

                if (is_array($last)) {
                    $this->query[] = 'AND';
                }
            }

            $nargs = func_num_args();

            if ($nargs == 1) {
                if (is_array($key)) {
                    if (count($key) == 1) {
                        $operator   = '=';
                        $value      = array_values($key);
                        $key        = array_keys($key);
                    } elseif (count($key) == 3) {
                        list($key, $operator, $value) = $key;
                    }
                }
            } elseif ($nargs == 2) {
                list($value, $operator) = [$operator, '='];
            } elseif ($nargs == 3) {
                list($key, $operator, $value) = func_get_args();
            } else {
                exception('octalia', "This method requires at least one argument to proceed.");
            }

            $operator = Strings::lower($operator);

            $liteTable = str_replace('.', '', $this->path);

            $this->query[] = [$key, $operator, $value];

            $keyCache = 'owhs.' . sha1(serialize($this->query) . $this->ns);

            $collection = coll($this->driver->get('rows', []));

            $results    = $collection->filter(function($item) use ($key, $operator, $value) {
                $item   = (object) $item;
                $actual = isset($item->{$key}) ? $item->{$key} : null;

                $id = $item->id;

                $insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

                if ((!is_array($actual) || !is_object($actual)) && $insensitive) {
                    $actual = Strings::lower(Strings::unaccent($actual));
                }

                if ((!is_array($value) || !is_object($value)) && $insensitive) {
                    $value  = Strings::lower(Strings::unaccent($value));
                }

                if ($insensitive) {
                    $operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
                }

                if ($key == 'id' || fnmatch('*_id', $key) && is_numeric($actual)) {
                    $actual = (int) $actual;
                }

                if (is_null($actual) && in_array($operator, ['=', '!=', '<>', '<', '>', '>=', '<='])) {
                    $v = Strings::lower(Strings::unaccent($value));

                    if (!is_null($value) || 'null' != $v) {
                        return false;
                    }
                }

                switch ($operator) {
                    case '<>':
                    case '!=':
                        return sha1(serialize($actual)) != sha1(serialize($value));
                    case '>':
                        return $actual > $value;
                    case '<':
                        return $actual < $value;
                    case '>=':
                        return $actual >= $value;
                    case '<=':
                        return $actual <= $value;
                    case 'between':
                        return $actual >= $value[0] && $actual <= $value[1];
                    case 'not between':
                        return $actual < $value[0] || $actual > $value[1];
                    case 'in':
                        return in_array($actual, $value);
                    case 'not in':
                        return !in_array($actual, $value);
                    case 'regex':
                        return preg_match($value, $actual);
                    case 'not regex':
                        return !preg_match($value, $actual);
                    case 'like':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        return fnmatch($value, $actual);
                    case 'not like':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        $check  = fnmatch($value, $actual);

                        return !$check;
                    case 'is':
                        return is_null($actual);
                    case 'is not':
                        return !is_null($actual);
                    case '=':
                    default:
                        return sha1(serialize($actual)) == sha1(serialize($value));
                }
            });

            $ids = array_values($results->fetch('id')->toArray());

            $this->ids = SplFixedArray::fromArray($ids);

            return $this;
        }

        public function where($key, $operator = null, $value = null)
        {
            if (!empty($this->query)) {
                $last = end($this->query);

                if (is_array($last)) {
                    $this->query[] = 'AND';
                }
            }

            if ($key instanceof \Closure) {
                return $key($this);
            }

            if ($key instanceof Object) {
                $fkTable = $key->table();

                return $this->where($fkTable . '_id', (int) $key->id);
            }

            if ($key instanceof Octalia) {
                $joins = $key->get();

                $ids = [];

                foreach ($joins as $row) {
                    $ids[] = $row['id'];
                }

                $table  = $key->table;
                $fk     = $table . '_id';

                return $this->where([$fk, 'IN', $ids]);
            }

            if ($key instanceof OctaliaIterator) {
                $joins = $key;

                $ids = [];

                foreach ($joins as $row) {
                    $ids[] = $row['id'];
                }

                $table  = $key->table();
                $fk     = $table . '_id';

                return $this->where([$fk, 'IN', $ids]);
            }

            $nargs = func_num_args();

            if ($nargs == 1) {
                if (is_array($key)) {
                    if (count($key) == 1) {
                        $operator   = '=';
                        $value      = array_values($key);
                        $key        = array_keys($key);
                    } elseif (count($key) == 3) {
                        list($key, $operator, $value) = $key;
                    }
                }
            } elseif ($nargs == 2) {
                list($value, $operator) = [$operator, '='];
            } elseif ($nargs == 3) {
                list($key, $operator, $value) = func_get_args();
            } else {
                exception('Octalia', "This method requires at least one argument to proceed.");
            }

            if ($value instanceof \Closure) {
                $value = $value($this);
            }

            $operator = Strings::lower($operator);

            $liteTable = str_replace('.', '', $this->path);

            $this->query[] = [$key, $operator, $value];

            $this->fire('query', $this->query);

            $keyCache = 'owhs.' . sha1(serialize($this->query) . $this->ns);

            $ids = $this->driver->until($keyCache, function () use ($key, $operator, $value) {
                $collection = coll($this->select($key));

                $results    = $collection->filter(function($item) use ($key, $operator, $value) {
                    $item   = (object) $item;
                    $actual = isset($item->{$key}) ? $item->{$key} : null;

                    $insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

                    if ((!is_array($actual) || !is_object($actual)) && $insensitive) {
                        $actual = Strings::lower(Strings::unaccent($actual));
                    }

                    if ((!is_array($value) || !is_object($value)) && $insensitive) {
                        $value  = Strings::lower(Strings::unaccent($value));
                    }

                    if ($insensitive) {
                        $operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
                    }

                    if ($key == 'id' || fnmatch('*_id', $key) && is_numeric($actual)) {
                        $actual = (int) $actual;
                    }

                    if (is_null($actual) && in_array($operator, ['=', '!=', '<>', '<', '>', '>=', '<='])) {
                        $v = Strings::lower(Strings::unaccent($value));

                        if (!is_null($value) || 'null' != $v) {
                            return false;
                        }
                    }

                    switch ($operator) {
                        case '<>':
                        case '!=':
                            return sha1(serialize($actual)) != sha1(serialize($value));
                        case '>':
                            return $actual > $value;
                        case '<':
                            return $actual < $value;
                        case '>=':
                            return $actual >= $value;
                        case '<=':
                            return $actual <= $value;
                        case 'between':
                            return $actual >= $value[0] && $actual <= $value[1];
                        case 'not between':
                            return $actual < $value[0] || $actual > $value[1];
                        case 'in':
                            $value = !is_array($value)
                                ? explode(',', str_replace([' ,', ', '], ',', $value))
                                : $value;

                            return in_array($actual, $value);
                        case 'not in':
                            $value = !is_array($value)
                                ? explode(',', str_replace([' ,', ', '], ',', $value))
                                : $value;

                            return !in_array($actual, $value);
                        case 'like':
                            $value  = str_replace("'", '', $value);
                            $value  = str_replace('%', '*', $value);

                            return fnmatch($value, $actual);
                        case 'not like':
                            $value  = str_replace("'", '', $value);
                            $value  = str_replace('%', '*', $value);

                            $check  = fnmatch($value, $actual);

                            return !$check;
                        case 'is':
                            return is_null($actual);
                        case 'is not':
                            return !is_null($actual);
                        case '=':
                        default:
                            return sha1(serialize($actual)) == sha1(serialize($value));
                    }
                });

                $ids = array_values($results->fetch('id')->toArray());

                return $ids;
            }, $this->age());

            $this->ids = SplFixedArray::fromArray($ids);

            return $this;
        }

        public function exists()
        {
            if (!empty($this->query)) {
                return $this->count() > 0;
            }

            return false;
        }

        public function isEmpty()
        {
            return $this->count() == 0;
        }

        public function hasNoRows()
        {
            return $this->isEmpty();
        }

        public function hasRows()
        {
            return !$this->isEmpty();
        }

        public function isNotEmpty()
        {
            return $this->hasRows();
        }

        public function map(callable $callback, $fields = null)
        {
            $fields     = is_null($fields) ? $this->fields() : $fields;
            $data       = $this->select($fields);

            $results    = coll($data)->each($callback);

            $this->ids = array_values($results->fetch('id')->toArray());

            return $this;
        }

        public function filter(callable $callback, $fields = null)
        {
            $fields     = is_null($fields) ? $this->fields() : $fields;
            $data       = $this->select($fields);

            $results    = coll($data)->filter($callback);

            $this->iterator(array_values($results->fetch('id')->toArray()));

            return $this;
        }

        public function fetch($field)
        {
            return $this->select($field);
        }

        public function paginate($page, $perPage)
        {
            return $this->new(
                array_slice(
                    (array) $this->iterator(),
                    ($page - 1) * $perPage,
                    $perPage
                )
            );
        }

        public function deletes()
        {
            $deleted = 0;

            foreach ($this->get() as $item) {
                if (isset($item['id'])) {
                    $row = $this->find((int) $item['id']);

                    if ($row) {
                        $row->delete();
                        $deleted++;
                    }
                }
            }

            return $deleted;
        }

        public function update(array $criteria)
        {
            $criteria = is_object($criteria) ? $criteria->toArray() : $criteria;

            $affected = 0;

            foreach ($this->get() as $item) {
                if (isset($item['id'])) {
                    $row = $this->find((int) $item['id']);

                    if ($row) {
                        foreach ($criteria as $k => $v) {
                            $setter = setter($k);
                            $v      = value($v);
                            $row->$setter($v);
                        }

                        $row->save();
                        $affected++;
                    }
                }
            }

            return $affected;
        }

        public function get($model = true)
        {
            $this->reset();

            $iterator = lib('OctaliaIterator', [$this]);

            return $this->fire('get', $model ? $iterator->model() : $iterator);
        }

        public function items()
        {
            $this->reset();

            $iterator = lib('OctaliaIterator', [$this]);

            return $this->fire('get', $iterator->item());
        }

        public function all()
        {
            return $this->newQuery()->get();
        }

        public function models()
        {
            return $this->get(true);
        }

        public function foreign()
        {
            return $this->get(false)->foreign();
        }

        public function splice($offset, $length = null, $replacement = [])
        {
            if (func_num_args() == 1) {
                return $this->new(
                    array_values(
                        array_splice(
                            (array) $this->getIterator(),
                            $offset
                        )
                    )
                );
            }

            return $this->new(
                array_values(
                    array_splice(
                        (array) $this->getIterator(),
                        $offset,
                        $length,
                        $replacement
                    )
                )
            );
        }

        public function average($field)
        {
            return $this->avg($field);
        }

        public function take($limit = null)
        {
            if ($limit < 0) {
                return $this->slice($limit, abs($limit));
            }

            return $this->slice(0, $limit);
        }

        public function limit($o, $l)
        {
            return $this->slice($o, $l);
        }

        public function like($field, $value)
        {
            return $this->where($field, 'like', $value);
        }

        public function orLike($field, $value)
        {
            return $this->or($field, 'like', $value);
        }

        public function notLike($field, $value)
        {
            return $this->where($field, 'not like', $value);
        }

        public function orNotLike($field, $value)
        {
            return $this->or($field, 'not like', $value);
        }

        public function findBy($field, $value)
        {
            if (is_array($value)) {
                return $this->in($field, $value);
            }

            return $this->where($field, $value);
        }

        public function firstBy($field, $value, $model = true)
        {
            if (is_array($value)) {
                return $this->in($field, $value)->first($model);
            }

            return $this->where($field, $value)->first($model);
        }

        public function lastBy($field, $value, $model = true)
        {
            if (is_array($value)) {
                return $this->in($field, $value)->last($model);
            }

            return $this->where($field, $value)->last($model);
        }

        public function in($field, array $values)
        {
            return $this->where($field, 'in', $values);
        }

        public function orIn($field, array $values)
        {
            return $this->or($field, 'in', $values);
        }

        public function notIn($field, array $values)
        {
            return $this->where($field, 'not in', $values);
        }

        public function orNotIn($field, array $values)
        {
            return $this->or($field, 'not in', $values);
        }

        public function WhereIn($field, array $values)
        {
            return $this->where($field, 'in', $values);
        }

        public function whereNotIn($field, array $values)
        {
            return $this->where($field, 'not in', $values);
        }

        public function rand($default = null)
        {
            $items = (array) $this->getIterator();

            if (!empty($items)) {
                shuffle($items);

                $row = current($items);

                return $this->find($row['id'], false);
            }

            return $default;
        }

        public function between($field, $min, $max)
        {
            return $this->where($field, 'between', [$min, $max]);
        }

        public function orBetween($field, $min, $max)
        {
            return $this->or($field, 'between', [$min, $max]);
        }

        public function notBetween($field, $min, $max)
        {
            return $this->where($field, 'not between', [$min, $max]);
        }

        public function orNotBetween($field, $min, $max)
        {
            return $this->or($field, 'not between', [$min, $max]);
        }

        public function isNull($field)
        {
            return $this->where($field, 'is', 'null');
        }

        public function orIsNull($field)
        {
            return $this->or($field, 'is', 'null');
        }

        public function isNotNull($field)
        {
            return $this->where($field, 'is not', 'null');
        }

        public function orIsNotNull($field)
        {
            return $this->or($field, 'is not', 'null');
        }

        public function post($create = false)
        {
            $row = $this->create($_POST);

            return $create ? $row->save() : $row;
        }

        public function storePost()
        {
            return $this->post(true);
        }

        public function lt($field, $value)
        {
            return $this->where($field, '<', $value);
        }

        public function orLt($field, $value)
        {
            return $this->or($field, '<', $value);
        }

        public function gt($field, $value)
        {
            return $this->where($field, '>', $value);
        }

        public function orGt($field, $value)
        {
            return $this->or($field, '>', $value);
        }

        public function lte($field, $value)
        {
            return $this->where($field, '<=', $value);
        }

        public function orLte($field, $value)
        {
            return $this->or($field, '<=', $value);
        }

        public function gte($field, $value)
        {
            return $this->where($field, '>=', $value);
        }

        public function orGte($field, $value)
        {
            return $this->or($field, '>=', $value);
        }

        public function before($date, $strict = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $strict ? $this->lt('created_at', $date) : $this->lte('created_at', $date);
        }

        public function orBefore($date, $strict = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $strict ? $this->orLt('created_at', $date) : $this->orLte('created_at', $date);
        }

        public function after($date, $strict = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $strict ? $this->gt('created_at', $date) : $this->gte('created_at', $date);
        }

        public function orAfter($date, $strict = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $strict ? $this->orGt('created_at', $date) : $this->orGte('created_at', $date);
        }

        public function when($field, $op, $date)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $this->where([$field, $op, $date]);
        }

        public function orWhen($field, $op, $date)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $this->or($field, $op, $date);
        }

        public function deleted()
        {
            return $this->lte('deleted_at', microtime(true));
        }

        public function orDeleted()
        {
            return $this->orLte('deleted_at', microtime(true));
        }

        public function findOrFail($id, $model = true)
        {
            $row = $this->row($id);

            if (!$row) {
                exception('octalia', "The row $id does not exist.");
            } else {
                return $model ? $this->model($row) : $row;
            }
        }

        public function firstOrFail($model = true)
        {
            $row = $this->first();

            if (!$row) {
                exception('octalia', "The row does not exist.");
            } else {
                return $model ? $this->model($row) : $row;
            }
        }

        public function lastOrFail($model = true)
        {
            $row = $this->last();

            if (!$row) {
                exception('octalia', "The row does not exist.");
            } else {
                return $model ? $this->model($row) : $row;
            }
        }

        public function noTuple($conditions)
        {
            $conditions = is_object($conditions) ? $conditions->toArray() : $conditions;

            foreach ($conditions as $k => $v) {
                $this->where($k, $v);
            }

            if ($this->count() == 0) {
                $this->store($conditions);
            } else {
                return $this->first(true);
            }
        }

        public function unique($conditions)
        {
            return $this->noTuple($conditions);
        }

        function search($conditions)
        {
            $conditions = is_object($conditions) ? $conditions->toArray() : $conditions;

            foreach ($conditions as $field => $value) {
                $this->where($field, $value);
            }

            return $this;
        }

        public function firstByAttributes($attributes, $model = true)
        {
            $attributes = is_object($attributes) ? $attributes->toArray() : $attributes;

            $q = $this;

            foreach ($attributes as $field => $value) {
                $q->where($field, $value);
            }

            return $q->first($model);
        }

        public function firstOrCreate($conditions)
        {
            $conditions = is_object($conditions) ? $conditions->toArray() : $conditions;

            $q = $this;

            foreach ($conditions as $field => $value) {
                $q->where($field, $value);
            }

            $exists = $q->first(true);

            if (null === $exists) {
                return $this->save($conditions);
            }

            return $exists;
        }

        public function firstOrNew($conditions)
        {
            $conditions = is_object($conditions) ? $conditions->toArray() : $conditions;

            $q = $this;

            foreach ($conditions as $field => $value) {
                $q->where($field, $value);
            }

            $exists = $q->first(true);

            if (null === $exists) {
                return $this->model($conditions);
            }

            return $exists;
        }

        public function getQuery()
        {
            return $this->query;
        }

        public function custom(callable $cb)
        {
            return $this->get()->hook($cb);
        }

        public function copy($to)
        {
            if (!fnmatch('*.*', $to)) {
                $to = $this->db . ".$to";
            }

            if ($this->driver instanceof Cache) {
                $actual = $this->getDirectory();

                $tab = explode(DS, $actual);

                array_pop($tab);

                $newDir = implode(DS, $tab) . DS . 'odb.' . $to;

                File::cpdir($actual, $newDir);

                list($newDb, $newTable) = explode('.', $to, 2);

                $new = new self($newDb, $newTable, $this->driver);

                $new->age(microtime(true));

                return $new;
            } elseif ($this->driver instanceof Now) {
                list($newDb, $newTable) = explode('.', $to, 2);

                $this->driver->changeNamespace("ndb.$newDb.$newTable");

                $this->age(microtime(true));

                return $this;
            }
        }

        public function rename($to)
        {
            if (!fnmatch('*.*', $to)) {
                $to = $this->db . ".$to";
            }

            if ($this->driver instanceof Cache) {
                $actual = $this->getDirectory();

                $tab = explode(DS, $actual);

                array_pop($tab);

                $newDir = implode(DS, $tab) . DS . 'odb.' . $to;

                File::cpdir($actual, $newDir);
                $this->drop();

                list($newDb, $newTable) = explode('.', $to, 2);

                $new = new self($newDb, $newTable, $this->driver);

                $new->age(microtime(true));

                return $new;
            }
        }

        public function transaction(callable $callback)
        {
            return new OctaliaTransaction($this, $callback);
        }

        public function awake()
        {
            $next = 'AND';

            foreach ($this->query as $segment) {
                if (is_array($segment)) {
                    if (count($segment) == 3) {
                        $field      = array_shift($segment);
                        $operator   = array_shift($segment);
                        $value      = array_shift($segment);

                        if ($next == 'AND') {
                            $this->where([$field, $operator, $value]);
                        } elseif ($next == 'OR') {
                            $this->or([$field, $operator, $value]);
                        } elseif ($next == 'XOR') {
                            $this->xor([$field, $operator, $value]);
                        }
                    } elseif (count($segment) == 1) {
                        $m      = key($segment);
                        $field  = $segment[$m];

                        $this->$m($field);
                    }
                } elseif (is_string($segment)) {
                    $next = $segment;
                }
            }

            return $this;
        }

        public function fromArray(array $datas)
        {
            foreach ($datas as $row) {
                $id = isAke($row, 'id', null);

                if (!$id || !is_numeric($id)) {
                    $row['id'] = $this->makeId();
                }

                $this->save($row);
            }

            return count($datas);
        }

        public function newQuery()
        {
            return new self($this->db, $this->table, $this->driver);
        }

        public function json($json)
        {
            $datas = json_decode($json, true);

            if (is_array($datas)) {
                foreach ($datas as $row) {
                    $id = isAke($row, 'id', null);

                    if (!$id || !is_numeric($id)) {
                        $row['id'] = $this->makeId();
                    }

                    $this->save($row);
                }
            }

            return count($datas);
        }

        public function csv($csv)
        {
            $datas  = explode("\n", $csv);
            $fields = explode(';', Arrays::first($datas));
            $count  = count($datas);

            for ($i = 1; $i < $count; $i++) {
                $data   = trim($datas[$i]);
                $row    = [];
                $j      = 0;
                $values = explode(';', $data);

                foreach ($fields as $field) {
                    $row[$field] = $values[$j];
                    $j++;
                }

                $id = isAke($row, 'id', null);

                if (!$id || !is_numeric($id)) {
                    $row['id'] = $this->makeId();
                }

                $this->save($row);
            }

            return $count - 1;
        }

        public function fromFile($file, $type = 'csv')
        {
            if (File::exists($file)) {
                if ($type == 'csv') {
                    $data = File::read($file);

                    return $this->csv($data);
                } elseif ($type == 'json') {
                    $data = File::read($file);

                    return $this->json($data);
                } elseif ($type == 'array') {
                    $array = include $file;

                    return $this->fromArray($array);
                }
            }
        }

        public function read($row)
        {
            if (is_object($row)) {
                return $row;
            }

            $model = $this->model($row);

            $before = aget($model->hooks, 'before.read', null);
            $after  = aget($model->hooks, 'after.read', null);

            if (is_callable($before)) {
                $row = $before($row, $this);
            }

            if (is_callable($after)) {
                $after($row, $this);
            }

            return $this->fire('fetch', $row);
        }

        public function attach($data)
        {
            $sync = Registry::get('octalia.sync');

            if ($sync) {
                $tables = [$this->table, $sync->table()];
                sort($tables);

                $table = implode('', $tables);

                if ($data instanceof Object) {
                    $data = [$data->getId()];
                } elseif (is_numeric($data)) {
                    $data = [(int) $data];
                }

                if (is_array($data) && !empty($data)) {
                    $db = new self($sync->db(), $table, $sync->driver());

                    foreach ($this->ids as $id) {
                        foreach ($data as $syncId) {
                            if (is_numeric($syncId)) {
                                $db->firstOrCreate([
                                    $sync->table() . '_id' => $syncId,
                                    $this->table . '_id' => $id
                                ]);
                            }
                        }
                    }

                    return true;
                }
            }

            return false;
        }

        public function detach($data)
        {
            $sync = Registry::get('octalia.sync');

            if ($sync) {
                $tables = [$this->table, $sync->table()];
                sort($tables);

                $table = implode('', $tables);

                if ($data instanceof Object) {
                    $data = [$data->getId()];
                } elseif (is_numeric($data)) {
                    $data = [(int) $data];
                }

                if (is_array($data) && !empty($data)) {
                    $db = new self($sync->db(), $table, $sync->driver());

                    foreach ($this->ids as $id) {
                        foreach ($data as $syncId) {
                            if (is_numeric($syncId)) {
                                $db->where([$sync->table() . '_id', '=', (int) $syncId])
                                ->where([$this->table . '_id', '=', (int) $id])
                                ->delete();
                            }
                        }
                    }

                    return true;
                }
            }

            return false;
        }

        public function sync($data)
        {
            $sync = Registry::get('octalia.sync');

            if ($sync) {
                $tables = [$this->table, $sync->table()];
                sort($tables);

                $table = implode('', $tables);

                if ($data instanceof Object) {
                    $data = [$data->getId()];
                } elseif (is_numeric($data)) {
                    $data = [(int) $data];
                }

                if (is_array($data)) {
                    $db = new self($sync->db(), $table, $sync->driver());

                    foreach ($this->ids as $id) {
                        $db->where([$this->table . '_id', '=', (int) $id])->delete();

                        foreach ($data as $syncId) {
                            if (is_numeric($syncId)) {
                                $db->firstOrCreate([
                                    $sync->table() . '_id' => $syncId,
                                    $this->table . '_id' => $id
                                ]);
                            }
                        }
                    }

                    return true;
                }
            }

            return false;
        }

        public function toggle($data)
        {
            $sync = Registry::get('octalia.sync');

            if ($sync) {
                $tables = [$this->table, $sync->table()];
                sort($tables);

                $table = implode('', $tables);

                if ($data instanceof Object) {
                    $data = [$data->getId()];
                } elseif (is_numeric($data)) {
                    $data = [(int) $data];
                }

                if (is_array($data)) {
                    $db = new self($sync->db(), $table, $sync->driver());

                    foreach ($this->ids as $id) {
                        $db->where([$this->table . '_id', '=', (int) $id])->delete();

                        foreach ($data as $syncId) {
                            if (is_numeric($syncId)) {
                                $exists = $db
                                ->where([$sync->table() . '_id', '=', (int) $syncId])
                                ->where([$this->table . '_id', '=', (int) $id])
                                ->first(true);

                                if ($exists) {
                                    $exists->delete();
                                } else {
                                    $db->store([
                                        $sync->table() . '_id' => $syncId,
                                        $this->table . '_id' => $id
                                    ]);
                                }
                            }
                        }
                    }

                    return true;
                }
            }

            return false;
        }

        public function repository()
        {
            return $this->get()->repository();
        }


        public function collection()
        {
            return $this->get()->collection();
        }

        public function query()
        {
            $conditions = array_chunk(func_get_args(), 3);

            foreach ($conditions as $condition) {
                $this->where($condition);
            }

            return $this;
        }

        public function lookfor($conditions, $cursor = false)
        {
            $conditions = is_object($conditions) ? $conditions->toArray() : $conditions;

            foreach ($conditions as $field => $value) {
                $this->where($field, $value);
            }

            return $cursor ? $this->get() : $this;
        }

        public function pluck($field, $default = null)
        {
            $tab = $this->first();

            if (is_string($field)) {
                return isAke($tab, $field, $default);
            } elseif (is_array($field)) {
                $collection = [];

                foreach ($field as $f) {
                    $collection[$f] = isAke($tab, $f, null);
                }

                return $collection;
            }

            return $default;
        }

        public static function listen(callable $cb)
        {
            $cbs = Registry::get('octalia.listen', []);
            $cbs[] = $cb;
            Registry::set('octalia.listen', $cbs);
        }

        public function findOrNew($id)
        {
            if (!is_null($model = $this->find((int) $id))) {
                return $model;
            }

            return $this->model([]);
        }

        public function updateOrCreate($attributes, array $values = [])
        {
            return $this->firstOrCreate($attributes)->fill($values)->save();
        }

        public function firstWhere(array $where, $object = true)
        {
            return $this->where($where)->first($object);
        }

        public function lastWhere(array $where, $object = true)
        {
            return $this->where($where)->last($object);
        }

        public function firstCreated($object = false)
        {
            return $this->sortBy('id')->first($object);
        }

        public function lastCreated($object = false)
        {
            return $this->sortBy('id')->last($object);
        }

        public function findFirstBy($field, $value, $object = false)
        {
            return $this->where($field, $value)->first($object);
        }

        public function findLastBy($field, $value, $object = false)
        {
            return $this->where($field, $value)->last($object);
        }

        public function multiQuery(array $queries)
        {
            foreach ($queries as $query) {
                $count = count($query);

                switch ($count) {
                    case 4:
                        list($field, $op, $value, $operand) = $query;
                        break;
                    case 3:
                        list($field, $op, $value) = $query;
                        $operand = 'and';
                        break;
                    case 2:
                        list($field, $value) = $query;
                        $operand = 'and';
                        $op = '=';
                        break;
                }

                $operand = Strings::lower($operand);

                $this->$operand([$field, $op, $value]);
            }

            return $this;
        }

        public function through()
        {
            $args = func_get_args();

            $t2 = array_pop($args);
            $t1 = array_pop($args);

            $where = $args;

            if (!fnmatch('*.*', $t1)) {
                $database = $this->db;
            } else {
                list($database, $t1) = explode('.', $t1, 2);
            }

            $where = empty($where) ? [['id', '>', 0]] : $where;

            $db1 = new self($database, $t1, $this->driver);

            $fk = $this->table . '_id';

            $rows = $this
            ->multiQuery($where)
            ->get();

            $ids = [];

            foreach ($rows as $row) {
                $ids[] = $row['id'];
            }

            $sub = $db1
            ->where([$fk, 'IN', implode(',', $ids)])
            ->get();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            if (!fnmatch('*.*', $t2)) {
                $database = $this->db;
            } else {
                list($database, $t2) = explode('.', $t2, 2);
            }

            return (new self($database, $t2, $this->driver))
            ->where([$fk2, 'IN', implode(',', $ids)])
            ->get();
        }

        public function findAndModify($where, array $update)
        {
            unset($update['id']);

            $where = is_numeric($where) ? ['id', '=', $where] : $where;

            $rows = $this->where($where)->get();

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $id = isAke($row, 'id', 0);

                    if ($id > 0) {
                        $data = array_merge($row, $update);
                        $this->model($data)->save();
                    }
                }
            }

            return $this->where($where);
        }

        public function refresh()
        {
            $this->age(time());

            return $this;
        }

        private function intersect($tab1, $tab2)
        {
            $ids1       = [];
            $ids2       = [];
            $collection = [];

            foreach ($tab1 as $row) {
                $id = isAke($row, 'id', null);

                if (strlen($id)) {
                    array_push($ids1, $id);
                }
            }

            foreach ($tab2 as $row) {
                $id = isAke($row, 'id', null);

                if (strlen($id)) {
                    array_push($ids2, $id);
                }
            }

            $sect = array_intersect($ids1, $ids2);

            if (!empty($sect)) {
                foreach ($sect as $idRow) {
                    array_push($collection, $this->row($idRow));
                }
            }

            return $collection;
        }

        public function has(Octalia $model)
        {
            $ids = [];

            $rows = $this->get();

            $fk = $this->table . '_id';

            foreach ($rows as $row) {
                $relations = $model->where($fk, (int) $row['id'])->get();

                if ($relations->count()) {
                    $ids[] = $row['id'];
                }
            }

            if (empty($ids)) {
                return $this->where(['id', '<', 0]);
            }

            return $this->where(['id', 'IN', $ids]);
        }

        public static function getFields(Octalia $model)
        {
            $file = path('models') . DS . $model->db . DS  . $model->table . '.php';

            if (File::exists($file)) {
                $infos = include($file);

                return array_keys($infos['fields']);
            }

            return ['id', 'created_at', 'updated_at'];
        }

        public function __invoke(array $data = [])
        {
            return $this->model($data);
        }

        public function fire($event, $concern = null, $return = false)
        {
            $key = 'octalia.' .
            lcfirst(Strings::camelize($this->db . '_' . $this->table))
            . '.' . $event;

            if (Fly::has($key)) {
                $result = Fly::listen($key, $concern, $this);

                if ($return) {
                    return $result;
                }
            }

            return $concern;
        }

        public function on($event, callable $cb, $back = null)
        {
            $key = 'octalia.' .
            lcfirst(Strings::camelize($this->db . '_' . $this->table))
            . '.' . $event;

           Fly::on($key, $cb);

           return is_null($back) ? $this : $back;
        }

        protected function _events()
        {
            actual('entity', $this);

            $this->on('added', function ($row) {
                $this->logs('added', $row);

                return $row;
            });

            $this->on('created', function ($row) {
                $this->logs('created', $row);

                return $row;
            });

            $this->on('updated', function ($row) {
                $this->logs('updated', $row);

                return $row;
            });

            $this->on('deleted', function ($row) {
                $this->logs('deleted', $row);

                return $row;
            });

            $this->on('query', function ($query) {
                $this->logs('query', $query, true);

                return $query;
            });
        }

        public function logs($key = null, $value = null, $replace = false)
        {
            $k = 'octalia.' .
            lcfirst(Strings::camelize($this->db . '_' . $this->table))
            . '.logs';

            $logs = Registry::get($k, []);

            if (is_null($key)) {
                return $logs;
            }

            if (is_null($value)) {
                return isAke($logs, $key, false === $replace ? [] : null);
            }

            if (false === $replace) {
                if (!isset($logs[$key])) {
                    $logs[$key] = [];
                }

                $logs[$key][] = $value;
            } else {
                $logs[$key] = $value;
            }

            Registry::set($k, $logs);
        }

        public function policy($event, callable $callable)
        {
            $guard = guard();

            $policy = $this->db . '.' . $this->table . '.' . $event;

            call_user_func_array([$guard, 'policy'], [$policy, $callable]);

            return $this;
        }

        public function allows()
        {
            $args = func_get_args();

            $event = array_shift($args);

            $guard = guard();

            $policy = $this->db . '.' . $this->table . '.' . $event;

            $argsMethod = array_merge([$policy], $args);

            return call_user_func_array([$guard, 'allows'], $argsMethod);
        }

        public function can()
        {
            return call_user_func_array([$this, 'allows'], func_get_args());
        }

        public function toArray()
        {
            return $this->get()->toArray();
        }

        public function toJson()
        {
            return $this->get()->toJson();
        }

        public function reduce(callable $callable)
        {
            $ids = [];

            foreach ($this->get(false) as $row) {
                if ($check = call_user_func_array($callable, [$row])) {
                    $ids[] = $row['id'];
                }
            }

            return $this->where('id', 'IN', $ids);
        }
    }
