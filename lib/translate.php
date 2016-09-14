<?php
    namespace Octo;

    class Translate
    {
        private $sentences;

        public function __construct(Dictionary $dico)
        {
            if ($dico->hasSegment(lng())) {
                $this->sentences = $dico->getSegment(lng());
            } else {
                $this->sentences = $dico->getSegment(Config::get('default.language', 'en'));
            }
        }

        public function get($id, $default = null)
        {
            return $this->sentences->get($id, $default);
        }
    }
