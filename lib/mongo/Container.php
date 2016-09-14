<?php
    namespace Octo\Mongo;

    class Container implements \ArrayAccess, \Countable, \IteratorAggregate
    {
        private $_data;

        public function __construct()
        {
            $this->_data = [];
        }

        public function hasDefinition($name)
        {
            return isset($this->_data[$name]);
        }

        public function setDefinition($name, Definition $definition)
        {
            $this->_data[$name] = $definition;
        }

        public function setData(array $_data)
        {
            $this->_data = [];

            foreach ($_data as $name => $definition) {
                $this->setDefinition($name, $definition);
            }
        }

        public function getDefinition($name)
        {
            if (!$this->hasDefinition($name)) {
                throw new \InvalidArgumentException(sprintf('The definition "%s" does not exists.', $name));
            }

            return $this->_data[$name];
        }

        public function getData()
        {
            return $this->_data;
        }

        public function removeDefinition($name)
        {
            if (!$this->hasDefinition($name)) {
                throw new \InvalidArgumentException(sprintf('The definition "%s" does not exists.', $name));
            }

            unset($this->_data[$name]);
        }

        public function clearData()
        {
            $this->_data = [];
        }

        public function offsetExists($name)
        {
            return $this->hasDefinition($name);
        }

        public function offsetSet($name, $definition)
        {
            $this->setDefinition($name, $definition);
        }

        public function offsetGet($name)
        {
            return $this->getDefinition($name);
        }

        public function offsetUnset($name)
        {
            $this->removeDefinition($name);
        }

        public function count()
        {
            return count($this->_data);
        }

        public function getIterator()
        {
            return new \ArrayIterator($this->_data);
        }
    }
