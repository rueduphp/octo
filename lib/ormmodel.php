<?php
    namespace Octo;

    use Illuminate\Database\Eloquent\Model as EloquentModel;

    class Ormmodel extends EloquentModel implements FastModelInterface
    {
        protected $guarded  = [];

        public function __destruct()
        {
            $class = get_called_class();
            actual('Ormmodel.' . $class, null);
        }

        public static function __callStatic($m, $a)
        {
            return (new static)->$m(...$a);
        }

        public function __call($m, $a)
        {
            $class      = get_called_class();
            $instance   = actual('Ormmodel.' . $class);
////
            if (!$instance) {
                actual('Ormmodel.' . $class, (new Orm)->eloquent($class));
            }

            if (in_array($m, ['increment', 'decrement'])) {
                return $this->$m(...$a);
            }

            return $this->newQuery()->$m(...$a);
        }

        /**
         * @return FastFactory
         */
        public static function factory()
        {
            $class = get_called_class();

            return new FastFactory($class, new $class());
        }
    }
