<?php
    use Octo\Facade;

    class Request extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'request';
        }
    }

    class Memory extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'memory';
        }
    }

    class Redys extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'redis';
        }
    }

    class Db extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'db';
        }
    }

    class Session extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'session';
        }
    }

    class Cache extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'cache';
        }
    }

    class Routing extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'router';
        }
    }

    class Route extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'routage';
        }
    }

    class Renderer extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'renderer';
        }
    }

    class ES extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'elasticsearch';
        }
    }

    class Flash extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'flash';
        }
    }

    class Redirect extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'redirect';
        }
    }

    class Auth extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'auth';
        }
    }

    class User extends Facade
    {
        /**
         * @return string
         */
        public static function getNativeClass(): string
        {
            return 'user';
        }
    }

    if (!function_exists('handler')) {
        function handler($concern, $object)
        {
            $handlers = Octo\Registry::get('core.$handlers', Octo\Registry::get('core.all.binds', []));

            $handlers[$concern] = $object;
            Octo\Registry::set('core.$handlers', $handlers);
        }
    }

    if (!function_exists('in')) {
        /**
         * @param array ...$args
         * @return mixed|null|object|\Octo\In
         */
        function in(...$args)
        {
            $nargs = count($args);

            $in = Octo\In::self();

            if (1 === $nargs) {
                return $in[current($args)];
            } elseif (2 === $nargs) {
                $in[current($args)] = end($args);
            }

            return $in;
        }
    }

    if (!function_exists('handled')) {
        function handled($concern)
        {
            $handlers = Octo\Registry::get('core.$handlers', Octo\Registry::get('core.all.binds', []));

            return Octo\isAke($handlers, $concern, null);
        }
    }

    if (!function_exists('crypto')) {
        /**
         * @param string $string
         * @param array $options
         * @return mixed
         */
        function crypto(string $string, array $options = [])
        {
            return in('hash')->make($string, $options);
        }
    }

    if (!function_exists('app')) {
        /**
         * @param mixed ...$args
         * @return mixed|null|object|\Octo\In
         */
        function app(...$args)
        {
            return Octo\in(...$args);
        }
    }

    if (!function_exists('call')) {
        function call()
        {
            $args = func_get_args();

            $callable = array_shift($args);

            return Octo\call(
                $callable,
                $args
            );
        }
    }

    if (!function_exists('toClosure')) {
        function toClosure($concern)
        {
            return function () use ($concern) {
                return $concern;
            };
        }
    }

    if (!function_exists('factory')) {
        function factory($class, $count = 1, $lng = 'fr_FR')
        {
            return Octo\factory($class, $count, $lng);
        }
    }

    if (!function_exists('memoryFactory')) {
        function memoryFactory($class, $count = 1, $lng = 'fr_FR')
        {
            return Octo\memoryFactory($class, $count, $lng);
        }
    }

    if (!function_exists('partial')) {
        function partial($file, $args = [])
        {
            return Octo\partial($file, $args);
        }
    }

    if (!function_exists('guard')) {
        function guard($em = 'user')
        {
            return Octo\guard($em);
        }
    }

    if (!function_exists('value')) {
        function value($value)
        {
            return Octo\File::value($value);
        }
    }

    if (!function_exists('auth')) {
        function auth($em = 'user')
        {
            return Octo\guard($em);
        }
    }

    if (!function_exists('vue')) {
        function vue($file, $args = [], $status = 200)
        {
            return Octo\vue($file, $args, $status);
        }
    }

    if (!function_exists('em')) {
        function em($model, $engine = 'engine', $force = false)
        {
            return Octo\em($model, $engine, $force);
        }
    }

    if (!function_exists('makeOnce')) {
        function makeOnce()
        {
            return call_user_func_array('\\Octo\\makeOnce', func_get_args());
        }
    }

    if (!function_exists('dd')) {
        function dd()
        {
            return call_user_func_array('\\Octo\\dd', func_get_args());
        }
    }

    if (!function_exists('vd')) {
        function vd()
        {
            return call_user_func_array('\\Octo\\vd', func_get_args());
        }
    }

    if (!function_exists('isAke')) {
        function isAke($array, $k, $d = [])
        {
            return Octo\isAke($array, $k, $d);
        }
    }

    if (!function_exists('lib')) {
        function lib($lib, $args = [], $singleton = false)
        {
            return Octo\lib($lib, $args, $singleton);
        }
    }

    if (!function_exists('maker')) {
        function maker($make, $args = [], $singleton = true)
        {
            return Octo\maker($make, $args, $singleton);
        }
    }

    if (!function_exists('image')) {
        function image($config = null)
        {
            return Octo\image($config);
        }
    }

    if (!function_exists('forever')) {
        function forever($ns = 'user')
        {
            return Octo\forever($ns);
        }
    }

    if (!function_exists('item')) {
        function item($attributes = [])
        {
            return Octo\item($attributes);
        }
    }

    if (!function_exists('isRoute')) {
        function isRoute($name, array $args = [])
        {
            return Octo\isRoute($name, $args);
        }
    }

    if (!function_exists('path')) {
        function path($k = null, $v = null, $d = null)
        {
            return Octo\path($k, $v, $d);
        }
    }

    if (!function_exists('coll')) {
        function coll(array $data = [])
        {
            return Octo\coll($data);
        }
    }

    if (!function_exists('memory')) {
        function memory()
        {
            return call_user_func_array('\\Octo\\dbMemory', func_get_args());
        }
    }

    if (!function_exists('dyn')) {
        function dyn($class = null)
        {
            return Octo\dyn($class);
        }
    }

    if (!function_exists('lower')) {
        function lower()
        {
            return call_user_func_array('\\Octo\\lower', func_get_args());
        }
    }

    if (!function_exists('octo')) {
        function octo($k = null, $v = 'octodummy')
        {
            $app = Octo\context('app');

            if ($k) {
                if ('octodummy' == $v) {
                    return $app[$k];
                }

                $app[$k] = $v;
            }

            return $app;
        }
    }

    if (!function_exists('upper')) {
        function upper()
        {
            return call_user_func_array('\\Octo\\upper', func_get_args());
        }
    }

    if (!function_exists('o')) {
        function o(array $o = [])
        {
            return Octo\o($o);
        }
    }

    if (!function_exists('start_session')) {
        function start_session($ns = 'web')
        {
            return Octo\start_session($ns);
        }
    }

    if (!function_exists('session')) {
        function session($context = 'web')
        {
            echo Octo\session($context);
        }
    }

    if (!function_exists('_')) {
        function _($segment, $args = [], $locale = null)
        {
            echo Octo\trans($segment, $args, $locale);
        }
    }

    if (!function_exists('trans')) {
        function trans($segment, $args = [], $locale = null)
        {
            return Octo\trans($segment, $args, $locale);
        }
    }

    if (!function_exists('lng')) {
        function lng($context = 'web')
        {
            return Octo\lng($context);
        }
    }

    if (!function_exists('request')) {
        function request($k = null, $d = null)
        {
            return Octo\Input::method($k, $d);
        }
    }

    if (!function_exists('url')) {
        function url($name, array $args = [])
        {
            return Octo\url($name, $args);
        }
    }

    if (!function_exists('redirect')) {
        function redirect()
        {
            return call_user_func_array('\\Octo\\redirect', func_get_args());
        }
    }

    if (!function_exists('auth')) {
        function auth($em = 'user')
        {
            return Octo\auth($em);
        }
    }

    if (!function_exists('controller')) {
        function controller()
        {
            return call_user_func_array('\\Octo\\controller', func_get_args());
        }
    }

    if (!function_exists('injector')) {
        function injector()
        {
            static $injector = null;

            if (!$injector) {
                $injector = maker(Octo\Injector::class);
            }

            return $injector;
        }
    }

    if (!function_exists('current_url')) {
        function current_url()
        {
            return call_user_func_array('\\Octo\\current_url', func_get_args());
        }
    }

    if (!function_exists('layout')) {
        function layout($file, $page = null, $sections = null)
        {
            return Octo\layout($file, $page, $sections);
        }
    }

    if (!function_exists('actual')) {
        function actual($key = null, $value = null)
        {
            return Octo\actual($key, $value);
        }
    }

    if (!function_exists('back')) {
        function back($url = null)
        {
            return Octo\back($url);
        }
    }

    if (!function_exists('action')) {
        function action($action, $args = [])
        {
            $controller = actual('controller');

            $controller->action = $action;

            $callable = [$controller, $action];

            return call_user_func_array($callable, $args);
        }
    }

    if (!function_exists('forward')) {
        function forward($url, $method = 'GET')
        {
            $url = url('/' . $url);

            $_SERVER['REQUEST_URI']     = $url;
            $_SERVER['REQUEST_METHOD']  = $method;

            lib('router')->run();

            exit;
        }
    }

    if (!function_exists('user')) {
        function user($k = null)
        {
            $user = auth()->user();

            if (!$user) {
                $user = [];
            }

            if ($k) {
                return isAke($user, $k, null);
            }

            return $user;
        }
    }

    if (!function_exists('magic')) {
        function magic($class, array $array = [])
        {
            return Octo\magic($class, $array);
        }
    }

    if (!function_exists('context')) {
        function context($context, array $data = [])
        {
            return Octo\context($context, $data);
        }
    }

    if (!function_exists('mockery')) {
        function mockery($mock, array $args = [])
        {
            return Octo\mockery($mock, $args);
        }
    }

    if (!function_exists('message')) {
        function message()
        {
            return call_user_func_array('\\Octo\\message', func_get_args());
        }
    }

    if (!function_exists('mailer')) {
        function mailer()
        {
            return call_user_func_array('\\Octo\\mailer', func_get_args());
        }
    }

    if (!function_exists('mailto')) {
        function mailto()
        {
            return call_user_func_array('\\Octo\\mailto', func_get_args());
        }
    }

    if (!function_exists('queue')) {
        function queue()
        {
            return call_user_func_array('\\Octo\\queue', func_get_args());
        }
    }

    if (!function_exists('listenQueue')) {
        function listenQueue()
        {
            return call_user_func_array('\\Octo\\listenQueue', func_get_args());
        }
    }

    if (!function_exists('bgQueue')) {
        function bgQueue(...$args)
        {
            return call_user_func_array('\\Octo\\bgQueue', $args);
        }
    }

    if (!function_exists('appenv')) {
        function appenv($key, $default = null)
        {
            return Octo\appenv($key, $default);
        }
    }

    if (!function_exists('appenv')) {
        function be($user, $ns = 'web')
        {
            return Octo\be($user, $ns);
        }
    }

    if (!function_exists('superdi')) {
        function superdi()
        {
            return Octo\superdi();
        }
    }

    if (!function_exists('sdi')) {
        function sdi()
        {
            return Octo\superdi();
        }
    }

    if (!class_exists('Core')) {
        class Core
        {
            public static function instance()
            {
                return context('app');
            }

            public static function get()
            {
                return context('app');
            }

            public static function __callStatic($method, $args)
            {
                return call_user_func_array([context('app'), $method], $args);
            }
        }
    }

    if (!class_exists('Octo')) {
        class Octo
        {
            public static function instance()
            {
                return context('app');
            }

            public static function get()
            {
                return context('app');
            }

            public static function __callStatic($method, $args)
            {
                return call_user_func_array([context('app'), $method], $args);
            }
        }
    }

    if (!class_exists('Registry')) {
        /**
         * @method static mixed get(string $key, $default = null)
         * @method static Octo\Now set(string $key, $value)
         * @method static bool has(string $key)
         * @method static bool del(string $key)
         * @method static int incr(string $key)
         * @method static int decr(string $key)
         */
        class Registry
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\Now)->{$method}(...$args);
            }
        }
    }

    if (!class_exists('Strings')) {
        class Strings
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\Inflector)->{$method}(...$args);
            }
        }
    }

    if (!class_exists('Dir')) {
        class Dir
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\File)->{$method}(...$args);
            }
        }
    }

    if (!class_exists('Utils')) {
        class Utils
        {
            /**
             * @param $m
             * @param $a
             * @return mixed
             * @throws Exception
             */
            public static function __callStatic($m, $a)
            {
                if (function_exists('\\Octo\\' . $m)) {
                    return call_user_func_array('\\Octo\\' . $m, $a);
                }

                if (function_exists($m)) {
                    return call_user_func_array($m, $a);
                }

                throw new Exception("The method $m does not exist!");
            }
        }
    }

    if (!class_exists('Helpers')) {
        class Helpers
        {
            public static function __callStatic($m, $a)
            {
                if (function_exists('\\Octo\\' . $m)) {
                    return call_user_func_array('\\Octo\\' . $m, $a);
                }

                if (function_exists($m)) {
                    return call_user_func_array($m, $a);
                }

                throw new Exception("The method $m does not exist!");
            }
        }
    }
