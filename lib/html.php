<?php
    namespace Octo;

    class Html
    {
        public static $macros = [];
        const encoding = 'UTF-8';

        public static function macro($name, $macro)
        {
            static::$macros[$name] = $macro;
        }

        public static function entities($value)
        {
            return htmlentities($value, ENT_QUOTES, static::encoding, false);
        }

        public static function decode($value)
        {
            return html_entity_decode($value, ENT_QUOTES, static::encoding);
        }

        public static function specialchars($value)
        {
            return htmlspecialchars($value, ENT_QUOTES, static::encoding, false);
        }

        public static function escape($value)
        {
            return static::decode(static::entities($value));
        }

        public static function script($url, $attributes = array())
        {
            return '<script src="' . $url . '"' . static::attributes($attributes) . '></script>' . PHP_EOL;
        }

        public static function style($url, $attributes = array())
        {
            $defaults = ['media' => 'all', 'type' => 'text/css', 'rel' => 'stylesheet'];
            $attributes = $attributes + $defaults;

            return '<link href="' . $url . '"' . static::attributes($attributes) . '>' . PHP_EOL;
        }

        public static function tag($tag, $value = '', $attributes = array())
        {
            $tag = Inflector::lower($tag);

            if ($tag == 'meta') {
                return '<' . $tag . static::attributes($attributes) . ' />';
            }

            return '<' . $tag . static::attributes($attributes) . '>' . static::entities($value) . '</' . $tag . '>';
        }

        public static function link($url, $title = null, $attributes = array())
        {
            if (null === $title) {
                $title = $url;
            }

            return '<a href="' . $url . '"' . static::attributes($attributes) . '>' . static::entities($title) . '</a>';
        }

        public static function mailto($email, $title = null, $attributes = array())
        {
            $email = static::email($email);

            if (null === $title) {
                $title = $email;
            }

            $email = '&#109;&#097;&#105;&#108;&#116;&#111;&#058;' . $email;

            return '<a href="' . $email . '"' . static::attributes($attributes) . '>' . static::entities($title) . '</a>';
        }

        public static function email($email)
        {
            return str_replace('@', '&#64;', static::obfuscate($email));
        }

        public static function image($url, $alt = '', $attributes = array())
        {
            $attributes['alt'] = $alt;
            return '<img src="' . $url . '"' . static::attributes($attributes) . ' />';
        }

        public static function ol($list, $attributes = array())
        {
            return static::listing('ol', $list, $attributes);
        }

        public static function ul($list, $attributes = array())
        {
            return static::listing('ul', $list, $attributes);
        }

        private static function listing($type, $list, $attributes = array())
        {
            $html = '';

            if (count($list) == 0) {
                return $html;
            }

            foreach ($list as $key => $value) {
                if (is_array($value)) {
                    if (is_int($key)) {
                        $html .= static::listing($type, $value);
                    } else {
                        $html .= '<li>' . $key . static::listing($type, $value) . '</li>';
                    }
                } else {
                    $html .= '<li>' . static::entities($value) . '</li>';
                }
            }

            return '<' . $type . static::attributes($attributes) . '>' . $html . '</' . $type . '>';
        }

        public static function dl($list, $attributes = array())
        {
            $html = '';

            if (count($list) == 0) {
                return $html;
            }

            foreach ($list as $term => $description) {
                $html .= '<dt>' . static::entities($term) . '</dt>';
                $html .= '<dd>' . static::entities($description) . '</dd>';
            }

            return '<dl' . static::attributes($attributes) . '>' . $html . '</dl>';
        }

        public static function attributes($attributes)
        {
            $html = array();

            foreach ((array) $attributes as $key => $value) {
                if (is_numeric($key)) {
                    $key = $value;
                }

                if (null !== $value) {
                    $html[] = $key . '="' . static::entities($value) . '"';
                }
            }

            return (count($html) > 0) ? ' ' . implode(' ', $html) : '';
        }

        protected static function obfuscate($value)
        {
            $safe = '';

            foreach (str_split($value) as $letter) {
                switch (rand(1, 3)) {
                    case 1:
                        $safe .= '&#' . ord($letter) . ';';
                        break;

                    case 2:
                        $safe .= '&#x' . dechex(ord($letter)) . ';';
                        break;

                    case 3:
                        $safe .= $letter;
                }
            }

            return $safe;
        }

        public static function strong($data)
        {
            return '<strong>' . $data . '</strong>';
        }

        public static function em($data)
        {
            return '<em>' . $data . '</em>';
        }

        public static function code($data)
        {
            return '<pre><code>' . $data . '</code></pre>';
        }

        public static function quote($data)
        {
            return '<blockquote><p>' . $data . '</p></blockquote>';
        }

        public static function del($data)
        {
            return '<del>' . $data . '</del>';
        }

        public static function iframe($url, $attributes = array())
        {
            return '<iframe src="' . $url . '"' . static::attributes($attributes) . '></iframe>';
        }

        public static function __callStatic($method, $parameters)
        {
            if (2 == count($parameters)) {
                return static::tag($method, current($parameters), end($parameters));
            } else if (1 == count($parameters)) {
                return static::tag($method, current($parameters));
            } else if (0 == count($parameters)) {
                return static::tag($method);
            } else {
                throw new Exception("The method $method is not well implemented.");
            }
        }

        protected $attributes = [];

        public function setAttribute($attribute, $value = null)
        {
            if (is_null($value)) {
                return;
            }

            $this->attributes[$attribute] = $value;
        }

        public function removeAttribute($attribute)
        {
            unset($this->attributes[$attribute]);
        }

        public function getAttribute($attribute)
        {
            return $this->attributes[$attribute];
        }

        public function data($attribute, $value = null)
        {
            if (is_array($attribute)) {
                foreach ($attribute as $key => $val) {
                    $this->setAttribute('data-'.$key, $val);
                }
            } else {
                $this->setAttribute('data-'.$attribute, $value);
            }

            return $this;
        }

        public function attribute($attribute, $value)
        {
            $this->setAttribute($attribute, $value);

            return $this;
        }

        public function clear($attribute)
        {
            if (! isset($this->attributes[$attribute])) {
                return $this;
            }

            $this->removeAttribute($attribute);

            return $this;
        }

        public function addClass($class)
        {
            if (isset($this->attributes['class'])) {
                $class = $this->attributes['class'] . ' ' . $class;
            }

            $this->setAttribute('class', $class);

            return $this;
        }

        public function removeClass($class)
        {
            if (!isset($this->attributes['class'])) {
                return $this;
            }

            $class = trim(str_replace($class, '', $this->attributes['class']));

            if ($class == '') {
                $this->removeAttribute('class');
                return $this;
            }

            $this->setAttribute('class', $class);

            return $this;
        }

        public function id($id)
        {
            $this->setId($id);

            return $this;
        }

        protected function setId($id)
        {
            $this->setAttribute('id', $id);
        }

        public function __toString()
        {
            return $this->render();
        }

        protected function renderAttributes()
        {
            list($attributes, $values) = $this->splitKeysAndValues($this->attributes);

            return implode(array_map(function ($attribute, $value) {
                return sprintf(' %s="%s"', $attribute, $value);
            }, $attributes, $values));
        }

        protected function splitKeysAndValues($array)
        {
            return [array_keys($array), array_values($array)];
        }

        public function __call($method, $params)
        {
            $params = !empty($params) ? $params : [$method];
            $params = array_merge([$method], $params);

            call_user_func_array([$this, 'attribute'], $params);

            return $this;
        }
    }
