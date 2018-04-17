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

    public static function migrate()
    {
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
            $table->longText('value');
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
     * @return int
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
     */
    public function delete(int $id): bool
    {
        $row = EavRow::find($id);

        if (null !== $row) {
            $row->attributes()->delete();
            $row->values(true)->delete();
            $row->delete($id);
            $this->age(time() + 1);

            return true;
        }

        return false;
    }

    /**
     * @param null $t
     * @return int
     */
    public function age($t = null)
    {
        $key = 'eav.' . $this->entity->id . '.age';

        if (empty($t)) {
            return time();
        } else {
            $this->cache->set($key, $t);
        }
    }

    /**
     * @return int
     */
    public function getAge()
    {
        $key = 'eav.' . $this->entity->id . '.age';

        return $this->cache->get($key, time());
    }

    /**
     * @param int $id
     * @return mixed|null
     */
    public function find(int $id, $model = true)
    {
        $key = 'eav.' . $this->entity->id . '.' . $id . '.find';

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

                return true === $model ? new Dynamicrecord($found, $this) : $found;
            }

            return null;
        }, $this->getAge());
    }

    /**
     * @return array
     */
    public function freshids()
    {
        return EavRow::where('entity_id', $this->entity->id)->pluck('id')->all();
    }

    /**
     * @return array
     */
    public function ids()
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
     *
     * @return Dynamicmodel
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
     * @param bool $model
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function first($model = true)
    {
        $row = $this->get()->first();

        return $model && null !== $row ? new Dynamicrecord($row, $this) : $row;
    }

    /**
     * @param bool $model
     * @return mixed|Dynamicrecord
     * @throws \ReflectionException
     */
    public function last($model = true)
    {
        $row = $this->get()->last();

        return $model && null !== $row ? new Dynamicrecord($row, $this) : $row;
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
            return $this->find($row);
        });
    }

    /**
     * @param null|Dynamicentity $entity
     * @return Iterator
     */
    public function model(?Dynamicentity $entity = null): Iterator
    {
        return $this->each(function ($row) use ($entity) {
            return new Dynamicrecord($this->find($row), $this, $entity);
        });
    }

    /**
     * @param $offset
     * @param null $length
     *
     * @return Dynamicmodel
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
     */
    public function like(string $field, $value)
    {
        return $this->where($field, 'like', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function notLike(string $field, $value)
    {
        return $this->where($field, 'not like', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function findBy(string $field, $value)
    {
        return $this->where($field, '=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return mixed|null|Dynamicrecord
     * @throws \ReflectionException
     */
    public function firstBy(string $field, $value)
    {
        return $this->where($field, '=', $value)->first();
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
     */
    public function in(string $field, array $values): self
    {
        return $this->where($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     */
    public function notIn(string $field, array $values): self
    {
        return $this->where($field, 'not in', $values);
    }

    /**
     * @param null $default
     * @return mixed|null
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
     */
    public function isNotBetween(string $field, $min, $max): self
    {
        return $this->where($field, 'not between', [$min, $max]);
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     */
    public function isNull(string $field): self
    {
        return $this->where($field, 'is', 'null');
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     */
    public function isNotNull(string $field): self
    {
        return $this->where($field, 'is not', 'null');
    }

    public function sum(string $field)
    {
        $keyCache = sha1('eav.sum.' . $this->entity->id . $field . serialize($this->query));

        return $this->cache->until($keyCache, function () use ($field) {
            return $this->get()->sum($field);
        }, $this->getAge());
    }

    public function min(string $field)
    {
        $keyCache = sha1('eav.min.' . $this->entity->id . $field . serialize($this->query));

        return $this->cache->until($keyCache, function () use ($field) {
            return $this->get()->min($field);
        }, $this->getAge());
    }

    public function max(string $field)
    {
        $keyCache = sha1('eav.max.' . $this->entity->id . $field . serialize($this->query));

        return $this->cache->until($keyCache, function () use ($field) {
            return $this->get()->max($field);
        }, $this->getAge());
    }

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

    public function __call(string $method, array $parameters)
    {
        if ('or' === $method) {
            $oldIds = $this->ids();

            $self = $this->where(...$parameters);

            $merged = array_merge($oldIds, $self->ids());

            $this->ids = array_values($merged);

            return $self;
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
     */
    public function orIn(string $field, array $values): self
    {
        return $this->or($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     */
    public function orNotIn(string $field, array $values): self
    {
        return $this->or($field, 'not in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     */
    public function WhereIn(string $field, array $values): self
    {
        return $this->where($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     */
    public function orWhereIn(string $field, array $values): self
    {
        return $this->or($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     */
    public function whereNotIn(string $field, array $values): self
    {
        return $this->where($field, 'not in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
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
     */
    public function orNotBetween(string $field, $min, $max): self
    {
        return $this->or($field, 'not between', [$min, $max]);
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     */
    public function orIsNull(string $field): self
    {
        return $this->or($field, 'is', 'null');
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     */
    public function orIsNotNull(string $field): self
    {
        return $this->or($field, 'is not', 'null');
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function startsWith(string $field, $value): self
    {
        return $this->where($field, 'Like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function orStartsWith(string $field, $value): self
    {
        return $this->or($field, 'Like', $value . '%');
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function endsWith(string $field, $value): self
    {
        return $this->where($field, 'Like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function orEndsWith(string $field, $value): self
    {
        return $this->or($field, 'Like', '%' . $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function lt(string $field, $value): self
    {
        return $this->where($field, '<', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function orLt(string $field, $value): self
    {
        return $this->or($field, '<', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function gt(string $field, $value): self
    {
        return $this->where($field, '>', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function orGt(string $field, $value): self
    {
        return $this->or($field, '>', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function lte(string $field, $value): self
    {
        return $this->where($field, '<=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function orLte(string $field, $value): self
    {
        return $this->or($field, '<=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function gte(string $field, $value): self
    {
        return $this->where($field, '>=', $value);
    }

    /**
     * @param string $field
     * @param $value
     * @return Dynamicmodel
     */
    public function orGte(string $field, $value): self
    {
        return $this->or($field, '>=', $value);
    }

    /**
     * @param $date
     * @param bool $strict
     * @return Dynamicmodel
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
     */
    public function deleted(): self
    {
        return $this->lte('deleted_at', microtime(true));
    }

    /**
     * @return Dynamicmodel
     */
    public function orDeleted(): self
    {
        return $this->orLte('deleted_at', microtime(true));
    }

    /**
     * @param $id
     * @return bool
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
     */
    public function findOr(int $id, $default = false)
    {
        $row = $this->find($id, false);

        return $row ? new Dynamicrecord($row, $this) : $default;
    }

    /**
     * @param int $id
     * @return bool|Dynamicrecord
     */
    public function findOrFalse(int $id)
    {
        return $this->findOr($id, false);
    }

    /**
     * @param int $id
     * @return bool|Dynamicrecord
     */
    public function findOrNull(int $id)
    {
        return $this->findOr($id, null);
    }

    /**
     * @param int $id
     * @param bool $model
     * @return mixed|null|Dynamicrecord
     */
    public function findOrFail(int $id, bool $model = true)
    {
        $row = $this->find($id, false);

        if (!$row) {
            exception('dynamicModel', "The row $id does not exist.");
        } else {
            return $model ? new Dynamicrecord($row, $this) : $row;
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

        return $row ? new Dynamicrecord($row, $this) : $default;
    }

    /**
     * @return bool|Dynamicrecord
     */
    public function firstOrFalse()
    {
        return $this->firstOr(false);
    }

    /**
     * @return bool|Dynamicrecord
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

        return $row ? new Dynamicrecord($row, $this) : $default;
    }

    /**
     * @return bool|Dynamicrecord
     */
    public function lastOrFalse()
    {
        return $this->lastOr(false);
    }

    /**
     * @return bool|Dynamicrecord
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
            return $model ? new Dynamicrecord($row, $this) : $row;
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
            return $model ? new Dynamicrecord($row, $this) : $row;
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

            return new Dynamicrecord($row, $this);
        } else {
            return $this->first(true);
        }
    }

    /**
     * @param $conditions
     * @return mixed|null|Dynamicrecord
     */
    public function unique($conditions)
    {
        return $this->noTuple($conditions);
    }

    /**
     * @param $conditions
     * @return Dynamicmodel
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

            return new Dynamicrecord($row, $this);
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
            return new Dynamicrecord($conditions, $this);
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
}
