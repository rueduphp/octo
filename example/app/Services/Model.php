<?php
namespace App\Services;

use App\Facades\Search;
use App\Traits\Remember;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\MessageBag;
use Mmanos\Search\Index;
use Mmanos\Search\Query;
use Octo\Elegant;
use function Octo\gi;
use function Octo\hydrator;
use Octo\Listener;
use Octo\Objet;

class Model extends Elegant
{
    use Remember;

    protected $indexables = [];
    protected $rules = [];
    protected $rulesMessages = [];
    protected $forceSave = false;

    /**
     * @param $id
     * @param array $columns
     * @return Model|null
     */
    public function findWithoutFail($id, $default = null, $columns = ['*'])
    {
        try {
            return $this->find($id, $columns);
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * @param array $attributes
     * @throws \ReflectionException
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->rememberCachePrefix = str_replace('\\', '.', mb_strtolower(get_called_class()));
    }

    /**
     * @throws \ReflectionException
     */
    protected static function boot()
    {
        static::setEventDispatcher(\Octo\dispatcher('models'));

        parent::boot();

        static::saving(function ($item) {
            if (!empty($item->rules) && false === $item->forceSave) {
                $errors = $item->performValidation();

                if (0 !== count($errors)) {
                    throw new \Exception(implode("\n", $errors->all()));
                }
            }
        });

        static::saved(function ($item) {
            static::indexIt($item);
        });

        static::deleted(function ($item) {
            if (!empty($item->indexables)) {
                static::indexator()->delete($item->getKey());
            }
        });
    }

    /**
     * @param mixed ...$params
     * @return Query
     */
    public static function search(...$params): Query
    {
        return static::indexator()->search(...$params);
    }

    /**
     * @return Index
     */
    public static function indexator(): Index
    {
        return Search::index(str_replace('\\', '.', mb_strtolower(get_called_class())));
    }

    /**
     * @return string
     */
    public function ck(): string
    {
        if (true === $this->exists && true === $this->timestamps) {
            return sprintf("%s.%s.%s",
                $this->searchableAs(),
                $this->getKey(),
                $this->updated_at->timestamp
            );
        }

        return sha1(serialize($this->toArray()));
    }

    /**
     * @return mixed
     */
    public function sk()
    {
        return $this->getKey();
    }

    /**
     * @return string
     */
    public function skName()
    {
        return $this->getQualifiedKeyName();
    }


    /**
     * @return string
     */
    protected function searchableAs()
    {
        return str_replace('\\', '.', mb_strtolower(get_called_class()));
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    protected function toSearchableArray()
    {
        $row = $this->toArray();

        $data = [];

        foreach ($this->indexables as $key) {
            if (is_string($key)) {
                $data[$key] = $row[$key] ?? null;
            } elseif ($key instanceof \Closure) {
                $data = gi()->makeClosure($key, $data, $this);
            }
        }

        return $data;
    }

    /**
     * @param Model $item
     * @throws \ReflectionException
     */
    public static function indexIt(Model $item)
    {
        if (!empty($item->indexables)) {
            $index = static::indexator();
            $row = $item->toArray();

            $data = [];

            foreach ($item->indexables as $key) {
                if (is_string($key)) {
                    $data[$key] = $row[$key] ?? null;
                } elseif ($key instanceof \Closure) {
                    $data = gi()->makeClosure($key, $data, $item);
                }
            }

            $index->insert($item->getKey(), $data);
        }
    }

    public function performValidation()
    {
        $attributes = $this->getAttributes();

        $check = validator($attributes, $this->rules, $this->rulesMessages);

        $errors = new MessageBag();

        if ($check->fails()) {
            $errors = $check->errors();
        }

        return $errors;
    }

    public function isValid()
    {
        $errors = $this->performValidation();

        return 0 === count($errors);
    }

    public function forceSave(array $options = [])
    {
        $this->forceSave = true;

        return parent::save($options);
    }

    /**
     * @param array $ids
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getSearchModelsByIds(array $ids)
    {
        $builder = in_array(SoftDeletes::class, class_uses_recursive($this))
            ? $this->withTrashed() : $this->newQuery();

        return $builder->whereIn(
            $this->sk(), $ids
        )->get();
    }

    /**
     * @param string $alias
     * @param null|string $indexBy
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function qb(string $alias, ?string $indexBy = null)
    {
        return qb()->select($alias)
            ->from($this->table, $alias, $indexBy);
    }

    /**
     * @param string $class
     * @param null $foreignKey
     * @param null $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function many(string $class, $foreignKey = null, $localKey = null)
    {
        return $this->hasMany($class, $foreignKey, $localKey);
    }

    /**
     * @param string $class
     * @param null $foreignKey
     * @param null $ownerKey
     * @param null $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function one(string $class, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        return $this->belongsTo($class, $foreignKey, $ownerKey, $relation);
    }

    /**
     * @param string $class
     * @return object
     * @throws \ReflectionException
     */
    public function toObject(string $class = Objet::class)
    {
        return hydrator($class, $this->toArray());
    }

    /**
     * @param string $event
     * @param $callback
     * @return Listener
     */
    public function listenEvent(string $event, $callback)
    {
        return dispatcher('db')->listen($event . ':' . get_class($this), $callback);
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    public function fireEvent(...$args)
    {
        return dispatcher('db')->fire(...$args);
    }
}
