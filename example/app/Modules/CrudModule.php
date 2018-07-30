<?php
namespace App\Modules;

use App\Facades\Redirect;
use App\Facades\Route;
use App\Models\User;
use App\Requests\CrudRequest;
use App\Services\Crud;
use App\Services\Model;
use App\Services\Module;
use App\Services\Repository;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Pluralizer;
use function Octo\aget;
use Octo\Arrays;
use Octo\Elegant;
use ReflectionException;

class CrudModule extends Module
{
    /** @var User */
    private $user;

    /** @var Model|Elegant */
    private $model;

    /** @var Repository  */
    private $repository;

    /** @var string */
    private $name;

    /** @var string */
    private $table;

    /** @var array */
    private $crud;

    /**
     * @throws \Octo\Exception
     * @throws \Octo\FastContainerException
     * @throws ReflectionException
     */
    public function __construct()
    {
        parent::__construct();

        $this->user = $this->request->user();

        if ($this->request->contains('crud')) {
            $model = Pluralizer::singular($this->revealModel());
            $this->model = $this->getModel($model);
            $this->repository = $this->getRepository($model);
            $this->name = $model;
            $this->crud = $this->makeCrud($this->repository->crud());
            Crud::policies($this->crud['entity'], $this->repository);
            $this->repository->policies();
            $this->table = $this->crud['entity'];
        }
    }

    /**
     * @throws ReflectionException
     */
    public function routes()
    {
        if ($this->model instanceof Model || $this->model instanceof Elegant) {
            $name = $this->crud['entity'];

            Route::get("crud/$name", [$this, "index"], 'crud.' . $name . ".index");
            Route::get("crud/$name/{id}/show", [$this, "show"], 'crud.' . $name . ".show");
            Route::get("crud/$name/create", [$this, "create"], 'crud.' . $name . ".create");
            Route::post("crud/$name/create", [$this, "store"]);
            Route::get("crud/$name/{id}/edit", [$this, "edit"], 'crud.' . $name . ".edit");
            Route::put("crud/$name/{id}/edit", [$this, "update"]);
            Route::delete("crud/$name/{id}/destroy", [$this, "destroy"], 'crud.' . $name . ".destroy");
        }
    }

    /**
     * @return mixed
     * @throws ReflectionException
     */
    public function index()
    {
        if (true === ($response = $this->untilIsGranted("crud.$this->table.index"))) {
            $table = $this->table;
            $orderBy = $this->request->get('order_by', 'id');
            $order = strtoupper($this->request->get('order', 'ASC'));
            $crud = $this->crud;
            $items = $this->model->query()->orderBy($orderBy, $order)->paginate($crud['per_page']);

            $path = route("crud.$table.index");

            if ($this->request->get('order_by')) {
                $path .= '?order_by=' . $orderBy;

                if ('DESC' === $order) {
                    $path .= '&order=' . $order;
                }
            }

            $items->withPath($path);

            $model = Pluralizer::singular($table);

            return $this->view('crud.index', compact('table', 'crud', 'items', 'model', 'orderBy', 'order'));
        }

        return $response;
    }

    /**
     * @param $id
     * @return bool|Response
     * @throws ReflectionException
     */
    public function show($id)
    {
        if (true === ($response = $this->untilIsGranted("crud.$this->table.show"))) {
            $crud = $this->crud;
            $table = $this->table;
            $item = $this->model->findOrFail($id);

            return $this->view('crud.show', compact('crud', 'table', 'item'));
        }

        return $response;
    }

    /**
     * @param $id
     * @return bool|Response
     * @throws ReflectionException
     */
    public function create()
    {
        if (true === ($response = $this->untilIsGranted("crud.$this->table.create"))) {
            $crud   = $this->crud;
            $table  = $this->table;
            $item   = $this->model;

            return $this->view('crud.create', compact('crud', 'table', 'item'));
        }

        return $response;
    }

    /**
     * @param $id
     * @return bool|Response
     * @throws ReflectionException
     */
    public function edit($id)
    {
        if (true === ($response = $this->untilIsGranted("crud.$this->table.edit"))) {
            $item   = $this->model->findOrFail($id);
            $crud   = $this->crud;
            $table  = $this->table;

            return $this->view('crud.edit', compact('crud', 'table', 'item'));
        }

        return $response;
    }

    /**
     * @param CrudRequest $request
     * @return bool|\GuzzleHttp\Psr7\MessageTrait|Response
     * @throws ReflectionException
     */
    public function store(CrudRequest $request)
    {
        /** @var Model $item */
        $item = $this->model;
        $crud = $this->crud;

        if (true === ($response = $this->untilIsGranted("crud.$this->table.store"))) {
            if (false === $request->hasFailed) {
                $fields = array_keys($crud['createable']->all());

                if (null !== ($event = $this->getEventHook('store'))) {
                    $fields = call_func($event, $request, $this);
                }

                $item->create($request->onlyTab(...$fields));

                return Redirect::success(__('crud.general.success_store'))->route("crud.$this->table.index");
            }

            $table      = $this->table;
            $no_flash   = true;

            return $this->view('crud.create', compact('crud', 'table', 'item', 'no_flash'));
        }

        return $response;
    }

    /**
     * @param $id
     * @param CrudRequest $request
     * @throws ReflectionException
     */
    public function update($id, CrudRequest $request)
    {
        /** @var Model $item */
        $item = $this->model->findOrFail($id);
        $crud = $this->crud;

        if (true === ($response = $this->untilIsGranted("crud.$this->table.update"))) {
            if (false === $request->hasFailed) {
                $fields = array_keys($crud['editable']->all());

                if (null !== ($event = $this->getEventHook('update'))) {
                    $fields = call_func($event, $request, $this);
                }

                $item->update($request->onlyTab(...$fields));

                return Redirect::success(__('crud.general.success_update'))->route("crud.$this->table.index");
            }

            $table      = $this->table;
            $no_flash   = true;

            return $this->view('crud.edit', compact('crud', 'table', 'item', 'no_flash'));
        }

        return $response;
    }

    /**
     * @param $id
     * @return bool|\GuzzleHttp\Psr7\MessageTrait|Response
     * @throws ReflectionException
     */
    public function destroy($id)
    {
        if (true === ($response = $this->untilIsGranted("crud.$this->table.destroy"))) {
            if (null !== ($event = $this->getEventHook('destroy'))) {
                call_func($event, $id, $this);
            }

            $this->model->findOrFail($id)->delete();

            return Redirect::success(__('crud.general.success_destroy'))->route("crud.$this->table.index");
        }

        return $response;
    }

    private function makeCrud(array $infos)
    {
        $coll = acoll($infos['fields']);

        $infos['listable']      = $coll->isTrue('listable');
        $infos['viewable']      = $coll->isTrue('viewable');
        $infos['searchable']    = $coll->isTrue('searchable');
        $infos['editable']      = $coll->isTrue('editable');
        $infos['createable']    = $coll->isTrue('createable');
        $infos['exportable']    = $coll->isTrue('exportable');
        $infos['sortable']      = $coll->isTrue('sortable');

        return $infos;
    }

    /**
     * @return string
     */
    protected function revealModel()
    {
        $uri = $this->request->getUri();
        $parts = explode('/crud/', explode('?', $uri)[0]);
        array_shift($parts);

        $model = current($parts);

        if (fnmatch('*/*', $model)) {
            $model = Arrays::first(explode('/', $model));
        }

        set('crud.model', $model);

        return $model;
    }

    protected function getEventHook(string $name)
    {
        $events = aget($this->crud, 'events', []);

        return aget($events, $name, null);
    }
}