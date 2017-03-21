<?php
    namespace Octo;

    $rights = [
        'admin' => '*',
        'guest' => null,
        'manager' => [
            'compta' => ['read', 'delete']
        ],
    ];

    class Rights
    {
        protected static $rules = [];
        protected static $inline = [];

        public function __construct(array $rules = [])
        {
            self::$rules = $rules;
        }

        public static function can($resource, $action)
        {
            $role = role()->getLabel();

            $inline = isAke(self::$inline, sha1($role . $resource . $action), false);

            if (true === $inline) {
                return true;
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
                        return in_array($action, $action);
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
    }
