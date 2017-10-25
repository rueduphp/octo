<?php
    namespace Octo;

    class ActiveRecord extends Object
    {
        /**
         * @var null|Octal
         */
        protected $entity;

        public function __construct($data, ?Octal $entity)
        {
            parent::__construct($data);

            $this->entity = $entity;

            $this->proxy();
        }

        public function proxy()
        {
            if (is_object($this->entity)) {
                $class = str_replace('\\', '_', get_class($this->entity)) . 'Proxy';

                actual('orm.proxy.' . $class, $this->entity);

                if (!class_exists($class)) {
                    $code = 'namespace Octo; class ' . $class . ' extends ActiveRecord {}';

                    eval($code);
                }
            }
        }
    }
