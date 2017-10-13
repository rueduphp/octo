<?php
    namespace Octo;

    class Form
    {
        public static $labels = array();
        public static $macros = array();

        const class_size = 'col_md_2';

        const spoofer = '_method';

        /**
        * Default twitter form class
        *
        * Options are form-vertical, form-horizontal, form-inline, form-search
        */
        public $formClass = 'form-horizontal';

        /**
        * Automatically create an id for each field based on the field name
        */
        public $nameAsId = true;

        /**
        * Text string to identify the required label
        */
        public $requiredLabel = '.req';

        /**
        * Extra text added before the label for required fields
        */
        public $requiredPrefix = '';

        /**
        * Extra text added after the label for required fields
        */
        public $requiredSuffix = ' *';

        /**
        * Extra class added to the label for required fields
        */
        public $requiredClass = 'label-required';

        /**
        * Display a class for the control group if an input field fails validation
        */
        public $controlGroupError = 'error';

        /**
        * Display inline validation error text
        */
        public $displayInlineErrors = false;

        public static function open($action = null, $method = 'POST', $attributes = [], $https = null, $upload = false)
        {
            $method = Strings::upper($method);

            if (!array_key_exists('id', $attributes)) {
                $attributes['id'] = md5(static::action($action, $https));
            }

            $attributes['method'] = static::method($method);
            $attributes['action'] = static::action($action, $https);

            if (true === $upload) {
                $attributes['enctype'] = 'multipart/form-data';
            }

            if (!array_key_exists('accept-charset', $attributes)) {
                $attributes['accept-charset'] = 'utf-8';
            }

            $append = '';

            if ($method == 'PUT' || $method == 'DELETE') {
                $append = static::hidden(static::spoofer, $method);
            }

            return '<form' . Html::attributes($attributes) . '>' . $append;
        }

        protected static function method($method)
        {
            return ($method !== 'GET') ? 'POST' : $method;
        }

        protected static function action($action, $https = null)
        {
            $uri = (null === $action) ? current_url() : $action;

            return (null === $https) ? $uri : str_replace('http://', 'https://', $uri);
        }

        public static function close()
        {
            return '</form>';
        }

        public static function token()
        {
            return static::input('hidden', '_token', token());
        }

        public static function label($name, $value, $attributes = [])
        {
            static::$labels[] = $name;
            $attributes = Html::attributes($attributes);

            return '<label for="' . $name . '"' . $attributes . '>' . static::display($value) . '</label>';
        }

        /**
        * Builds the label html
        *
        * @param string $name The name of the html field
        * @param string $label The label name
        * @param boolean $required
        * @return string
        */
        public static function buildLabel($name, $label = '', $required = false)
        {
            $out = '';

            if (!empty($label)) {
                $class              = 'control-label';
                $requiredLabel      = '.req';
                $requiredSuffix     = '<span class="required"><i class="icon-asterisk"></i></span>';
                $requiredPrefix     = '';
                $requiredClass      = 'labelrequired';

                if (false !== $required) {
                    $label = $requiredPrefix . $label . $requiredSuffix;
                    $class .= ' ' . $requiredClass;
                }

                $out .= static::label($name, $label, array('class' => $class));
            }

            return $out;
        }

        /**
        * Builds the Twitter Bootstrap control wrapper
        *
        * @param string $field The html for the field
        * @param string $name The name of the field
        * @param string $label The label name
        * @param boolean $checkbox
        * @return string
        */
        private static function buildWrapper($field, $name, $label = '', $checkbox = false, $required)
        {
            $getter = 'get' . Strings::camelize($name);
            $error  = null;
            $actual = session('web')->getActual();

            if (null !== $actual) {
                $error = $actual->getErrors()->$getter();
            }

            $class = 'control-group';

            if (!empty(static::$controlGroupError) && !empty($error)) {
                $class .= ' ' . static::$controlGroupError;
            }

            $id = ' id="control-group-' . $name . '"';
            $out = '<div class="' . $class . '"' . $id . '>';
            $out .= static::buildLabel($name, $label, $required);
            $out .= '<div class="controls">' . PHP_EOL;
            $out .= ($checkbox === true) ? '<label class="checkbox">' : '';
            $out .= $field;

            if (!empty($error)) {
                $out .= '<span class="help-inline">' . $error . '</span>';
            }

            $out .= ($checkbox === true) ? '</label>' : '';
            $out .= '</div>';
            $out .= '</div>' . PHP_EOL;

            return $out;
        }

        public static function input($type, $name, $value = null, $attributes = array(), $label = '', $checkbox = false)
        {
            $name = (isset($attributes['name'])) ? $attributes['name'] : $name;

            if (!array_key_exists('required', $attributes)) {
                $required = false;
            } else {
                $required = $attributes['required'];
            }

            if (false === $required) {
                unset($attributes['required']);
            }

            if (!array_key_exists('id', $attributes)) {
                $attributes['id'] = $name;
            }

            $id         = static::id($name, $attributes);
            $class      = '';
            $attributes = array_merge(
                $attributes,
                compact(
                    'type',
                    'name',
                    'value',
                    'id'
                )
            );

            if ($type == 'date') {
                $class .= ' datepicker';
            }

            $field = '<input class="'.static::class_size.'' . $class . '"' . Html::attributes($attributes) . ' />';

            return static::buildWrapper($field, $name, $label, $checkbox, $required);
        }

        public static function text($name, $value = null, $attributes = array(), $label = '')
        {
            return static::input('text', $name, $value, $attributes, $label);
        }

        public static function password($name, $attributes = array(), $label = '')
        {
            return static::input('password', $name, null, $attributes, $label);
        }

        public static function hidden($name, $value = null, $attributes = array(), $label = '')
        {
            return static::input('hidden', $name, $value, $attributes, $label);
        }

        public static function search($name, $value = null, $attributes = array(), $label = '')
        {
            return static::input('search', $name, $value, $attributes, $label);
        }

        public static function email($name, $value = null, $attributes = array(), $label = '')
        {
            return static::input('email', $name, $value, $attributes, $label);
        }

        public static function telephone($name, $value = null, $attributes = array(), $label = '')
        {
            return static::input('tel', $name, $value, $attributes, $label);
        }

        public static function url($name, $value = null, $attributes = array(), $label = '')
        {
            return static::input('url', $name, $value, $attributes, $label);
        }

        public static function number($name, $value = null, $attributes = array(), $label = '')
        {
            return static::input('number', $name, $value, $attributes, $label);
        }

        public static function date($name, $value = null, $attributes = array(), $label = '')
        {
            return static::input('date', $name, $value, $attributes, $label);
        }

        public static function file($name, $attributes = array(), $label = '')
        {
            return static::input('file', $name, null, $attributes, $label);
        }

        public static function textarea($name, $value = '', $attributes = array(), $label = '')
        {
            $attributes['name'] = $name;
            $attributes['id'] = static::id($name, $attributes);

            if (!array_key_exists('rows', $attributes)) $attributes['rows'] = 10;
            if (!array_key_exists('cols', $attributes)) $attributes['cols'] = 50;
            if (!array_key_exists('required', $attributes)) $attributes['required'] = false;

            $required = $attributes['required'];

            if (false === $required) {
                unset($attributes['required']);
            }

            $field = '<textarea class="'.static::class_size.'"' . Html::attributes($attributes) . '>' . Html::entities($value) . '</textarea>';

            return static::buildWrapper($field, $name, $label, false, $required);
        }

        public static function select($name, $options = array(), $selected = null, $attributes = array(), $label = '')
        {
            $attributes['id'] = static::id($name, $attributes);
            $attributes['name'] = $name;
            $html = array();

            foreach ($options as $value => $display) {
                if (is_array($display)) {
                    $html[] = static::optgroup($display, $value, $selected);
                } else {
                    $html[] = static::option($value, $display, $selected);
                }
            }

            if ( ! ake('required', $attributes)) $attributes['required'] = false;

            $required = $attributes['required'];

            if (false === $required) {
                unset($attributes['required']);
            }

            $field = '<select class="'.static::class_size.'"' . Html::attributes($attributes) . '>' . implode('', $html) . '</select>';

            return static::buildWrapper($field, $name, $label, false, $required);
        }

        protected static function optgroup($options, $label, $selected)
        {
            $html = array();

            foreach ($options as $value => $display) {
                $html[] = static::option($value, $display, $selected);
            }

            return '<optgroup label="' . Html::entities($label) . '">' . implode('', $html) . '</optgroup>';
        }

        protected static function option($value, $display, $selected)
        {
            if (is_array($selected)) {
                $selected = (in_arrayArray($value, $selected)) ? 'selected' : null;
            } else {
                $selected = ((string) $value == (string) $selected) ? 'selected' : null;
            }

            $attributes = array('value' => View::utf8($value), 'selected' => $selected);

            return '<option' . Html::attributes($attributes) . '>' . View::utf8($display) . '</option>';
        }

        public static function checkbox($name, $value = 1, $checked = false, $attributes = array(), $label = '')
        {
            return static::checkable('checkbox', $name, $value, $checked, $attributes, $label);
        }

        public static function radio($name, $value = null, $checked = false, $attributes = array(), $label = '')
        {
            if (null === $value) $value = $name;

            return static::checkable('radio', $name, $value, $checked, $attributes, $label);
        }

        protected static function checkable($type, $name, $value, $checked, $attributes, $label = '')
        {
            if ($checked) $attributes['checked'] = 'checked';

            $attributes['id'] = static::id($name, $attributes);

            return static::input($type, $name, $value, $attributes, $label, true);
        }

        public static function submit($value = null, $attributes = array(), $btnClass = 'btn')
        {
            $attributes['type'] = 'submit';

            if ($btnClass != 'btn') {
                $btnClass = 'btn btn-' . $btnClass;
            }

            if (!isset($attributes['class']))  {
                $attributes['class'] = $btnClass;
            } elseif (strpos($attributes['class'], $btnClass) === false) {
                $attributes['class'] .= ' ' . $btnClass;
            }

            return static::button($value, $attributes);
        }

        public static function reset($value = null, $attributes = array(), $btnClass = 'btn')
        {
            $attributes['type'] = 'reset';

            if ($btnClass != 'btn') {
                $btnClass = 'btn btn-' . $btnClass;
            }

            if ( ! isset($attributes['class']))  {
                $attributes['class'] = $btnClass;
            } elseif (strpos($attributes['class'], $btnClass) === false) {
                $attributes['class'] .= ' ' . $btnClass;
            }

            return static::button($value, $attributes);
        }

        /**
        * Shortcut method for creating a primary submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitPrimary($value, $attributes = array())
        {
            return static::submit($value, $attributes, 'primary');
        }

        /**
        * Shortcut method for creating an info submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitInfo($value, $attributes = array())
        {
            return static::submit($value, $attributes, 'info');
        }

        /**
        * Shortcut method for creating a success submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitSuccess($value, $attributes = array())
        {
            return static::submit($value, $attributes, 'success');
        }

        /**
        * Shortcut method for creating a warning submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitWarning($value, $attributes = array())
        {
            return static::submit($value, $attributes, 'warning');
        }

        /**
        * Shortcut method for creating a danger submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitDanger($value, $attributes = array())
        {
            return static::submit($value, $attributes, 'danger');
        }

        /**
        * Shortcut method for creating an inverse submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitInverse($value, $attributes = array())
        {
            return static::submit($value, $attributes, 'inverse');
        }


        public static function image($url, $name = null, $attributes = array())
        {
            $attributes['src'] = $url;

            return static::input('image', $name, null, $attributes);
        }

        public static function button($value = null, $attributes = array())
        {
            return '<button' . Html::attributes($attributes) . '>' . Html::entities($value) . '</button>';
        }

        protected static function id($name, $attributes)
        {
            // If an ID has been explicitly specified in the attributes, we will
            // use that ID. Otherwise, we will look for an ID in the array of
            // label names so labels and their elements have the same ID.
            if (array_key_exists('id', $attributes)) {
                return $attributes['id'];
            }

            if (in_array($name, static::$labels)) {
                return $name;
            }
        }

        public static function instance()
        {
            return new Form\View();
        }

        public static function display($str)
        {
            return Strings::utf8($str);
        }
    }


