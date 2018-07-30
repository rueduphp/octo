<?php
namespace App\Services;

use Illuminate\Support\HtmlString;
use Octo\Facades\Form;
use Octo\FastRequest;
use Octo\FastTwigExtension;
use function Octo\aget;
use function Octo\gi;
use ReflectionException;

class FormCrud
{
    /**
     * @var Form
     */
    private $form;

    /**
     * @var Model
     */
    private $model;

    /**
     * @var bool
     */
    private $placeholder = false;

    /**
     * @var bool
     */
    private $exists = false;

    private $errors;

    /**
     * @var array
     */
    private $infos;

    public function __construct(Form $form)
    {
        $this->form = $form;
    }

    /**
     * @param Model $model
     * @param array $infos
     * @param array $options
     * @return HtmlString
     * @throws ReflectionException
     */
    public function open(Model $model, array $infos, $errors, array $options = [])
    {
        if (!isset($options['method'])) {
            if (true === $model->exists) {
                $this->exists = true;
                $options['method'] = 'PUT';
            } else {
                $options['method'] = 'POST';
            }
        }

        if (!isset($options['url'])) {
            if (true === $this->exists) {
                $options['url'] = route('crud.' . $infos['entity'] . ".edit", ['id' => $model->id]);
            } else {
                $options['url'] = route('crud.' . $infos['entity'] . ".create");
            }
        }

        if (isset($options['placeholder']) && $options['placeholder'] === true) {
            $this->placeholder = true;
        }

        $this->errors = $errors;
        $this->infos = $infos;
        $this->model = $model;

        return $this->form::model($model, $options);
    }

    /**
     * @param string $key
     * @param $value
     * @param null|string $label
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function field(
        string $key,
        $value,
        ?string $label = null,
        array $options = [],
        array $attributes = []
    ) {
        /** @var FastTwigExtension $twig */
        $twig = app(FastTwigExtension::class);

        if (is_array($value)) {
            $options = is_array($options) && !empty($options) ? $options : [];
            $options += $value;
            $value = null;
        }

        if (is_array($label)) {
            $options = is_array($options) && !empty($options) ? $options : [];
            $options += $label;
            $label = null;
        }

        $value = $this->revealValue($key, $value);

        if (isset($options['required'])) {
            $attributes['required'] = 'required';
            unset($options['required']);
        }

        return $twig->field(['errors' => $this->errors], $key, $value, $label, $options, $attributes);
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function text(string $key, $value = null, array $options = [], array $attributes = [])
    {
        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function textarea(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'textarea';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function html(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'textarea';
        $options['class'] = $options['class'] ?? '';

        $options['class'] .= ' wysiwyg';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function hidden(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'hidden';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function email(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'email';

        return $this->field(
            $key,
            $value,
            $options['label']  ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function password(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'password';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function date(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'date';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function time(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'time';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function datetime(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'time';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function file(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'file';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function image(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'file';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function checkbox(string $key, $value = null, array $options = [], array $attributes = [])
    {
        $options['type'] = 'checkbox';

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param string $key
     * @param null $value
     * @param array $data
     * @param array $options
     * @param array $attributes
     * @return string
     * @throws ReflectionException
     */
    public function select(
        string $key,
        $value = null,
        array $data = [],
        array $options = [],
        array $attributes = []
    ) {
        $options['type'] = 'select';

        if (empty($data) && isset($this->infos['fields'][$key]['hooks']['options'])) {
            $data = gi()->makeClosure($this->infos['fields'][$key]['hooks']['options']);
        }

        $data = ['' => __('crud.general.select')] + $data;

        $options['options'] = $data;

        return $this->field(
            $key,
            $value,
            $options['label'] ?? $this->infos['fields'][$key]['label'],
            $options,
            $attributes
        );
    }

    /**
     * @param null|string $name
     * @return string
     * @throws ReflectionException
     */
    public function submit(?string $name = null)
    {
        if (!$name) {
            $name = $this->exists ? __('crud.general.edit') : __('crud.general.add');
        }

        return '<button type="submit" class="btn btn-primary">' . ucfirst($name) . '</button>';
    }

    /**
     * @param null|string $name
     * @return string
     * @throws ReflectionException
     */
    public function cancel(?string $name = null)
    {
        if (!$name) {
            $name = __('crud.general.cancel');
        }

        return '<button type="reset" class="btn btn-default">' . ucfirst($name) . '</button>';
    }

    /**
     * @return mixed
     */
    public function close()
    {
        return $this->form::close();
    }

    /**
     * @param Form $form
     * @return FormCrud
     */
    public function setForm(Form $form): FormCrud
    {
        $this->form = $form;

        return $this;
    }

    /**
     * @param bool $placeholder
     * @return FormCrud
     */
    public function setPlaceholder(bool $placeholder): FormCrud
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * @param bool $exists
     * @return FormCrud
     */
    public function setExists(bool $exists): FormCrud
    {
        $this->exists = $exists;

        return $this;
    }

    /**
     * @param array $infos
     * @return FormCrud
     */
    public function setInfos(array $infos): FormCrud
    {
        $this->infos = $infos;

        return $this;
    }

    /**
     * @param string $key
     * @param null $value
     * @return mixed|null
     * @throws ReflectionException
     * @throws \Exception
     */
    protected function revealValue(string $key, $value = null)
    {
        $request = new FastRequest;

        if (empty($value)) {
            if (in_array($request->getMethod(), ['POST', 'PUT'])) {
                return old($key);
            } else {
                if ($this->exists) {
                    $hooks = $this->infos['fields'][$key]['hooks'] ?? [];

                    if (null !== ($hook = aget($hooks, 'value', null))) {
                        return call_func($hook, $this->model, $key, $value);
                    }

                    return $this->model->{$key};
                }
            }
        }

        return $value;
    }
}
