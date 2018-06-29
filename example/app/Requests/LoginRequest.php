<?php

namespace App\Requests;

use Octo\FastRequest;

class LoginRequest extends FastRequest
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return bool
     */
    protected function can(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    protected function rules(): array
    {
         return [];
    }
}
