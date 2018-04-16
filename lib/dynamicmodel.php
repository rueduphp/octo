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
        $row = $this->find($id);

        unset($row['id']);

        $new = array_merge($row, $data);

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
     * @param int $id
     * @return bool
     */
    function delete(int $id)
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
    public function find(int $id)
    {
        $key = 'eav.' . $this->entity->id . '.' . $id . '.find';

        return $this->cache->until($key, function () use ($id) {
            /** @var EavRow $row */
            $row = EavRow::find($id);

            if ($row) {
                $found = [];

                $values = $row->values()->get()->toArray();

                foreach ($values as $value) {
                    $found[$value['attribute']['name']] = unserialize($value['value']);
                }

                $found['id'] = (int) $value['row_id'];

                return $found;
            }

            return null;
        }, $this->getAge());
    }

    /**
     * @return array
     */
    public function freshids()
    {
        return EavRow::all()->pluck('id')->all();
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
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function first()
    {
        return $this->get()->first();
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function last()
    {
        return $this->get()->last();
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
        return $this->each(function ($row) {
            return $this->find($row);
        });
    }


    /**
     * @return Iterator
     */
    public function model(): Iterator
    {
        return $this->each(function ($row) {
            return new Dynamicrecord($this->find($row), $this);
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
            $row = $this->find($id);

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
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function firstBy(string $field, $value)
    {
        return $this->where($field, '=', $value)->first();
    }

    /**
     * @param string $field
     * @param $value
     * @return mixed
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
    public function in(string $field, array $values)
    {
        return $this->where($field, 'in', $values);
    }

    /**
     * @param string $field
     * @param array $values
     * @return Dynamicmodel
     */
    public function notIn(string $field, array $values)
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
    public function isBetween(string $field, $min, $max)
    {
        return $this->where($field, 'between', [$min, $max]);
    }

    /**
     * @param string $field
     * @param $min
     * @param $max
     * @return Dynamicmodel
     */
    public function isNotBetween(string $field, $min, $max)
    {
        return $this->where($field, 'not between', [$min, $max]);
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     */
    public function isNull(string $field)
    {
        return $this->where($field, 'is', 'null');
    }

    /**
     * @param string $field
     * @return Dynamicmodel
     */
    public function isNotNull(string $field)
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

    public function getEntity()
    {
        return $this->entity;
    }
}