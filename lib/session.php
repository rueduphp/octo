<?php
    namespace Octo;

    class Session
    {
        private $_name;

        public function __construct($name = null)
        {
            $name = is_null($name) ? 'web' : $name;

            $this->_name = Strings::urlize($name, '.');

            $this->check();
        }

        private function check()
        {
            if (!session_id()) {
                session_start();
            }

            if (!isset($_SESSION['infos_' . session_id()])) {
                $_SESSION['infos_' . session_id()] = [];
            }

            if (!isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]            = [];
                $_SESSION['infos_' . session_id()][$this->_name]['start']   = time();
                $_SESSION['infos_' . session_id()][$this->_name]['end']     = time() + Config::get('session.duration', 3600);
            } else {
                if (isset($_SESSION['infos_' . session_id()][$this->_name]['end'])) {
                    if (time() > $_SESSION['infos_' . session_id()][$this->_name]['end']) {
                        session_destroy();

                        return new self($this->_name);
                    } else {
                        $_SESSION['infos_' . session_id()][$this->_name]['end']     = time() + Config::get('session.duration', 3600);
                    }
                }
            }
        }

        public function starting()
        {
            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                return $_SESSION['infos_' . session_id()][$this->_name]['start'];
            }

            return null;
        }

        public function ending()
        {
            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                return $_SESSION['infos_' . session_id()][$this->_name]['end'];
            }

            return null;
        }

        public function __get($key)
        {
            $this->check();

            return isAke($_SESSION, $this->name . '.' . $key, null);
        }

        public function __set($key, $value)
        {
            $this->check();

            if ($key == '_name') {
                $this->_name = $value;

                return $this;
            }

            $_SESSION[$this->_name . '.' . $key] = $value;

            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
            }

            return $this;
        }

        public function __isset($key)
        {
            $this->check();

            $dummy = sha1(__file__);

            return $dummy !== isAke($_SESSION, $this->_name . '.' . $key, $dummy);
        }

        public function __unset($key)
        {
            $this->check();

            unset($_SESSION[$this->_name . '.' . $key]);

            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
            }

            return $this;
        }

        public function get($key, $default = null)
        {
            $this->check();

            return isAke($_SESSION, $this->_name . '.' . $key, $default);
        }

        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return $new;
        }

        public function has($key)
        {
            $this->check();

            $check = sha1(__file__);

            return $check != isAke($_SESSION, $this->_name . '.' . $key, $check);
        }

        public function getOr($key, callable $c)
        {
            if (!$this->has($key)) {
                $value = $c();

                $this->set($key, $value);

                return $value;
            }

            return $this->get($key);
        }

        public function put($key, $value)
        {
            $this->check();

            $_SESSION[$this->_name . '.' . $key] = $value;

            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
            }

            return $this;
        }

        public function set($key, $value)
        {
            return $this->put($key, $value);
        }

        public function flash($key, $val = null)
        {
            $this->check();
            $key = "flash_{$key}";

            if ($val != null) {
                $this->set($key, $val);

                if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                    $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
                }
            } else {
                $val = $this->get($key);
                $this->remove($key);
            }

            return $val != null ? $this : $val;
        }

        public function __call($m, $a)
        {
            $this->check();

            if (fnmatch('get*', $m)) {
                $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                $key                = Strings::lower($uncamelizeMethod);
                $args               = [$key];

                if (!empty($a)) {
                    $args[] = current($a);
                }

                return call_user_func_array([$this, 'get'], $args);
            } elseif (fnmatch('set*', $m)) {
                $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                $key                = Strings::lower($uncamelizeMethod);

                return $this->set($key, current($a));
            } elseif (fnmatch('forget*', $m)) {
                $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 6)));
                $key                = Strings::lower($uncamelizeMethod);
                $args               = [$key];

                return call_user_func_array([$this, 'erase'], $args);
            }
        }

        public function forget($key)
        {
            return $this->erase($key);
        }

        public function remove($key)
        {
            return $this->erase($key);
        }

        public function delete($key)
        {
            return $this->erase($key);
        }

        public function erase($key = null)
        {
            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
            }

            $this->check();

            if (!empty($key)) {
                unset($_SESSION[$this->_name . '.' . $key]);

                return !isset($_SESSION[$this->_name . '.' . $key]);
            } else {
                foreach ($this->all() as $k => $v) {
                    unset($_SESSION[$this->_name . '.' . $k]);
                }
            }

            return empty($this->all());
        }

        public function getSessionId()
        {
            return session_id();
        }

        public function regenerate()
        {
            return session_regenerate_id();
        }

        public function destroy()
        {
            $_SESSION =[];

            return session_destroy();
        }

        protected function generateSessionId()
        {
            return sha1(
                uniqid('', true) .
                token() .
                microtime(true)
            );
        }

        public function isValidId($id)
        {
            return is_string($id) && preg_match('/^[a-f0-9]{40}$/', $id);
        }

        public function fill(array $data)
        {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        public function fillInfos(array $data)
        {
            foreach ($data as $k => $v) {
                if ($k != 'start' && $k != 'end') {
                    $_SESSION['infos_' . session_id()][$this->_name][$k] = $v;
                }
            }

            return $this;
        }

        public function addInfo($k, $v)
        {
            if ($k != 'start' && $k != 'end') {
                $_SESSION['infos_' . session_id()][$this->_name][$k] = $v;
            }

            return $this;
        }

        public function retrieveInfo($k, $v = null)
        {
            return isAke($_SESSION['infos_' . session_id()][$this->_name], $k, $v);
        }

        public function endAt($time)
        {
            if ($time instanceof Time) {
                $time = $time->timestamp;
            }

            $_SESSION['infos_' . session_id()][$this->_name]['end'] = $time;
        }

        public function all()
        {
            $keys = Arrays::pattern($_SESSION, $this->_name . '.*');

            $clean = [];

            foreach ($keys as $k => $v) {
                $clean[str_replace($this->_name . '.', '', $k)] = $v;
            }

            return $clean;
        }

        public function toArray()
        {
            return $this->all();
        }

        public function toCollection()
        {
            return coll($this->all());
        }

        public function drop()
        {
            return $this->erase();
        }

        public function count()
        {
            return count(Arrays::pattern($_SESSION, $this->_name . '.*'));
        }

        public static function __callStatic($m, $a)
        {
            $uncamelized = Strings::uncamelize($m);

            if (fnmatch('*_*', $uncamelized)) {
                list($ns, $m) = explode('_', $uncamelized, 2);
                $session = new self($ns);
            } else {
                $session = new self;
            }

            return call_user_func_array([$session, $m], $a);
        }
    }
