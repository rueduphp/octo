<?php
    namespace Octo;

    class Gravatar
    {
        protected $size             = 80;
        protected $default_image    = false;
        protected $max_rating       = 'g';
        protected $use_secure_url   = false;
        protected $param_cache      = null;

        const HTTP_URL              = 'http://www.gravatar.com/avatar/';
        const HTTPS_URL             = 'https://secure.gravatar.com/avatar/';

        public function getAvatarSize()
        {
            return $this->size;
        }

        public function setAvatarSize($size)
        {
            $this->param_cache = NULL;

            if (!is_int($size) && !ctype_digit($size)) {
                exception('gravatar', 'Avatar size specified must be an integer');
            }

            $this->size = (int) $size;

            if ($this->size > 512 || $this->size < 0) {
                exception('gravatar', 'Avatar size must be within 0 pixels and 512 pixels');
            }

            return $this;
        }

        public function getDefaultImage()
        {
            return $this->default_image;
        }

        public function setDefaultImage($image)
        {
            if ($image === false) {
                $this->default_image = false;

                return $this;
            }

            $this->param_cache = NULL;
            $_image = strtolower($image);
            $valid_defaults = array('404' => 1, 'mm' => 1, 'identicon' => 1, 'monsterid' => 1, 'wavatar' => 1, 'retro' => 1);

            if (!isset($valid_defaults[$_image])) {
                if (!filter_var($image, FILTER_VALIDATE_URL)) {
                    exception('gravatar', 'The default image specified is not a recognized gravatar "default" and is not a valid URL');
                } else {
                    $this->default_image = rawurlencode($image);
                }
            } else {
                $this->default_image = $_image;
            }

            return $this;
        }

        public function getMaxRating()
        {
            return $this->max_rating;
        }

        public function setMaxRating($rating)
        {
            $this->param_cache = NULL;

            $rating = strtolower($rating);
            $valid_ratings = array('g' => 1, 'pg' => 1, 'r' => 1, 'x' => 1);

            if (!isset($valid_ratings[$rating])) {
                exception('gravatar', sprintf('Invalid rating "%s" specified, only "g", "pg", "r", or "x" are allowed to be used.', $rating));
            }

            $this->max_rating = $rating;

            return $this;
        }

        public function usingSecureImages()
        {
            return $this->use_secure_url;
        }

        public function enableSecureImages()
        {
            $this->use_secure_url = true;

            return $this;
        }

        public function disableSecureImages()
        {
            $this->use_secure_url = false;

            return $this;
        }

        public function buildGravatarURL($email, $hash_email = true)
        {
            if ($this->usingSecureImages()) {
                $url = static::HTTPS_URL;
            } else {
                $url = static::HTTP_URL;
            }

            if ($hash_email == true && !empty($email)) {
                $url .= $this->getEmailHash($email);
            } elseif (!empty($email)) {
                $url .= $email;
            } else {
                $url .= str_repeat('0', 32);
            }

            if ($this->param_cache === NULL) {

                $params = array();
                $params[] = 's=' . $this->getAvatarSize();
                $params[] = 'r=' . $this->getMaxRating();

                if ($this->getDefaultImage()) {
                    $params[] = 'd=' . $this->getDefaultImage();
                }

                $this->params_cache = (!empty($params)) ? '?' . implode('&amp;', $params) : '';
            }

            $tail = '';

            if (empty($email)) {
                $tail = !empty($this->params_cache) ? '&amp;f=y' : '?f=y';
            }

            return $url . $this->params_cache . $tail;
        }

        public function getEmailHash($email)
        {
            return hash('md5', strtolower(trim($email)));
        }

        public function get($email, $hash_email = true)
        {
            return $this->buildGravatarURL($email, $hash_email);
        }
    }
