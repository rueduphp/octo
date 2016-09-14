<?php

    namespace Octo\Cron;

    interface FieldInterface
    {

        public function isSatisfiedBy(\DateTime $date, $value);

        public function increment(\DateTime $date, $invert = false);

        public function validate($value);
    }
