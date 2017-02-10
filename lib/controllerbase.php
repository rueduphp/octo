<?php
    namespace Octo;

    class ControllerBase extends FrontController
    {
        protected $title = '';
        protected $models = [];

        public function init()
        {
            $this->auth = session('web')->getUser() !== null;

            $this->csrf = token();

            session('web')->setLastCsrf(session('web')->getCsrf());
            session('web')->setCsrf($this->csrf);

            $actions = get_class_methods($this);

            if (in_array('boot', $actions)) {
                $this->boot();
            }
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

            lib('router')->run();

            exit;
        }

        public function user($k = null)
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

        public function formatSize($size)
        {
            $mod = 1024;
            $units = explode(' ','B KB MB GB TB PB');

            for ($i = 0; $size > $mod; $i++) {
                $size /= $mod;
            }

            return round($size, 2) . ' ' . $units[$i];
        }

        function foldersize($path)
        {
            $total_size = 0;
            $files = scandir($path);

            foreach($files as $t) {
                if (is_dir(rtrim($path, '/') . '/' . $t)) {
                    if ($t<>"." && $t<>"..") {
                        $size = $this->foldersize(rtrim($path, '/') . '/' . $t);

                        $total_size += $size;
                    }
                } else {
                    $size = filesize(rtrim($path, '/') . '/' . $t);
                    $total_size += $size;
                }
            }

            return $total_size;
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

            $a = time();

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

        protected function model($model, $force = false)
        {
            $model = Strings::uncamelize($model);

            if (!isset($this->models[$model]) || true === $force) {
                if (fnmatch('*_*', $model)) {
                    list($database, $table) = explode('_', $model, 2);
                } else {
                    $database   = Strings::uncamelize(Config::get('application.name', 'core'));
                    $table      = $model;
                }

                $this->models[$model] = engine($database, $table);
            }

            return $this->models[$model];
        }

        public function em()
        {
            return call_user_func_array('\\Octo\\em', func_get_args());
        }
    }
