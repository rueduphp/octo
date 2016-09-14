<?php
    namespace Octo;

    class Hasher
    {
        protected $rounds = 10;

        public function make($value, array $options = [])
        {
            $cost = isset($options['rounds']) ? $options['rounds'] : $this->rounds;

            $hash = password_hash($value, PASSWORD_BCRYPT, ['cost' => $cost]);

            if ($hash === false) {
                exception('Hasher', 'Bcrypt hashing not supported.');
            }

            return $hash;
        }

        public function check($value, $hashedValue, array $options = [])
        {
            if (!strlen($hashedValue)) {
                return false;
            }

            return password_verify($value, $hashedValue);
        }

        public function needsRehash($hashedValue, array $options = [])
        {
            $cost = isset($options['rounds']) ? $options['rounds'] : $this->rounds;

            return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, ['cost' => $cost]);
        }

        public function setRounds($rounds)
        {
            $this->rounds = (int) $rounds;

            return $this;
        }
    }
