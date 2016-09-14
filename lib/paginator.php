<?php
    namespace Octo;

    class Paginator
    {
        public $results;
        public $firstItemNumber;
        public $lastItemNumber;
        public $page;
        public $last;
        public $total;
        public $perPage;
        protected $appends;
        protected $appendage;
        protected $language;
        protected $dots = '<li class="dots disabled"><a href="#">...</a></li>';

        public function __construct($results, $page, $total, $perPage, $last)
        {
            $this->page         = $page;
            $this->last         = $last;
            $this->total        = $total;
            $this->results      = $results;
            $this->perPage      = $perPage;

            $this->lastItemNumber   = ($total < $page * $perPage) ? $total : $page * $perPage;
            $this->firstItemNumber  = $this->lastItemNumber - $perPage + 1;

            if (1 > $this->firstItemNumber) {
                $this->firstItemNumber = 1;
            }
        }

        public function getItemsByPage()
        {
            $offset = ($this->page - 1) * $this->perPage;
            return array_slice($this->results, $offset, $this->perPage);
        }

        public static function make($results, $total, $perPage)
        {
            $page = static::page($total, $perPage);

            $last = ceil($total / $perPage);

            return new static($results, $page, $total, $perPage, $last);
        }

        public static function page($total, $perPage)
        {
            $page = (null === request()->getCrudNumPage()) ? 1 : request()->getCrudNumPage();

            if (is_numeric($page) && $page > $last = ceil($total / $perPage)) {
                return ($last > 0) ? $last : 1;
            }

            return (static::valid($page)) ? $page : 1;
        }

        protected static function valid($page)
        {
            return $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
        }

        public function links($adjacent = 3)
        {
            if ($this->last <= 1) {
                return '';
            }

            if ($this->last < 7 + ($adjacent * 2)) {
                $links = $this->range(1, $this->last);
            } else {
                $links = $this->slider($adjacent);
            }

            $content = '<ul class="pagination">' . $this->previous('&larr;') . $links . $this->next('&rarr;') . '</ul>';

            return '<div class="pagination">' . Inflector::utf8($content) . '</div>';
        }

        public function slider($adjacent = 3)
        {
            $window = $adjacent * 2;

            if ($this->page <= $window)  {
                return $this->range(1, $window + 2).' '.$this->ending();
            } elseif ($this->page >= $this->last - $window) {
                return $this->beginning().' '.$this->range($this->last - $window - 2, $this->last);
            }

            $content = $this->range($this->page - $adjacent, $this->page + $adjacent);

            return $this->beginning() . ' ' . $content . ' ' . $this->ending();
        }

        public function previous($text = null)
        {
            $disabled = function($page) { return $page <= 1; };

            return $this->element(__FUNCTION__, $this->page - 1, $text, $disabled);
        }

        public function next($text = null)
        {
            $disabled = function($page, $last) { return $page >= $last; };

            return $this->element(__FUNCTION__, $this->page + 1, $text, $disabled);
        }

        protected function element($element, $page, $text, $disabled)
        {
            $class = "{$element}_page";

            if (is_null($text)) {
                $text = Lang::line("pagination.{$element}")->get($this->language);
            }

            if ($disabled($this->page, $this->last)) {
                return '<li' . Html::attributes(array('class'=>"{$class} disabled")).'><a href="#">' . Inflector::utf8($text) . '</a></li>';
            } else {
                return $this->link($page, $text, $class);
            }
        }

        protected function beginning()
        {
            return $this->range(1, 2) . ' ' . $this->dots;
        }

        protected function ending()
        {
            return $this->dots . ' ' . $this->range($this->last - 1, $this->last);
        }

        protected function range($start, $end)
        {
            $pages = array();

            for ($page = $start ; $page <= $end ; $page++) {
                if ($this->page == $page) {
                    $pages[] = '<li class="active"><a href="#">' . $page . '</a></li>';
                } else {
                    $pages[] = $this->link($page, $page, null);
                }
            }

            return implode(' ', $pages);
        }

        protected function link($page, $text, $class)
        {
            $query = '?page=' . $page . $this->appendage($this->appends);

            return '<li' . Html::attributes(array('class' => $class)).'><a href="#" onclick="paginationGoPage(' . $page . '); return false;">' . Inflector::utf8($text) . '</a></li>';
        }

        protected function appendage($appends)
        {
            if (!is_null($this->appendage)) {
                return $this->appendage;
            }

            if (count($appends) <= 0) {
                return $this->appendage = '';
            }

            return $this->appendage = '&' . http_build_query($appends);
        }

        public function appends($values)
        {
            $this->appends = $values;
            return $this;
        }

        public function speaks($language)
        {
            $this->language = $language;
            return $this;
        }
    }
