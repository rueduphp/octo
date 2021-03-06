<?php

namespace Octo;

use Illuminate\Support\HtmlString;
use Illuminate\View\Compilers\Compiler;

class Htmlfactory
{
    protected $url;

    protected $view;

    /**
     * @param Compiler $view
     */
    public function __construct(Compiler $view)
    {
        $this->view = $view;
    }

    /**
     * @param $value
     * @return string
     */
    public function entities($value)
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * @param $value
     * @return string
     */
    public function decode($value)
    {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param $url
     * @param array $attributes
     * @return HtmlString
     */
    public function script($url, $attributes = [])
    {
        $attributes['src'] = \asset($url);

        return $this->toHtmlString('<script' . $this->attributes($attributes) . '></script>');
    }

    /**
     * @param $url
     * @param array $attributes
     * @return HtmlString
     */
    public function style($url, $attributes = [])
    {
        $defaults = ['media' => 'all', 'type' => 'text/css', 'rel' => 'stylesheet'];

        $attributes = array_merge($defaults, $attributes);

        $attributes['href'] = \asset($url);

        return $this->toHtmlString('<link' . $this->attributes($attributes) . '>');
    }

    /**
     * @param $url
     * @param null $alt
     * @param array $attributes
     * @return HtmlString
     */
    public function image($url, $alt = null, $attributes = [])
    {
        $attributes['alt'] = $alt;

        return $this->toHtmlString(
            '<img src="' . \asset($url) . '"' . $this->attributes($attributes) . '>'
        );
    }

    /**
     * @param $url
     * @param array $attributes
     * @return HtmlString
     */
    public function favicon($url, $attributes = [])
    {
        $defaults = ['rel' => 'shortcut icon', 'type' => 'image/x-icon'];

        $attributes = array_merge($attributes, $defaults);

        $attributes['href'] = \asset($url);

        return $this->toHtmlString('<link' . $this->attributes($attributes) . '>');
    }

    /**
     * @param $url
     * @param null $title
     * @param array $attributes
     * @param null $secure
     * @param bool $escape
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function link($url, $title = null, $attributes = [], $secure = null, $escape = true)
    {
        $url = \to($url, []);

        if (is_null($title) || $title === false) {
            $title = $url;
        }

        if ($escape) {
            $title = $this->entities($title);
        }

        return $this->toHtmlString(
            '<a href="' . $this->entities($url) . '"' . $this->attributes($attributes) . '>' . $title . '</a>'
        );
    }

    /**
     * @param $url
     * @param null $title
     * @param array $attributes
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function secureLink($url, $title = null, $attributes = [])
    {
        return $this->link($url, $title, $attributes, true);
    }

    /**
     * @param $url
     * @param null $title
     * @param array $attributes
     * @param null $secure
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function linkAsset($url, $title = null, $attributes = [], $secure = null)
    {
        $url = \asset($url, $secure);

        return $this->link($url, $title ?: $url, $attributes, $secure);
    }

    /**
     * @param $url
     * @param null $title
     * @param array $attributes
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function linkSecureAsset($url, $title = null, $attributes = [])
    {
        return $this->linkAsset($url, $title, $attributes, true);
    }

    /**
     * @param $name
     * @param null $title
     * @param array $parameters
     * @param array $attributes
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function linkRoute($name, $title = null, $parameters = [], $attributes = [])
    {
        return $this->link(\route($name, $parameters), $title, $attributes);
    }

    /**
     * @param $action
     * @param null $title
     * @param array $parameters
     * @param array $attributes
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function linkAction($module, $action, $title = null, $parameters = [], $attributes = [])
    {
        return $this->link(action($module, $action, $parameters), $title, $attributes);
    }

    /**
     * @param $email
     * @param null $title
     * @param array $attributes
     * @param bool $escape
     * @return HtmlString
     */
    public function mailto($email, $title = null, $attributes = [], $escape = true)
    {
        $email = $this->email($email);

        $title = $title ?: $email;

        if ($escape) {
            $title = $this->entities($title);
        }

        $email = $this->obfuscate('mailto:') . $email;

        return $this->toHtmlString(
            '<a href="' . $email . '"' . $this->attributes($attributes) . '>' . $title . '</a>'
        );
    }

    /**
     * @param $email
     * @return mixed
     */
    public function email($email)
    {
        return str_replace('@', '&#64;', $this->obfuscate($email));
    }

    /**
     * @param int $num
     * @return string
     */
    public function nbsp($num = 1)
    {
        return str_repeat('&nbsp;', $num);
    }

    /**
     * @param $list
     * @param array $attributes
     * @return HtmlString|string
     */
    public function ol($list, $attributes = [])
    {
        return $this->listing('ol', $list, $attributes);
    }

    /**
     * @param $list
     * @param array $attributes
     * @return HtmlString|string
     */
    public function ul($list, $attributes = [])
    {
        return $this->listing('ul', $list, $attributes);
    }

    /**
     * @param array $list
     * @param array $attributes
     * @return HtmlString
     */
    public function dl(array $list, array $attributes = [])
    {
        $attributes = $this->attributes($attributes);

        $html = "<dl{$attributes}>";

        foreach ($list as $key => $value) {
            $value = (array) $value;

            $html .= "<dt>$key</dt>";

            foreach ($value as $v_key => $v_value) {
                $html .= "<dd>$v_value</dd>";
            }
        }

        $html .= '</dl>';

        return $this->toHtmlString($html);
    }

    /**
     * @param $type
     * @param $list
     * @param array $attributes
     * @return HtmlString|string
     */
    protected function listing($type, $list, $attributes = [])
    {
        $html = '';

        if (count($list) === 0) {
            return $html;
        }

        foreach ($list as $key => $value) {
            $html .= $this->listingElement($key, $type, $value);
        }

        $attributes = $this->attributes($attributes);

        return $this->toHtmlString("<{$type}{$attributes}>{$html}</{$type}>");
    }

    /**
     * @param $key
     * @param $type
     * @param $value
     * @return HtmlString|string
     */
    protected function listingElement($key, $type, $value)
    {
        if (is_array($value)) {
            return $this->nestedListing($key, $type, $value);
        } else {
            return '<li>' . e($value, false) . '</li>';
        }
    }

    /**
     * @param $key
     * @param $type
     * @param $value
     * @return HtmlString|string
     */
    protected function nestedListing($key, $type, $value)
    {
        if (is_int($key)) {
            return $this->listing($type, $value);
        } else {
            return '<li>' . $key . $this->listing($type, $value) . '</li>';
        }
    }

    /**
     * @param $attributes
     * @return string
     */
    public function attributes($attributes)
    {
        $html = [];

        foreach ((array) $attributes as $key => $value) {
            $element = $this->attributeElement($key, $value);

            if (!is_null($element)) {
                $html[] = $element;
            }
        }

        return !empty($html) ? ' ' . implode(' ', $html) : '';
    }

    /**
     * @param $key
     * @param $value
     * @return string
     */
    protected function attributeElement($key, $value)
    {
        if (is_numeric($key)) {
            return $value;
        }

        if (is_bool($value) && $key !== 'value') {
            return $value ? $key : '';
        }

        if (! is_null($value)) {
            return $key . '="' . \e($value, false) . '"';
        }
    }

    /**
     * @param string $value
     * @return string
     */
    public function obfuscate(string $value): string
    {
        $safe = '';

        foreach (str_split($value) as $letter) {
            if (ord($letter) > 128) {
                return $letter;
            }

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

    /**
     * @param $name
     * @param $content
     * @param array $attributes
     * @return HtmlString
     */
    public function meta($name, $content, array $attributes = [])
    {
        $defaults = compact('name', 'content');

        $attributes = array_merge($defaults, $attributes);

        return $this->toHtmlString('<meta' . $this->attributes($attributes) . '>');
    }

    /**
     * @param $tag
     * @param $content
     * @param array $attributes
     * @return HtmlString
     */
    public function tag($tag, $content, array $attributes = [])
    {
        $content = is_array($content) ? implode('', $content) : $content;

        return $this->toHtmlString(
            '<' . $tag . $this->attributes($attributes) . '>' . $this->toHtmlString($content) . '</' . $tag . '>'
        );
    }

    /**
     * @param $html
     * @return HtmlString
     */
    protected function toHtmlString($html)
    {
        return new HtmlString($html);
    }
}
