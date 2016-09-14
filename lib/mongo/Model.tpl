<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2015 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Octo;

    use Octo\Mongo\Crud as bundleCrud;
    use Octo\Mongo\Db;

    return [
        /* GENERAL SETTINGS */
        'singular'                  => '##singular##',
        'plural'                    => '##plural##',
        'default_order'             => '##default_order##',
        'default_order_direction'   => 'ASC',
        'items_by_page'             => 25,
        'display'                   => false,
        'soft_delete'               => ##soft_delete##,
        'many'                      => [],

        /* EVENTS */
        'before_create'             => ##before_create##,
        'after_create'              => ##after_create##,

        'before_read'               => ##before_read##,
        'after_read'                => ##after_read##,

        'before_update'             => ##before_update##,
        'after_update'              => ##after_update##,

        'before_delete'             => ##before_delete##,
        'after_delete'              => ##after_delete##,

        'before_list'               => ##before_list##,
        'after_list'                => ##after_list##,

        'indices'                   => [
            'uniques'               => ##uniques##,
            'foreigns'              => ##foreigns##
        ],

        /* extends the model Active Record */
        'functions'                 => [],

        /* FIELDS */
        'fields'                    => [
            ##fields##
        ]
    ];
