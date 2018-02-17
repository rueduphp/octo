<?php
    namespace Octo;

    class ActiveRecord extends Objet
    {
        /**
         * @var null|Octal
         */
        protected $entity;

        /**
         * @param $data
         * @param null|Octal $entity
         */
        public function __construct($data, ?Octal $entity = null)
        {
            parent::__construct($data);

            $this->entity = $entity;
        }
    }
