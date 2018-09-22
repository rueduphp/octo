<?php
namespace App\Traits;

use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Yaml;

trait Exportable
{
    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->__items);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->__items;
    }

    /**
     * @param  int $inline
     * @param  int $indent
     * @return string
     * @throws DumpException
     */
    public function toYml($inline = 3, $indent = 2)
    {
        return Yaml::dump($this->toArray(), $inline, $indent, true, false);
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
