<?php
namespace App\Repositories;

use App\Models\Category;
use App\Models\User;
use App\Requests\CrudRequest;
use App\Services\Crud;
use App\Services\Repository;
use Octo\Arrays;

class PostRepository extends Repository
{
    public function __construct()
    {
        //
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function crud()
    {
        $datas = [];

        $datas['entity']        = 'posts';
        $datas['model']         = \App\Models\Post::class;
        $datas['per_page']      = 15;
        $datas['singular']      = __('crud.entities.post');
        $datas['plural']        = __('crud.entities.posts');
        $datas['create_btn']    = __('crud.general.create') . ' ' . __('crud.general.a_m') . ' ' . $datas['singular'];
        $datas['list_title']    = __('crud.general.index') . ' ' . __('crud.general.of') . ' ' . $datas['plural'];
        $datas['show_title']    = __('crud.general.show') . ' ' . __('crud.general.a_m') . ' ' . $datas['singular'];
        $datas['create_title']  = __('crud.general.add') . ' ' . __('crud.general.a_m') . ' ' . $datas['singular'];
        $datas['edit_title']    = __('crud.general.edit') . ' ' . __('crud.general.a_m') . ' ' . $datas['singular'];

        $datas['events']['store'] = function (CrudRequest $request) {
            $app = $request->app();
            $newRequest = $request->withAttribute('user_id', $request->user('id'));

            $app->setRequest($newRequest);

            return ['title', 'content', 'category_id', 'user_id'];
        };

        $datas['events']['update'] = function (CrudRequest $request) {
            $app = $request->app();
            $newRequest = $request->withAttribute('user_id', $request->user('id'));

            $app->setRequest($newRequest);

            return ['title', 'content', 'category_id', 'user_id'];
        };

        $datas['fields'] = [
            'title' => [
                'label' => __('crud.fields.title'),
                'required' => true,
                'type' => 'text',
                'hooks' => [],

                'listable' => true,
                'viewable' => true,
                'searchable' => true,
                'editable' => true,
                'createable' => true,
                'exportable' => true,
                'sortable' => true,
            ],

            'content' => [
                'label' => __('crud.fields.content'),
                'required' => true,
                'type' => 'html',
                'hooks' => [],

                'listable' => false,
                'viewable' => true,
                'searchable' => true,
                'editable' => true,
                'createable' => true,
                'exportable' => true,
                'sortable' => true,
            ],

            'category_id' => [
                'label' => __('crud.fields.category_id'),
                'required' => false,
                'type' => 'select',
                'hooks' => [
                    'options' => function () {
                        return Category::list('name');
                    },
                    'list' => Crud::related('category_id', Category::class, 'name'),
                    'show' => Crud::related('category_id', Category::class, 'name'),

                ],

                'listable' => true,
                'viewable' => true,
                'searchable' => true,
                'editable' => true,
                'createable' => true,
                'exportable' => true,
                'sortable' => true,
            ],

            'user_id' => [
                'label' => __('crud.fields.user_id'),
                'required' => true,
                'type' => 'select',
                'hooks' => [
                    'options' => function () {
                        return Arrays::pluck(User::all()->toArray(), 'firstname&&lastname', 'id');
                    },
                    'list' => Crud::related('user_id', User::class, 'firstname&&lastname'),
                    'show' => Crud::related('user_id', User::class, 'firstname&&lastname'),
                ],

                'listable' => true,
                'viewable' => true,
                'searchable' => true,
                'editable' => false,
                'createable' => false,
                'exportable' => true,
                'sortable' => true,
            ],
        ];
        
        return $datas;
    }

    /**
     * @return bool
     */
    public function can(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [];
    }

    public function policies()
    {
        return null;
    }
}
