<?php
namespace Octo;

class Rows
{
    /**
     * @var string
     */
    private $id;

    /**
     * @param array $rows
     */
    public function __construct(array $rows = [])
    {
        $this->id = hash(token());

        Registry::set('rc.' . $this->id, coll($rows));
    }

    /**
     * @param string $method
     * @param array $paramas
     *
     * @return mixed
     */
    public function __call(string $method, array $paramas)
    {
        /** @var Collection $collection */
        $collection = Registry::get('rc.' . $this->id);

        return $collection->{$method}(...$paramas);
    }
}