<?php
    namespace Octo;

    class Closures
    {
        private static $callbacks = [];
        private $id;

        public function listen(\Closure $callback)
        {
            $ref  = new \ReflectionFunction($callback);
            $file = new \SplFileObject($ref->getFileName());
            $file->seek($ref->getStartLine() - 1);
            $content = '';

            while ($file->key() < $ref->getEndLine()) {
                $content .= $file->current();
                $file->next();
            }

            $id = sha1(
                json_encode(
                    array(
                        $content,
                        $ref->getStaticVariables(),
                        $ref->getFileName(),
                        $ref->getStartLine() - 1,
                        $ref->getEndLine()
                    )
                )
            );

            $this->id = $id;

            self::$callbacks[$id] = $callback;

            return $this;
        }

        public function fire($id)
        {
            $callback = isset(self::$callbacks[$id]) ? self::$callbacks[$id] : false;

            $args = func_get_args();

            array_shift($args);

            return !$callback ? false : call_user_func_array($callback, $args);
        }

        public function getId()
        {
            return $this->id;
        }

        public function serialize(\Closure $callback)
        {
            return $this->listen($callback)->getId();
        }

        public function unserialize($id)
        {
            $callback = isset(self::$callbacks[$id]) ? self::$callbacks[$id] : false;

            $args = func_get_args();

            array_shift($args);

            return !$callback ? false : call_user_func_array($callback, $args);
        }

        public function makeId(\Closure $callback)
        {
            $ref  = new \ReflectionFunction($callback);
            $file = new \SplFileObject($ref->getFileName());
            $file->seek($ref->getStartLine() - 1);
            $content = '';

            while ($file->key() < $ref->getEndLine()) {
                $content .= $file->current();
                $file->next();
            }

            return sha1(
                json_encode(
                    array(
                        $content,
                        $ref->getStaticVariables(),
                        $ref->getFileName(),
                        $ref->getStartLine() - 1,
                        $ref->getEndLine()
                    )
                )
            );
        }

        public function store($name, \Closure $callback)
        {
            $id = $this->makeId($callback);

            $ref  = new \ReflectionFunction($callback);
            $file = new \SplFileObject($ref->getFileName());
            $file->seek($ref->getStartLine() - 1);
            $content = '';

            while ($file->key() < $ref->getEndLine()) {
                $content .= $file->current();
                $file->next();
            }

            if (fnmatch('*function*', $content)) {
                list($dummy, $code) = explode('function', $content, 2);
                $code = 'function' . $code;

                $tab = explode('}', $code);
                $last = end($tab);

                $code = str_replace('}' . $last, '}', $code);
            }

            return Octal::SystemClosure()->firstOrCreate(['name' => $name, 'key' => $id])->setCode($code)->save();
        }

        public function fireStore($id, $args = [])
        {
            $row = Octal::SystemClosure()->findOrFail((int) $id);

            $closure = eval('return ' . $row->code . ';');

            if (is_callable($closure)) {
                $res = call_user_func_array($closure, $args);

                return $res;
            }

            throw new Exception('This closure does not exist.');
        }

        public function extract(callable $callback)
        {
            $ref  = new \ReflectionFunction($callback);
            $file = new \SplFileObject($ref->getFileName());
            $file->seek($ref->getStartLine() - 1);
            $content = '';

            while ($file->key() < $ref->getEndLine()) {
                $content .= $file->current();
                $file->next();
            }

            if (fnmatch('*function*', $content)) {
                list($dummy, $code) = explode('function', $content, 2);
                $code = 'function' . $code;

                $tab = explode('}', $code);
                $last = end($tab);

                $code = str_replace('}' . $last, '}', $code);

                return $code;
            }

            return null;
        }
    }
