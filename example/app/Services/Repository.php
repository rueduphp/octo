<?php
namespace App\Services;

class Repository
{
    /**
     * @return array
     */
    public function crud()
    {
        return [];
    }

    /**
     * @return bool
     */
    public function can(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [];
    }

    public function policies()
    {
        return null;
    }

    /**
     * @param string $name
     * @param $callback
     * @return Repository
     */
    public function addPolicy(string $name, $callback): self
    {
        trust()->policy($name, $callback);

        return $this;
    }
}