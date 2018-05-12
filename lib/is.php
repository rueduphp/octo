<?php
namespace Octo;

class Is
{
    /**
     * @param string $name
     * @param array $arguments
     * @return bool
     * @throws \ReflectionException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        try {
            $i = gi()->make(Assert::class);
            $i::{$name}(...$arguments);

            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return bool
     * @throws \ReflectionException
     */
    public function __call(string $name, array $arguments)
    {
        try {
            $i = gi()->make(Assert::class);
            $i::{$name}(...$arguments);

            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}