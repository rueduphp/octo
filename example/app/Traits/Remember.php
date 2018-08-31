<?php

namespace App\Traits;

use App\Services\Query\Builder;
use Octo\Arrays;

trait Remember
{
    /** @var null|int */
    protected $rememberMinutes;
    /** @var null|string */
    protected $rememberCachePrefix;
    /** @var null|bool */
    protected $forceCache;

    /**
     * @return Builder
     */
    protected function newBaseQueryBuilder(): Builder
    {
        $cnx = $this->getConnection();

        $grammar = $cnx->getQueryGrammar();

        $builder = new Builder($cnx, $grammar, $cnx->getPostProcessor());

        if (isset($this->rememberCachePrefix)) {
            $builder->prefix($this->rememberCachePrefix);
        }

        $builder->setCacheMinutes($this->rememberMinutes ?? 60)
            ->setMustCache($this->forceCache ?? isProd());

        return $this->macroize($builder);
    }

    /**
     * @param Builder $builder
     * @return Builder
     */
    protected function macroize(Builder $builder): Builder
    {
        $builder::macro('list', function ($column) {
            return Arrays::pluck($this->select($column, 'id')->get()->toArray(), $column, 'id');
        });

        $builder::macro('or', function ($column, $operator = null, $value = null) {
            return $this->orWhere($column, $operator, $value);
        });

        $builder::macro('firstWhere', function (...$args) {
            return $this->where(...$args)->first();
        });

        $builder::macro('orFirstWhere', function (...$args) {
            return $this->orWhere(...$args)->first();
        });

        $builder::macro('like', function ($column, $value) {
            return $this->where($column, 'like', $value);
        });

        $builder::macro('notLike', function ($column, $value) {
            return $this->where($column, 'not like', $value);
        });

        $builder::macro('ilike', function ($column, $value) {
            return $this->where($column, 'ilike', $value);
        });

        $builder::macro('rlike', function ($column, $value) {
            return $this->where($column, 'rlike', $value);
        });

        $builder::macro('similar', function ($column, $value) {
            return $this->where($column, 'similar to', $value);
        });

        $builder::macro('notSimilar', function ($column, $value) {
            return $this->where($column, 'not similar to', $value);
        });

        $builder::macro('regexp', function ($column, $value) {
            return $this->where($column, 'regexp', $value);
        });

        $builder::macro('notRegexp', function ($column, $value) {
            return $this->where($column, 'not regexp', $value);
        });

        $builder::macro('orLike', function ($column, $value) {
            return $this->where($column, 'like', $value, 'or');
        });

        $builder::macro('orNotLike', function ($column, $value) {
            return $this->where($column, 'not like', $value, 'or');
        });

        $builder::macro('orIlike', function ($column, $value) {
            return $this->where($column, 'ilike', $value, 'or');
        });

        $builder::macro('orRlike', function ($column, $value) {
            return $this->where($column, 'rlike', $value, 'or');
        });

        $builder::macro('orSimilar', function ($column, $value) {
            return $this->where($column, 'similar to', $value, 'or');
        });

        $builder::macro('orRegexp', function ($column, $value) {
            return $this->where($column, 'regexp', $value, 'or');
        });

        $builder::macro('orNotRegexp', function ($column, $value) {
            return $this->where($column, 'not regexp', $value, 'or');
        });

        $builder::macro('orNotSimilar', function ($column, $value) {
            return $this->where($column, 'not similar to', $value, 'or');
        });

        $builder::macro('as', function ($column, $value) {
            return $this->where($column, 'like', "%$value%");
        });

        $builder::macro('orAs', function ($column, $value) {
            return $this->where($column, 'like', "%$value%", 'or');
        });

        $builder::macro('notAs', function ($column, $value) {
            return $this->where($column, 'not like', "%$value%");
        });

        $builder::macro('orNotAs', function ($column, $value) {
            return $this->where($column, 'not like', "%$value%", 'or');
        });

        $builder::macro('null', function ($column) {
            return $this->whereNull($column);
        });

        $builder::macro('notNull', function ($column) {
            return $this->whereNull($column, 'and', true);
        });

        $builder::macro('orNull', function ($column) {
            return $this->whereNull($column, 'or');
        });

        $builder::macro('orNotNull', function ($column) {
            return $this->whereNull($column, 'or', true);
        });

        $builder::macro('between', function ($column, array $values) {
            return $this->whereBetween($column, $values);
        });

        $builder::macro('notBetween', function ($column, array $values) {
            return $this->whereBetween($column, $values, 'and', true);
        });

        $builder::macro('orBetween', function ($column, array $values) {
            return $this->whereBetween($column, $values, 'or');
        });

        $builder::macro('orNotBetween', function ($column, array $values) {
            return $this->whereBetween($column, $values, 'or', true);
        });

        return $builder;
    }
}
