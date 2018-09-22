<?php

namespace App\Traits;

use BadMethodCallException;
use Illuminate\Support\HtmlString;

trait Viewvable
{
    /**
     * @var array
     */
    protected static $components = [];

    /**
     * @param       $name
     * @param       $view
     * @param array $signature
     *
     * @return void
     */
    public static function component($name, $view, array $signature)
    {
        static::$components[$name] = compact('view', 'signature');
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public static function hasComponent($name)
    {
        return isset(static::$components[$name]);
    }

    /**
     * @param        $name
     * @param  array $arguments
     *
     * @return \Illuminate\Contracts\View\View
     */
    protected function renderComponent($name, array $arguments)
    {
        $component = static::$components[$name];
        $data = $this->getComponentData($component['signature'], $arguments);

        return new HtmlString(
            $this->view->make($component['view'], $data)->render()
        );
    }

    /**
     * @param  array $signature
     * @param  array $arguments
     *
     * @return array
     */
    protected function getComponentData(array $signature, array $arguments)
    {
        $data = [];

        $i = 0;
        foreach ($signature as $variable => $default) {
            if (is_numeric($variable)) {
                $variable = $default;
                $default = null;
            }

            $data[$variable] = array_get($arguments, $i, $default);

            ++$i;
        }

        return $data;
    }

    /**
     * @param  string $method
     * @param  array  $parameters
     *
     * @return \Illuminate\Contracts\View\View|mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (static::hasComponent($method)) {
            return $this->renderComponent($method, $parameters);
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
    }
}
