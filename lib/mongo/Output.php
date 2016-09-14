<?php
    namespace Octo\Mongo;

    class Output
    {
        private $dir;
        private $override;

        /**
         * Constructor.
         *
         * @param string $dir      The dir.
         * @param bool   $override The override. It indicate if override files (optional, false by default).
         *
         * @api
         */
        public function __construct($dir, $override = false)
        {
            $this->setDir($dir)->setOverride($override);
        }

        /**
         * Set the dir.
         *
         * @param $string $dir The dir.
         *
         * @api
         */
        public function setDir($dir)
        {
            $this->dir = $dir;

            return $this;
        }

        /**
         * Returns the dir.
         *
         * @return string The dir.
         *
         * @api
         */
        public function getDir()
        {
            return $this->dir;
        }

        /**
         * Set the override. It indicate if override files.
         *
         * @param bool $override The override.
         *
         * @api
         */
        public function setOverride($override)
        {
            $this->override = (bool) $override;

            return $this;
        }

        /**
         * Returns the override.
         *
         * @return bool The override.
         *
         * @api
         */
        public function getOverride()
        {
            return $this->override;
        }
    }
