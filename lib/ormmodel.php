<?php
    namespace Octo;

    use Illuminate\Database\Eloquent\Model as EloquentModel;

    class Ormmodel extends EloquentModel implements FastModelInterface
    {
        protected $guarded  = [];

        /**
         * @throws \ReflectionException
         */
        public function __destruct()
        {
            $class = get_called_class();
            actual('Ormmodel.' . $class, null);
        }

        /**
         * @param string $m
         * @param array $a
         * @return mixed
         * @throws \ReflectionException
         */
        public static function __callStatic($m, $a)
        {
            $callable = [gi()->make(get_called_class()), $m];

            $params = array_merge($callable, $a);

            return gi()->call(...$params);
        }

        /**
         * @param string $m
         * @param array $a
         * @return mixed|null
         * @throws \ReflectionException
         */
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

            $callable = [$this->newQuery(), $m];

            $params = array_merge($callable, $a);

            return gi()->call(...$params);
        }

        /**
         * @return FastFactory
         * @throws \ReflectionException
         */
        public static function factory()
        {
            return new FastFactory($class = get_called_class(), gi()->make($class));
        }
    }
