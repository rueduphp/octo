<?php

namespace App\Requests;

use App\Services\Repository;
use Illuminate\Support\Pluralizer;
use Octo\Arrays;
use Octo\FastRequest;

class CrudRequest extends FastRequest
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
        return $this->getRepo()->can();
    }

    /**
     * @return array
     */
    protected function rules(): array
    {
        return $this->getRepo()->rules();
    }

    /**
     * @return Repository
     */
    protected function getRepo()
    {
        return repo(Pluralizer::singular($this->revealModel()));
    }

    /**
     * @return string
     */
    protected function revealModel()
    {
        $uri = $this->getUri();
        $parts = explode('/crud/', $uri);
        array_shift($parts);

        $model = current($parts);

        if (fnmatch('*/*', $model)) {
            return Arrays::first(explode('/', $model));
        }

        return $model;
    }
}
