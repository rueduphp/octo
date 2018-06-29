<?php

namespace App\Services;

use Closure;

abstract class Paginator
{
    protected $items;

    protected $perPage;

    protected $currentPage;

    /**
     * @var string
     */
    protected $path = '/';

    /**
     * @var array
     */
    protected $query = [];

    protected $fragment;

    /**
     * @var string
     */
    protected $pageName = 'page';

    protected static $currentPathResolver;

    protected static $currentPageResolver;

    protected static $viewFactoryResolver;

    public static $defaultView = 'pagination::bootstrap-4';

    public static $defaultSimpleView = 'pagination::simple-bootstrap-4';

    /**
     * @param $page
     * @return bool
     */
    protected function isValidPageNumber($page)
    {
        return $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * @return string
     */
    public function previousPageUrl()
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }
    }

    /**
     * @param $start
     * @param $end
     * @return array
     */
    public function getUrlRange($start, $end)
    {
        return collect(range($start, $end))->mapWithKeys(function ($page) {
            return [$page => $this->url($page)];
        })->all();
    }

    /**
     * @param $page
     * @return string
     */
    public function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }

        $parameters = [$this->pageName => $page];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        return $this->path
            .(\Octo\contains($this->path, '?') ? '&' : '?')
            .http_build_query($parameters, '', '&')
            .$this->buildFragment();
    }

    /**
     * @param null $fragment
     * @return $this
     */
    public function fragment($fragment = null)
    {
        if (is_null($fragment)) {
            return $this->fragment;
        }

        $this->fragment = $fragment;

        return $this;
    }

    /**
     * @param $key
     * @param null $value
     * @return Paginator
     */
    public function appends($key, $value = null)
    {
        if (is_array($key)) {
            return $this->appendArray($key);
        }

        return $this->addQuery($key, $value);
    }

    /**
     * @param array $keys
     * @return $this
     */
    protected function appendArray(array $keys)
    {
        foreach ($keys as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    protected function addQuery($key, $value)
    {
        if ($key !== $this->pageName) {
            $this->query[$key] = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    protected function buildFragment()
    {
        return $this->fragment ? '#'.$this->fragment : '';
    }

    /**
     * @return mixed
     */
    public function items()
    {
        return $this->items->all();
    }

    /**
     * @return float|int|null
     */
    public function firstItem()
    {
        return count($this->items) > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
    }

    /**
     * @return float|int|null
     */
    public function lastItem()
    {
        return count($this->items) > 0 ? $this->firstItem() + $this->count() - 1 : null;
    }

    /**
     * @return mixed
     */
    public function perPage()
    {
        return $this->perPage;
    }

    /**
     * @return bool
     */
    public function hasPages()
    {
        return $this->currentPage() !== 1 || $this->hasMorePages();
    }

    /**
     * @return bool
     */
    public function onFirstPage()
    {
        return $this->currentPage() <= 1;
    }

    /**
     * @return mixed
     */
    public function currentPage()
    {
        return $this->currentPage;
    }

    /**
     * @return string
     */
    public function getPageName()
    {
        return $this->pageName;
    }

    /**
     * @param $name
     * @return $this
     */
    public function setPageName($name)
    {
        $this->pageName = $name;

        return $this;
    }

    /**
     * @param $path
     * @return Paginator
     */
    public function withPath($path)
    {
        return $this->setPath($path);
    }

    /**
     * @param $path
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @param string $default
     * @return mixed|string
     */
    public static function resolveCurrentPath($default = '/')
    {
        if (isset(static::$currentPathResolver)) {
            return call_user_func(static::$currentPathResolver);
        }

        return $default;
    }

    /**
     * @param Closure $resolver
     */
    public static function currentPathResolver(Closure $resolver)
    {
        static::$currentPathResolver = $resolver;
    }

    /**
     * @param string $pageName
     * @param int $default
     * @return int|mixed
     */
    public static function resolveCurrentPage($pageName = 'page', $default = 1)
    {
        if (isset(static::$currentPageResolver)) {
            return call_user_func(static::$currentPageResolver, $pageName);
        }

        return $default;
    }

    /**
     * @param Closure $resolver
     */
    public static function currentPageResolver(Closure $resolver)
    {
        static::$currentPageResolver = $resolver;
    }

    /**
     * @return mixed
     */
    public static function viewFactory()
    {
        return call_user_func(static::$viewFactoryResolver);
    }

    /**
     * @param Closure $resolver
     */
    public static function viewFactoryResolver(Closure $resolver)
    {
        static::$viewFactoryResolver = $resolver;
    }

    /**
     * @param $view
     */
    public static function defaultView($view)
    {
        static::$defaultView = $view;
    }

    /**
     * @param $view
     */
    public static function defaultSimpleView($view)
    {
        static::$defaultSimpleView = $view;
    }

    public static function useBootstrapThree()
    {
        static::defaultView('pagination::default');
        static::defaultSimpleView('pagination::simple-default');
    }

    /**
     * @return mixed
     */
    public function getIterator()
    {
        return $this->items->getIterator();
    }

    /**
     * @return mixed
     */
    public function isEmpty()
    {
        return $this->items->isEmpty();
    }

    /**
     * @return mixed
     */
    public function isNotEmpty()
    {
        return $this->items->isNotEmpty();
    }

    /**
     * @return mixed
     */
    public function count()
    {
        return $this->items->count();
    }

    /**
     * @return mixed
     */
    public function getCollection()
    {
        return $this->items;
    }

    /**
     * @param $collection
     * @return $this
     */
    public function setCollection($collection)
    {
        $this->items = $collection;

        return $this;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function offsetExists($key)
    {
        return $this->items->has($key);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items->get($key);
    }

    /**
     * @param $key
     * @param $value
     */
    public function offsetSet($key, $value)
    {
        $this->items->put($key, $value);
    }

    /**
     * @param $key
     */
    public function offsetUnset($key)
    {
        $this->items->forget($key);
    }

    /**
     * @return string
     */
    public function toHtml()
    {
        return (string) $this->render();
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->getCollection()->$method(...$parameters);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->render();
    }
}
