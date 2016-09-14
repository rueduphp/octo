<?php
    namespace Octo;

    class Role
    {
        protected $name;

        public function __construct($name = 'admin')
        {
            $this->name = $name;
        }

        public function allow($resource)
        {
            $abilities = Registry::get('abilities.' . $this->name, []);

            $abilities[$resource] = true;

            Registry::set('abilities.' . $this->name, $abilities);

            return $this;
        }

        public function disallow($resource)
        {
            $abilities = Registry::get('abilities.' . $this->name, []);

            unset($abilities[$resource]);

            Registry::set('abilities.' . $this->name, $abilities);

            return $this;
        }

        public function can($resource)
        {
            return isAke(Registry::get('abilities.' . $this->name, []), $resource, false);
        }

        public function cannot($resource)
        {
            return !$this->can($resource);
        }

        public function cant($resource)
        {
            return !$this->can($resource);
        }

        public function copy($role)
        {
            Registry::set('abilities.' . $this->name, Registry::get('abilities.' . $role, []));

            return $this;
        }

        public function flush()
        {
            Registry::set('abilities.' . $this->name, []);

            return $this;
        }

        public function inherit($role)
        {
            Registry::set('abilities.' . $role, Registry::get('abilities.' . $this->name, []));

            return new self($role);
        }
    }
