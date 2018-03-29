<?php
    namespace Octo;

    class Acl
    {
        private static $instances   = [];
        private static $rights      = [];
        private static $aliases     = [];

        private $ns;

        public function __construct($config = null, $ns = 'core')
        {
            self::$rights[$ns]  = [];
            self::$aliases[$ns] = [];

            if (is_array($config)) {
                $this->ns = $ns;

                foreach ($config as $roleName => $role) {
                    $parent = isAke($role, 'parent', null);

                    $this->addRole($roleName, $parent);

                    $resources = isAke($role, 'resources', '*');

                    if ('*' == $resources) {
                        $this->addResource($roleName, '*', '*');
                    } else {
                        foreach ($resources as $resource => $actions) {
                            $this->addResource($roleName, $resource, $actions);
                        }
                    }
                }

                self::$instances[$ns] = $this;
            }
        }

        public static function getInstance($ns = 'core', $config = [])
        {
            if (is_null($config)) {
                if (isset(self::$instances[$ns])) {
                    return self::$instances[$ns];
                }
            }

            if (is_string($config)) {
                if (is_file($config)) {
                    $config = include($config);
                }
            }

            if (!is_array($config)) {
                throw new Exception('The configuration is not correct.');
            }

            if (empty($config)) {
                $config = null;
            }

            if (!isset(self::$instances[$ns])) {
                self::$instances[$ns] = new self($config, $ns);
            }

            return self::$instances[$ns];
        }

        public function addRole($role, $parent = null)
        {
            if (!isset(self::$rights[$this->ns])) {
                self::$rights[$this->ns] = [];
            }

            $rights = isAke(self::$rights[$this->ns], $role, []);

            if (!is_null($parent)) {
                $rights = isAke(self::$rights[$this->ns], $parent, []);

                if (!isset(self::$aliases[$this->ns][$parent])) {
                    self::$aliases[$this->ns][$parent] = [];
                }

                if (!in_array($role, self::$aliases[$this->ns][$parent])) {
                    self::$aliases[$this->ns][$parent][] = $role;
                }
            }

            self::$rights[$this->ns][$role] = $rights;

            return $this;
        }

        public function addResource($role, $resource, $actions)
        {
            if (!is_array($actions)) {
                $actions = [$actions];
            }

            if (!isset(self::$aliases[$this->ns])) {
                self::$aliases[$this->ns] = [];
            }

            $roles = isAke(self::$aliases[$this->ns], $role, []);

            $roles[] = $role;

            foreach ($roles as $roleResource) {
                if (!isset(self::$rights[$this->ns][$roleResource])) {
                    self::$rights[$this->ns][$roleResource] = [];
                }

                foreach ($actions as $action) {
                    if (!isset(self::$rights[$this->ns][$roleResource][$resource])) {
                        self::$rights[$this->ns][$roleResource][$resource] = [];
                    }

                    self::$rights[$this->ns][$roleResource][$resource][] = $action;
                }
            }

            return $this;
        }

        public function check($role, $resource, $action)
        {
            $allRights = isAke(self::$rights[$this->ns][$role], '*', false);

            if ($allRights) {
                return true;
            }

            $rights = isAke(self::$rights[$this->ns][$role], $resource, []);

            if (!empty($rights)) {
                foreach ($rights as $right) {
                    if ($right === $action || $right === '*') {
                        return true;
                    }
                }
            }

            return false;
        }
    }
