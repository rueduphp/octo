<?php
    namespace Octo;

    class ControllerBase extends FrontController
    {
        protected $app      = null;
        protected $title    = '';
        protected $models   = [];

        public function init()
        {
            $this->auth = session('web')->getUser() !== null;

            $this->csrf = token();

            session('web')->setLastCsrf(session('web')->getCsrf());
            session('web')->setCsrf($this->csrf);

            $actions = get_class_methods($this);

            if (in_array('boot', $actions)) {
                callMethod($this, 'boot');
            }

            actual('controller', $this);
        }

        public function getCsrf()
        {
            return $this->csrf;
        }

        public function getLastCsrf()
        {
            return session('web')->getLastCsrf();
        }

        public function url($url = '/')
        {
            return Registry::get('octo.subdir', '') . $url;
        }

        public function urlFor()
        {
            return call_user_func_array('\\Octo\urlFor', func_get_args());
        }

        public function isRoute()
        {
            return call_user_func_array('\\Octo\isRoute', func_get_args());
        }

        public function minify($asset)
        {
            return substr($this->url($asset), 1);
        }

        public function redirect($url)
        {
            $url = $this->url('/' . $url);
            header("Location: $url");

            exit;
        }

        public function flash($k, $v = null)
        {
            if (is_null($v)) {
                $val = session('flash')->get($k);
                session('flash')->erase($k);

                return $val;
            } else {
                return session('flash')->set($k, $v);
            }
        }

        public function hasFlash($k)
        {
            return session('flash')->has($k);
        }

        public function post()
        {
            return coll($_POST);
        }

        public function server()
        {
            return coll($_SERVER);
        }

        public function request()
        {
            return coll($_REQUEST);
        }

        public function load($class = null, array $args = [])
        {
            if (empty($class)) {
                return App::getInstance();
            }

            if ($class[0] != '\\') {
                return lib($class, [$args]);
            }

            return App::getInstance()->make($class, $args);
        }

        public function forward($url, $method = 'GET')
        {
            $url = $this->url('/' . $url);

            $_SERVER['REQUEST_URI']     = $url;
            $_SERVER['REQUEST_METHOD']  = $method;

            lib('router')->run(
                (new \ReflectionClass(get_called_class()))
                ->getNamespaceName()
            );

            exit;
        }

        public function user()
        {
            $callable = '\\Octo\\user';

            return call_user_func_array($callable, func_get_args());
        }

        public function lng()
        {
            return lng();
        }

        public function stayConnected()
        {
            $account = System::Account()->where(['forever', '=', forever()])->first();

            if ($account) {
                $stay_connected = isAke($account, 'stay_connected', 0);

                if (1 == $stay_connected) {
                    $this->auth = true;
                    session('web')->setUser($account);

                    return false;
                }
            } else {
                $this->auth = false;
            }

            return true;
        }

        public function e($what)
        {
            echo stripslashes($what);
        }

        public static function limit($str, $length = 30, $end = '&hellip;')
        {
            $this->e(Inflector::limit($str, $length, $end));
        }

        public function lang($str, $id = null)
        {
            $key = $this->_name . '.' . $this->action;

            if (is_null($id)) {
                $id = Inflector::urlize($str, '.');
            }

            $key .= '.' . $id;

            return trad($str, $key);
        }

        public function trad($str, $id = null)
        {
            $this->e($str);
            // echo $this->lang($str, $id);
        }

        public function accessError()
        {
            $this->flash('notif.error', "L'accès à cette page est impossible.", 'access.error');
            $this->forward('');
        }

        public function success(array $array = [])
        {
            $array['status'] = 200;
            Api::render($array);
        }

        public function error(array $array = [])
        {
            $array['status'] = 500;
            Api::render($array);
        }

        public function __call($m, $a)
        {
            if (function_exists('\\Octo\\' . $m)) {
                return call_user_func_array('\\Octo\\' . $m, $a);
            }

            return call_user_func_array(['\\Octo\\OctaliaHelpers', $m], $a);
        }

        public static function __callStatic($m, $a)
        {
            if (function_exists('\\Octo\\' . $m)) {
                return call_user_func_array('\\Octo\\' . $m, $a);
            }

            return call_user_func_array(['\\Octo\\OctaliaHelpers', $m], $a);
        }

        public function cache()
        {
            $args = func_get_args();

            if (is_string($args[0])) {
                viewCache(array_shift($args), array_shift($args), array_shift($args), array_shift($args));
            } else {
                viewCacheObject(array_shift($args), array_shift($args), array_shift($args));
            }
        }

        public function cacheassets($fields, $type = 'css')
        {
            if (is_string($fields)) {
                if (!strstr($fields, ',')) {
                    $fields = [$fields];
                } else {
                    $fields = explode(',', str_replace([', ', ' ,'], ',', $fields));
                }
            }

            $implode = 'css' == $type ? ' ' : "\n";

            $key = 'min.' . $type . '.' . sha1(serialize($fields));
            $keyage = 'min.age.' . $type . '.' . sha1(serialize($fields));

            $cached = fmr('minify')->get($key);

            if ($cached) {
                $age = fmr('minify')->get($keyage, time());
                $continue = true;

                foreach ($fields as $field) {
                    $field = path('public') . $field;

                    if (is_file($field)) {
                        $aged = filemtime($field);

                        $continue = $aged <= $age;
                    }

                    if (!$continue) {
                        break;
                    }
                }

                if ($continue) {
                    $file = path('public') . DS . 'cache' . DS . $age . '.' . $type;

                    if (!file_exists($file) || is_readable($file)) {
                        File::delete($file);

                        $content = [];

                        foreach ($fields as $field) {
                            $field = path('public') . $field;

                            if (is_file($field) && is_readable($field)) {
                                if ('css' == $type) $content[] = str_replace(["\r", "\n", "\t"], "", File::read($field));
                                else $content[] = File::read($field);
                            }
                        }

                        File::put($file, implode($implode, $content));
                    }

                    return Registry::get('octo.subdir', '') . '/cache/' . $age . '.' . $type;
                }
            }

            $a = hash(time());

            $file = path('public') . DS . 'cache' . DS . $a . '.' . $type;
            File::delete($file);

            $content = [];

            foreach ($fields as $field) {
                $field = path('public') . $field;

                if (is_file($field) && is_readable($field)) {
                    if ('css' == $type) $content[] = str_replace(["\r", "\n", "\t"], "", File::read($field));
                    else $content[] = File::read($field);
                }
            }

            File::put($file, implode($implode, $content));

            fmr('minify')->set($key, true);
            fmr('minify')->set($keyage, $a);

            return Registry::get('octo.subdir', '') . '/cache/' . $a . '.' . $type;
        }

        public function burst($asset)
        {
            return burst($asset);
        }

        protected function model($model, $force = false)
        {
            $model = Strings::uncamelize($model);

            if (!isset($this->models[$model]) || true === $force) {
                $this->models[$model] = em($model);
            }

            return $this->models[$model];
        }

        public function em($model, $engine = 'engine', $force = false)
        {
            return em($model, $engine, $force);
        }

        public function middleware($class)
        {
            if (is_string($class)) {
                $class = maker($class, [], false);
            }

            return $class->handle();
        }

        public function action($action)
        {
            $this->action = $action;
            $this->$action();
        }

        public function etag($time)
        {
            $etag = 'W/"' . md5($time) . '"';

            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $time) . " GMT");
            header('Cache-Control: public, max-age=604800');
            header("Etag: $etag");

            if ((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $time) || (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $etag === trim($_SERVER['HTTP_IF_NONE_MATCH']))) {
                header('HTTP/1.1 304 Not Modified');

                exit();
            }
        }

        public function routing($route, $args = [])
        {
            $this->go($this->urlFor($route, $args));
        }

        public function route($route, $args = [])
        {
            $this->go($this->urlFor($route, $args));
        }

        public function routeFor($route, $args = [])
        {
            $this->go($this->urlFor($route, $args));
        }

        public function authorize()
        {
            $guard = guard();

            $check = call_user_func_array([$guard, 'allows'], func_get_args());

            if ($check) {
                return true;
            }

            exception('guard', 'This user is not authorized to execute this action.');
        }

        public function cannot()
        {
            $check = call_user_func_array([$this, 'can'], func_get_args());

            return !$check;
        }

        public function can()
        {
            $guard = guard();

            $check = call_user_func_array([$guard, 'allows'], func_get_args());

            if ($check) {
                return true;
            }

            return false;
        }

        public function policy()
        {
            $guard = guard();

            return call_user_func_array([$guard, 'policy'], func_get_args());
        }

        public function pagination($query, $byPage = 10)
        {
            $page   = Input::request('page', 1);
            $byPage = 10;

            $this->total = $query->count();

            $last = ceil($this->total / $byPage);

            $this->results = $query->paginate($page, $byPage)->models();

            if ($this->total > $byPage) {
                $paginator          = new Paginator($this->results, $page, $this->total, $byPage, $last);
                $this->pagination   = $paginator->links();
            }
        }

        public function app($k = null, $v = null)
        {
            $app = context('app');

            if (is_null($k)) {
                return $app;
            }

            if (is_null($v)) {
                return $app[$k];
            }

            $app[$k] = $v;

            return $app;
        }

        public function queue()
        {
            return call_user_func_array('\\Octo\\queue', func_get_args());
        }

        public function bgQueue()
        {
            return call_user_func_array('\\Octo\\bgQueue', func_get_args());
        }
    }
