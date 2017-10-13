<?php
    namespace Octo;

    class Rights
    {
        protected static $rules = [];
        protected static $inline = [];

        public function __construct(array $rules = [])
        {
            self::$rules = $rules;
        }

        public static function register(array $rules = [])
        {
            new static($rules);
        }

        public static function can($resource, $action)
        {
            $role = role()->getLabel();

            $inline = isAke(self::$inline, sha1($role . $resource . $action), 'octodummy');

            if ('octodummy' != $inline) {
                return value($inline);
            }

            $resources = isAke(self::$rules, $role, []);

            if (!empty($resources)) {
                if ('*' == $resources) {
                    return true;
                }

                if (is_array($resources)) {
                    $actions = isAke($resources, $resource, []);

                    if (!empty($actions)) {
                        if ('*' == $actions) {
                            return true;
                        }
                    }

                    if (is_array($actions)) {
                        foreach ($actions as $ka => $va) {
                            if ($ka == $action) {
                                return value($va);
                            } elseif (is_numeric($ka) && $va == $action) {
                                return true;
                            }
                        }
                    }
                }
            }

            return false;
        }

        public static function allow($role, $resource, $action)
        {
            $key = sha1($role, $resource, $admin);

            self::$inline[$key] = true;
        }

        public static function disallow($role, $resource, $action)
        {
            $key = sha1($role . $resource . $action);

            self::$inline[$key] = false;
        }

        public static function add($role, $resource, $action, $status = true)
        {
            $key = sha1($role . $resource . $action);

            self::$inline[$key] = $status;
        }
    }

    /*
        Exemple

        Rights::register([
            'admin' => '*',
            'guest' => null,
            'manager' => [
                'compta' => ['read' => function () {
                    return user()->business_unit == 'CTO';
                }, 'delete']
            ],
        ]);

        Rights::allow('manager', 'compta', 'copy');
        Rights::disallow('manager', 'compta', 'delete');
    */


