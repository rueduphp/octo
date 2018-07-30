<?php
namespace Octo;

use ArrayAccess;

class Dynamicrecord implements ArrayAccess
{
    /**
     * @var Dynamicmodel
     */
    protected $__db;

    /** @var array  */
    protected $__data;

    /** @var array  */
    protected $__initial;

    /** @var string */
    private $__entity;

    /** @var string */
    private $__database;

    /** @var string */
    private $__table;

    /** @var null|Dynamicentity */
    private $__class;

    /**
     * @param array $data
     * @param Dynamicmodel $db
     * @param null|Dynamicentity $class
     * @throws \ReflectionException
     */
    public function __construct($data = [], Dynamicmodel $db, ?Dynamicentity $class = null)
    {
        $data = arrayable($data) ? $data->toArray() : $data;

        $entity = $db->getEntity();

        $class = is_null($class) ? getDynamicEntity($entity) : $class;

        $this->__db         = $db;
        $this->__initial    = $data;
        $this->__data       = $this->__initial;
        $this->__class      = $class;

        if (null !== $class) {
            $class->fire('booting', $this);
            $entity = $class->entity();
            $this->__entity = $entity;

            $parts = explode('.', $entity, 2);
            $this->__database = current($parts);
            $this->__table = end($parts);

            $methods = get_class_methods($class);

            if (in_array('activeRecord', $methods)) {
                gi()->call($class, 'activeRecord', $this);
            }

            $traits = allClasses($class);

            if (!empty($traits)) {
                foreach ($traits as $trait) {
                    $tab        = explode('\\', $trait);
                    $traitName  = Strings::lower(end($tab));
                    $method     = lcfirst(Strings::camelize('boot_' . $traitName . '_trait'));

                    if (in_array($method, $methods)) {
                        $params = [$class, $method, $this];

                        return gi()->call(...$params);
                    }

                    $method = lcfirst(Strings::camelize('boot_' . $traitName));

                    if (in_array($method, $methods)) {
                        $params = [$class, $method, $this];

                        return gi()->call(...$params);
                    }
                }

                $class->fire('booted', $this);
            }
        } else {
            $this->__entity = $entity;

            $parts = explode('.', $entity, 2);
            $this->__database = current($parts);
            $this->__table = end($parts);
        }
    }

    /**
     * @param $event
     * @return $this
     */
    public function fire($event)
    {
        nullable($this->__class)->fire($event, $this);

        return $this;
    }

    /**
     * @return Dynamicmodel
     */
    public function db(): Dynamicmodel
    {
        return $this->__db;
    }

    /**
     * @return Dynamicrecord
     * @throws \ReflectionException
     */
    public function save(): self
    {
        if ($this->exists() && !$this->isDirty()) {
            return $this;
        }

        nullable($this->__class)->fire('saving', $this);

        $this->validate();

        if ($this->exists()) {
            nullable($this->__class)->fire('updating', $this);

            $result = $this->__db->update($this['id'], $this->__data);

            $this['updated_at'] = $result['updated_at'];

            nullable($this->__class)->fire('updated', $this);
        } else {
            nullable($this->__class)->fire('creating', $this);

            $result = $this->__db->create($this->__data);

            $this['id'] = $result['id'];
            $this['created_at'] = $result['created_at'];
            $this['updated_at'] = $result['updated_at'];

            nullable($this->__class)->fire('created', $this);
        }

        nullable($this->__class)->fire('saved', $this);

        return $this;
    }

    /**
     * @throws \ReflectionException
     */
    public function validate()
    {
        nullable($this->__class)->fire('validating', $this);

        if ($this->__class instanceof Dynamicentity) {
            $guarded    = $this->__class->guarded();
            $data       = $this->toArray();

            if ($this->exists()) {
                unset($data['id']);
                unset($data['created_at']);
                unset($data['updated_at']);
            }

            $keys = array_keys($data);

            if (!is_array($guarded) && in_array('fillable', get_class_methods($this->__class))) {
                $fillable   = $this->__class->fillable();

                if (is_array($fillable)) {
                    if ($fillable !== $keys) {
                        foreach ($keys as $key) {
                            if (!in_array($key, $fillable)) {
                                exception(
                                    'dynamicentity',
                                    "Field $key is not fillable in model " . get_class($this->__class) . "."
                                );
                            }
                        }
                    }
                }
            } else {
                foreach ($guarded as $key) {
                    if (in_array($key, $keys)) {
                        exception(
                            'dynamicentity',
                            "Field $key is guarded in model " . get_class($this->__class) . "."
                        );
                    }
                }
            }
        }

        nullable($this->__class)->fire('validated', $this);
    }

    /**
     * @param callable $cb
     *
     * @return mixed|null|Record
     *
     * @throws \ReflectionException
     */
    public function checkAndSave(callable $cb)
    {
        $check = callThat($cb, $this);

        if (true === $check) {
            return $this->save();
        }

        return $check;
    }

    /**
     * @return Dynamicrecord
     * @throws \ReflectionException
     */
    public function touch(): Dynamicrecord
    {
        if ($this->exists()) {
            $this->__data['updated_at'] = time();
        }

        return $this->save();
    }

    /**
     * @return bool
     * @throws \ReflectionException
     */
    public function delete(): bool
    {
        if ($this->exists()) {
            nullable($this->__class)->fire('deleting', $this);
            $status = $this->__db->delete($this['id']);
            nullable($this->__class)->fire('deleted', $this);

            return $status;
        }

        return false;
    }

    /**
     * @return Dynamicrecord
     * @throws \ReflectionException
     */
    public function copy(): Dynamicrecord
    {
        $clone = clone $this;

        unset($clone['id']);
        unset($clone['created_at']);
        unset($clone['updated_at']);

        return (new self($clone->toArray(), $this->__db, $this->__class))->save();
    }

    /**
     * @return Dynamicrecord
     * @throws \ReflectionException
     */
    public function same()
    {
        return $this->copy();
    }

    /**
     * @return bool
     */
    public function isDirty(): bool
    {
        return $this->__initial !== $this->__data;
    }

    /**
     * @return null|Dynamicrecord|static
     * @throws Exception
     * @throws \ReflectionException
     */
    public function getKey()
    {
        return $this->get('id');
    }

    /**
     * @return string
     */
    public function cacheKey(): string
    {
        if ($this->exists()) {
            return sprintf(
                "%s:%s:%s",
                $this->__db->getEntity(),
                $this->id,
                $this->updated_at->timestamp
            );
        }

        return sha1(serialize($this->__data));
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed
     * @throws Exception
     * @throws \ReflectionException
     */
    public function get(string $key, $default = null)
    {
        nullable($this->__class)->fire('get_' . $key, $this);

        if (fnmatch('*_at', $key)) {
            return Time::createFromTimestamp(isAke($this->__data, $key, time()));
        }

        if (array_key_exists($key, $this->__data)) {
            return $this->__data[$key];
        }

        $entity = $this->__class;

        if ($entity) {
            $attrMethod = lcfirst(Strings::camelize('get_attribute_' . $key));
            $methods    = get_class_methods($entity);

            if (in_array($attrMethod, $methods)) {
                $params = array_merge([$entity, $attrMethod], [$this]);

                return gi()->call(...$params);
            }
        }

        if (fnmatch('*s', $key)) {
            $fk = $this->__table . '_id';
            $fkParent = substr($key, 0, -1);

            $query = (new Dynamicmodel(
                $this->__database . '.' . $fkParent,
                $this->makeCache($fkParent)
            ))->where($fk, (int) $this->get('id'));

            if ($query->exists()) {
                return $query;
            }
        } else {
            $id = isAke($this->__data, $key . '_id', 'octodummy');

            if (is_numeric($id)) {
                return (new Dynamicmodel(
                    $this->__database . '.' . $key,
                    $this->makeCache($key)
                ))->find((int) $id);
            } else {
                $fk = $this->__table . '_id';

                $query = (new Dynamicmodel(
                    $this->__database . '.' . $key,
                    $this->makeCache($key)
                ))->where($fk, (int) $this->get('id'));

                if ($query->exists()) {
                    return $query->first();
                }
            }
        }

        return $default;
    }

    /**
     * @param $key
     * @return null|Dynamicrecord
     * @throws Exception
     * @throws \ReflectionException
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * @param string $key
     * @param $value
     * @return Dynamicrecord
     * @throws Exception
     * @throws \ReflectionException
     */
    public function set(string $key, $value): self
    {
        $entity = $this->__class;

        nullable($entity)->fire('set_' . $key, $this);

        if ($entity) {
            $attrMethod = lcfirst(Strings::camelize('set_attribute_' . $key));
            $methods    = get_class_methods($entity);

            if (in_array($attrMethod, $methods)) {
                $params = array_merge([$entity, $attrMethod], [$key, $this]);
                $value = gi()->call(...$params);
                $this->__data[$key] = value($value);

                return $this;
            }
        }

        if (fnmatch('*s', $key) && is_array($value) && !empty($value)) {
            $first = current($value);

            if ($first instanceof self) {
                foreach ($value as $model) {
                    $setter = setter($this->__table . '_id');
                    $model->$setter($this->get('id'))->save();
                }

                return $this;
            }
        }

        if ($value instanceof self && $value->exists()) {
            return $this->set($value->__table . '_id', $value->id);
        }

        if ($key === 'password') {
            $value = lib('hasher')->make($value);
        }

        $this->__data[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @throws Exception
     * @throws \ReflectionException
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->__data);
    }

    /**
     * @param $key
     * @return bool
     */
    public function __isset($key): bool
    {
        return array_key_exists($key, $this->__data);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function remove($key): bool
    {
        $status = $this->has($key);

        unset($this->__data[$key]);

        return $status;
    }

    /**
     * @param $key
     */
    public function __unset($key)
    {
        $this->remove($key);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|null|Dynamicrecord
     * @throws Exception
     * @throws \ReflectionException
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws Exception
     * @throws \ReflectionException
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return isset($this->id) && reallyInt($this->id);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->__data as $key => $value) {
            $value = arrayable($value) ? $value->toArray() : $value;

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function toJson($option = JSON_PRETTY_PRINT)
    {
        return json_encode($this->toArray(), $option);
    }

    public function json()
    {
        echo $this->toJson();
    }

    /**
     * @return Dynamicrecord
     * @throws Exception
     * @throws \ReflectionException
     */
    public function fromRequest(): self
    {
        $data = getRequest()->getParsedBody() ?? [];

        $this->fill($data);

        return $this->save();
    }

    /**
     * @param array $data
     * @return Dynamicrecord
     * @throws Exception
     * @throws \ReflectionException
     */
    public function fill(array $data = []): self
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @param array $data
     * @return Dynamicrecord
     */
    public function merge(array $data = []): self
    {
        $this->__data = array_merge($this->__data, $data);

        return $this;
    }

    /**
     * @return array
     */
    public function dirty(): array
    {
        $dirty = [];

        if ($this->__initial !== $this->__data) {
            foreach ($this->__data as $k => $v) {
                if (!isset($this->__initial[$k]) || $this->__initial[$k] !== $v) {
                    $dirty[$k] = $v;
                }
            }
        }

        return $dirty;
    }

    /**
     * @param $table
     * @return Caching
     * @throws \ReflectionException
     */
    private function makeCache($table)
    {
        return gi()->make($this->__db->getCacheClass(), [$this->__database . '.' . $table], false);
    }

    /**
     * @param $records
     * @throws \ReflectionException
     */
    public function sync($records)
    {
        $records = !is_array($records) ? func_get_args() : $records;

        /** @var Dynamicrecord $record */
        foreach ($records as $record) {
            if ($this->exists() && $record->exists()) {
                $tables = [$this->__table, $record->__table];

                sort($tables);

                $pivot = implode('', $tables);

                $pivotEntity = new Dynamicmodel($this->__database . '.' . $pivot, $this->makeCache($pivot));

                $idValue = $this->id;

                $pivotEntity->where($this->__table . '_id', $idValue)->remove();

                $idRecord = $record->id;

                $row = [
                    $this->__table . '_id'    => $idValue,
                    $record->__table . '_id'  => $idRecord
                ];

                $pivotEntity->firstOrCreate($row);
            }
        }
    }

    /**
     * @param Dynamicrecord $otherEntity
     * @param bool $many
     * @return mixed|null|Dynamicrecord|Iterator
     * @throws \ReflectionException
     */
    public function pivots(Dynamicrecord $otherEntity, $many = true)
    {
        $tables = [$this->__table, $otherEntity->__table];

        sort($tables);

        $pivot = implode('', $tables);

        $pivotEntity = new Dynamicmodel($this->__database . '.' . $pivot, $this->makeCache($pivot));

        $query = $pivotEntity
            ->where(
                $this->__table . '_id',
                $this->id
            )
            ->where(
                $otherEntity->__table . '_id',
                $otherEntity->id
            )
        ;

        if ($many) {
            return $query->model();
        }

        return $query->first();
    }

    /**
     * @param Dynamicrecord $otherEntity
     * @param bool $many
     * @return mixed|null|Dynamicrecord|Iterator
     * @throws \ReflectionException
     */
    public function allPivots(Dynamicrecord $otherEntity, $many = true)
    {
        $tables = [$this->__table, $otherEntity->__table];

        sort($tables);

        $pivot = implode('', $tables);

        $pivotEntity = new Dynamicmodel($this->__database . '.' . $pivot, $this->makeCache($pivot));

        $query = $pivotEntity
            ->where(
                $this->__table . '_id',
                $this->id
            )
        ;

        if ($many) {
            return $query->model();
        }

        return $query->first();
    }

    /**
     * @param Dynamicrecord $model
     * @return Dynamicrecord
     */
    public function morphWith(Dynamicrecord $model): self
    {
        if ($model->exists()) {
            $this->morph_id     = $model->id;
            $this->morph_type   = $model->__table;
        }

        return $this;
    }

    /**
     * @param string $table
     * @return mixed|null|Dynamicrecord|Iterator
     * @throws \ReflectionException
     */
    public function morphOne(string $table)
    {
        return $this->morphing($table, false);
    }

    /**
     * @param string $table
     * @return mixed|null|Dynamicrecord|Iterator
     * @throws \ReflectionException
     */
    public function morphs(string $table)
    {
        return $this->morphing($table);
    }

    /**
     * @param string $table
     * @param bool $many
     * @return mixed|null|Dynamicrecord|Iterator
     * @throws \ReflectionException
     */
    public function morphing(string $table, $many = true)
    {
        $db = new Dynamicmodel(
            $this->__database . '.' . $table,
            $this->makeCache($table)
        );

        $ids = $this->__db
            ->where(
                'morph_type',
                $table
            )->pluck('ud')
        ;

        $query = $db->in('id', $ids);

        if ($many) {
            return $query->model();
        }

        return $query->first();
    }

    /**
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function morph()
    {
        if ($this->exists()) {
            $db = new Dynamicmodel(
                $this->__database . '.' . $this['morph_type'],
                $this->makeCache($this['morph_type'])
            );

            return $db->find((int) $this->morph_id);
        }

        return null;
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return $this|mixed|null|Dynamicrecord
     * @throws Exception
     * @throws \ReflectionException
     */
    public function __call(string $method, array $parameters)
    {
        if (fnmatch('get*', $method) && strlen($method) > 3) {
            $key = Inflector::uncamelize(substr($method, 3));
            $default = empty($parameters) ? null : current($parameters);

            return isset($this->{$key}) ? $this->{$key} : $default;
        } elseif (fnmatch('set*', $method) && strlen($method) > 3) {
            $key = Inflector::uncamelize(substr($method, 3));

            $this->{$key} = current($parameters);

            return $this;
        } elseif (fnmatch('has*', $method) && strlen($method) > 3) {
            $key = Inflector::uncamelize(substr($method, 3));

            return isset($this->{$key});
        } elseif (fnmatch('remove*', $method) && strlen($method) > 6) {
            $key = Inflector::uncamelize(substr($method, 6));

            if (isset($this->{$key})) {
                unset($this->{$key});

                return true;
            }

            return false;
        }

        if (isset($this->{$method}) && empty($parameters)) {
            return $this->{$method};
        }

        if (!empty($parameters)) {
            $this->{$method} = current($parameters);

            return $this;
        }

        $model = $o = array_shift($parameters);

        if ($o instanceof self) {
            try {
                $fkTable = $o->__table;

                if ($fkTable && $o->exists()) {
                    $this->__data[$fkTable . '_id'] = $o['id'];

                    return $this;
                }
            } catch (\Exception $e) {
                return $this;
            }
        } else {
            if (true !== $o && false !== $o) {
                $o = true;
            }

            $fktable = Strings::lower($method);

            if (fnmatch('*s', $fktable)) {
                $fk = $this->__table . '_id';
                $fkParent = substr($fktable, 0, -1);

                return (new Dynamicmodel(
                    $this->__database . '.' . $fkParent,
                    $this->makeCache($fkParent)
                ))->where($fk, (int) $this->get('id'));
            } else {
                $id = isAke($this->__data, $fktable . '_id', 'octodummy');

                if (is_numeric($id)) {
                    return (new Dynamicmodel(
                        $this->__database . '.' . $fktable,
                        $this->makeCache($fktable)
                    ))->find((int) $id, $o);
                } else {
                    $fk = $this->__table . '_id';

                    if (is_null($model)) {
                        $model = true;
                    } else {
                        if (true !== $model) {
                            $model = false;
                        }
                    }

                    return (new Dynamicmodel(
                        $this->__database . '.' . $fktable,
                        $this->makeCache($fktable)
                    ))->where($fk, (int) $this->get('id'))->first($model);
                }
            }
        }
    }
}
