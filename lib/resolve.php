<?php

namespace Octo;

class Resolve
{
    /**
     * @var array
     */
    protected $parsed = [];

    /**
     * @param  string  $key
     * @return array
     */
    public function parseKey($key)
    {
        if (isset($this->parsed[$key])) {
            return $this->parsed[$key];
        }

        if (strpos($key, '::') === false) {
            $segments = explode('.', $key);

            $parsed = $this->parseBasicSegments($segments);
        } else {
            $parsed = $this->parseNamespacedSegments($key);
        }

        return $this->parsed[$key] = $parsed;
    }

    /**
     * @param  array  $segments
     * @return array
     */
    protected function parseBasicSegments(array $segments)
    {
        $group = $segments[0];

        $item = count($segments) === 1
            ? null
            : implode('.', array_slice($segments, 1))
        ;

        return [null, $group, $item];
    }

    /**
     * @param  string  $key
     * @return array
     */
    protected function parseNamespacedSegments($key)
    {
        list($namespace, $item) = explode('::', $key);

        $itemSegments = explode('.', $item);

        $groupAndItem = array_slice(
            $this->parseBasicSegments($itemSegments), 1
        );

        return array_merge([$namespace], $groupAndItem);
    }

    /**
     * @param  string  $key
     * @param  array   $parsed
     * @return void
     */
    public function setParsedKey($key, $parsed)
    {
        $this->parsed[$key] = $parsed;
    }
}
