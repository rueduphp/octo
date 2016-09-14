<?php
    namespace Octo;

    class ##class## extends \Octo\Mongo\Model
    {
        /* Make indices of model */
        public function checkIndices()
        {
            $db = $this->_db;

            $collection = $db->getCollection();
            $collection->ensureIndex(['id' => 1]);
        }

        /* Make hooks of model */
        public function _hooks()
        {
            $obj = $this;
            // $this->_hooks['beforeCreate'] = function () use ($obj) {};
            // $this->_hooks['beforeRead'] = ;
            // $this->_hooks['beforeUpdate'] = ;
            // $this->_hooks['beforeDelete'] = ;
            // $this->_hooks['afterCreate'] = ;
            // $this->_hooks['afterRead'] = ;
            // $this->_hooks['afterUpdate'] = ;
            // $this->_hooks['afterDelete'] = ;
            // $this->_hooks['validate'] = function () use ($data) {
            //     return true;
            // };
        }
    }
