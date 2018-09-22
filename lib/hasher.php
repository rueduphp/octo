<?php
namespace Octo;

class Hasher
{
    /** @var int  */
    protected $rounds = 10;

    /**
     * @param string $value
     * @param array $options
     * @return bool|string
     */
    public function make(string $value, array $options = [])
    {
        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Bcrypt hashing not supported.');
        }

        return $hash;
    }

    /**
     * @param string $value
     * @param string $hashedValue
     * @return bool
     */
    public function check(string $value, string $hashedValue)
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        if (strlen($hashedValue) <= 32) { // if the hash is still md5
            return $hashedValue === md5($value);
        }

        return password_verify($value, $hashedValue);
    }

    public function needsRehash($hashedValue, array $options = [])
    {
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);
    }

    /**
     * @param $rounds
     * @return Hasher
     */
    public function setRounds($rounds): self
    {
        $this->rounds = (int) $rounds;

        return $this;
    }

    /**
     * @param array $options
     * @return int|mixed
     */
    protected function cost(array $options = [])
    {
        return $options['rounds'] ?? $this->rounds;
    }
}
