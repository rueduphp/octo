<?php
namespace App\Services;

use App\Facades\Search;
use App\Traits\Decorate;
use App\Traits\Remember;
use Exception;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\MessageBag;
use Mmanos\Search\Index;
use Mmanos\Search\Query;
use Octo\Elegant;
use function Octo\dispatcher as AppDispatcher;
use Octo\FastRequest;
use function Octo\gi;
use function Octo\hydrator;
use Octo\Listener;
use Octo\Objet;

class Model extends Elegant
{
    use Decorate, Remember;

    protected $indexables = [];
    protected $rules = [];
    protected $rulesMessages = [];
    protected $forceSave = false;
    protected $decorator;

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
        static::setEventDispatcher(AppDispatcher('models'));

        parent::boot();

        static::saving(function (Model $model) {
            if (!empty($model->rules) && false === $model->forceSave) {
                $errors = $model->performValidation();

                if (0 !== count($errors)) {
                    throw new Exception(implode("\n", $errors->all()));
                }
            }
        });

        static::updating(function (Model $model) {
            $model->replicate()->setRawAttributes($model->getOriginal());
        });

        static::saved(function (Model $model) {
            static::indexIt($model);
        });

        static::deleted(function (Model $model) {
            if (!empty($model->indexables)) {
                static::indexator()->delete($model->getKey());
            }
        });
    }

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

    /**
     * @return MessageBag
     */
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

    /**
     * @return bool
     */
    public function isValid()
    {
        $errors = $this->performValidation();

        return 0 === count($errors);
    }

    /**
     * @param array $options
     * @return bool|Elegant
     */
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
    public function qb(?string $alias = null, ?string $indexBy = null)
    {
        $alias = $alias ?? substr($this->table, 0, 1);

        return qb()->select($alias)->from($this->table, $alias, $indexBy);
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder(
            $connection,
            $connection->getQueryGrammar(),
            $connection->getPostProcessor()
        );
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

    /**
     * @return Model|null
     */
    public static function lastUpdated()
    {
        $class = get_called_class();

        return static::orderBy('updated_at', 'DESC')
            ->select(
                (new $class)->skName(),
                'updated_at'
            )
            ->first()
        ;
    }

    /**
     * @param array|null $data
     * @return bool|Elegant
     */
    public function saver(?array $data = null)
    {
        $data = $data ?? (new FastRequest)->getParsedBody();

        foreach ($data as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this->save();
    }

    /**
     * @param array|null $only
     * @return bool|Elegant
     */
    public function posted(?array $only = null)
    {
        if (null === $only) {
            $data = (new FastRequest)->getParsedBody();
        } else {
            if (is_array($only)) {
                $data = (new FastRequest)->only($only);
            } else {
                $data = (new FastRequest)->only(...func_get_args());
            }
        }

        return $this->saver($data);
    }
}
