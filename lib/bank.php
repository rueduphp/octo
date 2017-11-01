<?php

namespace Octo;

use function serialize;

class Bank implements FastOrmInterface
{
    /**
     * @var string
     */
    private $database;

    /**
     * @var string
     */
    private $table;

    /**
     * @var FastStorageInterface
     */
    private $engine;

    /**
     * @var null
     */
    private $computed = null;

    /**
     * @param string $database
     * @param string $table
     * @param FastStorageInterface $engine
     */
    public function __construct(string $database, string $table, FastStorageInterface $engine)
    {
        $this->database = $database;
        $this->table    = $table;
        $this->engine   = $engine;
    }

    /**
     * @return $this
     */
    public function reset()
    {
        $this->computed = null;

        return $this;
    }

    /**
     * @return Bank
     */
    public function newQuery()
    {
        return $this->reset();
    }

    /**
     * @return string
     */
    protected function makeKey()
    {
        $args = array_merge([$this->database, $this->table], func_get_args());

        return implode('.', $args);
    }

    /**
     * @return int
     */
    protected function makeId()
    {
        $keyIds = $this->makeKey('ids');
        $keyLastid = $this->makeKey('lastid');
        $keyCount = $this->makeKey('count');

        $id = $this->engine->incr($keyIds);
        $this->engine->incr($keyCount);
        $this->engine->set($keyLastid, $id);

        return $id;
    }

    /**
     * @param null $t
     *
     * @return mixed
     */
    public function age($t = null)
    {
        $key = $this->makeKey('age');

        if (empty($t)) {
            $t = $this->engine->getOr($key, function () {
                return microtime(true);
            });
        } else {
            $this->engine->set($key, $t);
        }

        return $t;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function store($data)
    {
        $row = arrayable($data) ? $data->toArray() : $data;

        $id = isAke($row, 'id', null);

        return $id ? $this->alter($row) : $this->insert($row);
    }

    public function lastid()
    {
        $keyLastid = $this->makeKey('lastid');

        return $this->engine->get($keyLastid, 1);
    }

    /**
     * @param array $row
     * @return array
     */
    protected function add(array $row)
    {
        $fields = array_keys($row);

        foreach ($fields as $field) {
            $key = $this->makeKey('f', $row['id'], $field);
            $this->engine->set($key, $row[$field]);
        }

        $this->engine->set($this->makeKey('r', $row['id']), $row);

        $key = $this->makeKey('fields');

        if (!$this->engine->has($key)) {
            $this->engine->set($key, $fields);
        }

        $this->age(microtime(true));

        return $row;
    }

    /**
     * @param $id
     * @return bool
     */
    public function delete($id)
    {
        $row = $this->engine->get($this->makeKey('r', $id));

        if ($row) {
            $fields = array_keys($row);

            foreach ($fields as $field) {
                $key = $this->makeKey('f', $row['id'], $field);
                $this->engine->del($key, $row[$field]);
            }

            $this->engine->del($this->makeKey('r', $id));

            $keyCount = $this->makeKey('count');
            $this->engine->decr($keyCount);

            $this->age(microtime(true));

            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function all()
    {
        $results = [];

        $rows = $this->engine->keys($this->makeKey('r', '*'));

        foreach ($rows as $row) {
            $results[] = $this->engine->get($row);
        }

        $this->reset();

        return $results;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function find($id)
    {
        $this->reset();

        return $this->engine->get($this->makeKey('r', $id));
    }

    /**
     * @param $row
     * @return array
     */
    protected function alter($row)
    {
        $row['updated_at'] = time();

        return $this->add($row);
    }

    /**
     * @param $row
     * @return array
     */
    protected function insert($row)
    {
        $row['id'] = $this->makeId();
        $row['updated_at'] = $row['created_at'] = time();

        return $this->add($row);
    }

    /**
     * @return $this
     */
    public function drop()
    {
        $rows = $this->engine->keys($this->makeKey('r', '*'));

        foreach ($rows as $row) {
            $id = Arrays::last(explode('.', $row));
            $this->delete($id);
        }

        $keyIds     = $this->makeKey('ids');
        $keyLastid  = $this->makeKey('lastid');
        $keyCount   = $this->makeKey('count');

        $this->engine->del($keyIds);
        $this->engine->del($keyCount);
        $this->engine->del($keyLastid);

        $this->engine->flush();

        return $this;
    }

    /**
     * @param null $fields
     * @return array
     */
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

        $data = [];

        foreach ($fields as $field) {
            if (is_null($this->computed)) {
                $rows = $this->engine->keys($this->makeKey('f', '*', $field));

                foreach ($rows as $row) {
                    $tab = explode('.', $row);
                    $id = $tab[count($tab) - 2];
                    $data[$id] = [];
                    $data[$id]['id'] = $id;
                    $data[$id][$field] = $this->engine->get($row);
                }
            } else {
                foreach ($this->computed as $id) {
                    $data[$id] = [];
                    $data[$id]['id'] = $id;
                    $row = $this->makeKey('f', $id, $field);
                    $data[$id][$field] = $this->engine->get($row);
                }
            }
        }

        return $data;
    }

    /**
     * @param $key
     * @param null $operator
     * @param null $value
     * @return $this|mixed|Bank
     */
    public function where($key, $operator = null, $value = null)
    {
        if ($key instanceof \Closure) {
            return $key($this);
        }

        if ($key instanceof Object) {
            $fkTable = $key->table();

            return $this->where($fkTable . '_id', (int) $key->id);
        }

        if ($key instanceof Bank) {
            $joins = $key->get();

            $ids = [];

            foreach ($joins as $row) {
                $ids[] = $row['id'];
            }

            $table  = $key->table;
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

        $this->computed = array_values($results->fetch('id')->toArray());

        return $this;
    }

    /**
     * @param array $tab1
     * @param array $tab2
     *
     * @return array
     */
    private function merge(array $tab1, array $tab2)
    {
        return array_unique(
            array_merge(
                $tab1,
                $tab2
            )
        );
    }

    /**
     * @return $this
     */
    public function emptyQuery()
    {
        $this->computed = [];

        return $this;
    }

    /**
     * @return bool
     */
    public function exists()
    {
        if (!is_null($this->computed)) {
            return !empty($this->computed);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->computed);
    }

    /**
     * @return bool
     */
    public function hasNoRows()
    {
        return $this->isEmpty();
    }

    /**
     * @return bool
     */
    public function hasRows()
    {
        return !$this->isEmpty();
    }

    /**
     * @return bool
     */
    public function isNotEmpty()
    {
        return $this->hasRows();
    }

    /**
     * @return int
     */
    public function count()
    {
        if (!is_null($this->computed)) {
            $count =  count($this->computed);
            $this->reset();

            return $count;
        }

        return count($this->all());
    }

    /**
     * @param $field
     * @return float|int|mixed
     */
    public function sum($field)
    {
        $result = coll($this->select($field))->sum($field);

        $this->reset();

        return $result;
    }

    /**
     * @param $field
     * @return float|int
     */
    public function avg($field)
    {
        $result = coll($this->select($field))->avg($field);

        $this->reset();

        return $result;
    }

    /**
     * @param $field
     * @return mixed
     */
    public function min($field)
    {
        $result = coll($this->select($field))->min($field);

        $this->reset();

        return $result;
    }

    /**
     * @param $field
     * @return mixed
     */
    public function max($field)
    {
        $result = coll($this->select($field))->max($field);

        $this->reset();

        return $result;
    }

    public function paginate($page, $perPage)
    {
        return $this->new(
            array_slice(
                $this->computed,
                ($page - 1) * $perPage,
                $perPage
            )
        );
    }

    /**
     * @param $field
     *
     * @return $this
     */
    public function groupBy($field)
    {
        $results = coll($this->select($field))->groupBy($field);

        $this->computed = array_values($results->fetch('id')->toArray());

        return $this;
    }

    /**
     * @param $criteria
     *
     * @return $this
     */
    public function multisort($criteria)
    {
        $results = coll($this->select(array_keys($criteria)))->multisort($criteria);

        $this->computed = array_values($results->fetch('id')->toArray());

        return $this;
    }

    /**
     * @param $field
     *
     * @return $this
     */
    public function sortBy($field)
    {
        $results = coll($this->select($field))->sortBy($field);

        $this->computed = array_values($results->fetch('id')->toArray());

        return $this;
    }

    /**
     * @param $field
     * @return $this
     */
    public function sortByDesc($field)
    {
        $results = coll($this->select($field))->sortByDesc($field);

        $this->computed = array_values($results->fetch('id')->toArray());

        return $this;
    }

    /**
     * @return array
     */
    public function fetchAll()
    {
        if (is_null($this->computed)) {
            return $this->all();
        }

        $results = [];

        foreach ($this->computed as $id) {
            $results[] = $this->engine->get($this->makeKey('r', $id));
        }

        $this->reset();

        return $results;
    }

    /**
     * @return \Generator
     */
    public function fetch()
    {
        if (is_null($this->computed)) {
            $rows = $this->engine->keys($this->makeKey('r', '*'));

            foreach ($rows as $row) {
                yield $this->engine->get($row);
            }
        } else {
            foreach ($this->computed as $id) {
                yield $this->engine->get($this->makeKey('r', $id));
            }

            $this->reset();
        }
    }

    /**
     * @return \Generator
     */
    public function hydrate()
    {
        if (is_null($this->computed)) {
            $rows = $this->engine->keys($this->makeKey('r', '*'));

            foreach ($rows as $row) {
                yield $this->hydrator($this->engine->get($row));
            }
        } else {
            foreach ($this->computed as $id) {
                yield $this->hydrator($this->engine->get($this->makeKey('r', $id)));
            }

            $this->reset();
        }
    }

    public function instanciate($db = null, $table = null, $driver = null)
    {
        $db     = is_null($db)      ? $this->database : $db;
        $table  = is_null($table)   ? $this->table    : $table;
        $driver = is_null($driver)  ? $this->engine   : $driver;

        return new self($db, $table, $driver);
    }

    public function step(callable $callback)
    {
        $callback($this->instanciate()->computed($this->computed));

        return $this;
    }

    public function computed(array $ids)
    {
        $this->computed = $ids;

        return $this;
    }

    /**
     * @param array $criteria
     * @return int
     */
    public function update(array $criteria)
    {
        $criteria = is_object($criteria) ? $criteria->toArray() : $criteria;

        $affected = 0;

        foreach ($this->fetch() as $item) {
            if (isset($item['id'])) {
                $row = $this->find((int) $item['id']);

                if ($row) {
                    foreach ($criteria as $k => $v) {
                        $row[$k] = value($v);
                    }

                    $this->store($row);
                    $affected++;
                }
            }
        }

        return $affected;
    }

    /**
     * @return int
     */
    public function remove()
    {
        $deleted = 0;

        foreach ($this->fetch() as $item) {
            if ($item) {
                $id = $item['id'];

                if (is_numeric($id)) {
                    $this->delete($id);

                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * @return array
     */
    public function fields()
    {
        $key = $this->makeKey('fields');

        return $this->engine->get($key, []);
    }

    /**
     * @param callable $callback
     * @param null $fields
     *
     * @return $this
     */
    public function map(callable $callback, $fields = null)
    {
        $fields     = is_null($fields) ? $this->fields() : $fields;
        $data       = $this->select($fields);

        $results    = coll($data)->each($callback);

        $this->computed = array_values($results->fetch('id')->toArray());

        return $this;
    }

    /**
     * @param callable $callback
     * @param null $fields
     *
     * @return $this
     */
    public function filter(callable $callback, $fields = null)
    {
        $fields     = is_null($fields) ? $this->fields() : $fields;
        $data       = $this->select($fields);

        $results    = coll($data)->filter($callback);

        $this->computed = array_values($results->fetch('id')->toArray());

        return $this;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return $this
     */
    public function __call($name, array $arguments)
    {
        if ($name == 'new') {
            $this->computed = current($arguments);

            return $this;
        }

        if ($name == 'or') {
            if (is_null($this->computed)) {
                exception('bank', 'You must have at least one where clause before using the method or.');
            }

            $oldIds = $this->computed;

            call_user_func_array([$this->newQuery(), 'where'], $arguments);

            $merged = $this->merge($oldIds, $this->computed);

            $this->computed = array_values($merged);

            return $this;
        }

        if ($name === 'is' && count($arguments) === 2) {
            return $this->where(
                current($arguments),
                end($arguments)
            );
        }

        if ($name == 'empty') {
            return $this->drop()->newQuery();
        }

        if ($name == 'and') {
            return call_user_func_array([$this, 'where'], $arguments);
        }

        if ($name == 'list') {
            return call_user_func_array(coll($this->fetchAll()), pluck, $arguments);
        }

        if (fnmatch('*Hydrate', $name) && strlen($name) > 7) {
            $method = str_replace('Hydrate', '', $name);

            $results = $this->{$method}(...$arguments);

            return !is_null($results) ? $this->hydrator($results) : $results;
        }

        if (fnmatch('*Cache', $name) && strlen($name) > 5) {
            $method = str_replace('Cache', '', $name);

            $keyCache = sha1(
                $method .
                serialize($this->computed) .
                $this->database .
                $this->table .
                serialize($arguments)
            );

            return $this->engine->until($keyCache, function () use ($method, $arguments) {
                return $this->{$method}(...$arguments);
            }, $this->age());
        }

        if (fnmatch('findBy*', $name) && strlen($name) > 6) {
            $field = callField($name, 'findBy');

            $op = '=';

            if (count($arguments) == 2) {
                $op     = array_shift($arguments);
                $value  = array_shift($arguments);
            } else {
                $value  = array_shift($arguments);
            }

            return $this->where($field, $op, $value);
        }

        if (fnmatch('getBy*', $name) && strlen($name) > 5) {
            $field = callField($name, 'getBy');

            $op = '=';

            if (count($arguments) == 2) {
                $op     = array_shift($arguments);
                $value  = array_shift($arguments);
            } else {
                $value  = array_shift($arguments);
            }

            return $this->where($field, $op, $value);
        }

        if (fnmatch('where*', $name) && strlen($name) > 5) {
            $field = callField($name, 'where');

            $op = '=';

            if (count($arguments) == 2) {
                $op     = array_shift($arguments);
                $value  = array_shift($arguments);
            } else {
                $value  = array_shift($arguments);
            }

            return $this->where($field, $op, $value);
        }

        if (fnmatch('by*', $name) && strlen($name) > 2) {
            $field = callField($name, 'by');
            $value = array_shift($arguments);

            return $this->where($field, $value);
        }

        if (fnmatch('like*', $name) && strlen($name) > 4) {
            $field = callField($name, 'like');
            $value = array_shift($arguments);

            return $this->like($field, $value);
        }

        if (fnmatch('notLike*', $name) && strlen($name) > 47) {
            $field = callField($name, 'notLike');

            return $this->notLike($field, array_shift($arguments));
        }

        if (fnmatch('between*', $name) && strlen($name) > 7) {
            $field = callField($name, 'between');

            return $this->between($field, current($arguments), end($arguments));
        }

        if (fnmatch('sortWith*', $name)) {
            $field = callField($name, 'sortWith');

            return $this->sortBy($field);
        }

        if (fnmatch('asortWith*', $name)) {
            $field = callField($name, 'sortWith');

            return $this->sortByDesc($field);
        }

        if (fnmatch('sortDescWith*', $name)) {
            $field = callField($name, 'sortDescWith');

            return $this->sortByDesc($field);
        }

        if (fnmatch('firstBy*', $name) && strlen($name) > 7) {
            $field = callField($name, 'firstBy');
            $value = array_shift($arguments);

            return $this->firstBy($field, $value);
        }

        if (fnmatch('lastBy*', $name) && strlen($name) > 6) {
            $field = callField($name, 'lastBy');
            $value = array_shift($arguments);

            return $this->lastBy($field, $value);
        }
    }

    protected function ids()
    {
        return is_null($this->computed) ?
            array_values(coll($this->all())->fetch('id')->toArray()) :
            $this->computed
        ;
    }

    public function splice($offset, $length = null, $replacement = [])
    {
        $ids = $this->ids();

        if (func_num_args() == 1) {
            return $this->new(
                array_values(
                    array_splice(
                        $ids,
                        $offset
                    )
                )
            );
        }

        return $this->new(
            array_values(
                array_splice(
                    $ids,
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

    public function firstBy($field, $value)
    {
        return $this->findBy($field, $value)->first();
    }

    public function lastBy($field, $value)
    {
        return $this->findBy($field, $value)->last();
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

    public function orWhereIn($field, array $values)
    {
        return $this->or($field, 'in', $values);
    }

    public function whereNotIn($field, array $values)
    {
        return $this->where($field, 'not in', $values);
    }

    public function orWhereNotIn($field, array $values)
    {
        return $this->or($field, 'not in', $values);
    }

    public function rand($default = null)
    {
        $ids = $this->ids();

        if (!empty($ids)) {
            shuffle($ids);

            $id = current($ids);

            return $this->find($id);
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

    public function startsWith($field, $value)
    {
        return $this->where($field, 'Like', $value . '%');
    }

    public function orStartsWith($field, $value)
    {
        return $this->or($field, 'Like', $value . '%');
    }

    public function endsWith($field, $value)
    {
        return $this->where($field, 'Like', '%' . $value);
    }

    public function orEndsWith($field, $value)
    {
        return $this->or($field, 'Like', '%' . $value);
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

        return $this->where($field, $op, $date);
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

    public function hasId($id)
    {
        $row = $this->find($id);

        return $row ? true : false;
    }

    public function findOr($id, $default = false)
    {
        $row = $this->find($id);

        return $row ?: $default;
    }

    public function findOrFalse($id)
    {
        return $this->findOr($id, false);
    }

    public function findOrNull($id)
    {
        return $this->findOr($id, null);
    }

    public function findOrFail($id)
    {
        $row = $this->find($id);

        if (!$row) {
            exception('bank', "The row $id does not exist.");
        } else {
            return $row;
        }
    }

    public function firstOr($default = false)
    {
        $row = $this->first();

        return $row ? $row : $default;
    }

    public function firstOrFalse()
    {
        return $this->firstOr(false);
    }

    public function firstOrNull()
    {
        return $this->firstOr(null);
    }

    public function lastOr($default = false)
    {
        $row = $this->last();

        return $row ? $row : $default;
    }

    public function lastOrFalse()
    {
        return $this->lastOr(false);
    }

    public function lastOrNull()
    {
        return $this->lastOr(null);
    }

    public function firstOrFail()
    {
        $row = $this->first();

        if (!$row) {
            exception('bank', "The row does not exist.");
        } else {
            return $row;
        }
    }

    public function lastOrFail()
    {
        $row = $this->last();

        if (!$row) {
            exception('bank', "The row does not exist.");
        } else {
            return $row;
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
            return $this->first();
        }
    }

    public function unique($conditions)
    {
        return $this->noTuple($conditions);
    }

    function search($conditions)
    {
        $conditions = arrayable($conditions) ? $conditions->toArray() : $conditions;

        foreach ($conditions as $field => $value) {
            $this->where($field, $value);
        }

        return $this;
    }

    public function firstByAttributes($attributes)
    {
        $attributes = is_object($attributes) ? $attributes->toArray() : $attributes;

        $q = $this;

        foreach ($attributes as $field => $value) {
            $q->where($field, $value);
        }

        return $q->first();
    }

    public function firstOrCreate($conditions)
    {
        $conditions = arrayable($conditions) ? $conditions->toArray() : $conditions;

        $q = $this;

        foreach ($conditions as $field => $value) {
            $q->where($field, $value);
        }

        $exists = $q->first();

        if (null === $exists) {
            return $this->store($conditions);
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

        $exists = $q->first();

        if (null === $exists) {
            return $conditions;
        }

        return $exists;
    }

    public function first()
    {
        $ids = $this->ids();

        $id = current($ids);

        $this->reset();

        if (!$id) return null;

        return $this->find($id);
    }

    public function last()
    {
        $ids = $this->ids();
        $id = end($ids);

        $this->reset();

        if (!$id) return null;

        return $this->find($id);
    }

    public function takeFisrt($limit = 1)
    {
        return $this->sortBy('id')->take($limit)->fetch();
    }

    public function takeLast($limit = 1, $model = true)
    {
        return $this->sortByDesc('id')->take($limit)->get($model);
    }

    public function slice($offset, $length = null)
    {
        $ids = $this->ids();

        $this->computed = array_values(
            array_slice(
                $ids,
                $offset,
                $length,
                true
            )
        );

        return $this;
    }

    public function hydrator($row = [])
    {
        if (is_null($row)) {
            $row = [];
        }

        $row = arrayable($row) ? $row->toArray() : $row;

        $model  = o($row);

        $model->fn('save', function () use ($model) {
            if ($model->exists() && !$model->isDirty()) {
                return $model;
            }

            $row =  $this->store($model->toArray());

            return $this->hydrator($row);
        })->fn('cacheKey', function () use ($model) {
            if ($model->exists()) {
                return sprintf(
                    "%s:%s:%s",
                    $model->db() . '_' . $model->table(),
                    $model->id,
                    $model->updated_at->timestamp
                );
            }

            return sha1(serialize($model->toArray()));
        })->fn('delete', function () use ($model) {
            if ($model->exists()) {
                $status = $this->delete($model->getId());

                return $status;
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

            $record = $this->hydrator($row);

            return $create ? $record->save() : $record;
        })->fn('table', function () {
            return $this->table;
        })->fn('db', function () {
            return $this->database;
        })->fn('em', function () {
            return $this;
        })->fn('bank', function () {
            return $this;
        })->fn('entityName', function () {
            $database   = $this->database;
            $table      = $this->table;

            return Strings::camelize($database . "_" . $table);
        })->fn('instance', function () {
            return $this;
        })->fn('driver', function () {
            return $this->engine;
        })->fn('engine', function () {
            return $this->engine;
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

            foreach ($data as $k => $v) {
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

        return $model;
    }
}