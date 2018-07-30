<?php
namespace App\Repositories;

use App\Services\Repository;

class CategoryRepository extends Repository
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

        $datas['entity']        = 'categories';
        $datas['model']         = \App\Models\Category::class;
        $datas['per_page']      = 15;
        $datas['singular']      = __('crud.entities.category');
        $datas['plural']        = __('crud.entities.categories');
        $datas['create_btn']    = __('crud.general.create') . ' ' . __('crud.general.a_m') . ' ' . $datas['singular'];
        $datas['list_title']    = __('crud.general.index') . ' ' . __('crud.general.of') . ' ' . $datas['plural'];
        $datas['show_title']    = __('crud.general.show') . ' ' . __('crud.general.a_m') . ' ' . $datas['singular'];
        $datas['create_title']  = __('crud.general.add') . ' ' . __('crud.general.a_m') . ' ' . $datas['singular'];
        $datas['edit_title']    = __('crud.general.edit') . ' ' . __('crud.general.a_m') . ' ' . $datas['singular'];

        $datas['fields'] = [
            'name' => [
                'label' => __('crud.fields.name'),
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
