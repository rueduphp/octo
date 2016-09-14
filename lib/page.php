<?php
    namespace Octo;

    class Page
    {
        private $cache = false, $_code = 200, $_theme, $_path, $_router, $_method, $_cache;
        private static $_routes;

        public function __construct($path = null, $theme = null, $render = true, $cache = false)
        {
            $this->_router  = Router::instance();
            $this->_cache   = Cache::instance('pages');
            $this->_method  = Strings::lower($this->_router->getMethod());

            $this->_theme   = is_null($theme) ? Config::get('pages.theme', 'default') : $theme;

            path('public_theme', path('public') . DS . 'themes' . DS . $this->_theme);

            if (is_null($path)) {
                $path = $this->_router->getUri();
            }

            $this->cache = $cache;
            $this->_path = $path;

            if (true === $render && true === $this->cache) {
                $this->checkCache();
            } else {
                $this->routes();
                $this->render($render);
            }
        }

        private function getCacheKey()
        {
            return sha1($this->_path, $this->_theme);
        }

        private function checkCache()
        {
            $key = $this->getCacheKey();

            if ($cache = $this->_cache->getNow($key)) {
                $age = self::age();
                $cacheAge = $this->_cache->ageNow($key);

                if ($age > $cacheAge) {
                    $this->_cache->delNow($key);
                    $this->routes();
                    $this->render(true);
                } else {
                    die($cache);
                }
            } else {
                $this->routes();
                $this->render(true);
            }
        }

        public static function age()
        {
            return (new Finder)->in(path('content'))->youngest()->age;
        }

        public static function link($name, array $args = [])
        {
            if ($name == 'home' || $name == '/') {
                return "/" . trim(Config::get('pages.home', '/'));
            }

            if (!empty($args)) {
                foreach ($args as $k => $v) {
                    $name = str_replace('{' . $k . '}', $v, $name);
                }
            }

            return WEBROOT . '/' . trim(str_replace('.', '/', $name), '/');
        }

        public static function redirect($name, array $args = [])
        {
            redirect(self::link($name, $args));
        }

        public function render($render = true)
        {
            $found = false;
            $config = [];

            foreach (self::$_routes as $uri => $callback) {
                if (preg_match_all('#^' . trim($uri) . '$#', $this->_path, $matches, PREG_OFFSET_CAPTURE)) {
                    $found = true;

                    $path   = $callback();
                    $page   = self::getUri($path, false);
                    $config = $this->getJson($path);

                    break;
                }
            }

            if (false === $found && true === $render) {
                if ($this->exists(404)) {
                    $page           = 404;
                    $this->_code    = 404;
                    $config         = $this->getJson(path('pages') . DS . '404.json');
                } else {
                    $this->is404();
                }
            }

            $this->fill($config);
            $this->controller($page);

            if (true === $render) {
                $this->make($page);
            }
        }

        private function controller($path)
        {
            $controller = path('controllers') . DS . $path . '.php';

            if (File::exists($controller)) {
                require_once $controller;

                $instance = new PageController;
                $instance->attach($this);
                $instance->boot();
            }
        }

        private function getJson($json)
        {
            if (File::exists($json)) {
                if (fnmatch('*.json', $json)) {
                    return json_decode(File::read($json), true);
                }
            }

            return [];
        }

        private function fill(array $data = [])
        {
            $data = array_merge($data, $_REQUEST);

            if (!empty($data)) {
                if (Arrays::isAssoc($data)) {
                    foreach ($data as $k => $v) {
                        $this->{$k} = value($v);
                    }
                }
            }
        }

        private function make($path)
        {
            $page = $this;

            $layout         = $page->tpl('layouts' . DS . $page->get('layout', 'main'));
            $page->content  = $page->eval($page->tpl('pages' . DS . $path));

            if (true === $page->cache) {
                ob_start();
            }

            eval(' namespace Octo; ?>' . $layout . "\n\n" . '<!-- Displayed by Octo in ' . Timer::get() . ' s. --><?php ');

            if (true === $page->cache) {
                $cache = ob_get_contents();

                ob_end_clean();

                $page->_cache->setNow($page->getCacheKey(), $cache);

                echo $cache;
            }
        }

        private function eval($code)
        {
            $page = $this;

            ob_start();

            eval(' namespace Octo; ?>' . $code . '<?php ');

            $compiled = ob_get_contents();

            ob_end_clean();

            return $compiled;
        }

        private function tpl($page)
        {
            $file = path('themes') . DS . $this->_theme . DS . $page . '.php';

            if (File::exists($file)) {
                return $this->parse($file);
            } else {
                $this->is404();
            }
        }

        private function parse($file)
        {
            $content = File::read($file);

            $content = $this->compile('include', $content);
            $content = $this->compile('lang', $content);

            return $content;
        }

        private function compile($tag, $content)
        {
            $tab = explode('<' . $tag . '>', $content);

            foreach ($tab as $seg) {
                list($val, $dummy) = explode('</' . $tag . '>', $seg, 2);

                $content = str_replace("<$tag>$val</$tag>", '<?=' . '$page->' . $tag . '('.json_encode($val).') ?>', $content);
            }

            return $content;
        }

        public function include($path)
        {
            $page = $this;

            eval(' ?>' . $this->tpl('partials' . DS . $path) . '<?php ');
        }

        public function is404()
        {
            $cb = Config::get('pages.404', function () {
                view('<h1>Error 404</h1>', 404, 'Error 404');
            });

            if (is_callable($cb)) $cb();
            else view('<h1>Error 404</h1>', 404, 'Error 404');
        }

        public function isError()
        {
            $cb = Config::get('pages.500', function () {
                view('<h1>System Error</h1>', 500, 'System Error');
            });

            if (is_callable($cb)) $cb();
            else view('<h1>System Error</h1>', 500, 'System Error');
        }

        public function isForbidden()
        {
            $cb = Config::get('pages.403', function () {
                view('<h1>Forbidden Access</h1>', 403, 'Forbidden Access');
            });

            if (is_callable($cb)) $cb();
            else view('<h1>Forbidden Access</h1>', 403, 'Forbidden Access');
        }

        public function exists($path)
        {
            return File::exists(path('pages') . DS . $path . '.json');
        }

        public function getRoutes()
        {
            return self::$_routes;
        }

        private function routes()
        {
            if (empty(self::$_routes)) {
                $pages = $this->_cache->glob(path('pages') . DS . '*.json');
                $vars = [];

                foreach ($pages as $path) {
                    $uri = self::getUri($path);

                    if (fnmatch('*{*}*', $uri)) {
                        list($uri, $vars) = $this->parseVars($uri);
                    }

                    self::$_routes[$uri] = function () use ($path, $vars, $uri) {
                        if (!empty($vars)) {
                            $this->parseUri($uri, $vars);
                        }

                        return $path;
                    };
                }
            }
        }

        private function parseUri($uri, $vars)
        {
            preg_match_all('#^' . trim($uri) . '$#', $this->_path, $matches, PREG_OFFSET_CAPTURE);

            $matches = array_slice($matches, 1);

            $params = array_map(function ($match, $index) use ($matches) {
                if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                    return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                } else {
                    return isset($match[0][0]) ? trim($match[0][0], '/') : null;
                }
            }, $matches, array_keys($matches));

            if (count($params) == count($vars)) {
                $i = 0;

                foreach ($vars as $var) {
                    $this->{$var} = $params[$i];

                    $i++;
                }
            }
        }

        private function parseVars($uri)
        {
            $vars = [];

            $tab = explode('{', $uri);

            array_shift($tab);

            foreach ($tab as $seg) {
                list($var, $dummy) = explode('}', $seg, 2);

                $vars[] = $var;

                $uri = str_replace('{' . $var . '}', '(.*)', $uri);
            }

            return [$uri, $vars];
        }

        public function collection($path, $theme = null)
        {
            $theme = is_null($theme) ? Config::get('pages.theme', 'dedault') : $theme;

            $collection = [];

            $pages = $this->_cache->glob(path('pages') . DS . $path . DS . '*.json');

            foreach ($pages as $page_path) {
                $page = new self($this->getPath($page_path), $theme);

                $collection[] = $page;
            }

            return coll($collection);
        }

        private static function getUri($path = null, $force = true)
        {
            $path = is_null($path) ? $this->_path : $path;

            $path = str_replace([path('pages') . DS, '.json'], '', $path);

            return $force ? str_replace('home', '/', $path) : $path;
        }

        public static function getName($path = null)
        {
            $path = is_null($path) ? $this->_path : $path;

            $name = str_replace('/', '.', self::getUri($path));

            $name = str_replace(['(.*)'], '', $name);

            if ('.' == $name) {
                $name = 'home';
            }

            return $name;
        }

        public function __set($k, $v)
        {
            $this->{$k} = $v;
        }

        public function __get($k)
        {
            if (property_exists($this, $k)) {
                return $this->{$k};
            }

            return null;
        }

        public function get($k, $d = null)
        {
            if (property_exists($this, $k)) {
                return $this->{$k};
            }

            return $d;
        }

        public function url($url)
        {
            return WEBROOT . '/' . trim($url, '/');
        }

        public function asset($file)
        {
            return WEBROOT . '/themes/' . $this->_theme . '/assets/' . trim($file, '/');
        }

        public function i64($file, $ext = null)
        {
            $image = path('public') . DS . 'themes' . DS . $this->_theme . DS . 'assets' . DS . 'img' . DS . $file;

            if (File::exists($image)) {
                $ext = empty($ext)
                    ? Strings::lower(
                        Arrays::last(
                            explode(
                                '.',
                                $file
                            )
                        )
                    )
                    : $ext;

                $binary = fread(
                    fopen($image, "r"),
                    filesize($image)
                );

                return 'data:image/' . $ext . ';base64,' . base64_encode($binary);
            }
        }

        public static function disqus($title = null, $url = null)
        {
            return "<script type='text/javascript'>
        var disqus_shortname = '" . Config::get('disqus.name') . "';
        var disqus_title = '{$title}';
        var disqus_url = '{$url}';

        (function () {
            var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
            dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
            (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
        })();
    </script>";
        }

        public static function disqus_lastcomments($max = 5)
        {
            return '<script type="text/javascript" src="//' . Config::get('disqus.name') . '.disqus.com/recent_comments_widget.s?num_items=' . $max . '&hide_avatars=0&avatar_size=48&excerpt_length=200&hide_mods=0"></script>';
        }

        public static function disqus_count()
        {
            return '<script type="text/javascript">
        (function () {
            var dsq = document.createElement("script"); dsq.type = "text/javascript"; dsq.async = true;
            dsq.src = "//' . Config::get('disqus.name') . '.disqus.com/count.js";
            (document.getElementsByTagName("head")[0] || document.getElementsByTagName("body")[0]).appendChild(dsq);
        })();
    </script>';
        }

        public static function home()
        {
            redirect("/" . trim(Config::get('pages.home', '/')));
        }
    }
