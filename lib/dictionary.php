<?php
namespace Octo;

class Dictionary
{
    /**
     * @var array|mixed
     */
    private $data = [], $segment = [];

    /**
     * @param string $file
     */
    public function __construct(string $file)
    {
        $this->data = include $file;
    }

    /**
     * @param string $k
     *
     * @return bool
     */
    public function hasSegment(string $k): bool
    {
        $segment = aget($this->data, $k, []);

        return !empty($segment);
    }

    /**
     * @param string $k
     *
     * @return Dictionary
     */
    public function getSegment(string $k): self
    {
        $this->segment = aget($this->data, $k, []);

        return $this;
    }

    /**
     * @param string $k
     * @param null|string $d
     *
     * @return null|string
     */
    public function get(string $k, ?string $d = null): ?string
    {
        return aget($this->segment, $k, $d);
    }

    /**
     * @param string $k
     *
     * @return bool
     */
    public function has(string $k): bool
    {
        return 'octodummy' !== isAke($this->segment, $k, 'octodummy');
    }
}
