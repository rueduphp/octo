<?php
namespace App\Services;

use App\Facades\Search;
use App\Traits\Remember;
use Mmanos\Search\Index;
use Mmanos\Search\Query;
use function Octo\dispatcher;
use Octo\Elegant;

class Model extends Elegant
{
    use Remember;

    protected $indexables = [];

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
        static::setEventDispatcher(dispatcher('models'));

        parent::boot();

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
    protected static function search(...$params): Query
    {
        return static::indexator()->search(...$params);
    }

    /**
     * @return Index
     */
    protected static function indexator(): Index
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
                str_replace('\\', '.', mb_strtolower(get_called_class())),
                $this->getKey(),
                $this->updated_at->timestamp
            );
        }

        return sha1(serialize($this->toArray()));
    }

    /**
     * @param Model $item
     */
    protected static function indexIt(Model $item)
    {
        if (!empty($item->indexables)) {
            $index = static::indexator();
            $row = $item->toArray();

            $data = [];

            foreach ($item->indexables as $key) {
                $data[$key] = $row[$key] ?? null;
            }

            $index->insert($item->getKey(), $data);
        }
    }
}
