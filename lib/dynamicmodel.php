<?php
namespace Octo;

use Closure;
use Illuminate\Database\Schema\Blueprint;

class EavEntity extends Elegant
{
    protected $table   = 'eav_entities';
    public $timestamps = false;

    public function rows()
    {
        return $this->hasMany(EavRow::class, 'entity_id');
    }

    public function attributes()
    {
        return $this->hasMany(EavAttribute::class, 'entity_id');
    }
}

class EavAttribute extends Elegant
{
    protected $table   = 'eav_attributes';
    public $timestamps = false;

    public function entity()
    {
        return $this->belongsTo(EavEntity::class, 'entity_id');
    }
}

class EavValue extends Elegant
{
    protected $table   = 'eav_values';
    public $timestamps = false;

    public function attribute()
    {
        return $this->belongsTo(EavAttribute::class, 'attribute_id');
    }

    public function row()
    {
        return $this->belongsTo(EavRow::class, 'row_id');
    }

    public function entity()
    {
        return $this->attribute->entity();
    }
}

class EavRow extends Elegant
{
    protected $table = 'eav_rows';

    public function entity()
    {
        return $this->belongsTo(EavEntity::class, 'entity_id');
    }

    public function values($raw = false)
    {
        $query = $this->hasMany(EavValue::class, 'row_id');

        return false === $raw
            ? $query->with('attribute')
            : $query
        ;
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function attributes()
    {
        $attributes = coll(coll($this->values()->get()->toArray())->pluck('attribute'))->pluck('id');

        return EavAttribute::whereIn('id', $attributes);
    }
}

class Dynamicmodel
{
    /**
     * @var string
     */
    protected $entity;

    /**
     * @var null
     */
    protected $cache;

    /** @var array  */
    protected $query = [];

    /** @var array  */
    protected $ids = [];

    /**
     * @throws \ReflectionException
     */
    public static function migrate()
    {
        $exists = File::exists($file = storage_path() . '/eav');

        $testing = defined('testing');

        if (false === $exists || true === $testing) {
            File::put($file, '');
            $schema = getSchema();

            $schema->create('eav_entities', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
            });

            $schema->create('eav_rows', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('entity_id')->unsigned()->index();
                $table->foreign('entity_id', 'fk_entities_rows')->references('id')->on('eav_entities')->onDelete('cascade');
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();
            });

            $schema->create('eav_attributes', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->integer('entity_id')->unsigned()->index();
                $table->foreign('entity_id', 'fk_entities_attributes')
                    ->references('id')
                    ->on('eav_entities')
                    ->onDelete('cascade')
                ;
            });

            $schema->create('eav_values', function (Blueprint $table) {
                $table->increments('id');
                $table->longText('value')->nullable();
                $table->integer('row_id')->unsigned()->index();
                $table->foreign('row_id', 'fk_rows_values')
                    ->references('id')
                    ->on('eav_rows')
                    ->onDelete('cascade')
                ;
                $table->integer('attribute_id')->unsigned()->index();
                $table->foreign('attribute_id', 'fk_attr_values')
                    ->references('id')
                    ->on('eav_attributes')
                    ->onDelete('cascade')
                ;
            });
        }
    }

    /**
     * @param string $entity
     * @param null $cache
     * @throws \ReflectionException
     */
    public function __construct(string $entity, $cache = null)
    {
        $cache = null === $cache ? new Caching('eav.' . $entity) : $cache;

        $this->entity   = EavEntity::firstOrCreate(['name' => $entity]);
        $this->cache    = $cache;
    }

    /**
     * @return Dynamicmodel
     */
    public function reset(): self
    {
        $this->query = [];
        $this->ids = [];

        return $this;
    }

    /**
     * @return Dynamicmodel
     */
    public function newQuery()
    {
        return $this->reset();
    }

    /**
     * @param $data
     * @return mixed
     * @throws \ReflectionException
     */
    public function create($data)
    {
        $data = arrayable($data) ? $data->toArray() : $data;

        $data['created_at'] = time();
        $data['updated_at'] = time();

        list($keys, $values) = Arrays::divide($data);

        $row = EavRow::create(['entity_id' => $this->entity->id]);

        foreach ($keys as $i => $key) {
            $attribute = EavAttribute::firstOrCreate(['name' => $key, 'entity_id' => $this->entity->id]);
            $value = $values[$i];

            EavValue::create([
                'attribute_id'  => (int) $attribute->id,
                'row_id'        => (int) $row->id,
                'value'         => serialize($value)
            ]);
        }

        $data['id'] = $row->id;

        $this->age(time());

        return $data;
    }

    /**
     * @param int $id
     * @param $data
     * @return array
     * @throws \ReflectionException
     */
    public function update(int $id, $data)
    {
        $data = arrayable($data) ? $data->toArray() : $data;
        $row = $this->find($id, false);

        unset($row['id']);

        $new = array_merge($row, $data);
        $new['updated_at'] = time();

        foreach ($new as $key => $value) {
            $attribute = EavAttribute::firstOrCreate(['name' => $key, 'entity_id' => $this->entity->id]);

            $v = EavValue::firstOrCreate([
                'attribute_id'  => (int) $attribute->id,
                'row_id'        => (int) $id,
            ]);

            $v->value = serialize($value);

            $v->save();
        }

        $new['id'] = $id;

        $this->age(time() + 1);

        return $new;
    }

    /**
     * @return int
     */
    public function remove(): int
    {
        $affected = 0;

        foreach ($this->ids() as $id) {
            $this->delete($id);
            $affected++;
        }

        $this->reset();

        return $affected;
    }

    /**
     * @param array $parameters
     * @return int
     * @throws \ReflectionException
     */
    public function edit(array $parameters): int
    {
        $affected = 0;

        foreach ($this->ids() as $id) {
            $this->update($id,$parameters);
            $affected++;
        }

        $this->reset();

        return $affected;
    }

    /**
     * @param int $id
     * @return bool
     * @throws \ReflectionException
     */
    public function delete(int $id): bool
    {
        /** @var  EavRow $row */
        $row = EavRow::find($id);

        if (null !== $row) {
            $row->attributes()->delete();
            $row->values(true)->delete();

            try {
                $row->delete();
                $this->age(time() + 1);

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param int|null $t
     * @return int
     */
    public function age(?int $t = null): int
    {
        $key = 'eav.' . $this->entity->id . '.age';

        if (empty($t)) {
            return time();
        } else {
            $this->cache->set($key, $t);

            return $t;
        }
    }

    /**
     * @return int
     * @throws \ReflectionException
     */
    public function getAge(): int
    {
        $key = 'eav.' . $this->entity->id . '.age';

        return $this->cache->get($key, time());
    }

    /**
     * @param int $id
     * @param bool $model
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function find(int $id, $model = true)
    {
        $m = $model ? 1 : 0;
        $key = 'eav.' . $this->entity->id . '.' . $id . $m . '.find';

        return $this->cache->until($key, function () use ($id, $model) {
            /** @var EavRow $row */
            $row = EavRow::find($id);

            if ($row) {
                $found = [];

                $values = $row->values()->get()->toArray();

                foreach ($values as $value) {
                    $found[$value['attribute']['name']] = unserialize($value['value']);
                }

                $found['id'] = (int) $value['row_id'];

                return true === $model ? $this->makeModel($found) : $found;
            }

            return null;
        }, $this->getAge());
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function freshids(): array
    {
        return EavRow::select('id')->where('entity_id', $this->entity->id)->pluck('id')->all();
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function ids(): array
    {
        if (!empty($this->query)) {
            return $this->ids;
        }

        $key = 'eav.' . $this->entity->id . '.ids';

        return $this->cache->until($key, function () {
            return $this->freshids();
        }, $this->getAge());
    }

    public function select($fields = null)
    {
        $data = [];

        if (is_null($fields)) {
            $fields = $this->fields();
        }

        if (is_string($fields)) {
            $fields = [$fields];
        }

        if (!in_array('id', $fields)) {
            $fields[] = 'id';
        }

        foreach ($this->ids() as $id) {
            $data[$id] = [];

            foreach ($fields as $field) {
                $data[$id][$field] = $this->getFieldValueById($field, $id);
            }
        }

        return $data;
    }

    /**
     * @param $key
     * @param null|string $operator
     * @param null $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function where($key, ?string $operator = null, $value = null): self
    {
        if (func_num_args() === 1) {
            if (is_array($key)) {
                list($key, $operator, $value) = $key;
                $operator = Inflector::lower($operator);
            }
        } elseif (func_num_args() === 2) {
            list($value, $operator) = [$operator, '='];
        }

        $collection = coll($this->select($key));

        $this->query[] = [$key, $value, $operator];

        $keyCache = sha1(serialize($this->query) . $this->entity->id . $key . '.query');

        $this->ids = $this->cache->until($keyCache, function () use ($key, $operator, $value, $collection) {
            return $collection->filter(function($item) use ($key, $operator, $value) {
                $item = (object) $item;
                $actual = isset($item->{$key}) ? $item->{$key} : null;

                $insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

                if ((!is_array($actual) || !is_object($actual)) && $insensitive) {
                    $actual = Inflector::lower(Inflector::unaccent($actual));
                }

                if ((!is_array($value) || !is_object($value)) && $insensitive) {
                    $value  = Inflector::lower(Inflector::unaccent($value));
                }

                if ($insensitive) {
                    $operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
                }

                if ($key === 'id' || fnmatch('*_id', $key) && is_numeric($actual)) {
                    $actual = (int) $actual;
                }

                return compare($actual, $operator, $value);
            })->pluck('id');
        }, $this->getAge());

        return $this;
    }

    /**
     * @param null|Dynamicentity $entity
     * @return Iterator
     */
    public function exec(?Dynamicentity $entity = null): Iterator
    {
        /** @var Dynamicentity $entity */
        $entity = null === $entity ? getDynamicEntity($this->entity->name) : $entity;

        if ($entity && null !== $entity->getIterator()) {
            $iterator   = $entity->getIterator();

            $callback = function ($row) use ($entity, $iterator) {
                return new $iterator($this->find($row), $this, $entity);
            };

            return $this->each($callback);
        }

        return $this->each(function ($row) {
            return new Dynamicrecord($this->find($row), $this, null);
        });
    }

    /**
     * @param array $data
     * @return Dynamicrecord
     */
    private function makeModel(array $data)
    {
        /** @var Dynamicentity $entity */
        $entity = getDynamicEntity($this->entity->name);

        if ($entity && null !== $entity->getIterator()) {
            $iterator = $entity->getIterator();
        } else {
            $iterator = Dynamicrecord::class;
        }

        return new $iterator($data, $this);
    }

    /**
     * @param bool $model
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function first($model = true)
    {
        $row = $this->get()->first();

        return $model && null !== $row ? $this->makeModel($row) : $row;
    }

    /**
     * @param bool $model
     * @return mixed|Dynamicrecord
     * @throws \ReflectionException
     */
    public function last($model = true)
    {
        $row = $this->get()->last();

        return $model && null !== $row ? $this->makeModel($row) : $row;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * @param Closure $callable
     * @return Iterator
     */
    public function each(Closure $callable): Iterator
    {
        $callable->bindTo($this);

        $iterator = new Iterator($this->ids(), $callable);

        $this->reset();

        return $iterator;
    }

    /**
     * @return Iterator
     */
    public function get(): Iterator
    {
        return $this->each(function (int $row) {
            return $this->find($row, false);
        });
    }

    /**
     * @param bool $model
     * @return array
     * @throws \ReflectionException
     */
    public function fetchAll(bool $model = false): array
    {
        $rows = [];

        foreach ($this->ids() as $id) {
            $rows[] = $this->find((int) $id, $model);
        }

        return $rows;
    }

    /**
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function fetch()
    {
        foreach ($this->ids() as $id) {
            return $this->find((int) $id, false);
        }

        return null;
    }

    /**
     * @param $value
     * @param null $key
     * @return array
     * @throws \ReflectionException
     */
    public function pluck($value, $key = null): array
    {
        return coll($this->fetchAll())->pluck($value, $key);
    }

    /**
     * @param null|Dynamicentity $entity
     * @return Iterator
     */
    public function model(?Dynamicentity $entity = null): Iterator
    {
        $entity = $entity ?: getDynamicEntity($this->entity->name);

        if (null !== $entity) {
            return $this->exec($entity);
        }

        return $this->each(function ($row) use ($entity) {
            return new Dynamicrecord($this->find($row), $this, $entity);
        });
    }

    /**
     * @param $offset
     * @param null $length
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function slice($offset, $length = null): self
    {
        $this->ids = array_slice($this->ids(), $offset, $length, true);

        $this->query[] = 'slice';

        return $this;
    }

    /**
     * @param null $limit
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function take($limit = null)
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * @param $offset
     * @param null $length
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function limit($offset, $length = null)
    {
        return $this->slice($offset, $length);
    }

    /**
     * @param string $field
     * @param int $id
     * @param null $default
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function getFieldValueById(string $field, int $id, $default = null)
    {
            $row = $this->find($id, false);

            if (null !== $row) {
                return isAke($row, $field, $default);
            }

            return $default;
    }

    /**
     * @param string $entity
     * @return Dynamicmodel
     */
    public function setEntity(string $entity): Dynamicmodel
    {
        $this->entity = $entity;

        return $this;
    }

    /**
     * @param FastCacheInterface $cache
     * @return Dynamicmodel
     */
    public function setCache(FastCacheInterface $cache)
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function like(string $field, $value)
    {
        return $this->where($field, 'like', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function notLike(string $field, $value)
    {
        return $this->where($field, 'not like', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function findBy(string $field, $value)
    {
        return $this->where($field, '=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @param bool $model
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function firstBy(string $field, $value, bool $model = true)
    {
        return $this->where($field, '=', $value)->first($model);
    }

    /**
     * @param string $field
     * @param $value
     * @return mixed|Dynamicrecord
     * @throws \ReflectionException
     */
    public function lastBy(string $field, $value)
    {
        return $this->where($field, '=', $value)->last();
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function in(string $field, array $values): self
    {
        return $this->where($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function notIn(string $field, array $values): self
    {
        return $this->where($field, 'not in', $values);
    }

    /**
     * @param null $default
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function rand($default = null)
    {
        $items = $this->ids();

        if (!empty($items)) {
            shuffle($items);

            $row = current($items);

            return $this->find($row);
        }

        return $default;
    }

    /**
     * @param string $field
     * @param $min
     * @param $max
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function isBetween(string $field, $min, $max): self
    {
        return $this->where($field, 'between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param $min
     * @param $max
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function isNotBetween(string $field, $min, $max): self
    {
        return $this->where($field, 'not between', [$min, $max]);
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function isNull(string $field): self
    {
        return $this->where($field, 'is', 'null');
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function isNotNull(string $field): self
    {
        return $this->where($field, 'is not', 'null');
    }

    /**
     * @param string $field
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function sum(string $field)
    {
        $keyCache = sha1('eav.sum.' . $this->entity->id . $field . serialize($this->query));

        return $this->cache->until($keyCache, function () use ($field) {
            return $this->get()->sum($field);
        }, $this->getAge());
    }

    /**
     * @param string $field
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function min(string $field)
    {
        $keyCache = sha1('eav.min.' . $this->entity->id . $field . serialize($this->query));

        return $this->cache->until($keyCache, function () use ($field) {
            return $this->get()->min($field);
        }, $this->getAge());
    }

    /**
     * @param string $field
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function max(string $field)
    {
        $keyCache = sha1('eav.max.' . $this->entity->id . $field . serialize($this->query));

        return $this->cache->until($keyCache, function () use ($field) {
            return $this->get()->max($field);
        }, $this->getAge());
    }

    /**
     * @param string $field
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function avg(string $field)
    {
        $keyCache = sha1('eav.avg.' . $this->entity->id . $field . serialize($this->query));

        return $this->cache->until($keyCache, function () use ($field) {
            return $this->get()->avg($field);
        }, $this->getAge());
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function sortBy(string $field): self
    {
        $keyCache = sha1('eav.sortBy.' . $this->entity->id . $field . serialize($this->query));

        $rows = $this->cache->until($keyCache, function () use ($field) {
            return $this->get()->sortBy($field)->toArray();
        }, $this->getAge());

        $this->query[] = 'sortBy';

        $this->ids = coll($rows)->pluck('id');

        return $this;
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function sortByDesc(string $field): self
    {
        $keyCache = sha1('eav.sortByDesc.' . $this->entity->id . $field . serialize($this->query));

        $rows = $this->cache->until($keyCache, function () use ($field) {
            return $this->get()->sortByDesc($field)->toArray();
        }, $this->getAge());

        $this->query[] = 'sortByDesc';

        $this->ids = coll($rows)->pluck('id');

        return $this;
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    public function __call(string $method, array $parameters)
    {
        if ('or' === $method) {
            if (!empty($this->query)) {
                $oldIds = $this->ids();

                $self = $this->reset()->where(...$parameters);

                $merged = array_merge($oldIds, $self->ids());

                $this->ids = array_values($merged);

                return $self;
            } else {
                throw new \Exception("You must provide at least one query.");
            }
        } elseif ($method === 'xor') {
            if (empty($this->query)) {
                exception('dynmodel', 'You must have at least one where clause before using the method xor.');
            }

            $oldIds = $this->ids();

            $this->query[] = 'XOR';

            $this->reset()->where(...$parameters);

            $this->ids = array_merge(array_diff($oldIds, $this->ids()), array_diff($this->ids(), $oldIds));

            return $this;
        } elseif ($method === 'and') {
            return $this->where(...$parameters);
        }

        if (fnmatch('like*', $method) && strlen($method) > 4) {
            $field = callField($method, 'like');
            $value = array_shift($parameters);

            return $this->like($field, $value);
        }

        if ($method === 'list') {
            $field = array_shift($parameters);

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

        if (fnmatch('findBy*', $method) && strlen($method) > 6) {
            $field = callField($method, 'findBy');

            $op = '=';

            if (count($parameters) === 2) {
                $op     = array_shift($parameters);
                $value  = array_shift($parameters);
            } else {
                $value  = array_shift($parameters);
            }

            return $this->where($field, $op, $value);
        }

        if (fnmatch('getBy*', $method) && strlen($method) > 5) {
            $field = callField($method, 'getBy');

            $op = '=';

            if (count($parameters) === 2) {
                $op     = array_shift($parameters);
                $value  = array_shift($parameters);
            } else {
                $value  = array_shift($parameters);
            }

            return $this->where($field, $op, $value);
        }

        if (fnmatch('where*', $method) && strlen($method) > 5) {
            $field = callField($method, 'where');

            $op = '=';

            if (count($parameters) === 2) {
                $op     = array_shift($parameters);
                $value  = array_shift($parameters);
            } else {
                $value  = array_shift($parameters);
            }

            return $this->where($field, $op, $value);
        }

        if (fnmatch('by*', $method) && strlen($method) > 2) {
            $field = callField($method, 'by');
            $value = array_shift($parameters);

            return $this->where($field, $value);
        }

        if (fnmatch('sortWith*', $method)) {
            $field = callField($method, 'sortWith');

            return $this->sortBy($field);
        }

        if (fnmatch('asortWith*', $method)) {
            $field = callField($method, 'sortWith');

            return $this->sortByDesc($field);
        }

        if (fnmatch('sortDescWith*', $method)) {
            $field = callField($method, 'sortDescWith');

            return $this->sortByDesc($field);
        }

        if (fnmatch('firstBy*', $method) && strlen($method) > 7) {
            $field = callField($method, 'firstBy');
            $value = array_shift($parameters);
            $model = array_shift($parameters);

            if (is_null($model)) {
                $model = true;
            }

            return $this->firstBy($field, $value, $model);
        }

        if (fnmatch('notLike*', $method) && strlen($method) > 47) {
            $field = callField($method, 'notLike');

            return $this->notLike($field, array_shift($parameters));
        }

        if (fnmatch('between*', $method) && strlen($method) > 7) {
            $field = callField($method, 'between');

            return $this->between($field, current($parameters), end($parameters));
        }

        if (fnmatch('lastBy*', $method) && strlen($method) > 6) {
            $field = callField($method, 'lastBy');
            $value = array_shift($parameters);
            $model = array_shift($parameters);

            if (is_null($model)) {
                $model = true;
            }

            return $this->lastBy($field, $value, $model);
        }

        $entity = getDynamicEntity($this->entity->name);

        if (is_object($entity) && $entity instanceof Dynamicentity) {
            $methods    = get_class_methods($entity);
            $met     = 'scope' . ucfirst(Strings::camelize($method));

            if (in_array($met, $methods)) {
                $params = array_merge([$entity, $met], [$this]);

                return gi()->call(...$params);
            }

            $met = 'query' . ucfirst(Strings::camelize($method));

            if (in_array($met, $methods)) {
                $params = array_merge([$entity, $met], [$this]);

                return gi()->call(...$params);
            }
        }

        if (count($parameters) === 1) {
            $o = array_shift($parameters);

            if ($o instanceof Dynamicrecord) {
                $fk = Strings::uncamelize($method) . '_id';

                return $this->where($fk, (int) $o->id);
            }
        }
    }

    /**
     * @return string
     */
    public function getEntity(): string
    {
        return $this->entity->name;
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @return null
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @return string
     */
    public function getCacheClass()
    {
        return get_class($this->cache);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orIn(string $field, array $values): self
    {
        return $this->or($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orNotIn(string $field, array $values): self
    {
        return $this->or($field, 'not in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function WhereIn(string $field, array $values): self
    {
        return $this->where($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orWhereIn(string $field, array $values): self
    {
        return $this->or($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function whereNotIn(string $field, array $values): self
    {
        return $this->where($field, 'not in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orWhereNotIn(string $field, array $values): self
    {
        return $this->or($field, 'not in', $values);
    }

    /**
     * @param string $field
     * @param $min
     * @param $max
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function between(string $field, $min, $max): self
    {
        return $this->where($field, 'between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param $min
     * @param $max
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orBetween(string $field, $min, $max): self
    {
        return $this->or($field, 'between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param $min
     * @param $max
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function notBetween(string $field, $min, $max): self
    {
        return $this->where($field, 'not between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param $min
     * @param $max
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orNotBetween(string $field, $min, $max): self
    {
        return $this->or($field, 'not between', [$min, $max]);
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orIsNull(string $field): self
    {
        return $this->or($field, 'is', 'null');
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orIsNotNull(string $field): self
    {
        return $this->or($field, 'is not', 'null');
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function startsWith(string $field, $value): self
    {
        return $this->where($field, 'Like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orStartsWith(string $field, $value): self
    {
        return $this->or($field, 'Like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function endsWith(string $field, $value): self
    {
        return $this->where($field, 'Like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orEndsWith(string $field, $value): self
    {
        return $this->or($field, 'Like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function lt(string $field, $value): self
    {
        return $this->where($field, '<', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orLt(string $field, $value): self
    {
        return $this->or($field, '<', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function gt(string $field, $value): self
    {
        return $this->where($field, '>', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orGt(string $field, $value): self
    {
        return $this->or($field, '>', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function lte(string $field, $value): self
    {
        return $this->where($field, '<=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orLte(string $field, $value): self
    {
        return $this->or($field, '<=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function gte(string $field, $value): self
    {
        return $this->where($field, '>=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orGte(string $field, $value): self
    {
        return $this->or($field, '>=', $value);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function before($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->lt('created_at', $date) : $this->lte('created_at', $date);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orBefore($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->orLt('created_at', $date) : $this->orLte('created_at', $date);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return Dynamicmodel
     */
    public function after($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->gt('created_at', $date) : $this->gte('created_at', $date);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orAfter($date, bool $strict = true): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $strict ? $this->orGt('created_at', $date) : $this->orGte('created_at', $date);
    }

    /**
     * @param string $field
     * @param string $op
     * @param $date
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function when(string $field, string $op, $date): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $this->where($field, $op, $date);
    }

    /**
     * @param string $field
     * @param string $op
     * @param $date
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orWhen(string $field, string $op, $date): self
    {
        if (!is_int($date)) {
            $date = (int) $date->timestamp;
        }

        return $this->or($field, $op, $date);
    }

    /**
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    public function deleted(): self
    {
        return $this->lte('deleted_at', microtime(true));
    }

    /**
     * @return Dynamicmodel
     * @throws \Exception
     */
    public function orDeleted(): self
    {
        return $this->orLte('deleted_at', microtime(true));
    }

    /**
     * @param $id
     * @return bool
     * @throws \ReflectionException
     */
    public function hasId($id): bool
    {
        $row = $this->find($id);

        return $row ? true : false;
    }

    /**
     * @param int $id
     * @param bool $default
     * @return bool|Dynamicrecord
     * @throws \ReflectionException
     */
    public function findOr(int $id, $default = false)
    {
        $row = $this->find($id, false);

        return $row ? $this->makeModel($row) : $default;
    }

    /**
     * @param int $id
     * @return bool|Dynamicrecord
     * @throws \ReflectionException
     */
    public function findOrFalse(int $id)
    {
        return $this->findOr($id, false);
    }

    /**
     * @param int $id
     * @return bool|Dynamicrecord
     * @throws \ReflectionException
     */
    public function findOrNull(int $id)
    {
        return $this->findOr($id, null);
    }

    /**
     * @param int $id
     * @param bool $model
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function findOrFail(int $id, bool $model = true)
    {
        $row = $this->find($id, false);

        if (!$row) {
            exception('dynamicModel', "The row $id does not exist.");
        } else {
            return $model ? $this->makeModel($row) : $row;
        }
    }

    /**
     * @param bool $default
     * @return bool|Dynamicrecord
     * @throws \ReflectionException
     */
    public function firstOr($default = false)
    {
        $row = $this->first(false);

        return $row ? $this->makeModel($row) : $default;
    }

    /**
     * @return bool|Dynamicrecord
     * @throws \ReflectionException
     */
    public function firstOrFalse()
    {
        return $this->firstOr(false);
    }

    /**
     * @return bool|Dynamicrecord
     * @throws \ReflectionException
     */
    public function firstOrNull()
    {
        return $this->firstOr(null);
    }

    /**
     * @param bool $default
     * @return bool|Dynamicrecord
     * @throws \ReflectionException
     */
    public function lastOr($default = false)
    {
        $row = $this->last(false);

        return $row ? $this->makeModel($row) : $default;
    }

    /**
     * @return bool|Dynamicrecord
     * @throws \ReflectionException
     */
    public function lastOrFalse()
    {
        return $this->lastOr(false);
    }

    /**
     * @return bool|Dynamicrecord
     * @throws \ReflectionException
     */
    public function lastOrNull()
    {
        return $this->lastOr(null);
    }

    /**
     * @param bool $model
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function firstOrFail(bool $model = true)
    {
        $row = $this->first(false);

        if (!$row) {
            exception('dynamicModel', "The row does not exist.");
        } else {
            return $model ? $this->makeModel($row) : $row;
        }
    }

    /**
     * @param bool $model
     * @return mixed|Dynamicrecord
     * @throws \ReflectionException
     */
    public function lastOrFail(bool $model = true)
    {
        $row = $this->last(false);

        if (!$row) {
            exception('dynamicModel', "The row does not exist.");
        } else {
            return $model ? $this->makeModel($row) : $row;
        }
    }

    /**
     * @param $conditions
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function noTuple($conditions)
    {
        $conditions = arrayable($conditions) ? $conditions->toArray() : $conditions;

        foreach ($conditions as $k => $v) {
            $this->where($k, $v);
        }

        if ($this->count() === 0) {
            $row = $this->create($conditions);

            return $this->makeModel($row);
        } else {
            return $this->first(true);
        }
    }

    /**
     * @param $conditions
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function unique($conditions)
    {
        return $this->noTuple($conditions);
    }

    /**
     * @param $conditions
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    function search($conditions): self
    {
        $conditions = arrayable($conditions) ? $conditions->toArray() : $conditions;

        foreach ($conditions as $field => $value) {
            $this->where($field, $value);
        }

        return $this;
    }

    /**
     * @param $attributes
     * @param bool $model
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function firstByAttributes($attributes, bool $model = true)
    {
        $attributes = arrayable($attributes) ? $attributes->toArray() : $attributes;

        $q = $this;

        foreach ($attributes as $field => $value) {
            $q->where($field, $value);
        }

        return $q->first($model);
    }

    /**
     * @param $conditions
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function firstOrCreate($conditions)
    {
        $conditions = arrayable($conditions) ? $conditions->toArray() : $conditions;

        $q = $this;

        foreach ($conditions as $field => $value) {
            $q->where($field, $value);
        }

        $exists = $q->first(true);

        if (null === $exists) {
            $row = $this->create($conditions);

            return $this->makeModel($row);
        }

        return $exists;
    }

    /**
     * @param $conditions
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function firstOrNew($conditions)
    {
        $conditions = arrayable($conditions) ? $conditions->toArray() : $conditions;

        $q = $this;

        foreach ($conditions as $field => $value) {
            $q->where($field, $value);
        }

        $exists = $q->first(true);

        if (null === $exists) {
            return $this->makeModel($conditions);
        }

        return $exists;
    }

    /**
     * @param bool $model
     * @return Iterator
     */
    public function all(bool $model = true): Iterator
    {
        return $model ? $this->reset()->model() :$this->reset()->get();
    }

    /**
     * @param $event
     * @param $concern
     * @return mixed
     * @throws \ReflectionException
     */
    public function fire($event, $concern)
    {
        /** @var Dynamicentity $entity */
        $entity = getDynamicEntity($this->entity->name);

        if ($entity) {
            $entity->fire($event, $concern);
        }

        return $concern;
    }
}
