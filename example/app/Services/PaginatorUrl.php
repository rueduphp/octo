<?php

namespace App\Services;

class PaginatorUrl
{
    /**
     * @var Paginate
     */
    protected $paginator;

    /**
     * @param Paginate $paginator
     */
    public function __construct(Paginate $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * @param Paginate $paginator
     * @param int $onEachSide
     * @return array
     */
    public static function make(Paginate $paginator, $onEachSide = 3)
    {
        return (new static($paginator))->get($onEachSide);
    }

    /**
     * @param int $onEachSide
     * @return array
     */
    public function get($onEachSide = 3)
    {
        if ($this->paginator->lastPage() < ($onEachSide * 2) + 6) {
            return $this->getSmallSlider();
        }

        return $this->getUrlSlider($onEachSide);
    }

    /**
     * @return array
     */
    protected function getSmallSlider()
    {
        return [
            'first'  => $this->paginator->getUrlRange(1, $this->lastPage()),
            'slider' => null,
            'last'   => null,
        ];
    }

    /**
     * @param $onEachSide
     * @return array
     */
    protected function getUrlSlider($onEachSide)
    {
        $window = $onEachSide * 2;

        if (! $this->hasPages()) {
            return ['first' => null, 'slider' => null, 'last' => null];
        }

        if ($this->currentPage() <= $window) {
            return $this->getSliderTooCloseToBeginning($window);
        } elseif ($this->currentPage() > ($this->lastPage() - $window)) {
            return $this->getSliderTooCloseToEnding($window);
        }

        return $this->getFullSlider($onEachSide);
    }

    /**
     * @param $window
     * @return array
     */
    protected function getSliderTooCloseToBeginning($window)
    {
        return [
            'first' => $this->paginator->getUrlRange(1, $window + 2),
            'slider' => null,
            'last' => $this->getFinish(),
        ];
    }

    /**
     * @param $window
     * @return array
     */
    protected function getSliderTooCloseToEnding($window)
    {
        $last = $this->paginator->getUrlRange(
            $this->lastPage() - ($window + 2),
            $this->lastPage()
        );

        return [
            'first' => $this->getStart(),
            'slider' => null,
            'last' => $last,
        ];
    }

    /**
     * @param $onEachSide
     * @return array
     */
    protected function getFullSlider($onEachSide)
    {
        return [
            'first'  => $this->getStart(),
            'slider' => $this->getAdjacentUrlRange($onEachSide),
            'last'   => $this->getFinish(),
        ];
    }

    /**
     * @param $onEachSide
     * @return array
     */
    public function getAdjacentUrlRange($onEachSide)
    {
        return $this->paginator->getUrlRange(
            $this->currentPage() - $onEachSide,
            $this->currentPage() + $onEachSide
        );
    }

    /**
     * @return array
     */
    public function getStart()
    {
        return $this->paginator->getUrlRange(1, 2);
    }

    /**
     * @return array
     */
    public function getFinish()
    {
        return $this->paginator->getUrlRange(
            $this->lastPage() - 1,
            $this->lastPage()
        );
    }

    /**
     * @return bool
     */
    public function hasPages()
    {
        return $this->paginator->lastPage() > 1;
    }

    /**
     * @return mixed
     */
    protected function currentPage()
    {
        return $this->paginator->currentPage();
    }

    /**
     * @return int
     */
    protected function lastPage()
    {
        return $this->paginator->lastPage();
    }
}
