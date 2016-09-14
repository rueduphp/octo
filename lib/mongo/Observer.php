<?php
    namespace Octo\Mongo;

    use Closure;
    use Octo\Exception;

    class Observer
    {
        private $model;

        public function __construct($model)
        {
            $this->model = $model;
        }

        public function set($event, $action)
        {
            if (!is_callable($action) || !$action instanceof Closure) {
                throw new Exception("The observer's set method requires a valid callback method.");
            }

            $this->model->_hooks[$event] = $action;

            return $this;
        }

        public function remove($event)
        {
            unset($this->model->_hooks[$event]);

            return $this;
        }

        public function forget($event)
        {
            return $this->remove($event);
        }

        public function get($event, $default = null)
        {
            return isAke($this->model->_hooks, $event, $default);
        }

        public function has($event)
        {
            $now = time();

            $callback = $this->get($event, $now);

            return $callback != $now;
        }

        public function fire($event, $args = [], $returnRes = false)
        {
            return $this->run($event, $args, $returnRes);
        }

        public function run($event, $args = [], $returnRes = false)
        {
            $cb = $this->get($event, false);

            if (false !== $cb && is_callable($cb)) {
                $args = array_merge([$this->model->actual()], $args);

                $res = call_user_func_array($cb, $args);

                if (true === $returnRes) {
                    return $res;
                }
            } else {
                throw new Exception("Event $event does not exist.");
            }

            return $this;
        }

        public function model()
        {
            return $this->model->actual();
        }

        public function __call($method, $args)
        {
            return $this->run($method, $args);
        }
    }
