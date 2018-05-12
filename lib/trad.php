<?php

namespace Octo;

use Countable;

class Trad extends Resolve implements FastTranslatorInterface
{
    use Macroable;

    /**
     * @var Fileloader
     */
    protected $loader;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var string
     */
    protected $fallback;

    /**
     * @var array
     */
    protected $loaded = [];

    /**
     * @var Selector
     */
    protected $selector;

    /**
     * @param  Fileloader  $loader
     * @param  string  $locale
     *
     * @return void
     */
    public function __construct(Fileloader $loader, $locale)
    {
        $this->loader = $loader;
        $this->locale = $locale;
    }

    /**
     * @param  string  $key
     * @param  string|null  $locale
     *
     * @return bool
     */
    public function hasForLocale($key, $locale = null)
    {
        return $this->has($key, $locale, false);
    }

    /**
     * @param  string  $key
     * @param  string|null  $locale
     * @param  bool  $fallback
     *
     * @return bool
     */
    public function has($key, $locale = null, $fallback = true)
    {
        return $this->get($key, [], $locale, $fallback) !== $key;
    }

    /**
     * @param  string  $key
     * @param  array   $replace
     * @param  string  $locale
     *
     * @return string|array|null
     */
    public function trans($key, array $replace = [], $locale = null)
    {
        return $this->get($key, $replace, $locale);
    }

    /**
     * @param  string  $key
     * @param  array   $replace
     * @param  string  $locale
     *
     * @return string|array|null
     */
    public function __($key, array $replace = [], $locale = null)
    {
        return $this->get($key, $replace, $locale);
    }

    /**
     * @param  string  $key
     * @param  array   $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     *
     * @return string|array|null
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        list($namespace, $group, $item) = $this->parseKey($key);

        $locales = $fallback
            ? $this->localeArray($locale)
            : [$locale ?: $this->locale]
        ;

        foreach ($locales as $locale) {
            if (!is_null($line = $this->getLine(
                $namespace, $group, $locale, $item, $replace
            ))) {
                break;
            }
        }

        if (isset($line)) {
            return $line;
        }

        return $key;
    }

    /**
     * @param  string  $key
     * @param  array  $replace
     * @param  string  $locale
     * @return string|array|null
     */
    public function getFromJson($key, array $replace = [], $locale = null)
    {
        $locale = $locale ?: $this->locale;

        $this->load('*', '*', $locale);

        $line = $this->loaded['*']['*'][$locale][$key] ?? null;

        if (!isset($line)) {
            $fallback = $this->get($key, $replace, $locale);

            if ($fallback !== $key) {
                return $fallback;
            }
        }

        return $this->makeReplacements($line ?: $key, $replace);
    }

    /**
     * @param  string  $key
     * @param  int|array|\Countable  $number
     * @param  array   $replace
     * @param  string  $locale
     * @return string
     */
    public function transChoice($key, $number, array $replace = [], $locale = null)
    {
        return $this->choice($key, $number, $replace, $locale);
    }

    /**
     * @param  string  $key
     * @param  int|array|\Countable  $number
     * @param  array   $replace
     * @param  string  $locale
     * @return string
     */
    public function choice($key, $number, array $replace = [], $locale = null)
    {
        $line = $this->get(
            $key, $replace, $locale = $this->localeForChoice($locale)
        );

        if (is_array($number) || $number instanceof Countable) {
            $number = count($number);
        }

        $replace['count'] = $number;

        return $this->makeReplacements(
            $this->getSelector()->choose($line, $number, $locale), $replace
        );
    }

    /**
     * @param  string|null  $locale
     * @return string
     */
    protected function localeForChoice($locale)
    {
        return $locale ?: $this->locale ?: $this->fallback;
    }

    /**
     * @param $namespace
     * @param $group
     * @param $locale
     * @param $item
     * @param array $replace
     * @return mixed|null|string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function getLine($namespace, $group, $locale, $item, array $replace)
    {
        $this->load($namespace, $group, $locale);

        $line = aget($this->loaded[$namespace][$group][$locale], $item);

        if (is_string($line)) {
            return $this->makeReplacements($line, $replace);
        } elseif (is_array($line) && count($line) > 0) {
            return $line;
        }
    }

    /**
     * @param  string  $line
     * @param  array   $replace
     * @return string
     */
    protected function makeReplacements($line, array $replace)
    {
        if (empty($replace)) {
            return $line;
        }

        $replace = $this->sortReplacements($replace);

        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':'.$key, ':' . Inflector::upper($key), ':' . Inflector::ucfirst($key)],
                [$value, Inflector::upper($value), Inflector::ucfirst($value)],
                $line
            );
        }

        return $line;
    }

    /**
     * @param  array  $replace
     * @return array
     */
    protected function sortReplacements(array $replace)
    {
        return coll($replace)->sortBy(function ($value, $key) {
            return mb_strlen($key) * -1;
        })->all();
    }

    /**
     * @param  array  $lines
     * @param  string  $locale
     * @param  string  $namespace
     */
    public function addLines(array $lines, $locale, $namespace = '*')
    {
        foreach ($lines as $key => $value) {
            list($group, $item) = explode('.', $key, 2);

            aset($this->loaded, "$namespace . $group . $locale . $item", $value);
        }
    }

    /**
     * @param $namespace
     * @param $group
     * @param $locale
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function load($namespace, $group, $locale)
    {
        if ($this->isLoaded($namespace, $group, $locale)) {
            return;
        }

        $lines = $this->loader->load($locale, $group, $namespace);

        $this->loaded[$namespace][$group][$locale] = $lines;
    }

    /**
     * @param  string  $namespace
     * @param  string  $group
     * @param  string  $locale
     * @return bool
     */
    protected function isLoaded($namespace, $group, $locale)
    {
        return isset($this->loaded[$namespace][$group][$locale]);
    }

    /**
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function addNamespace($namespace, $hint)
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    /**
     * @param  string  $path
     * @return void
     */
    public function addJsonPath($path)
    {
        $this->loader->addJsonPath($path);
    }

    /**
     * @param  string  $key
     * @return array
     */
    public function parseKey($key)
    {
        $segments = parent::parseKey($key);

        if (is_null($segments[0])) {
            $segments[0] = '*';
        }

        return $segments;
    }

    /**
     * @param  string|null  $locale
     * @return array
     */
    protected function localeArray($locale)
    {
        return array_filter([$locale ?: $this->locale, $this->fallback]);
    }

    /**$
     * @return Selector
     */
    public function getSelector()
    {
        if (! isset($this->selector)) {
            $this->selector = new Selector;
        }

        return $this->selector;
    }

    /**
     * @return void
     */
    public function setSelector(Selector $selector)
    {
        $this->selector = $selector;
    }

    /**
     * @return Fileloader
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * @return string
     */
    public function locale()
    {
        return $this->getLocale();
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param  string  $locale
     * @return void
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return string
     */
    public function getFallback()
    {
        return $this->fallback;
    }

    /**
     * @param $fallback
     * @return Trad
     */
    public function setFallback($fallback): self
    {
        $this->fallback = $fallback;

        return $this;
    }
}
