<?php

namespace App\Traits;

use App\Services\Query\Builder;

trait Remember
{
    /**
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
        $cnx = $this->getConnection();

        $grammar = $cnx->getQueryGrammar();

        $builder = new Builder($cnx, $grammar, $cnx->getPostProcessor());

        if (isset($this->rememberFor)) {
            $builder->remember($this->rememberFor);
        }

        if (isset($this->rememberCachePrefix)) {
            $builder->prefix($this->rememberCachePrefix);
        }

        if (isset($this->rememberCacheDriver)) {
            $builder->cacheDriver($this->rememberCacheDriver);
        }

        $builder->setCacheMinutes($this->rememberMinutes ?? 60)
            ->setMustCache($this->forceCache ?? isProd());

        return $builder;
    }
}
