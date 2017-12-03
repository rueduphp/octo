<?php
    namespace Octo;

    class ActiveRecord extends Objet
    {
        /**
         * @var null|Octal
         */
        protected $entity;

        public function __construct($data, ?Octal $entity)
        {
            parent::__construct($data);

            $this->entity = $entity;
        }
    }
