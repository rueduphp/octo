<?php

namespace App\Services;

use Octo\Collection;
use function Octo\views_path;

class Directives
{
    /**
     * @param array $directives
     * @throws \ReflectionException
     */
    public static function register(array $directives = [])
    {
        foreach ($directives as $name => $directive) {
            \Octo\bladeCompiler()->directive($name, $directive);
        }

        view()->addNamespace('bootstrap', views_path('components/bootstrap'));
    }

    /**
     * @param $expression
     * @return Collection
     */
    public static function parseMultipleArgs($expression)
    {
        return \Octo\coll(explode(',', str_replace(['(', ')'], '', $expression)))->map(function ($item) {
            return trim($item);
        });
    }

    /**
     * @param $expression
     * @return Collection
     */
    public static function cleanArgs($expression)
    {
        return static::parseMultipleArgs(str_replace("'", '', $expression));
    }

    /**
     * @param $expression
     * @return mixed
     */
    public static function stripQuotes($expression)
    {
        return str_replace("'", '', $expression);
    }

    /**
     * @param $items
     * @param int $page
     * @param int $perPage
     * @param string $path
     * @param array $componentOptions
     * @example ‘pagination’ => Directives::pagination($items, $page, $perPage)
     * @return mixed
     */
    public function pagination($items, $page = 1, $perPage = 25, $path = '', array $componentOptions = [])
    {
        $items = $items instanceof Collection ? $items : Collection::make($items);

        $paginator = (new Paginate($items, $items->count(), (int) $perPage, (int) $page))
            ->withPath($path);

        return $paginator->render('bootstrap::view.pagination', $componentOptions);
    }
}
