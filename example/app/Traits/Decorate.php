<?php

namespace App\Traits;

use Exception;

trait Decorate
{
    /**
     * @var mixed
     */
    protected $decoratorInstance;

    /**
     * @return mixed
     * @throws Exception
     */
    public function decorates()
    {
        if (!isset($this->decorator) || !$this->decorator || !class_exists($this->decorator)) {
            $called = get_called_class();
            $decorator = str_replace('\\Models\\', '\\Decorators\\', $called);

            if (class_exists($decorator)) {
                $this->decorator = $decorator;
            } else {
                throw new Exception('Please set the d$ecorator property to your decorator path.');
            }
        }

        if (!$this->decoratorInstance) {
            $this->decoratorInstance = new $this->decorator($this);
        }

        return $this->decoratorInstance;
    }
}
