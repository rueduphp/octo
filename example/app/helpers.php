<?php

use App\Facades\Db;
use App\Facades\Event;
use App\Managers\Main;
use App\Models\Option;
use App\Models\Setting;
use App\Models\Settings;
use App\Services\Auth;
use App\Services\Cache;
use App\Services\Data;
use App\Services\Directives;
use App\Services\Log;
use App\Services\Logger;
use App\Services\Lua;
use App\Services\Model;
use Carbon\Carbon;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOMySql\Driver;
use GuzzleHttp\Psr7\MessageTrait;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Collection as LColl;
use Illuminate\Support\HtmlString;
use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
use Octo\Api;
use Octo\Arrays;
use Octo\Bcrypt;
use Octo\Collection;
use Octo\Component;
use Octo\Decorator;
use Octo\Dynamicentity;
use Octo\Elegant;
use Octo\Entity;
use Octo\Facades\Config as CoreConf;
use Octo\Facades\Validator;
use Octo\FastContainerException;
use Octo\FastRequest;
use Octo\FastTwigExtension;
use Octo\Fillable;
use Octo\Fire;
use Octo\Fluent;
use Octo\In;
use Octo\Inflector;
use Octo\Listener;
use Octo\Octalia;
use Octo\Pack;
use Octo\Shoppingcart;
use Octo\Ultimate;
use Octo\Url;
use Zend\Expressive\Router\FastRouteRouter;
use function Octo\arrayable;
use function Octo\getCore as getIt;
use function Octo\gi;
use function Octo\isAke;
use function Octo\setCore as setIt;

/**
 * @param $facade
 * @param $resolver
 * @throws ReflectionException
 */
function makeFacade($facade, $resolver)
{
    dic()::singleton('facades.' . $facade, $resolver);
}

/**
 * @throws ReflectionException
 */
function directives()
{
    $directives = include Octo\config_path('directives.php');
    Directives::register($directives);
}

/**
 * @param null $content
 * @param int $status
 * @param array $headers
 * @return Component
 */
function response($content = null, int $status = 200, array $headers = [])
{
    static $response;

    if (!is_object($response)) {
        $response = new Component;

        $response['render'] = function ($file, array $data = []) {
            return render($file, $data);
        };

        $response['new'] = function ($content, int $status = 200, array $headers = []) {
            if (is_array($content) || Octo\jsonable($content) || arrayable($content)) {
                $headers['content-type'] = 'application/json; charset=utf-8';

                if (is_array($content)) {
                    $content = json_encode($content, JSON_PRETTY_PRINT);
                } elseif (Octo\jsonable($content)) {
                    $content = $content->toJson(JSON_PRETTY_PRINT);
                } elseif (arrayable($content)) {
                    $content = json_encode($content->toArray(), JSON_PRETTY_PRINT);
                }
            }

            return new GuzzleHttp\Psr7\Response($status, $headers, $content);
        };

        $response['zip'] = function ($source, $destination, $include_dir = false) use ($response) {
            $file = Octo\zip($source, $destination, $include_dir);

            if (false !== $file) {
                return $response->download($destination);
            }

            return err(404, 'File not found.');
        };

        $response['pdf'] = function (string $file, string $name, array $data = [], string $orientation = 'Portrait') {
            $password = pkey('pdf.password');
            $html = view($file, $data);
            $pdf = \Octo\post_request(pkey('pdf.url'), compact('html', 'password', 'orientation'));

            $headers = [
                'Content-Description' => 'File Transfer',
                'Content-Disposition' => 'attachment; filename="'.$name.'.pdf"',
                'Content-Transfer-Encoding' => 'binary',
                'Content-Type' => 'application/pdf',
            ];

            return new GuzzleHttp\Psr7\Response(200, $headers, $pdf);
        };

        $response['download'] = function (
            string $file,
            ?string $name = null,
            string $disposition = 'attachment',
            array $headers = []
        ) {
            $name = $name ?? Arrays::last(explode('/', $file));
            /** @var string $content */
            $content = Octo\File::read($file);
            $response = new GuzzleHttp\Psr7\Response(200, $headers, $content);
            $response = $response
                ->withHeader('Content-Disposition', $disposition . '; filename="' . $name . '"')
            ;

            return $response;
        };

        $response['make'] = function (...$args) {
            return Octo\fast()->response(...$args);
        };

        $response['redirect'] = function (string $url, int $status = 301) {
            return Octo\fast()->redirectResponse($url, $status);
        };

        $response['route'] = function (string $route, array $parameters = [], int $status = 301) {
            return Octo\fast()->redirectRouteResponse($route, $parameters, $status);
        };

        $response['json'] = function ($data, int $status = 200) {
            $data = arrayable($data) ? $data->toArray() : $data;

            if (is_array($data)) {
                return Octo\fast()->response(
                    $status,
                    ['content-type' => 'application/json; charset=utf-8'],
                    json_encode($data, JSON_PRETTY_PRINT)
                );
            } else {
                throw new Exception('Wrong data parameter');
            }
        };
    }

    if (!empty($content)) {
        return $response->new($content, $status, $headers);
    }

    return $response;
}

/**
 * @param null|Ultimate $session
 * @return Component
 */
function flash(?Ultimate $session = null)
{
    static $flash;

    if (!is_object($flash)) {
        $flash = new Component;
        $session = $session ?? session();

        $flash['set'] = function (string $type, string $message) use ($session, $flash) {
            $key = 'flash.' . Inflector::lower($type);
            $session->set($key, $message);

            return $flash;
        };

        $flash['get'] = function (string $type) use ($session, $flash) {
            $key = 'flash.' . Inflector::lower($type);

            return $session->pull($key);
        };

        $flash['has'] = function (string $type) use ($session, $flash) {
            $key = 'flash.' . Inflector::lower($type);

            return $session->has($key);
        };

        /** set */
        $flash['success'] = function (string $message) use ($flash) {
            return $flash->set('success', $message);
        };

        $flash['error'] = function (string $message) use ($flash) {
            return $flash->set('error', $message);
        };

        $flash['warning'] = function (string $message) use ($flash) {
            return $flash->set('warning', $message);
        };

        $flash['info'] = function (string $message) use ($flash) {
            return $flash->set('warning', $message);
        };

        /** get */
        $flash['getSuccess'] = function () use ($flash) {
            return $flash->get('success');
        };

        $flash['getError'] = function () use ($flash) {
            return $flash->get('error');
        };

        $flash['getWarning'] = function () use ($flash) {
            return $flash->get('warning');
        };

        $flash['getInfo'] = function () use ($flash) {
            return $flash->get('info');
        };

        /** has */
        $flash['hasSuccess'] = function () use ($flash) {
            return $flash->has('success');
        };

        $flash['hasError'] = function () use ($flash) {
            return $flash->has('error');
        };

        $flash['hasWarning'] = function () use ($flash) {
            return $flash->has('warning');
        };

        $flash['hasInfo'] = function () use ($flash) {
            return $flash->has('info');
        };
    }

    return $flash;
}

/**
 * @param Ultimate $session
 * @return Component
 */
function flasher(Ultimate $session)
{
    $flash = new Component;

    $flash['set'] = function (string $type, string $message) use ($session, $flash) {
        $session->push($type, $message);

        return $flash;
    };

    $flash['get'] = function (string $type) use ($session) {
        return $session->pull($type);
    };

    $flash['count'] = function (string $type) use ($session) {
        $items = $session->get($type, []);

        return count($items);
    };

    $flash['has'] = function (string $type) use ($session) {
        return $session->has($type);
    };

    /** set */
    $flash['success'] = function (string $message) use ($flash) {
        return $flash->set('success', $message);
    };

    $flash['error'] = function (string $message) use ($flash) {
        return $flash->set('error', $message);
    };

    $flash['warning'] = function (string $message) use ($flash) {
        return $flash->set('warning', $message);
    };

    $flash['info'] = function (string $message) use ($flash) {
        return $flash->set('warning', $message);
    };

    /** get */
    $flash['getSuccess'] = function () use ($flash) {
        return $flash->get('success');
    };

    $flash['getError'] = function () use ($flash) {
        return $flash->get('error');
    };

    $flash['getWarning'] = function () use ($flash) {
        return $flash->get('warning');
    };

    $flash['getInfo'] = function () use ($flash) {
        return $flash->get('info');
    };

    /** has */
    $flash['hasSuccess'] = function () use ($flash) {
        return $flash->has('success');
    };

    $flash['hasError'] = function () use ($flash) {
        return $flash->has('error');
    };

    $flash['hasWarning'] = function () use ($flash) {
        return $flash->has('warning');
    };

    $flash['hasInfo'] = function () use ($flash) {
        return $flash->has('info');
    };

    /** count */
    $flash['countSuccess'] = function () use ($flash) {
        return $flash->count('success');
    };

    $flash['countError'] = function () use ($flash) {
        return $flash->count('error');
    };

    $flash['countWarning'] = function () use ($flash) {
        return $flash->count('warning');
    };

    $flash['countInfo'] = function () use ($flash) {
        return $flash->count('info');
    };

    return $flash;
}

/**
 * @param $method
 * @return HtmlString
 */
function method_field($method)
{
    return new HtmlString('<input type="hidden" name="_method" value="'.$method.'">');
}

/**
 * @param string $path
 * @param string $subpath
 * @return string
 */
function path(string $path, string $subpath = '')
{
    $method = 'Octo\\' . $path . '_path';

    if (function_exists($method)) {
        return $method($subpath);
    }

    return __DIR__  . ($subpath ? DIRECTORY_SEPARATOR . trim($subpath, DIRECTORY_SEPARATOR) : $subpath);
}

/**
 * @param string $name
 * @param array $args
 * @return string
 * @throws ReflectionException
 */
function to(string $name, array $args = [])
{
    /** @var FastTwigExtension $twig */
    $twig = app(FastTwigExtension::class);

    return $twig->path($name, $args);
}

/**
 * @param string $name
 * @param array $args
 * @return string
 * @throws ReflectionException
 */
function route(string $name, array $args = [])
{
    /** @var FastTwigExtension $twig */
    $twig = app(FastTwigExtension::class);

    return $twig->path($name, $args);
}

/**
 * @param string $key
 * @param array $parameters
 * @param string $scope
 * @return array|null|string
 */
function __(string $key, array $parameters = [], string $scope = 'main')
{
    return \Octo\setTranslator(\Octo\lang_path(), main()->locale($scope))->get($key, $parameters);
}

/**
 * @param string $key
 * @param $value
 * @param null|string $label
 * @param array $options
 * @param array $attributes
 * @return string
 * @throws ReflectionException
 */
function field (
    string $key,
    $value,
    ?string $label = null,
    array $options = [],
    array $attributes = []
) {
    /** @var FastTwigExtension $twig */
    $twig       = app(FastTwigExtension::class);
    $context    = getIt('blade.context', []);

    return $twig->field($context, $key, $value, $label, $options, $attributes);
}

/**
 * @return string
 * @throws Exception
 */
function csrf_field(): string
{
    return Octo\csrf();
}
/**
 * @return string
 * @throws Exception
 */
function csrf(): string
{
    return Octo\csrf_make();
}

/**
 * @param  DateTimeZone|string|null $tz
 * @return Carbon
 */
function now($tz = null)
{
    return Carbon::now($tz);
}

/**
 * @return int
 */
function currentTime()
{
    return now()->getTimestamp();
}

/**
 * @param $delay
 * @return int
 */
function secondsUntil($delay)
{
    $delay = parseDateInterval($delay);

    return $delay instanceof DateTimeInterface
        ? max(0, $delay->getTimestamp() - currentTime())
        : (int) $delay
    ;
}

/**
 * @param $delay
 * @return Carbon
 */
function parseDateInterval($delay)
{
    if ($delay instanceof DateInterval) {
        $delay = now()->add($delay);
    }

    return $delay;
}

/**
 * @param string $path
 * @return string
 */
function asset(string $path): string
{
    $path = trim($path, '/');

    return url('assets/' . $path);
}

/**
 * @param string $path
 * @return string
 */
function url(string $path): string
{
    $path = trim($path, '/');

    return Url::root() . '/' . $path;
}

/**
 * @param string $name
 * @return string
 * @throws ReflectionException
 */
function fullRoute(string $name)
{
    return Url::root() . route($name);
}

/**
 * @param string $name
 * @param array $args
 * @return string
 */
function urlRoute(string $name, array $args = [])
{
    return Url::root() . route($name, $args);
}

/**
 * @param $key
 * @param $default
 * @return mixed|null|Ultimate
 * @throws ReflectionException
 * @throws \Octo\Exception
 */
function session($key = null, $default = null)
{
    /** @var Ultimate $session */
    $session = Octo\getSession();

    if (is_null($key)) {
        return $session;
    }

    if (is_array($key)) {
        return $session->put($key);
    }

    return $session->get($key, $default);
}

/**
 * @param string $namespace
 * @param mixed ...$args
 * @return Ultimate
 */
function getSession($namespace = 'web', ...$args)
{
    return ultimate($namespace, ...$args);
}

/**
 * @param string $namespace
 * @param string $userKey
 * @param string $userModel
 *
 * @return Ultimate
 */
function ultimate(
    string $namespace = 'web',
    string $userKey = 'user',
    string $userModel = '\\App\\Models\\User'
): Ultimate {
    static $sessions = [];

    $key = sha1($namespace . $userKey . $userModel);

    if (null === ($session = isAke($sessions, $key, null))) {
        $session = new Ultimate($namespace, $userKey, $userModel);
        $sessions[$key] = $session;
    }

    return $session;
}

/**
 * @param null|string $key
 * @param null $default
 * @return mixed|null
 * @throws ReflectionException
 * @throws \Octo\Exception
 */
function old(?string $key = null, $default = null)
{
    return session()->alive() ? Octo\isAke(Octo\viewParams()['olds'], $key, input($key)) : $default;
}

/**
 * @param null $abstract
 * @param array $parameters
 * @param bool $singleton
 * @return \App\Services\Container|\Illuminate\Container\Container|mixed|null|object
 * @throws FastContainerException
 * @throws ReflectionException
 */
function app($abstract = null, array $parameters = [], $singleton = true)
{
    if (null === $abstract) {
        return c();
    }

    if (l()->bound($abstract) && true === $singleton) {
        return l($abstract);
    }

    return gi()->make($abstract, $parameters, $singleton);
}

/**
 * @param string $name
 * @return Fire
 */
function dispatcher($name = 'core')
{
    static $dispatchers = [];

    if (!$dispatcher = isAke($dispatchers, $name, null)) {
        $dispatcher = new Fire($name);

        $dispatchers[$name] = $dispatcher;
    }

    return $dispatcher;
}

/**
 * @param string $name
 * @return Fillable
 */
function bag(string $name = 'core')
{
    static $bags = [];

    $key = "bag.{$name}";

    if (!$bag       = isAke($bags, $key, null)) {
        $bag        = new Fillable($key);
        $bags[$key] = $bag;
    }

    return $bag;
}

/**
 * @param string $name
 * @return Shoppingcart
 * @throws ReflectionException
 * @throws \Octo\Exception
 */
function cart(string $name = 'core')
{
    static $carts = [];

    if (!$cart = isAke($carts, $name, null)) {
        $cart = new Shoppingcart;
        $cart->instance($name);
        $carts[$name] = $cart;
    }

    return $cart;
}

/**
 * @param mixed ...$arguments
 * @return mixed|Cache
 * @throws \Octo\Exception
 * @throws Exception
 */
function cache(...$arguments)
{
    $config = CoreConf::get('app');
    /** @var Cache $cache */
    $cache = cacheService($config['cache_ttl'] ?? 60, 'app');

    if (empty($arguments)) {
        return $cache;
    }

    if (is_string($arguments[0])) {
        return $cache->get($arguments[0], $arguments[1] ?? null);
    }

    if (!is_array($arguments[0])) {
        throw new Exception(
            'When setting a value in the cache, you must pass an array of key / value pairs.'
        );
    }

    return $cache->set(key($arguments[0]), reset($arguments[0]), $arguments[1] ?? 0);
}

/**
 * @param null|string $key
 * @param string $value
 * @return mixed
 */
function config(...$args)
{
    $key = array_shift($args);

    if (is_array($key)) {
        foreach ($key as $k => $v) {
            CoreConf::set($k, $v);
        }

        return true;
    }

    return CoreConf::get($key, reset($args));
}

/**
 * @param string $ley
 * @param null $default
 * @return mixed
 */
function conf(string $ley, $default = null)
{
    return l('config')->get($ley, $default);
}

/**
 * @param string $key
 */
function addConfig(string $key)
{
    $file = Octo\config_path() . '/' . Inflector::lower($key) . '.php';

    $conf = CoreConf::get($key, []);

    CoreConf::set($key, array_merge(require $file, $conf));
}

/**
 * @param null|string $key
 * @param string $value
 * @return mixed|null|Fillable
 */
function paths(?string $key = null, $value = 'octodummy')
{
    /** @var Fillable $paths */
    $paths = dic('paths');

    if (is_null($key)) {
        return $paths;
    }

    if (is_array($key)) {
        foreach ($key as $k => $v) {
            $paths->set($k, $v);
        }

        return $paths;
    }

    if ('octodummy' === $value) {
        return $paths->get($key);
    }

    return $paths->set($key, $value);
}

/**
 * @param null $abstract
 * @param array $parameters
 * @return \App\Services\Container|\Illuminate\Container\Container|mixed|null|object
 * @throws FastContainerException
 * @throws ReflectionException
 */
function maker($abstract = null, array $parameters = [])
{
    return app($abstract, $parameters, false);
}

/**
 * @param $value
 * @return string
 * @throws FastContainerException
 * @throws ReflectionException
 */
function encrypt($value)
{
    return app(Bcrypt::class)->make($value);
}

/**
 * @return Bcrypt
 * @throws FastContainerException
 * @throws ReflectionException
 */
function hasher()
{
    return app(Bcrypt::class);
}

/**
 * @param int $code
 * @param string $message
 * @return Response
 * @throws ReflectionException
 */
function abort($code = 403, $message = null)
{
    if (null === $message) {
        $message = Api::getMessage($code);
    } else {
        $message = Octo\value($message);
    }

    if (is_array($message)) {
        $message = json_encode($message);
    }

    return Octo\fast()->abort($code, $message);
}

/**
 * @param $condition
 * @param int $code
 * @param string $message
 * @return Response
 * @throws ReflectionException
 */
function abort_if($condition, $code = 403, $message = 'Forbidden')
{
    $condition = Octo\value($condition);

    if ($condition) {
        return abort($code, $message);
    }
}

/**
 * @param $condition
 * @param int $code
 * @param string $message
 * @return Response
 * @throws ReflectionException
 */
function abort_unless($condition, $code = 403, $message = 'Forbidden')
{
    $condition = Octo\value($condition);

    if (!$condition) {
        return abort($code, $message);
    }
}

/**
 * @param null $key
 * @param null $default
 * @return array|mixed|FastRequest
 * @throws ReflectionException
 */
function request($key = null, $default = null)
{
    /** @var FastRequest $request */
    $request = app(FastRequest::class);

    if (is_null($key)) {
        return $request;
    }

    if (is_array($key)) {
        return $request->only($key);
    }

    return Octo\isAke($request->all(), $key, $default);
}

/**
 * @param mixed ...$keys
 * @return array
 * @throws ReflectionException
 */
function input(...$keys)
{
    return request()->only(...$keys);
}

/**
 * @param mixed ...$keys
 * @return array
 * @throws ReflectionException
 */
function inputs(...$keys)
{
    return array_values(input(...$keys));
}

/**
 * @param string $name
 * @return null|object
 */
function inst(string $name)
{
    $class = '\Octo\Facades\\' . Inflector::camelize($name);

    if (class_exists($class)) {
        return app($class);
    }

    return null;
}

/**
 * @param null $to
 * @param int $status
 * @return MessageTrait|\Octo\FastRedirector
 * @throws ReflectionException
 */
function redirect($to = null, $status = 302)
{
    /** @var Octo\FastRedirector $r */
    $r = app(Octo\FastRedirector::class);

    if (is_null($to)) {
        return $r;
    }

    return $r->to($to, $status);
}

/**
 * @param int $status
 * @return MessageTrait|\Octo\FastRedirector
 * @throws ReflectionException
 * @throws \Octo\Exception
 */
function back($status = 302)
{
    return redirect()->back($status);
}

/**
 * @return FastRouteRouter
 */
function router(): FastRouteRouter
{
    return Octo\getRouter();
}

/**
 * @param mixed ...$args
 * @return string
 */
function action(...$args): string
{
    return Octo\action(...$args);
}

/**
 * @param mixed ...$args
 * @return mixed|null
 * @throws ReflectionException
 */
function runAction(...$args)
{
    $module = array_shift($args);

    if (!\fnmatch('*@*', $module)) {
        $action = array_shift($args);
    } else {
        list($module, $action) = explode('@', $module, 2);
    }

    $instance = gi()->make($module);

    $params = array_merge([$instance, $action], $args);

    return gi()->call(...$params);
}

/**
 * @param null|string $key
 * @param null $default
 * @param string $namespace
 * @return mixed|null
 * @throws FastContainerException
 * @throws ReflectionException
 */
function user(?string $key = null, $default = null, string $namespace = 'core')
{
    $session = auth($namespace);

    return $session->user($key, $default);
}

/**
 * @param mixed ...$args
 * @return bool
 * @throws ReflectionException
 */
function can(...$args): bool
{
    return auth()->can(...$args);
}

/**
 * @param mixed ...$args
 * @return bool
 * @throws ReflectionException
 */
function cannot(...$args): bool
{
    return auth()->cannot(...$args);
}

/**
 * @param string $role
 * @return bool
 * @throws ReflectionException
 */
function is(string $role): bool
{
    return auth()->is($role);
}

/**
 * @param string $namespace
 * @return Auth
 */
function auth(string $namespace = 'core')
{
    return Auth::getInstance($namespace);
}

/**
 * @param string $namespace
 * @return Auth
 */
function trust(string $namespace = 'core')
{
    return Auth::getInstance($namespace);
}

/**
 * @param string $expression
 * @return string
 */
function inject(string $expression)
{
    $segments   = explode(',', preg_replace("/[\(\)\\\"\']/", '', $expression));
    $variable   = trim($segments[0]);
    $service    = trim($segments[1]);

    return "<?php \${$variable} = l('{$service}'); ?>";
}

/**
 * @param string $file
 * @param array $parameters
 * @param null|string $path
 * @return string
 * @throws ReflectionException
 */
function tpl(string $file, array $parameters = [], ?string $path = null)
{
    $path = null !== $path ? $path : Octo\in_path('views');

    $blade = Octo\bladeFactory([$path]);

    return $blade->make($file, $parameters)->render();
}

/**
 * @param mixed ...$params
 * @return mixed|null|Fire
 * @throws ReflectionException
 */
function event(...$params)
{
    return Octo\event(...$params);
}

/**
 * @param null|Ultimate $session
 * @return array|mixed|string
 * @throws ReflectionException
 * @throws \Exception
 */
function locale(?Ultimate $session = null)
{
    $session         = $session ?? session();
    $language        = $session[$localKey = $session->getLocaleKey()];
    $isCli           = false;
    $fromBrowser     = isAke($_SERVER, 'HTTP_ACCEPT_LANGUAGE', false);

    if (false === $fromBrowser) {
        $isCli = true;
    }

    if ($isCli) {
        $app = CoreConf::get('app');

        return $language ?? $app['locale'] ?? $app['fallback_locale'] ?? 'en';
    }

    if (is_null($language) || !is_string($language)) {
        $request = new FastRequest;

        $language = $request->input('_locale', Locale::acceptFromHttp($fromBrowser));
    }

    if (fnmatch('*_*', $language)) {
        $language = explode('_', $language, 2)[0];
    }

    CoreConf::set('app.locale', $language);

    return $session[$localKey] = $language;
}

/**
 * @param string $locale
 * @param null|Ultimate $session
 */
function setAppLocale(string $locale, ?Ultimate $session = null)
{
    $session = $session ?? session();

    CoreConf::set('app.locale', $locale);

    $session[$session->getLocaleKey()] = $locale;
}

/**
 * @param null|string $key
 * @param string $value
 * @return \App\Services\Container|mixed|null|object
 * @throws ReflectionException
 * @throws FastContainerException
 */
function c(?string $key = null, $value = 'octodummy')
{
    /** @var \App\Services\Container $app */
    $app = gi()->make(\App\Services\Container::class);

    if (null === $key) {
        return $app;
    }

    if ($value === 'octodummy') {
        return $app->get($key);
    }

    return $app->set($key, $value);
}

/**
 * @param null|string $key
 * @param mixed $value
 * @return mixed|\Illuminate\Container\Container
 */
function l(?string $key = null, $value = 'octodummy')
{
    if (!$app = inInstance('larapp')) {
        $app = Illuminate\Container\Container::getInstance();
    }

    if (null === $key) {
        return $app;
    }

    if ($value === 'octodummy') {
        return $app[$key];
    }

    $app[$key] = $value;

    return $app;
}

/**
 * @param string $path
 * @return string
 */
function base_path(string $path = '')
{
    return realpath(__DIR__) . ($path ? DS . trim($path, DS) : $path);;
}

/**
 * @param string $path
 * @return string
 */
function app_path(string $path = '')
{
    return realpath(__DIR__) . ($path ? DS . trim($path, DS) : $path);;
}

/**
 * @param array $conf
 */
function lc(array $conf)
{
    l('config')->set($conf);
}

/**
 * @return Inflector
 * @throws ReflectionException
 * @throws FastContainerException
 */
function i(): Inflector
{
    return c(Inflector::class);
}

/**
 * @return Arrays
 * @throws ReflectionException
 * @throws FastContainerException
 */
function arr(): Arrays
{
    return c(Arrays::class);
}

/**
 * @param null|string $key
 * @param string $value
 * @return mixed|In
 */
function dic(?string $key = null, $value = 'octodummy')
{
    $app = In::self();

    if (null === $key) {
        return $app;
    }

    if ($value === 'octodummy') {
        $value = $app[$key];

        if (is_callable($value)) {
            $value = cf($value);
        }

        return $value;
    }

    $app[$key] = $value;

    return $app;
}

/**
 * @param mixed ...$args
 * @return mixed|In
 */
function ioc(...$args)
{
    return dic(...$args);
}

/**
 * @param array $data
 * @param array $rules
 * @param array $messages
 * @param array $customAttributes
 * @return mixed|object
 */
function validator(array $data = [], array $rules = [], array $messages = [], array $customAttributes = [])
{
    $validator = app(Validator::class);

    if (0 === func_num_args()) {
        return $validator;
    }

    return $validator::make($data, $rules, $messages, $customAttributes);
}

/**
 * @param string $name
 * @return object
 */
function repo(string $name)
{
    if (!class_exists($name) || !Inflector::contains($name, 'App\Repositories')) {
        $class = '\App\Repositories\\' . Inflector::camelize($name . '_repository');
    } else {
        $class = $name;
    }

    return app($class);
}

/**
 * @param string $name
 * @return object
 */
function middleware(string $name)
{
    if (!class_exists($name) || !Inflector::contains($name, 'App\Middlewares')) {
        $class = '\App\Middlewares\\' . Inflector::camelize($name);
    } else {
        $class = $name;
    }

    return app($class);
}

/**
 * @param string $name
 * @return object
 */
function observer(string $name)
{
    if (!class_exists($name) || !Inflector::contains($name, 'App\Observers')) {
        $class = '\\App\\Observers\\' . Inflector::camelize($name);
    } else {
        $class = $name;
    }

    return app($class);
}

/**
 * @param string $name
 * @return Model
 */
function model(string $name): Elegant
{
    if (!class_exists($name) || !Inflector::contains($name, 'App\Models')) {
        $class = '\\App\\Models\\' . Inflector::camelize($name);
    } else {
        $class = $name;
    }

    return app($class);
}

/**
 * @param string $name
 * @param int $times
 * @param array $attributes
 * @return Component
 * @throws ReflectionException
 */
function factory(string $name, int $times = 1, array $attributes = [])
{
    /** @var Elegant $model */
    $model = model($name);

    if (!class_exists($name) || !Inflector::contains($name, 'App\Factories')) {
        $class = '\\App\\Factories\\' . Inflector::camelize($name);
    } else {
        $class = $name;
    }

    app($class);

    /** @var \Octo\FastFactory $factory */
    $factory = $model::factory();

    $factor = new Component;

    $factor['create'] = function () use ($factory, $times, $attributes) {
        return $factory->create($times, $attributes);
    };

    $factor['make'] = function () use ($factory, $times, $attributes) {
        return $factory->make($times, $attributes);
    };

    $factor['raw'] = function () use ($factory, $times, $attributes) {
        return $factory->make($times, $attributes)->toArray();
    };

    return $factor;
}

/**
 * @param string $name
 * @return Entity
 */
function entity(string $name): Entity
{
    if (!class_exists($name) || !Inflector::contains($name, 'App\Entities')) {
        $class = '\\App\\Entities\\' . Inflector::camelize($name);
    } else {
        $class = $name;
    }

    return app($class);
}

/**
 * @param string $name
 * @return object
 */
function service(string $name)
{
    if (!class_exists($name) || !Inflector::contains($name, 'App\Services')) {
        $class = '\\App\\Services\\' . Inflector::camelize($name);
    } else {
        $class = $name;
    }

    return app($class);
}

/**
 * @param string $name
 * @return object
 */
function manager(string $name)
{
    if (!class_exists($name) || !Inflector::contains($name, 'App\Managers')) {
        $class = '\\App\\Managers\\' . Inflector::camelize($name);
    } else {
        $class = $name;
    }

    return app($class);
}

/**
 * @return Main|mixed
 */
function main(?string $scope = null, ...$params)
{
    $main = Main::getInstance();

    return null === $scope ? $main : $main->{$scope}(...$params);
}

/**
 * @param string $name
 * @return Dynamicentity
 */
function eav(string $name): Dynamicentity
{
    $class = '\\App\\EAV\\' . Inflector::camelize($name);

    return app($class);
}

/**
 * @param string $ns
 * @return Fillable
 */
function reg(string $ns = 'core'): Fillable
{
    return bag($ns . '.reg');
}

/**
 * @return bool
 * @throws ReflectionException
 */
function isPost(): bool
{
    return request()->method() === 'POST';
}

/**
 * @param string $key
 * @param $value
 * @return Fillable
 */
function sendToView(string $key, $value)
{
    $vars = Octo\viewParams();
    $vars[$key] = $value;

    return $vars;
}

/**
 * @param string $table
 * @param string $database
 * @return Octalia
 */
function dbstore(string $table, string $database = 'core')
{
    $key = 'filestore.' . $table . '.' . $database;

    if (!$db = bag('instances')[$key])  {
        $path = Octo\cache_path() . '/fs';

        if (is_dir($path)) {
            Octo\File::mkdir($path);
        }

        $db = new Octalia($database, $table, new Octo\Cache('fs', $path), $path);

        bag('instances')[$key] = $db;
    }

    return $db->reset();
}

/**
 * @param string $table
 * @param string $database
 * @return Octalia
 */
function octalia(string $table, string $database = 'core')
{
    $key = 'octalia.' . $table . '.' . $database;

    if (!$db = bag('instances')[$key])  {
        $db = new Octalia($database, $table, (new Data('octalia'))->setStore((new \App\Models\Cache)->setNamespace('octalia')));

        bag('instances')[$key] = $db;
    }

    return $db->reset();
}

/**
 * @param string $table
 * @param string $database
 * @return Octalia
 */
function redisDb(string $table, string $database = 'core')
{
    $key = 'octared.' . $table . '.' . $database;

    if (!$db = bag('instances')[$key])  {
        $db = new Octalia($database, $table, new \App\Services\Reddy('octalia'));

        bag('instances')[$key] = $db;
    }

    return $db->reset();
}

/**
 * @param string $str
 * @param string $char
 * @return bool
 */
function str_has(string $str, string $char)
{
    return strpos($str, $char) !== false;
}

/**
 * @param mixed ...$args
 */
function pred(...$args)
{
    foreach ($args as $arg) {
        _pre($arg);
    }

    exit();
}

/**
 * @param mixed ...$args
 */
function pre(...$args)
{
    foreach ($args as $arg) {
        _pre($arg);
    }
}

/**
 * @param $arg
 */
function _pre($arg)
{
    echo "<pre>";
    print_r($arg);
    echo "</pre>";
}

/**
 * @return RedisManager
 */
function redis()
{
    return l('redis');
}

/**
 * @return SQLiteConnection
 */
function sqlite()
{
    /** @var SQLiteConnection $cnx */
    $cnx = dic('db')->connection('sqlite');

    inInstance($cnx->getPdo(), 'lite.pdo');

    return $cnx;
}

/**
 * @return MySqlConnection
 */
function db()
{
    $db = CoreConf::get('db');

    return dic('db')->connection($db['default']);
}

/**
 * @param mixed ...$args
 * @return mixed|null
 * @throws ReflectionException
 */
function call(...$args)
{
    return gi()->call(...$args);
}

/**
 * @param string $name
 * @param $notifiable
 * @return object
 * @throws ReflectionException
 */
function notify(string $name, $notifiable)
{
    if (!class_exists($name) || !Inflector::contains($name, 'App\Notifications')) {
        $class = '\\App\\Notifications\\' . Inflector::camelize($name);
    } else {
        $class = $name;
    }

    $instance = gi()->make($class, [$notifiable]);

    $channels = call($instance, 'channels');

    foreach ($channels as $channel) {
        call($instance, $channel, $notifiable);
    }

    return $instance;
}

/**
 * @param mixed $key
 * @param $value
 */
function setViewVar($key, $value)
{
    Octo\viewParams($key, $value);
}

/**
 * @param null|string $name
 * @param array $data
 * @param array $mergeData
 * @return \Illuminate\View\Factory|string
 */
function view(?string $name = null, array $data = [], array $mergeData = [])
{
    /** @var \Illuminate\View\Factory $view */
    $view = dic('view');

    if (0 === func_num_args()) {
        return $view;
    }

    $data += Octo\viewParams()->toArray();
    $data += session()->pull('_with', []);

    $data['errors'] = $data['errors'] ?? coll();

    Octo\setCore('blade.context', $data);

    return $view->make($name, $data, $mergeData)->render();
}

/**
 * @param array $data
 */
function redirectWith(array $data)
{
    session()->set('_with', $data);
}

/**
 * @param string $name
 * @param array $data
 * @param array $mergeData
 * @return string
 */
function render(string $name, array $data = [], array $mergeData = [])
{
    $vars = Octo\viewParams();

    foreach ($vars as $key => $value) {
        $data[$key] = $value;
    }

    $data['errors'] = $data['errors'] ?? coll();

    Octo\setCore('blade.context', $data);

    return dic('view')->file($name, $data, $mergeData)->render();
}

/**
 * @param int $ttl
 * @param string $prefix
 * @param string $connection
 * @return Cache
 */
function cacheService($ttl = 60, $prefix = '', $connection = 'default')
{
    static $caches = [];

    $key = sha1(serialize(func_get_args()));

    if (!$cache = isAke($caches, $key, null)) {
        $cache = new App\Services\Cache($ttl, $prefix, $connection);
        $caches[$key] = $cache;
    }

    return $cache;
}

/**
 * @param string $name
 * @return Data
 */
function dataStore(string $name = 'core')
{
    static $datas = [];

    $key = sha1($name);

    if (!$data = isAke($datas, $key, null)) {
        $data = new App\Services\Data($name);
        $datas[$key] = $data;
    }

    return $data;
}

/**
 * @param mixed ...$args
 * @return object
 * @throws ReflectionException
 */
function single(...$args)
{
    static $instances = [];

    $key = sha1(serialize($args));

    $class = array_shift($args);

    if (!$instance = isAke($instances, $key, null)) {
        $instance = gi()->make($class, $args, false);
        $instances[$key] = $instance;
    }

    return $instance;
}

/**
 * @param null $key
 * @param null $default
 * @return \App\Services\Reddy|null
 * @throws ReflectionException
 */
function reddy($key = null, $default = null)
{
    /** @var \App\Services\Reddy $session */
    $session = single(App\Services\Reddy::class);

    if (is_null($key)) {
        return $session;
    }

    if (is_array($key)) {
        return $session->put($key);
    }

    return $session->get($key, $default);
}

/**
 * @param string $message
 * @param array $context
 * @return Log
 * @throws ReflectionException
 */
function info(string $message, array $context = [])
{
    /** @var Log $logger */
    $logger = dic('logservice');

    $logger->info($message, $context);

    return $logger;
}
/**
 * @param string $message
 * @param array $context
 * @return Log
 * @throws ReflectionException
 */
function warning(string $message, array $context = [])
{
    /** @var Log $logger */
    $logger = dic('logservice');

    $logger->warning($message, $context);

    return $logger;
}

/**
 * @param null|string $message
 * @param array $context
 * @return Log
 * @throws ReflectionException
 */
function logs(?string $message = null, array $context = [])
{
    /** @var Log $logger */
    $logger = dic('logservice');

    if (!is_null($message)) {
        $logger->debug($message, $context);

    }

    return $logger;
}

/**
 * @param string $to
 * @param array $args
 * @param int $status
 * @return MessageTrait
 * @throws ReflectionException
 */
function redirector(string $to, array $args = [], int $status = 302)
{
    /** @var Octo\FastRedirector $r */
    $r = app(Octo\FastRedirector::class);

    if (fnmatch('/*', $to)) {
        return $r->to($to, $status);
    }

    return $r->route($to, $args, $status);
}

/**
 * @param string $tagName
 * @param array $attributes
 * @param null $content
 * @return string
 */
function html(string $tagName, array $attributes = [], $content = null)
{
    $result = "";
    $result .= "<{$tagName}";

    foreach ($attributes as $attributeName => $attributeValue) {
        $attrName = clean($attributeName);
        $attrValue = clean($attributeValue);
        $result .= " {$attrName}=\"$attrValue\"";
    }

    if (!empty($content)) {
        $result .= ">";
        $result .= clean($content);
        $result .= "</{$tagName}>";
    } else {
        $result .= "/>";
    }

    return $result;
}

/**
 * @param string $value
 * @return null|string|string[]
 */
function clean(string $value)
{
    return preg_replace('/[\x00-\x1F\x7F]/', '', $value);
}

/**
 * @param $arg
 * @return mixed
 */
function cloner($arg)
{
    return clone $arg;
}

/**
 * @param string $name
 * @param Closure|null $resolver
 * @param bool $makeAlias
 */
function resolver(string $name, ?Closure $resolver = null, bool $makeAlias = true)
{
    $resolvers = getIt('app.resolvers', []);

    if (!class_exists($name) && true === $makeAlias) {
        $aliases = include(Octo\app_path('config/aliases.php'));

        if ($alias = isAke($aliases, $name, null)) {
            if (is_string($alias)) {
                class_alias($alias, $name);
            } elseif ($alias instanceof Closure) {
                $factory = gi()->makeClosure($alias);

                class_alias(get_class($factory), $name);

                $resolver = Octo\voidToCallback($factory);
            }
        } else {
            $factory = gi()->makeClosure($resolver);

            if (is_object($factory)) {
                class_alias(get_class($factory), $name);
            }

            $resolver = Octo\voidToCallback($factory);
        }
    }

    $resolvers[$name] = $resolver;

    setIt('app.resolvers', $resolvers);
}

/**
 * @param mixed ...$args
 * @return mixed|null
 * @throws ReflectionException
 */
function resolve(...$args)
{
    $class = array_shift($args);
    $resolvers = getIt('app.resolvers', []);

    if ($resolver = isAke($resolvers, $class, null)) {
        if ($resolver instanceof Closure) {
            return gi()->makeClosure($resolver, ...$args);
        } elseif (is_callable($resolver) && is_array($resolver)) {
            $params = array_merge($resolver, $args);

            return gi()->call(...$params);
        }
    }

    return null;
}

/**
 * @param null|string $key
 * @param string $value
 * @return mixed|Component
 * @throws ReflectionException
 */
function container(?string $key = null, $value = 'octodummy')
{
    static $container;

    if (!is_object($container)) {
        $container = new Pack;

        $container['factory'] = function (string $name, Closure $resolver) use ($container) {
            resolver($name, $resolver);
            $container[$name] = $resolver;
        };
    }

    if (null !== $key && 'factory' !== $key) {
        if ('octodummy' === $value) {
            return $container[$key];
        } else {
            $container[$key] = $value;
        }
    }

    return $container;
}

/**
 * @param string $ip
 * @return mixed
 */
function ipinfo(string $ip)
{
    $json = file_get_contents("https://fr.mappy.com/front-services/geoip?ip=$ip");

    return json_decode($json, true);
}

/**
 * @param object $object
 * @param string $property
 * @param $value
 * @throws ReflectionException
 */
function setValue(&$object, string $property, $value)
{
    $prop = new ReflectionProperty($object, $property);
    $accessible = $prop->isPublic();

    if (false === $accessible) {
        $prop->setAccessible(true);
    }

    $prop->setValue($object, $value);

    if (false === $accessible) {
        $prop->setAccessible(false);
    }
}

/**
 * @param $object
 * @param string $method
 * @return ReflectionMethod
 */
function setPublic($object, string $method)
{
    $reflector = new ReflectionObject($object);
    $meth = $reflector->getMethod($method);
    $meth->setAccessible(true);

    return $meth;
}

/**
 * @param $class
 * @param array $args
 * @param bool $singleton
 * @return mixed
 */
function build($class, array $args = [], bool $singleton = true)
{
    static $bounds = [];

    if (true === $singleton) {
        $key = sha1($class);

        if ($instance = $bounds[$key] ?? null) {
            return $instance;
        }
    }

    $reflector = new ReflectionClass($class);

    if (!$reflector->isInstantiable()) {
        return false;
    }

    $constructor = $reflector->getConstructor();

    if (is_null($constructor)) {
        $instance = new $class;

        if (true === $singleton) {
            $bounds[$key] = $instance;
        }

        return $instance;
    }

    $app = l();

    if (!empty($args)) {
        $prop = new ReflectionProperty($app, 'with');
        $prop->setAccessible(true);
        $prop->setValue($app, [$args]);
    }

    $dependencies = $constructor->getParameters();

    $resolver = setPublic($app, 'resolveDependencies');

    $instances = $resolver->invokeArgs(
        $app, [$dependencies]
    );

    $instance = $reflector->newInstanceArgs($instances);

    if (true === $singleton) {
        $bounds[$key] = $instance;
    }

    return $instance;
}

/**
 * @param string $string
 * @param array $vars
 * @return string
 */
function compileVars(string $string, array $vars): string
{
    foreach ($vars as $key => $value) {
        $string = str_replace("<$key>", $value, $string);
    }

    return $string;
}

/**
 * @param string $path
 * @param string $manifestDirectory
 * @return string
 * @throws Exception
 */
function mix(string $path, string $manifestDirectory = ''): string
{
    return Octo\mix($path, $manifestDirectory);
}

/**
 * @param string $start
 * @param string $end
 * @param string $concern
 * @param null|string $default
 * @return null|string
 */
function findIn(string $start, string $end, string $concern, ?string $default = null): ?string
{
    if (!empty($concern) &&
        strstr($concern, $start) &&
        strstr($concern, $end) &&
        !empty($start) &&
        !empty($end)) {
        $segment = explode($start, $concern, 2)[1];

        if (!empty($segment) && strstr($segment, $end)) {
            return explode($end, $segment, 2)[0];
        }
    }

    return $default;
}

/**
 * @param string $code
 * @return mixed
 * @throws Exception
 */
function evaluate(string $code, array $data = [])
{
    $output = Octo\evalNs('', "return $code;", $data);

    return eval($output);
}

/**
 * @param $code
 * @throws Exception
 */
function myEval(string $code, array $data = [])
{
    $output = Octo\evalNs('', $code, $data);

    return eval($output);
}

/**
 * @param string $expression
 * @return string
 */
function stripParentheses(string $expression)
{
    if (Octo\startsWith($expression, '(')) {
        $expression = substr($expression, 1, -1);
    }

    return $expression;
}

/**
 * @param string $string
 * @return string
 */
function he(string $string): string
{
    return htmlentities($string, ENT_QUOTES, 'UTF-8', false);
}

/**
 * @param $concern
 * @return mixed
 */
function w($concern)
{
    return $concern;
}

/**
 * @param $concern
 * @return mixed
 */
function cl($concern)
{
    return cloner($concern);
}

/**
 * @param $concern
 * @return Decorator
 */
function decorate($concern)
{
    return new Decorator($concern);
}

/**
 * @param $concern
 * @return mixed
 */
function instance($concern)
{
    return App\Facades\Container::set(get_class($concern), Octo\toClosure($concern));
}

/**
 * @param $concern
 * @return \Octo\Nullable
 */
function nullable($concern)
{
    return Octo\nullable($concern);
}

/**
 * @param string $key
 * @param $value
 * @return \App\Services\Container
 * @throws ReflectionException
 * @throws FastContainerException
 */
function set(string $key, $value)
{
    return c()->define($key, $value);
}

/**
 * @param string $key
 * @param null $default
 * @return mixed|null
 * @throws ReflectionException
 * @throws FastContainerException
 */
function get(string $key, $default = null)
{
    return c()->defined($key, $default);
}

/**
 * @param string $key
 * @return bool
 * @throws ReflectionException
 * @throws FastContainerException
 */
function has(string $key)
{
    return c()->isDefined($key);
}

/**
 * @param string $key
 * @return bool
 * @throws ReflectionException
 * @throws FastContainerException
 */
function del(string $key)
{
    return c()->forget($key);
}

/**
 * @param string $key
 * @param int $by
 * @return int
 * @throws ReflectionException
 * @throws FastContainerException
 */
function incr(string $key, int $by = 1)
{
    return c()->incr($key, $by);
}

/**
 * @param string $key
 * @param int $by
 * @return int
 * @throws ReflectionException
 * @throws FastContainerException
 */
function decr(string $key, int $by = 1)
{
    return c()->decr($key, $by);
}

/**
 * @param int $ŝtatus
 * @param string $content
 * @param array $headers
 * @return Response
 */
function err(int $status, string $content, array $headers = [])
{
    return new Response($status, $headers, $content);
}

/**
 * @param string $message
 * @param string $class
 */
function fail(string $message, string $class = Exception::class)
{
    throw new $class($message);
}

/**
 * @param null $job
 * @return \App\Services\Queue|mixed
 * @throws Exception
 */
function queue($job = null)
{
    /** @var \App\Services\Queue $queue */
    $queue = dic(App\Services\Queue::class);

    if (is_object($job)) {
        return $queue->push($job);
    } elseif (is_string($job) && class_exists($job)) {
        return $queue->push(dic($job));
    }

    return $queue;
}

/**
 * @param $job
 * @param int $minutes
 * @param string $queue
 * @return string
 * @throws \Octo\Exception
 */
function job($job, int $minutes = 0, string $queue = 'main')
{
    $path = Octo\storage_path('queues');

    if (!is_dir($path)) {
        Octo\File::mkdir($path);
    }

    $path .= '/' . $queue;

    if (!is_dir($path)) {
        Octo\File::mkdir($path);
    }

    $path .= '/todo';

    if (!is_dir($path)) {
        Octo\File::mkdir($path);
    }

    if (is_string($job) && class_exists($job)) {
        $job = dic($job);
    }

    $id = Illuminate\Support\Str::random(32);

    $file = $path . '/' . $id . '.job';

    Octo\File::put($file, serialize($job));

    touch($file, time() + ($minutes * 60));

    return $id;
}

function jobs(string $queue = 'main')
{
    $now = Carbon::now()->getTimestamp();
    $path = Octo\storage_path("queues/$queue/todo");
    $jobs = glob($path . '/*.job', GLOB_NOSORT);
    $collection = [];

    foreach ($jobs as $job) {
        $at = filemtime($job);

        if ($at <= $now) {
            $item = ['job' => $job, 'time' => $at];
            $collection[] = $item;
        }
    }

    return acoll($collection)->sortBy('time')->pluck('job');
}

function runJobs()
{
    $jobs = jobs();

    foreach ($jobs as $job) {
        $explode = explode('/', $job);
        array_pop($explode);
        array_pop($explode);
        $failDir = implode('/', $explode) . '/failed';

        if (!is_dir($failDir)) {
            Octo\File::mkdir($failDir);
        }

        $instance = unserialize(file_get_contents($job));

        try {
            gi()->call($instance, 'handle');

            if (method_exists($instance, 'onSuccess')) {
                gi()->call($instance, 'onSuccess');
            }

            unlink($job);
        } catch (\Exception $e) {
            if (method_exists($instance, 'onFail')) {
                gi()->call($instance, 'onFail');
            }
        }
    }
}

function execJobs()
{
    exec("cd ..;php bg.php > /dev/null &");
}

/**
 * @param $value
 * @return array
 */
function wrap($value): array
{
    if (is_null($value)) {
        return [];
    }

    if (arrayable($value)) {
        return $value->toArray();
    }

    return !is_array($value) ? [$value] : $value;
}

/**
 * @param Throwable $e
 * @return bool
 */
function lostRedis(Throwable $e): bool
{
    $message = $e->getMessage();

    return Inflector::contains($message, [
        'server has gone away',
        'no connection to the server',
        'Lost connection',
        'is dead or not enabled',
        'Error while sending',
        'decryption failed or bad record mac',
        'server closed the connection unexpectedly',
        'SSL connection has been closed unexpectedly',
        'Error writing data to the connection',
        'Resource deadlock avoided',
        'Transaction() on null',
        'child connection forced to terminate due to client_idle_limit',
        'query_wait_timeout',
        'reset by peer',
    ]);
}

/**
 * @param $concern
 * @return bool|mixed|null
 */
function data($concern)
{
    static $stored = [];

    if (is_array($concern) && count($concern) === 1) {
        $stored[key($concern)] = reset($concern);

        return true;
    }

    if (is_object($concern)) {
        $stored[get_class($concern)] = $concern;

        return true;
    }

    if (is_string($concern)) {
        if (1 === func_num_args()) {
            return $stored[$concern] ?? null;
        } elseif (2 === func_num_args()) {
            $args = func_get_args();

            $stored[array_shift($args)] = array_shift($args);

            return true;
        }
    }
}

/**
 * @param Elegant $item
 * @param array $fields
 */
function searchable(Elegant $item, array $fields = [])
{
    $pipe   = redis()->pipeline();
    $data   = $item->toArray();
    $class  = str_replace('\\', '_', get_class($item));
    $key    = $class . '.' . $data['id'];

    unset($data['created_at']);
    unset($data['deleted_at']);
    unset($data['updated_at']);

    $pipe->hmset($key, $data);

    if (!empty($fields)) {
        foreach ($fields as $field) {
            $ikey = $class . '.' . $field . '.' . $data[$field];
            $pipe->sadd($ikey, $data['id']);
        }
    }

    $pipe->execute();
}

/**
 * @param $a
 * @param Closure $c
 * @return mixed
 */
function watch($concern, Closure $callback, ...$args)
{
    return $concern ?? gi()->makeClosure($callback, ...$args);
}

/**
 * @return Connection
 */
function em()
{
    $db         = CoreConf::get('db');
    $default    = $db['default'];
    $conf       = $db[$default];

    $driver = new Driver;

    return new Connection([
        'pdo'       => dic(PDO::class),
        'dbname'    => $conf['database'],
        'driver'    => $driver->getName(),
    ], $driver);
}

/**
 * @return \Doctrine\DBAL\Query\QueryBuilder
 */
function qb()
{
    return em()->createQueryBuilder();
}

/**
 * @param string $table
 * @return \Illuminate\Database\Query\Builder
 */
function table(string $table)
{
    return Db::table($table);
}

/**
 * @param $key
 * @param string $match
 * @return mixed
 */
function sscan($key, $match = '*')
{
    return redis()->eval(Lua::sscan(), 2, $key, $match)[1];
}

/**
 * @param array $attributes
 * @return Fluent
 */
function fluent($attributes = [])
{
    return new Fluent($attributes);
}

/**
 * @param array $attributes
 * @return Fluent
 */
function crudField($attributes = [])
{
    return fluent($attributes);
}

/**
 * @param mixed ...$arrays
 * @return array
 */
function unique(...$arrays)
{
    return array_unique(array_merge(...$arrays));
}

/**
 * @param mixed $concerns
 * @param Closure $callback
 * @return array
 */
function filter($concerns, Closure $callback)
{
    $concerns = arrayable($concerns) ? $concerns->toArray() : $concerns;

    return array_filter($concerns, $callback);
}

/**
 * @param $needle
 * @param $haystack
 * @return bool
 */
function in_arrayi($needle, $haystack)
{
    return in_array(mb_strtolower($needle), array_map('mb_strtolower', $haystack));
}

/**
 * @param $callback
 * @param mixed ...$args
 * @return mixed|null
 */
function call_func($callback, ...$args)
{
    if (is_string($callback) && strpos($callback, '::') !== false) {
        $callback = explode('::', $callback);
    } elseif (is_string($callback) && strpos($callback, '@') !== false) {
        $callback = explode('@', $callback);
    }

    if (is_string($callback) && class_exists($callback)) {
        $callback = gi()->make($callback);
    }

    if (is_array($callback) && isset($callback[1]) && is_object($callback[0])) {
        if (!empty($args)) {
            $args = array_values($args);
        }

        $params = array_merge($callback, $args);

        return gi()->call(...$params);
    } elseif (is_array($callback) && isset($callback[1]) && is_string($callback[0])) {
        list($class, $method) = $callback;
        $class = '\\'.ltrim($class, '\\');
        $instance = gi()->make($class);
        $params = array_merge([$instance, $method], $args);

        return gi()->call(...$params);
    } elseif ($callback instanceOf Closure) {
        return gi()->makeClosure($callback, ...$args);
    } elseif (is_object($callback) && \Octo\is_invokable($callback)) {
        return gi()->call([$callback, '__invoke'], ...$args);
    }

    return $callback(...$args);
}

/**
 * @param $callback
 * @param mixed ...$args
 * @return mixed|null
 */
function cf($callback, ...$args)
{
    return call_func($callback, ...$args);
}

/**
 * @param $callback
 * @param mixed ...$args
 * @return mixed|null
 */
function caller($callback, ...$args)
{
    return call_func($callback, ...$args);
}

/**
 * @return bool
 */
function isProd(): bool
{
    return getAppenv() === 'production';
}

/**
 * @return string
 */
function getAppenv(): string
{
    return CoreConf::get('app')['env'] ?? 'production';
}

/**
 * @param mixed ...$args
 * @return mixed
 * @throws FastContainerException
 * @throws ReflectionException
 */
function callOnce(...$args)
{
    return Octo\callOnce(...$args);
}

/**
 * @param array $data
 * @return LColl
 */
function lcoll(array $data = [])
{
    return LColl::make($data);
}

/**
 * @param array $data
 * @return Collection
 */
function acoll(array $data = [])
{
    return Collection::make($data);
}

/**
 * @param string $name
 * @param null $default
 * @return mixed|null
 */
function pkey(string $name, $default = null)
{
    $keys = include Octo\config_path('keys.php');

    return Octo\aget($keys, $name, $default);
}

/**
 * @param string $type
 * @return mixed
 */
function disk($type = 'local')
{
    return \Storage::disk($type);
}

/**
 * @param string $html
 * @return mixed
 */
function pdf(string $html, string $name, string $orientation = 'Portrait')
{
    $password = pkey('pdf.password');
    $pdf = Octo\post_request(pkey('pdf.url'), compact('html', 'password', 'orientation'));

    header("Content-type: application/pdf");
    header("Content-Length: " . strlen($pdf));
    header("Content-Disposition: attachement; filename=$name.pdf");

    die($pdf);
}

/**
 * @param string $class
 * @return Listener[]
 */
function subscribe(string $class)
{
    return Event::subscribe($class);
}

/**
 * @param null|string $class
 * @return string
 */
function userModel(?string $class = null)
{
    static $model = App\Models\User::class;

    if (null !== $class && class_exists($class)) {
        $model = $class;
    }

    return $model;
}

/**
 * @param null|string $class
 * @return string
 */
function userRepository(?string $class = null)
{
    static $repository = App\Repositories\UserRepository::class;

    if (null !== $class && class_exists($class)) {
        $repository = $class;
    }

    return $repository;
}

/**
 * @param Model $model
 * @param string $class
 * @param null $foreignKey
 * @param null $localKey
 * @return mixed
 */
function many(Model $model, string $class, $foreignKey = null, $localKey = null)
{
    return $model->hasMany($class, $foreignKey, $localKey)->getResults();
}

/**
 * @param $source
 * @param $dest
 * @param int $permissions
 * @return bool
 */
function xcopy($source, $dest, $permissions = 0755)
{
    if (is_link($source)) {
        return symlink(readlink($source), $dest);
    }

    if (is_file($source)) {
        return copy($source, $dest);
    }

    if (!is_dir($dest)) {
        $oldmask = umask(0);
        mkdir($dest, $permissions, true);
        umask($oldmask);
    }

    $dir = dir($source);

    while (false !== $entry = $dir->read()) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        xcopy("$source/$entry", "$dest/$entry", $permissions);
    }

    $dir->close();

    return true;
}

/**
 * @param null|string $key
 * @param mixed $value
 * @return Settings|mixed|null
 * @throws ReflectionException
 */
function option(?string $key = null, $value = 'octodummy')
{
    $option = Option::self();

    if (null === $key) {
        return $option;
    }

    if ('octodummy' === $value) {
        return $option->get($key);
    }

    return $option->set($key, $value);
}

/**
 * @param null|string $key
 * @param string $value
 * @return Settings|mixed|null
 * @throws ReflectionException
 */
function setting(?string $key = null, $value = 'octodummy')
{
    $option = Setting::self();

    if (null === $key) {
        return $option;
    }

    if ('octodummy' === $value) {
        return $option->get($key);
    }

    return $option->set($key, $value);
}

/**
 * @param null|string $namespace
 * @return \App\Models\Cache
 */
function store(?string $namespace = null)
{
    $store = new \App\Models\Cache;

    if (null !== $namespace) {
        return $store->setNamespace($namespace);
    }

    return $store;
}

/**
 * @return \App\Models\Cache
 * @throws FastContainerException
 * @throws ReflectionException
 */
function me()
{
    if ($user = user()) {
        return store('u.' . $user->id);
    }

    return store('u.' . Octo\forever());
}

/**
 * @param callable $job
 * @param int $count
 * @param int $sleep
 * @return array
 * @throws ReflectionException
 */
function multiProcess(callable $job, int $count = 5, int $sleep = 1)
{
    $parentPid = getmypid();

    $children = [];

    for ($i = 0; $i < $count; $i += 1) {
        $pid = pcntl_fork();

        if (!$pid) {
            sleep($sleep);
            $partition = [$i, $count, $parentPid];
            call_func($job, $partition);

            exit($i + 1);
        } else {
            $children[] = $pid;
        }
    }

    $logs = ['success' => [], 'fail' => []];

    foreach($children as $child) {
        $pid = pcntl_wait($status);

        if (pcntl_wifexited($status)) {
            $code = pcntl_wexitstatus($status);
            $logs['success'][] = ['code' => $code, 'pid' => $pid];
        } else {
            $logs['fail'][] = ['pid' => $pid];
        }
    }

    return $logs;
}

/**
 * @param string $name
 * @return Logger
 */
function logger(string $name = 'core')
{
    static $loggers = [];

    if (null === ($instance = isAke($loggers, $name, null))) {
        $instance = (new Logger)->setNamespace($name);

        $loggers[$name] = $instance;
    }

    return $instance;
}

/**
 * @param null|string $key
 * @param null $default
 * @return array|mixed|null
 */
function calledBy(?string $key = null, $default = null)
{
    $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? [];

    if (is_string($key)) {
        return $caller[$key] ?? $default;
    }

    return $caller;
}

/**
 * @param string|object $concern
 * @param null|string $concernKey
 * @return mixed|null
 */
function inInstance($concern, ?string $concernKey = null)
{
    if (is_object($concern)) {
        if (null === $concernKey) {
            $key = 'ii.' . get_class($concern);
        } else {
            $key = 'ii.' . $concernKey;
        }

        set($key, $concern);
    }

    if (is_string($concern)) {
        $key = 'ii.' . $concern;

        return get($key);
    }
}

/**
 * @param string $url
 * @return array
 */
function urlCron(string $url)
{
    exec("wget -q -O /dev/null '$url' > /dev/null 2>&1", $result, $status);

    return [$result, $status];
}

/**
 * @param array $array
 * @param callable $callback
 */
function browseArray(array &$array, callable $callback)
{
    if (isset($array)) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                browseArray($array[$key], $callback);
            } else {
                $array[$key] = cf($callback, $array[$key], $key);
            }
        }
    }
}

/**
 * @return array
 */
function getTZList()
{
    return [
        'EUROPE'        => DateTimeZone::listIdentifiers(DateTimeZone::EUROPE),
        'AMERICA'       => DateTimeZone::listIdentifiers(DateTimeZone::AMERICA),
        'INDIAN'        => DateTimeZone::listIdentifiers(DateTimeZone::INDIAN),
        'AUSTRALIA'     => DateTimeZone::listIdentifiers(DateTimeZone::AUSTRALIA),
        'ASIA'          => DateTimeZone::listIdentifiers(DateTimeZone::ASIA),
        'AFRICA'        => DateTimeZone::listIdentifiers(DateTimeZone::AFRICA),
        'ANTARCTICA'    => DateTimeZone::listIdentifiers(DateTimeZone::ANTARCTICA),
        'ARCTIC'        => DateTimeZone::listIdentifiers(DateTimeZone::ARCTIC),
        'ATLANTIC'      => DateTimeZone::listIdentifiers(DateTimeZone::ATLANTIC),
        'PACIFIC'       => DateTimeZone::listIdentifiers(DateTimeZone::PACIFIC),
        'UTC'           => DateTimeZone::listIdentifiers(DateTimeZone::UTC),
    ];
}

function gd()
{
    return new Filesystem(new SftpAdapter(pkey('gd')));
}

/**
 * @param string $what
 * @return PDO
 */
function pdo(string $what = 'main')
{
    return inInstance($what . '.pdo');
}

/**
 * @param string $type
 * @param int $len
 * @return bool|int|string
 */
function rdmString(string $type = 'alnum', int $len = 8)
{
    switch ($type) {
        case 'basic':
            return mt_rand();
        case 'alnum':
        case 'numeric':
        case 'nozero':
        case 'alpha':
            switch ($type) {
                case 'alpha':
                    $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    break;
                case 'alnum':
                    $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    break;
                case 'numeric':
                    $pool = '0123456789';
                    break;
                case 'nozero':
                    $pool = '123456789';
                    break;
            }

            return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
        case 'unique': // todo: remove in 3.1+
        case 'md5':
            return md5(uniqid(mt_rand()));
        case 'encrypt': // todo: remove in 3.1+
        case 'sha1':
            return sha1(uniqid(mt_rand(), TRUE));
    }
}

/**
 * @param string $str
 * @param string $sep
 * @param int $first
 * @return string
 */
function incrString(string $str, string $sep = '_', int $first = 1): string
{
    preg_match('/(.+)'.preg_quote($sep, '/').'([0-9]+)$/', $str, $match);

    return isset($match[2]) ? $match[1] . $sep . ($match[2] + 1) : $str . $sep . $first;
}

/**
 * @param \Psr\Http\Message\ResponseInterface $response
 * @return \Psr\Http\Message\ResponseInterface
 */
function noCache(\Psr\Http\Message\ResponseInterface $response)
{
    return $response
        ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
        ->withHeader('Pragma', 'no-cache')
    ;
}

/**
 * @param string $field
 * @param string $destination
 * @throws ReflectionException
 */
function upload(string $field, string $destination)
{
    $request = new FastRequest();

    $file = $request->file($field);

    $file->moveTo($destination);
}

/**
 * @param string $to
 * @param string $from
 * @param string $subject
 * @param string $body
 * @param bool $html
 * @return bool
 */
function mailCurl(string $to, string $from, string $subject, string $body, bool $html = true)
{
    $headers = [];

    $headers[] = "From: $from";

    if (true === $html) {
        $headers[] = "Content-Type: text/html; charset=utf-8";
    }

    try {
        $handler = curl_init(pkey('mail.curl'));

        $data = [
            'to'        => base64_encode($to),
            'subject'   => base64_encode($subject),
            'body'      => base64_encode($body),
            'key'       => pkey('mail.remote.key'),
            'headers'   => base64_encode(implode("\n", $headers))
        ];

        curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handler, CURLOPT_POST, 1);
        curl_setopt($handler, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($handler);

        curl_close($handler);

        return 'OK' === $result ? true : false;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * @param string $to
 * @param string $from
 * @param string $subject
 * @param string $html
 * @param string $text
 * @param null|string $file
 * @param null|string $filename
 * @return bool
 */
function remoteMail(
    string $to,
    string $from,
    string $subject,
    string $html,
    string $text = '',
    ?string $file = null,
    ?string $filename = null
) {
    if (!empty($file)) {
        $content = file_get_contents($file);
        $content = chunk_split(base64_encode($content));

        if (empty($filename)) {
            $filename = Arrays::last(explode('/', $file));
        }
    }

    $separator = md5(uniqid(time()));

    $eol = "\r\n";

    $headers = "From: " . $from . $eol;
    $headers .= "Date: Fri, 7 Sep 2018 16:42:50 +0200" . $eol;
    $headers .= "MIME-Version: 1.0" . $eol;
    $headers .= "Content-Type: multipart/alternative; charset=\"UTF-8\"; boundary=\"" . $separator . "\"" . $eol;
    $headers .= "Content-Transfer-Encoding: 8bit" . $eol;
    $headers .= "This is a MIME encoded message." . $eol;

    $body = '';

    if (!empty($text)) {
        $body .= "--" . $separator . $eol;
        $body .= "Content-Type: text/plain; charset=\"utf-8\"" . $eol;
        $body .= "Content-Transfer-Encoding: 8bit" . $eol . $eol;
        $body .= $text . $eol . $eol;
    }

    if (!empty($html)) {
        $body .= "--" . $separator . $eol;
        $body .= "Content-Type: text/html; charset=\"utf-8\"" . $eol;
        $body .= "Content-Transfer-Encoding: 8bit" . $eol . $eol;
        $body .= $html . $eol . $eol;
    }

    if (!empty($file)) {
        $body .= "--" . $separator . $eol;
        $body .= "Content-Type: application/octet-stream; name=\"" . $filename . "\"" . $eol;
        $body .= "Content-Transfer-Encoding: base64" . $eol;
        $body .= "Content-Disposition: attachment" . $eol . $eol;
        $body .= $content . $eol . $eol;
        $body .= "--" . $separator . "--";
    }

    try {
        $handler = curl_init(pkey('mail.remote.url'));

        $data = [
            'to'        => base64_encode($to),
            'subject'   => base64_encode($subject),
            'body'      => base64_encode($body),
            'headers'   => base64_encode($headers),
            'key'       => pkey('mail.remote.key'),
        ];

        curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handler, CURLOPT_POST, 1);
        curl_setopt($handler, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($handler);

        curl_close($handler);

        return 'OK' === $result ? true : false;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * @param null|string $key
 * @param callable|null $hook
 * @return array|callable|null
 */
function hook(?string $key = null, ?callable $hook = null)
{
    static $hooks = [];

    if (is_callable($hook)) {
        $hooks[$key] = $hook;
    }

    if (is_string($key)) {
        return $hooks[$key] ?? null;
    }

    return $hooks;
}

/**
 * @param string $key
 * @param mixed ...$args
 * @return mixed|null
 */
function hooky(string $key, ...$args)
{
    $hook = hook($key);

    if (is_callable($hook)) {
        $params = array_merge([$hook], $args);

        return gi()->makeClosure(...$params);
    }

    return null;
}

function bank(string $table)
{
    return new \Octo\Bank('main', $table, new \App\Services\RedisEngine);
}
