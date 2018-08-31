<?php
namespace App\Repositories;

use App\Models\User as UserModel;
use App\Services\Crud;
use App\Services\Model;
use App\Services\Repository;
use Octo\Bcrypt;
use Octo\FastRequest;
use Octo\FastSessionInterface;
use Octo\Ultimate;

class UserRepository extends Repository
{
    /**
     * @var FastRequest
     */
    private $request;

    /**
     * @var Ultimate
     */
    private $session;

    /**
     * @var Model
     */
    private $model;

    /**
     * @var Bcrypt
     */
    private $hash;

    /**
     * @param FastRequest $request
     * @param FastSessionInterface $session
     * @param Bcrypt $hash
     */
    public function __construct(FastRequest $request, FastSessionInterface $session, Bcrypt $hash)
    {
        $this->request  = $request;
        $this->session  = $session;
        $this->hash     = $hash;
        $this->model    = model($session->getUserModel());
    }

    /**
     * @param string $email
     * @param string $password
     * @param null|string $remember
     * @return null
     * @throws \ReflectionException
     */
    public function login(string $email, string $password, ?string $remember = null)
    {
        /** @var null|UserModel $user */
        $user = $this->model->whereEmail($email)->first();

        if (null !== $user) {
            if ($this->hash->check($password, $user->password)) {
                $user->logged_at = now();

                if ('on' === $remember) {
                    $user->remember_token = \Octo\forever();
                }

                $user->save();

                $this->session->set($this->session->getUserKey(), $user->toArray());

                return $user;
            }
        }

        return null;
    }

    public function connect(UserModel $user)
    {
        $this->session->set($this->session->getUserKey(), $user->toArray());
    }


    public function logout(): void
    {
        if ($user = user()) {
            $user->remember_token = null;
            $user->save();
        }

        $this->session->forget($this->session->getUserKey());
    }

    public function policies()
    {
        $entity = 'users';
    }

    public function rules(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function crud()
    {
        $infos = [];

        $infos['entity']        = 'users';
        $infos['model']         = userModel();
        $infos['per_page']      = 15;
        $infos['singular']      = __('crud.entities.user');
        $infos['plural']        = __('crud.entities.users');
        $infos['create_btn']    = __('crud.general.create') . ' ' . __('crud.general.a_m') . ' ' . $infos['singular'];
        $infos['list_title']    = __('crud.general.index') . ' ' . __('crud.general.of') . ' ' . $infos['plural'];
        $infos['show_title']    = __('crud.general.show') . ' ' . __('crud.general.a_m') . ' ' . $infos['singular'];
        $infos['create_title']  = __('crud.general.add') . ' ' . __('crud.general.a_m') . ' ' . $infos['singular'];
        $infos['edit_title']    = __('crud.general.edit') . ' ' . __('crud.general.a_m') . ' ' . $infos['singular'];

        $infos['fields'] = [
            'firstname' => [
                'label' => __('crud.fields.firstname'),
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

            'lastname' => [
                'label' => __('crud.fields.lastname'),
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

            'email' => [
                'label' => __('crud.fields.email'),
                'required' => true,
                'type' => 'email',
                'hooks' => [],

                'listable' => true,
                'viewable' => true,
                'searchable' => true,
                'editable' => true,
                'createable' => true,
                'exportable' => true,
                'sortable' => true,
            ],

            'roles' => [
                'label' => __('crud.fields.roles'),
                'default' => null,
                'required' => true,
                'type' => 'email',
                'hooks' => [
                    'list' => Crud::implode('roles'),
                    'show' => Crud::implode('roles')
                ],

                'listable' => true,
                'viewable' => true,
                'searchable' => true,
                'editable' => false,
                'createable' => false,
                'exportable' => true,
                'sortable' => false,
            ],
        ];

        return $infos;
    }
}
