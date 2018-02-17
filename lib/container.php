<?php
namespace Octo;


class Container extends Ghost
{
    /**
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        parent::__construct($values, 'container');
    }

    /**
     * @return Container
     *
     * @throws \ReflectionException
     */
    protected static function called()
    {
        return instanciator()->singleton(get_called_class());
    }

    public function build($class, $args = [], $single = true)
    {
        if (!isset($this->{$class}) || false === $single) {
            $this->{$class} = instanciator()->make($class, $args);
        }

        return $this->{$class};
    }

    /**
     * @param string $m
     * @param array $a
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function __callStatic(string $m, array $a)
    {
        return  call_user_func_array([static::called(), $m], $a);
    }
}
