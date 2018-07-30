<?php

namespace App\Services;

use AlgoliaSearch\Client as AlgoliaClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;

class Algolia
{
    /**
     * @var \AlgoliaSearch\Client
     */
    protected $algolia;

    /**
     * @param  \AlgoliaSearch\Client  $algolia
     */
    public function __construct(AlgoliaClient $algolia)
    {
        $this->algolia = $algolia;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @throws \AlgoliaSearch\AlgoliaException
     * @return void
     */
    public function update(Collection $models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $index = $this->algolia->initIndex($models->first()->searchableAs());

        if ($this->usesSoftDelete($models->first()) && \Config::get('algolia.soft_delete', false)) {
            $models->each->pushSoftDeleteMetadata();
        }

        $index->addObjects($models->map(function ($model) {
            $array = $model->toSearchableArray();

            if (empty($array)) {
                return;
            }

            return array_merge(['objectID' => $model->sk()], $array);
        })->filter()->values()->all());
    }

    /**
     * @param Collection $models
     * @throws \AlgoliaSearch\AlgoliaException
     */
    public function delete(Collection $models)
    {
        $index = $this->algolia->initIndex($models->first()->searchableAs());

        $index->deleteObjects(
            $models->map(function ($model) {
                return $model->sk();
            })->values()->all()
        );
    }

    /**
     * @param AlgoliaBuilder $builder
     * @return mixed
     * @throws \AlgoliaSearch\AlgoliaException
     * @throws \ReflectionException
     */
    public function search(AlgoliaBuilder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'hitsPerPage' => $builder->limit,
        ]));
    }

    /**
     * @param AlgoliaBuilder $builder
     * @param $perPage
     * @param $page
     * @return mixed
     * @throws \AlgoliaSearch\AlgoliaException
     * @throws \ReflectionException
     */
    public function paginate(AlgoliaBuilder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'hitsPerPage' => $perPage,
            'page' => $page - 1,
        ]);
    }

    /**
     * @param AlgoliaBuilder $builder
     * @param array $options
     * @return mixed
     * @throws \AlgoliaSearch\AlgoliaException
     */
    protected function performSearch(AlgoliaBuilder $builder, array $options = [])
    {
        $algolia = $this->algolia->initIndex(
            $builder->index ?: $builder->model->searchableAs()
        );

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $algolia,
                $builder->query,
                $options
            );
        }

        return $algolia->search($builder->query, $options);
    }

    /**
     * @param AlgoliaBuilder $builder
     * @return mixed
     * @throws \ReflectionException
     */
    protected function filters(AlgoliaBuilder $builder)
    {
        return coll($builder->wheres)->map(function ($value, $key) {
            return $key . '=' . $value;
        })->values()->all();
    }

    /**
     * @param $results
     * @return \Octo\Collection
     */
    public function mapIds($results)
    {
        return coll(array_values(coll($results['hits'])->pluck('objectID')));
    }

    /**
     * @param $results
     * @param $model
     * @return Collection|\Illuminate\Support\Collection
     */
    public function map($results, $model)
    {
        if (count($results['hits']) === 0) {
            return Collection::make();
        }

        $models = $model->getSearchModelsByIds(
            $this->mapIds($results)
        )->keyBy(function ($model) {
            return $model->sk();
        });

        return Collection::make($results['hits'])->map(function ($hit) use ($models) {
            if (isset($models[$hit['objectID']])) {
                return $models[$hit['objectID']];
            }
        })->filter()->values();
    }

    /**
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['nbHits'];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }
}
