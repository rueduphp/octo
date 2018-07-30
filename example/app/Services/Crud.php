<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Pluralizer;
use function Octo\aget;
use Octo\Collection;

class Crud
{
    /**
     * @param string $type
     * @param string $field
     * @param $item
     * @param array $crud
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function hook(string $type, string $field, $item = null, array $crud = [])
    {
        $crud = $crud['fields'][$field] ?? [];

        $hooks = aget($crud, 'hooks', []);

        if (null === ($hook = aget($hooks, $type, null)) && !empty($item)) {
            return $item[$field] ?? null;
        }

        return !empty($hook) ? call_func($hook, $item, $crud) : $item;
    }

    /**
     * @param string $field
     * @param Collection $sortable
     * @return bool
     */
    public static function sortable(string $field, Collection $sortable)
    {
        return aget($sortable->all(), $field, false);
    }

    /**
     * @param string $key
     * @param string $glue
     * @return \Closure
     */
    public static function implode(string $key, string $glue = ', ')
    {
        return function ($item) use ($key, $glue) {
            return empty($item->{$key}) ? 'NA' : implode($glue, $item->{$key});
        };
    }

    /**
     * @param string $key
     * @param string $model
     * @param string $field
     * @param bool $html
     * @return \Closure
     */
    public static function related(string $key, string $model, string $field, bool $html = true)
    {
        return function ($item) use ($key, $model, $field, $html) {
            if (empty($item->{$key})) {
                return 'NA';
            }

            $id = $item->{$key};

            /** @var Model $model */
            $model = new $model;

            $row = $model->findOrFail($id);

            if (!fnmatch('*&&*', $field)) {
                $result = $row->{$field} ?? null;
            } else {
                $value = [];
                $parts = explode('&&', $field);

                foreach ($parts as $part) {
                    $value[] = $row->{$part} ?? null;
                }

                $result = implode(' ', $value);
            }

            if (false === $html) {
                return $result;
            }

            $route = static::route($model, 'edit', ['id' => $id]);

            return '<a href="' . $route . '" target="crud_edit">' . $result . '</a>';
        };
    }

    /**
     * @param Model $model
     * @param $action
     * @param array $args
     * @return mixed
     * @throws \Octo\FastContainerException
     * @throws \ReflectionException
     */
    public static function route(Model $model, $action, array $args = [])
    {
        $plural = Pluralizer::plural($model->getTable());

        $route = route('crud.' . get('crud.model') . '.' . $action, $args);

        return str_replace('/crud/' . get('crud.model') . '/', '/crud/' . $plural . '/', $route);
    }

    /**
     * @param string $entity
     * @param Repository $repository
     */
    public static function policies(string $entity, Repository $repository)
    {
        $repository->addPolicy("crud.$entity.index", function () {
            return true;
        })->addPolicy("crud.$entity.show", function () {
            return true;
        })->addPolicy("crud.$entity.create", function () {
            return true;
        })->addPolicy("crud.$entity.store", function () {
            return true;
        })->addPolicy("crud.$entity.edit", function () {
            return true;
        })->addPolicy("crud.$entity.update", function () {
            return true;
        })->addPolicy("crud.$entity.destroy", function () {
            return true;
        })->addPolicy("crud.$entity.export", function () {
            return true;
        })->addPolicy("crud.$entity.search", function () {
            return true;
        })->addPolicy("crud.$entity.duplicate", function () {
            return true;
        });

        $repository->policies();
    }
}
