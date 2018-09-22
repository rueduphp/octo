<?php

namespace App\Services;

use Octo\Hasher;

class Password
{
    public function __construct()
    {
        $this->hasher = new Hasher;
    }

    /**
     * Create a hash (encrypt) of a plain text password.
     *
     * @param string $password Plain text user password to hash
     * @return string The hash string of the password
     */
    public function makeHash($password)
    {
        return $this->hasher->make(trim($password));
    }

    /**
     * @param $password
     * @param $hash
     * @return bool
     */
    public function check($password, $hash)
    {
        return $this->hasher->check($password, $hash);
    }
}
