<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Octo\Ultimate;

trait Translatable
{
    /**
     * @param $key
     * @return string
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function getAttributeValue($key)
    {
        if (!$this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->getTranslation($key, $this->getLocale());
    }

    /**
     * @param $key
     * @param $value
     * @return Translatable
     * @throws \ReflectionException
     */
    public function setAttribute($key, $value)
    {
        if (!$this->isTranslatableAttribute($key) || is_array($value)) {
            return parent::setAttribute($key, $value);
        }

        return $this->setTranslation($key, $this->getLocale(), $value);
    }

    /**
     * @param string $key
     * @param string $locale
     * @return string
     * @throws \Exception
     */
    public function translate(string $key, string $locale = '')
    {
        return $this->getTranslation($key, $locale);
    }

    /***
     * @param string $key
     * @param string $locale
     * @param bool $useFallbackLocale
     * @return string
     * @throws \Exception
     */
    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true)
    {
        $locale = $this->normalizeLocale($key, $locale, $useFallbackLocale);

        $translations = $this->getTranslations($key);

        $translation = $translations[$locale] ?? '';

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $translation);
        }

        return $translation;
    }

    /**
     * @param string $key
     * @param string $locale
     * @return mixed
     */
    public function getTranslationWithFallback(string $key, string $locale)
    {
        return $this->getTranslation($key, $locale, true);
    }

    /**
     * @param string $key
     * @param string $locale
     * @return mixed
     */
    public function getTranslationWithoutFallback(string $key, string $locale)
    {
        return $this->getTranslation($key, $locale, false);
    }

    /**
     * @param null $key
     * @return array
     * @throws \Exception
     */
    public function getTranslations($key = null) : array
    {
        if ($key !== null) {
            $this->guardAgainstUntranslatableAttribute($key);

            return json_decode($this->getAttributes()[$key] ?? '' ?: '{}', true) ?: [];
        }

        return array_reduce($this->getTranslatableAttributes(), function ($result, $item) {
            $result[$item] = $this->getTranslations($item);

            return $result;
        });
    }

    /**
     * @param string $key
     * @param string $locale
     * @param $value
     * @return $this
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function setTranslation(string $key, string $locale, $value): self
    {
        $this->guardAgainstUntranslatableAttribute($key);

        $translations = $this->getTranslations($key);

        if ($this->hasSetMutator($key)) {
            $method = 'set' . Str::studly($key) . 'Attribute';
            $this->{$method}($value, $locale);
            $value = $this->attributes[$key];
        }

        $translations[$locale] = $value;

        $this->attributes[$key] = $this->asJson($translations);

        return $this;
    }

    /**
     * @param string $key
     * @param array $translations
     * @return $this
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function setTranslations(string $key, array $translations)
    {
        $this->guardAgainstUntranslatableAttribute($key);

        foreach ($translations as $locale => $translation) {
            $this->setTranslation($key, $locale, $translation);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param string $locale
     *
     * @return $this
     */
    public function forgetTranslation(string $key, string $locale)
    {
        $translations = $this->getTranslations($key);

        unset($translations[$locale]);

        $this->setAttribute($key, $translations);

        return $this;
    }

    /**
     * @param string $locale
     */
    public function forgetAllTranslations(string $locale)
    {
        lcoll($this->getTranslatableAttributes())->each(function (string $attribute) use ($locale) {
            $this->forgetTranslation($attribute, $locale);
        });
    }

    /**
     * @param string $key
     * @return array
     */
    public function getTranslatedLocales(string $key): array
    {
        return array_keys($this->getTranslations($key));
    }

    /**
     * @param string $key
     * @return bool
     */
    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes());
    }

    /**
     * @param string $key
     * @throws \Exception
     */
    protected function guardAgainstUntranslatableAttribute(string $key)
    {
        if (!$this->isTranslatableAttribute($key)) {
            throw new \Exception("$key is not translatable");
        }
    }

    /**
     * @param string $key
     * @param string $locale
     * @param bool $useFallbackLocale
     * @return string
     */
    protected function normalizeLocale(string $key, string $locale, bool $useFallbackLocale): string
    {
        if (in_array($locale, $this->getTranslatedLocales($key))) {
            return $locale;
        }

        if (!$useFallbackLocale) {
            return $locale;
        }

        if (!is_null($fallbackLocale = \Octo\Facades\Config::get('app.fallback_locale'))) {
            return $fallbackLocale;
        }

        return $locale;
    }

    /**
     * @param null|Ultimate $session
     * @return string
     * @throws \ReflectionException
     */
    protected function getLocale(?Ultimate $session = null): string
    {
        return locale($session);
    }

    /**
     * @return array
     */
    public function getTranslatableAttributes(): array
    {
        return is_array($this->translatable)
            ? $this->translatable
            : [];
    }

    /**
     * @return array
     */
    public function getCasts(): array
    {
        return array_merge(
            parent::getCasts(),
            array_fill_keys($this->getTranslatableAttributes(), 'array')
        );
    }
}