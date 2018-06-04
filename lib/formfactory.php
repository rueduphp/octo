<?php

namespace Octo;

use DateTime;
use Illuminate\Support\HtmlString;

class Formfactory
{
    /**
     * @var Htmlfactory
     */
    protected $html;

    /**
     * @var \Illuminate\Contracts\Routing\UrlGenerator
     */
    protected $url;

    /**
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $view;

    /**
     * @var string
     */
    protected $csrfToken;

    /**
     * @var Ultimate
     */
    protected $session;

    /**
     * @var mixed
     */
    protected $model;

    /**
     * @var array
     */
    protected $labels = [];

    /** @var FastRequest  */
    protected $request;

    /**
     * @var array
     */
    protected $reserved = ['method', 'url', 'route', 'action', 'files'];

    /**
     * @var array
     */
    protected $spoofedMethods = ['DELETE', 'PATCH', 'PUT'];

    /**
     * @var array
     */
    protected $skipValueTypes = ['file', 'password', 'checkbox', 'radio'];


    /**
     * @var null
     */
    protected $type = null;

    /**
     * @param Htmlfactory $html
     * @param $view
     * @param $csrfToken
     * @param FastRequest|null $request
     */
    public function __construct(Htmlfactory $html, $view, $csrfToken, FastRequest $request = null)
    {
        $this->html = $html;
        $this->view = $view;
        $this->csrfToken = $csrfToken;
        $this->request = $request;
    }

    /**
     * Open up a new HTML form.
     *
     * @param  array $options
     *
     * @return \Illuminate\Support\HtmlString
     */
    public function open(array $options = [])
    {
        $method = aget($options, 'method', 'post');

        $attributes['method'] = $this->getMethod($method);

        $attributes['action'] = $this->getAction($options);

        $attributes['accept-charset'] = 'UTF-8';

        $append = $this->getAppendage($method);

        if (isset($options['files']) && true === $options['files']) {
            $options['enctype'] = 'multipart/form-data';
        }


        $attributes = array_merge(
            $attributes, Arrays::except($options, $this->reserved)
        );

        $attributes = $this->html->attributes($attributes);

        return $this->toHtmlString('<form' . $attributes . '>' . $append);
    }

    /**
     * @param  mixed $model
     * @param  array $options
     *
     * @return \Illuminate\Support\HtmlString
     */
    public function model($model, array $options = [])
    {
        $this->model = $model;

        return $this->open($options);
    }

    /**
     * @param  mixed $model
     *
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * @return mixed $model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return string
     */
    public function close()
    {
        $this->labels = [];

        $this->model = null;

        return $this->toHtmlString('</form>');
    }

    /**
     * @return string
     */
    public function token()
    {
        $token = !empty($this->csrfToken) ? $this->csrfToken : $this->session->token();

        return $this->hidden('_csrf', $token);
    }

    /**
     * @param  string $name
     * @param  string $value
     * @param  array  $options
     * @param  bool   $escape_html
     *
     * @return \Illuminate\Support\HtmlString
     */
    public function label($name, $value = null, $options = [], $escape_html = true)
    {
        $this->labels[] = $name;

        $options = $this->html->attributes($options);

        $value = $this->formatLabel($name, $value);

        if ($escape_html) {
            $value = $this->html->entities($value);
        }

        return $this->toHtmlString('<label for="' . $name . '"' . $options . '>' . $value . '</label>');
    }

    /**
     * @param  string      $name
     * @param  string|null $value
     *
     * @return string
     */
    protected function formatLabel($name, $value)
    {
        return $value ?: ucwords(str_replace('_', ' ', $name));
    }

    /**
     * @param $type
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function input($type, $name, $value = null, $options = [])
    {
        $this->type = $type;

        if (!isset($options['name'])) {
            $options['name'] = $name;
        }

        $id = $this->getIdAttribute($name, $options);

        if (!in_array($type, $this->skipValueTypes) && null === $value) {
            $value = $this->getValueAttribute($name, $value);
        }

        $merge = compact('type', 'value', 'id');

        $options = array_merge($options, $merge);

        return $this->toHtmlString('<input' . $this->html->attributes($options) . '>');
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function text($name, $value = null, $options = [])
    {
        return $this->input('text', $name, $value, $options);
    }

    /**
     * @param $name
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function password($name, $options = [])
    {
        return $this->input('password', $name, '', $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function range($name, $value = null, $options = [])
    {
        return $this->input('range', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function hidden($name, $value = null, $options = [])
    {
        return $this->input('hidden', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function search($name, $value = null, $options = [])
    {
        return $this->input('search', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function email($name, $value = null, $options = [])
    {
        return $this->input('email', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function tel($name, $value = null, $options = [])
    {
        return $this->input('tel', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function number($name, $value = null, $options = [])
    {
        return $this->input('number', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function date($name, $value = null, $options = [])
    {
        if ($value instanceof DateTime) {
            $value = $value->format('Y-m-d');
        }

        return $this->input('date', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function datetime($name, $value = null, $options = [])
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::RFC3339);
        }

        return $this->input('datetime', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function datetimeLocal($name, $value = null, $options = [])
    {
        if ($value instanceof DateTime) {
            $value = $value->format('Y-m-d\TH:i');
        }

        return $this->input('datetime-local', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function time($name, $value = null, $options = [])
    {
        if ($value instanceof DateTime) {
            $value = $value->format('H:i');
        }

        return $this->input('time', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function url($name, $value = null, $options = [])
    {
        return $this->input('url', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function week($name, $value = null, $options = [])
    {
        if ($value instanceof DateTime) {
            $value = $value->format('Y-\WW');
        }

        return $this->input('week', $name, $value, $options);
    }

    /**
     * @param $name
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function file($name, $options = [])
    {
        return $this->input('file', $name, null, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function textarea($name, $value = null, $options = [])
    {
        $this->type = 'textarea';

        if (! isset($options['name'])) {
            $options['name'] = $name;
        }

        $options = $this->setTextAreaSize($options);

        $options['id'] = $this->getIdAttribute($name, $options);

        $value = (string) $this->getValueAttribute($name, $value);

        unset($options['size']);

        $options = $this->html->attributes($options);

        return $this->toHtmlString('<textarea' . $options . '>' . e($value, false). '</textarea>');
    }

    /**
     * @param $options
     * @return array
     */
    protected function setTextAreaSize($options)
    {
        if (isset($options['size'])) {
            return $this->setQuickTextAreaSize($options);
        }

        $cols = array_get($options, 'cols', 50);

        $rows = array_get($options, 'rows', 10);

        return array_merge($options, compact('cols', 'rows'));
    }

    /**
     * @param $options
     * @return array
     */
    protected function setQuickTextAreaSize($options)
    {
        $segments = explode('x', $options['size']);

        return array_merge($options, ['cols' => $segments[0], 'rows' => $segments[1]]);
    }

    /**
     * @param $name
     * @param array $list
     * @param null $selected
     * @param array $selectAttributes
     * @param array $optionsAttributes
     * @param array $optgroupsAttributes
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function select(
        $name,
        $list = [],
        $selected = null,
        array $selectAttributes = [],
        array $optionsAttributes = [],
        array $optgroupsAttributes = []
    ) {
        $this->type = 'select';

        $selected = $this->getValueAttribute($name, $selected);

        $selectAttributes['id'] = $this->getIdAttribute($name, $selectAttributes);

        if (! isset($selectAttributes['name'])) {
            $selectAttributes['name'] = $name;
        }

        $html = [];

        if (isset($selectAttributes['placeholder'])) {
            $html[] = $this->placeholderOption($selectAttributes['placeholder'], $selected);
            unset($selectAttributes['placeholder']);
        }

        foreach ($list as $value => $display) {
            $optionAttributes = $optionsAttributes[$value] ?? [];
            $optgroupAttributes = $optgroupsAttributes[$value] ?? [];
            $html[] = $this->getSelectOption($display, $value, $selected, $optionAttributes, $optgroupAttributes);
        }

        $selectAttributes = $this->html->attributes($selectAttributes);

        $list = implode('', $html);

        return $this->toHtmlString("<select{$selectAttributes}>{$list}</select>");
    }

    /**
     * @param $name
     * @param $begin
     * @param $end
     * @param null $selected
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function selectRange($name, $begin, $end, $selected = null, $options = [])
    {
        $range = array_combine($range = range($begin, $end), $range);

        return $this->select($name, $range, $selected, $options);
    }

    /**
     * @return mixed
     */
    public function selectYear()
    {
        return call_user_func_array([$this, 'selectRange'], func_get_args());
    }

    /**
     * @param $name
     * @param null $selected
     * @param array $options
     * @param string $format
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function selectMonth($name, $selected = null, $options = [], $format = '%B')
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[$month] = strftime($format, mktime(0, 0, 0, $month, 1));
        }

        return $this->select($name, $months, $selected, $options);
    }

    /**
     * @param $display
     * @param $value
     * @param $selected
     * @param array $attributes
     * @param array $optgroupAttributes
     * @return HtmlString
     */
    public function getSelectOption($display, $value, $selected, array $attributes = [], array $optgroupAttributes = [])
    {
        if (is_array($display)) {
            return $this->optionGroup($display, $value, $selected, $optgroupAttributes, $attributes);
        }

        return $this->option($display, $value, $selected, $attributes);
    }

    /**
     * @param $list
     * @param $label
     * @param $selected
     * @param array $attributes
     * @param array $optionsAttributes
     * @param int $level
     * @return HtmlString
     */
    protected function optionGroup(
        $list,
        $label,
        $selected,
        array $attributes = [],
        array $optionsAttributes = [],
        $level = 0
    ) {
        $html = [];
        $space = str_repeat("&nbsp;", $level);

        foreach ($list as $value => $display) {
            $optionAttributes = $optionsAttributes[$value] ?? [];

            if (is_array($display)) {
                $html[] = $this->optionGroup($display, $value, $selected, $attributes, $optionAttributes, $level+5);
            } else {
                $html[] = $this->option($space.$display, $value, $selected, $optionAttributes);
            }
        }

        return $this->toHtmlString(
            '<optgroup label="'
            . e($space.$label, false)
            . '"'
            . $this->html->attributes($attributes)
            . '>'
            . implode('', $html)
            . '</optgroup>'
        );
    }

    /**
     * @param $display
     * @param $value
     * @param $selected
     * @param array $attributes
     * @return HtmlString
     */
    protected function option($display, $value, $selected, array $attributes = [])
    {
        $selected = $this->getSelectedValue($value, $selected);

        $options = array_merge(['value' => $value, 'selected' => $selected], $attributes);

        $string = '<option' . $this->html->attributes($options) . '>';

        if ($display !== null) {
            $string .= e($display, false) . '</option>';
        }

        return $this->toHtmlString($string);
    }

    /**
     * @param $display
     * @param $selected
     * @return HtmlString
     */
    protected function placeholderOption($display, $selected)
    {
        $selected = $this->getSelectedValue(null, $selected);

        $options = [
            'selected' => $selected,
            'value' => '',
        ];

        return $this->toHtmlString(
            '<option' .
            $this->html->attributes($options) .
            '>' . e($display, false) .
            '</option>'
        );
    }

    /**
     * @param $value
     * @param $selected
     * @return bool|null|string
     */
    protected function getSelectedValue($value, $selected)
    {
        if (is_array($selected)) {
            return in_array($value, $selected, true) || in_array((string) $value, $selected, true) ? 'selected' : null;
        } elseif ($selected instanceof Collection) {
            return $selected->contains($value) ? 'selected' : null;
        }

        if (is_int($value) && is_bool($selected)) {
            return (bool)$value === $selected;
        }

        return ((string) $value === (string) $selected) ? 'selected' : null;
    }

    /**
     * @param $name
     * @param int $value
     * @param null $checked
     * @param array $options
     * @return HtmlString
     */
    public function checkbox($name, $value = 1, $checked = null, $options = [])
    {
        return $this->checkable('checkbox', $name, $value, $checked, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param null $checked
     * @param array $options
     * @return HtmlString
     */
    public function radio($name, $value = null, $checked = null, $options = [])
    {
        if (is_null($value)) {
            $value = $name;
        }

        return $this->checkable('radio', $name, $value, $checked, $options);
    }

    /**
     * @param $type
     * @param $name
     * @param $value
     * @param $checked
     * @param $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    protected function checkable($type, $name, $value, $checked, $options)
    {
        $this->type = $type;

        $checked = $this->getCheckedState($type, $name, $value, $checked);

        if ($checked) {
            $options['checked'] = 'checked';
        }

        return $this->input($type, $name, $value, $options);
    }

    /**
     * @param $type
     * @param $name
     * @param $value
     * @param $checked
     * @return bool
     * @throws \ReflectionException
     */
    protected function getCheckedState($type, $name, $value, $checked)
    {
        switch ($type) {
            case 'checkbox':
                return $this->getCheckboxCheckedState($name, $value, $checked);
            case 'radio':
                return $this->getRadioCheckedState($name, $value, $checked);
            default:
                return $this->compareValues($name, $value);
        }
    }

    /**
     * @param $name
     * @param $value
     * @param $checked
     * @return bool
     * @throws \ReflectionException
     */
    protected function getCheckboxCheckedState($name, $value, $checked)
    {
        $request = $this->request($name);

        if (isset($this->session) && !$this->oldInputIsEmpty() && is_null($this->old($name)) && !$request) {
            return false;
        }

        if ($this->missingOldAndModel($name) && is_null($request)) {
            return $checked;
        }

        $posted = $this->getValueAttribute($name, $checked);

        if (is_array($posted)) {
            return in_array($value, $posted);
        } elseif ($posted instanceof Collection) {
            return $posted->contains('id', $value);
        } else {
            return (bool) $posted;
        }
    }

    /**
     * @param $name
     * @param $value
     * @param $checked
     * @return bool
     * @throws \ReflectionException
     */
    protected function getRadioCheckedState($name, $value, $checked)
    {
        $request = $this->request($name);

        if ($this->missingOldAndModel($name) && !$request) {
            return $checked;
        }

        return $this->compareValues($name, $value);
    }

    /**
     * @param $name
     * @param $value
     * @return bool
     * @throws \ReflectionException
     */
    protected function compareValues($name, $value)
    {
        return $this->getValueAttribute($name) == $value;
    }

    /**
     * @param  string $name
     *
     * @return bool
     */
    protected function missingOldAndModel($name)
    {
        return is_null($this->old($name)) && is_null($this->getModelValueAttribute($name));
    }

    /**
     * @param $value
     * @param array $attributes
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function reset($value, $attributes = [])
    {
        return $this->input('reset', null, $value, $attributes);
    }

    /**
     * @param $url
     * @param null $name
     * @param array $attributes
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function image($url, $name = null, $attributes = [])
    {
        $attributes['src'] = \asset($url);

        return $this->input('image', $name, null, $attributes);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function month($name, $value = null, $options = [])
    {
        if ($value instanceof DateTime) {
            $value = $value->format('Y-m');
        }

        return $this->input('month', $name, $value, $options);
    }

    /**
     * @param $name
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function color($name, $value = null, $options = [])
    {
        return $this->input('color', $name, $value, $options);
    }

    /**
     * @param null $value
     * @param array $options
     * @return HtmlString
     * @throws \ReflectionException
     */
    public function submit($value = null, $options = [])
    {
        return $this->input('submit', null, $value, $options);
    }

    /**
     * @param null $value
     * @param array $options
     * @return HtmlString
     */
    public function button($value = null, $options = [])
    {
        if (! array_key_exists('type', $options)) {
            $options['type'] = 'button';
        }

        return $this->toHtmlString('<button' . $this->html->attributes($options) . '>' . $value . '</button>');
    }

    /**
     * @param $method
     * @return string
     */
    protected function getMethod($method)
    {
        $method = strtoupper($method);

        return $method !== 'GET' ? 'POST' : $method;
    }

    /**
     * @param array $options
     * @return mixed|string
     * @throws \ReflectionException
     */
    protected function getAction(array $options)
    {
        if (isset($options['url'])) {
            return $this->getUrlAction($options['url']);
        }

        if (isset($options['route'])) {
            return $this->getRouteAction($options['route']);
        }

        elseif (isset($options['action'])) {
            return $this->getControllerAction($options['action']);
        }

        return Url::full();
    }

    /**
     * @param $options
     * @return string
     * @throws \ReflectionException
     */
    protected function getUrlAction($options)
    {
        if (is_array($options)) {
            return \to($options[0], array_slice($options, 1));
        }

        return \to($options);
    }

    /**
     * @param $options
     * @return string
     * @throws \ReflectionException
     */
    protected function getRouteAction($options)
    {
        if (is_array($options)) {
            return \route($options[0], array_slice($options, 1));
        }

        return \route($options);
    }

    /**
     * @param $options
     * @return mixed|null|string
     */
    protected function getControllerAction($options)
    {
        return action($options[0], $options[1], $options[2] ?? []);
    }

    /**
     * @param $method
     * @return mixed|string
     * @throws \ReflectionException
     */
    protected function getAppendage($method)
    {
        list($method, $appendage) = [strtoupper($method), ''];

        if (in_array($method, $this->spoofedMethods)) {
            $appendage .= $this->hidden('_method', $method);
        }

        if ($method !== 'GET') {
            $appendage .= $this->token();
        }

        return $appendage;
    }

    /**
     * @param $name
     * @param $attributes
     * @return mixed
     */
    public function getIdAttribute($name, $attributes)
    {
        if (array_key_exists('id', $attributes)) {
            return $attributes['id'];
        }

        if (in_array($name, $this->labels)) {
            return $name;
        }
    }

    /**
     * @param $name
     * @param null $value
     * @return array|mixed|null
     * @throws \ReflectionException
     */
    public function getValueAttribute($name, $value = null)
    {
        if (is_null($name)) {
            return $value;
        }

        $old = $this->old($name);

        if (!is_null($old) && $name !== '_method') {
            return $old;
        }

        $request = $this->request($name);

        if (! is_null($request) && $name != '_method') {
            return $request;
        }

        if (!is_null($value)) {
            return $value;
        }

        if (isset($this->model)) {
            return $this->getModelValueAttribute($name);
        }
    }

    /**
     * @param $name
     * @return array|mixed|null
     * @throws \ReflectionException
     */
    protected function request($name)
    {
        if (!isset($this->request)) {
            return null;
        }

        return $this->request->input($this->transformKey($name));
    }

    /**
     * @param $name
     * @return array|mixed|null
     * @throws \ReflectionException
     */
    protected function getModelValueAttribute($name)
    {
        $key = $this->transformKey($name);

        if (method_exists($this->model, 'getFormValue')) {
            return $this->model->getFormValue($key);
        }

        return dataget($this->model, $this->transformKey($name));
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function old($name)
    {
        if (isset($this->session)) {
            $key = $this->transformKey($name);
            $payload = $this->session->getOldInput($key);

            if (!is_array($payload)) {
                return $payload;
            }

            if (!in_array($this->type, ['select', 'checkbox'])) {
                if (! isset($this->payload[$key])) {
                    $this->payload[$key] = coll($payload);
                }

                if (!empty($this->payload[$key])) {
                    $value = $this->payload[$key]->shift();
                    return $value;
                }
            }

            return $payload;
        }
    }

    /**
     * @return bool
     */
    public function oldInputIsEmpty()
    {
        return (isset($this->session) && count((array) $this->session->getOldInput()) === 0);
    }

    /**
     * @param $key
     * @return mixed
     */
    protected function transformKey($key)
    {
        return str_replace(['.', '[]', '[', ']'], ['_', '', '.', ''], $key);
    }

    /**
     * @param $html
     * @return HtmlString
     */
    protected function toHtmlString($html)
    {
        return new HtmlString($html);
    }

    /**
     * @return Ultimate
     */
    public function getSessionStore()
    {
        return $this->session;
    }

    /**
     * @param $session
     * @return Formfactory
     */
    public function setSessionStore($session): self
    {
        $this->session = $session;

        return $this;
    }

    /**
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     * @throws \ReflectionException
     */
    public function validate(array $rules = [], array $messages = [], array $customAttributes = [])
    {
        /** @var \Illuminate\Validation\Factory $validator */
        $validator = gi()->make(\Octo\Facades\Validator::class);

        return $validator->make(
            $this->request->all(),
            $rules,
            $messages,
            $customAttributes
        )->validate();
    }

}
