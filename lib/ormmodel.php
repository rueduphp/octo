<?php
    namespace Octo;

    class Ormmodel extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded  = [];

        public static function __callStatic($m, $a)
        {
            return (new static)->$m(...$a);
        }

        public function __call($m, $a)
        {
            $class      = get_called_class();
            $instance   = actual('Ormmodel.' . $class);

            if (!$instance) {
                actual('Ormmodel.' . $class, (new Orm)->eloquent($class));
            }

            if (in_array($m, ['increment', 'decrement'])) {
                return $this->$m(...$a);
            }

            return $this->newQuery()->$m(...$a);
        }
    }