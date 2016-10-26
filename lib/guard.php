<?php
    namespace Octo;

    class Guard
    {
        private $ns;
        private static $policies = [];

        public function __construct($ns = 'core', array $policies = [])
        {
            $this->ns            = $ns;
            self::$policies[$ns] = $policies;

            $config = realpath(path('app') . '/config/guard.php');

            if (is_file($config) && is_readable($config)) {
                $guards = include $config;

                self::$policies[$ns] = array_merge(self::$policies, $guards);
            }
        }

        public function define($name, callable $cb)
        {
            $row = [$name => $cb];

            self::$policies[$this->ns] = $row;

            return $this;
        }

        public function allows($name, $args = [])
        {
            $cb = isAke(self::$policies[$this->ns], $name, null);

            if (is_callable($cb)) {
                if (!is_array($args)) {
                    $args = [$args];
                }

                $args = array_merge([$this->user()], $args);

                return call_user_func_array($cb, $args);
            }

            return false;
        }

        public function denies($name, $args = [])
        {
            return !$this->allows($name, $args);
        }

        public function user($k = null, $d = null)
        {
            $user = lib('user')->get();

            if (is_null($k)) {
                return $user;
            }

            return isAke($user, $k, $d);
        }

        function isLogged()
        {
            $user = session('web')->getUser();

            return !is_null($user);
        }

        function isNotLogged()
        {
            return !$this->isLogged;
        }

        function guest()
        {
            return !$this->isLogged;
        }

        public function getAccountByIdentifier($identifier = null)
        {
            if (empty($identifier)) {
                return $this->user();
            }

            return System::Account()->firstByForever($identifier);
        }

        public function getVisitorByIdentifier($identifier = null)
        {
            if (empty($identifier)) {
                return $this->user();
            }

            return System::Visitor()->firstByForever($identifier);
        }

        public function login($login, $password)
        {
            if (!$this->isLogged()) {
                $user = System::Account()->firstByLogin($login);

                if (lib('hasher')->check($password, $user['password'])) {
                    session('web')->setUser($user);

                    $controller = Registry::get('octo.controller');

                    if (is_object($controller)) {
                        $controller->auth = true;
                    }

                    return true;
                }
            }

            return false;
        }

        public function logout()
        {
            session('log')->erase('user');
            session('web')->erase('user');

            $controller = Registry::get('octo.controller');

            if (is_object($controller)) {
                $controller->auth = false;
            }

            return $this;
        }

        public function loginById($id)
        {
            $user = System::Account()->findOrFail((int) $id);

            session('web')->setUser($user->toArray());

            $controller = Registry::get('octo.controller');

            if (is_object($controller)) {
                $controller->auth = true;
            }

            return $this;
        }

        public function loginByLogin($login)
        {
            $user = System::Account()->firstByLogin((string) $login);

            if (!$user) {
                throw new Exception("User with login $login does not exist.");
            }

            session('web')->setUser($user);

            $controller = Registry::get('octo.controller');

            if (is_object($controller)) {
                $controller->auth = true;
            }

            return $this;
        }

        public function loginByEmail($email)
        {
            $user = System::Account()->firstByEmail((string) $email);

            if (!$user) {
                throw new Exception("User with email $email does not exist.");
            }

            session('web')->setUser($user);

            $controller = Registry::get('octo.controller');

            if (is_object($controller)) {
                $controller->auth = true;
            }

            return $this;
        }
    }
