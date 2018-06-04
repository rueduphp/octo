<?php

use App\Modules\SocialLoginModule;
use App\Modules\StaticModule;
use App\Services\Log;
use Carbon\Carbon;
use GuzzleHttp\Psr7\MessageTrait;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\DatabasePresenceVerifier;
use Monolog\Logger as Monolog;
use Octo\App;
use Octo\Bcrypt;
use Octo\Capsule;
use Octo\Component;
use Octo\Configurator;
use Octo\Dynamicentity;
use Octo\Dynamicmodel;
use Octo\Elegant;
use Octo\Entity;
use Octo\Facades\Config as CoreConf;
use Octo\Facades\Validator;
use Octo\Fast;
use Octo\FastBladeDirectives;
use Octo\Fastcontainer;
use Octo\FastRequest;
use Octo\FastTwigExtension;
use Octo\Fillable;
use Octo\In;
use Octo\Inflector;
use Octo\Orm;
use Octo\Setup;
use Octo\Shoppingcart;
use Octo\Url;
use Zend\Expressive\Router\FastRouteRouter;
use function Octo\bladeDirective;
use function Octo\echoInDirective;
use function Octo\getCore as getIt;
use function Octo\gi;
use function Octo\in_paths;
use function Octo\inners;
use function Octo\innersSession;
use function Octo\isAke;
use function Octo\setCore as setIt;
use function Octo\startSession;
use function Octo\systemBoot;

/**
 * @param bool $cli
 * @throws ReflectionException
 * @throws \Octo\Exception
 */
function bootApp($cli = false)
{
    systemBoot(realpath(__DIR__));

    $paths = in_paths();

    $dirApp = __DIR__;

    $paths['app']       = $dirApp;
    $paths['base']      = realpath(__DIR__ . '/../');
    $paths['public']    = realpath(__DIR__ . '/../public');
    $paths['config']    = $dirApp . '/config';
    $paths['storage']   = $dirApp . '/storage';
    $paths['database']  = $dirApp . '/storage/database';
    $paths['cache']     = $dirApp . '/storage/cache';
    $paths['sessions']  = $dirApp . '/storage/sessions';
    $paths['log']       = $dirApp . '/storage/log';
    $paths['lang']      = $dirApp . '/lang';
    $paths['views']     = $dirApp . '/views';

    addConfig('app');
    addConfig('session');
    addConfig('db');
    addConfig('redis');
    addConfig('mail');

    (new Octo\Sender())
        ->setHost(config('mail.host'))
        ->setPort(config('mail.port'))
        ->smtp();

    dic('mail', function () {
        return new \Octo\Mailable;
    });

    dic()::singleton('view', function () {
       return Octo\bladeFactory([__DIR__ . '/views']);
    });

    dic()::singleton('logservice', function () {
        $log = new Log(new Monolog('octo'));
        $log->useDailyFiles(
            \Octo\log_path() . '/octo.log',
            5,
            'debug'
        );

        return $log;
    });

    l()->singleton('view', function () {
       return dic('view');
    });

    l('config', app(Configurator::class));

    $sessions = include \Octo\config_path() . '/session.php';
    l('config')->set(['session' => $sessions]);

    directives();

    dic('eventer', \Octo\getEventManager());

    inners();
    startDb();

    if (false === $cli) {
        session_set_save_handler(new \App\Services\SessionRedis);
        startSession();
    }

    innersSession();

    $verifier = new DatabasePresenceVerifier(l('db'));

    Validator::setPresenceVerifier($verifier);

    $app = App::create();

    aliases();

    l()->singleton(\Laravel\Socialite\Contracts\Factory::class, function ($app) {
        return new \Laravel\Socialite\SocialiteManager($app);
    });

    l()->singleton('session', function ($app) {
        return new \Illuminate\Session\SessionManager($app);
    });

    l()->singleton('files', function () {
        return new \Illuminate\Filesystem\Filesystem;
    });

    l()->singleton('session.store', function ($app) {
        return $app->make('session')->driver();
    });

    l()->singleton('request', function ($app) use ($dirApp) {
        $request = new \Illuminate\Http\Request;
        $request->setLaravelSession(l('session.store'));

        return $request;
    });

    dic('social', l(\Laravel\Socialite\Contracts\Factory::class));

    if (false === $cli) {
        $app
            ->set(Octo\Fastmiddlewarecsrf::class, function () {
                $session = session();

                return new Octo\Fastmiddlewarecsrf($session);
            })
            ->set(Octo\FastSessionInterface::class, function () {
                return session();
            })
        ;

        bootMiddlewares($app);

        $app->addModule(StaticModule::class);
        $app->addModule(SocialLoginModule::class);

        $response = $app->run();

        $app->render($response);
    }
}

function bootMiddlewares($app)
{
    $app
        ->addMiddleware(\App\Middlewares\Exception::class)
        ->addMiddleware(Octo\Fastmiddlewaretrailingslash::class)
        ->addMiddleware(Octo\Fastmiddlewarecsrf::class)
        ->addMiddleware(\App\Middlewares\Session::class)
        ->addMiddleware(Octo\Fastmiddlewarerouter::class)
        ->addMiddleware(\App\Middlewares\Gate::class)
        ->addMiddleware(Octo\Fastmiddlewaredispatch::class)
        ->addMiddleware(Octo\Fastmiddlewarenotfound::class)
    ;
}

/**
 * @param Fillable $db
 * @throws ReflectionException
 */
function startDb()
{
    $db     = CoreConf::get('db');
    $redis  = CoreConf::get('redis');

    $default = $db['default'];

    $conf = $db[$default];
    $lite = $db["sqlite"];

    $PDOoptions = [
        PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
        PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES    => false,
        PDO::ATTR_EMULATE_PREPARES     => false,
    ];

    $pdo = new PDO(
        "{$conf['driver']}:host={$conf['host']};dbname={$conf['database']}",
        $conf['username'],
        $conf['password'],
        $PDOoptions
    );

    Capsule::instance($pdo);

    l('config')->set([
        'database' => [
            'default' => $default,
            'connections' => [
                $default => [
                    'driver'        => $conf['driver'],
                    'host'          => $conf['host'],
                    'port'          => $conf['port'],
                    'database'      => $conf['database'],
                    'username'      => $conf['username'],
                    'password'      => $conf['password'],
                    'unix_socket'   => '',
                    'charset'       => $conf['charset'] ?? 'utf8',
                    'collation'     => $conf['collation'] ?? 'utf8_unicode_ci',
                    'prefix'        => '',
                    'strict'        => true,
                    'engine'        => null,
                ],
                'sqlite' => [
                    'driver'    => 'sqlite',
                    'database'  => $lite['database'],
                    'prefix'    => $lite['prefix'] ?? '',
                ]
            ],
            'redis' => [

                'client' => 'predis',

                'default'       => [
                    'host'      => $redis['host'],
                    'password'  => $redis['password'],
                    'port'      => $redis['port'],
                    'database'  => $redis['database'],
                ],

            ],
        ]
    ]);

    l(
        'db',
        new DatabaseManager(
            l(),
            new Illuminate\Database\Connectors\ConnectionFactory(l())
        )
    );

    dic('db', l('db'));

    dic('orm', new Orm($pdo));

    Dynamicmodel::migrate();

    /* REDIS */
    l()->singleton('redis', function ($app) {
        $config = $app->make('config')->get('database.redis');

        return new RedisManager(Octo\Arrays::pull($config, 'client', 'predis'), $config);
    });

    l()->bind('redis.connection', function ($app) {
        return $app['redis']->connection();
    });

    dic('redis', l('redis'));
    dic('redis.connection', l('redis.connection'));
}

function aliases()
{
    Setup::alias('App\Session',    'session');
    Setup::alias('App\Form',       'form');
    Setup::alias('App\Html',       'html');
}

/**
 * @throws ReflectionException
 */
function directives()
{
    /** @var FastTwigExtension $twig */
    $twig = app(FastTwigExtension::class);

    FastBladeDirectives::register();

    bladeDirective('locale', function () {
        return echoInDirective(locale());
    });

    bladeDirective('isLogged', function () {
        return '<?php if (auth()->logged()): ?>';
    });

    bladeDirective('isNotLogged', function () {
        return '<?php else: ?>';
    });

    bladeDirective('endIsLogged', function () {
        return '<?php endif; ?>';
    });

    bladeDirective('panelBtns', function ($expression) {
        $class = empty($expression) ? 'hide' : '';

        $btns = '<div class="panel-heading-btn">
                            <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-default" data-click="panel-expand"><i class="fa fa-expand"></i></a>
                            <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-warning" data-click="panel-collapse"><i class="fa fa-minus"></i></a>
                            <a href="javascript:;" class="btn btn-xs btn-icon btn-circle btn-danger '.$class.'" 
                            data-click="panel-remove"><i class="fa fa-times"></i></a>
                        </div>';

        return echoInDirective($btns);
    });
}

/**
 * @return Component
 */
function response()
{
    static $response;

    if (!is_object($response)) {
        $response = new Component;

        $response['render'] = function ($file, array $data = []) {
            return render($file, $file);
        };

        $response['download'] = function (
            string $file,
            ?string $name = null,
            string $disposition = 'attachment',
            array $headers = []
        ) {
            $name = $name ?? \Octo\Arrays::last(explode('/', $file));
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
            $data = Octo\arrayable($data) ? $data->toArray() : $data;

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

    return $response;
}

function flash()
{
    static $flash;

    if (!is_object($flash)) {
        $flash = new Component;
        $session = session();

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
 * @return array|null|string
 * @throws ReflectionException
 */
function __(string $key, array $parameters = [])
{
    /** @var FastTwigExtension $twig */
    $twig = app(FastTwigExtension::class);

    return $twig->lang($key, $parameters);
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
    $twig = app(FastTwigExtension::class);
    $context = getIt('blade.context', []);

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
 * @param $key
 * @param $default
 * @return mixed|null|\Octo\Ultimate
 * @throws ReflectionException
 * @throws \Octo\Exception
 */
function session($key = null, $default = null)
{
    /** @var \Octo\Ultimate $session */
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
 * @param null|string $key
 * @param null $default
 * @return mixed|null
 */
function old(?string $key = null, $default = null)
{
    return session()->alive() ? session()->getOldInput($key, $default) : $default;
}

/**
 * @param mixed|null $abstract
 * @param array $parameters
 * @param bool $singleton
 * @return mixed|object|Fast
 * @throws ReflectionException
 */
function app($abstract, array $parameters = [], $singleton = true)
{
    return gi()->make($abstract, $parameters, $singleton);
}

/**
 * @param string $name
 * @return Fillable
 */
function bag(string $name = 'core')
{
    static $bags = [];

    $key = "bag.{$name}";

    if (!$bag = isAke($bags, $key, null)) {
        $bag = new Fillable($key);
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
        $cart = new Shoppingcart();
        $cart->instance($name);
        $carts[$name] = $cart;
    }

    return $cart;
}

/**
 * @param mixed ...$arguments
 * @return mixed|\App\Services\Cache
 * @throws Octo\Exception
 * @throws Exception
 */
function cache(...$arguments)
{
    $config = CoreConf::get('app');
    /** @var \App\Services\Cache $cache */
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
function config(?string $key = null, $value = 'octodummy')
{
    if (is_array($key)) {
        foreach ($key as $k => $v) {
            CoreConf::set($k, $v);
        }

        return true;
    }

    if ('octodummy' === $value) {
        return CoreConf::get($key, $value);
    }

    CoreConf::set($key, $value);
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
 * @return mixed|null|Octo\Fillable
 */
function paths(?string $key = null, $value = 'octodummy')
{
    /** @var Octo\Fillable $paths */
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
 * @return object
 */
function maker($abstract = null, array $parameters = [])
{
    return app($abstract, $parameters, false);
}

/**
 * @param $value
 * @return mixed
 * @throws ReflectionException
 */
function encrypt($value)
{
    return app(Bcrypt::class)->make($value);
}

/**
 * @return Bcrypt
 */
function hasher()
{
    return app(Bcrypt::class);
}

/**
 * @param int $code
 * @param string $message
 * @return \GuzzleHttp\Psr7\Response
 * @throws ReflectionException
 */
function abort($code = 403, $message = 'Forbidden')
{
    $message = Octo\value($message);

    if (is_array($message)) {
        $message = json_encode($message);
    }

    return \Octo\fast()->abort($code, $message);
}

/**
 * @param $condition
 * @param int $code
 * @param string $message
 * @return \GuzzleHttp\Psr7\Response
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
 * @return \GuzzleHttp\Psr7\Response
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
    $r = app(\Octo\FastRedirector::class);

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
 * @param null|string $key
 * @param null $default
 * @return mixed|null
 */
function user(?string $key = null, $default = null)
{
    $result = session()->user($key, $default);

    if ($key && is_string($result)) {
        $result = utf8_encode($result);
    }

    return $result;
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
 * @param string $userKey
 * @return Component
 */
function auth()
{
    return Setup::auth(session());
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
 * @return mixed|null|\Octo\Fire
 * @throws ReflectionException
 */
function event(...$params)
{
    return \Octo\event(...$params);
}

/**
 * @param null|string $locale
 * @return null|string
 */
function locale(?string $locale = null): ?string
{
    if (null === $locale) {
        return dic('locale');
    }

    dic('locale', function () use ($locale) {
        return $locale;
    });

    return $locale;
}

/**
 * @param null|string $key
 * @param mixed $value
 * @return mixed|\Illuminate\Container\Container
 */
function l(?string $key = null, $value = 'octodummy')
{
    $app = \Illuminate\Container\Container::getInstance();

    if (null === $key) {
        return $app;
    }

    if ($value === 'octodummy') {
        return $app[$key];
    }

    $app[$key] = $value;

    return $app;
}

function i(): Inflector
{
    return app(Inflector::class);
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
        return $app[$key];
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
 * @return \Illuminate\Validation\Factory|\Illuminate\Validation\Validator
 */
function validator(array $data = [], array $rules = [], array $messages = [], array $customAttributes = [])
{
    /** @var \Illuminate\Validation\Factory $validator */
    $validator = app(Validator::class);

    if (0 === func_num_args()) {
        return $validator;
    }

    return $validator->make($data, $rules, $messages, $customAttributes);
}

/**
 * @param string $name
 * @return object
 */
function repo(string $name)
{
    if (!class_exists($name) || !Inflector::contains($name, 'App\Repositories')) {
        $class = '\\App\\Repositories\\' . Inflector::camelize($name);
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
        $class = '\\App\\Middlewares\\' . Inflector::camelize($name);
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
 * @return Elegant
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
 * @return \Octo\Octalia
 * @throws ReflectionException
 * @throws \Octo\Exception
 */
function dbstore(string $table, string $database = 'core')
{
    $key = 'filestore.' . $table . '.' . $database;

    if (!$db = bag('instances')[$key])  {
        $path = \Octo\cache_path() . '/fs';

        if (is_dir($path)) {
            \Octo\File::mkdir($path);
        }

        $db = new \Octo\Octalia($database, $table, new Octo\Cache('fs', $path), $path);

        bag('instances')[$key] = $db;
    }

    return $db->reset();
}

/**
 * @return RedisManager
 */
function redis()
{
    return dic('redis');
}

/**
 * @return SQLiteConnection
 */
function sqlite()
{
    return dic('db')->connection('sqlite');
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

    $vars = Octo\viewParams();

    foreach ($vars as $key => $value) {
        $data[$key] = $value;
    }

    $data['errors'] = $data['errors'] ?? coll();

    \Octo\setCore('blade.context', $data);

    return $view->make($name, $data, $mergeData)->render();
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

    \Octo\setCore('blade.context', $data);

    return dic('view')->file($name, $data, $mergeData)->render();
}

/**
 * @param int $ttl
 * @param string $prefix
 * @param string $connection
 * @return \App\Services\Cache
 */
function cacheService($ttl = 60, $prefix = '', $connection = 'default')
{
    static $caches = [];

    $key = sha1(serialize(func_get_args()));

    if (!$cache = isAke($caches, $key, null)) {
        $cache = new \App\Services\Cache($ttl, $prefix, $connection);
        $caches[$key] = $cache;
    }

    return $cache;
}

/**
 * @param string $name
 * @return \App\Services\Data
 */
function data(string $name = 'core')
{
    static $datas = [];

    $key = sha1(serialize(func_get_args()));

    if (!$data = isAke($datas, $key, null)) {
        $data = new \App\Services\Data($name);
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
    $session = single(\App\Services\Reddy::class);

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
function logger(?string $message = null, array $context = [])
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
    $r = app(\Octo\FastRedirector::class);

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
 * @throws ReflectionException
 */
function resolver(string $name, ?Closure $resolver = null)
{
    $resolvers = getIt('app.resolvers', []);

    if (!class_exists($name)) {
        $aliases = include(\Octo\app_path('config/aliases.php'));

        if ($alias = isAke($aliases, $name, null)) {
            if (is_string($alias)) {
                class_alias($alias, $name);
            } elseif ($alias instanceof Closure) {
                $factory = gi()->makeClosure($alias);

                class_alias(get_class($factory), $name);

                $resolver = \Octo\voidToCallback($factory);
            }
        } else {
            $factory = gi()->makeClosure($resolver);

            class_alias(get_class($factory), $name);

            $resolver = \Octo\voidToCallback($factory);
        }
    }

    $resolvers[$name] = $resolver;

    setIt('app.resolvers', $resolvers);
}

function resolve(...$args)
{
    $class = array_shift($args);
    $resolvers = getIt('app.resolvers', []);

    if ($resolver = isAke($resolvers, $class, null)) {
        if (is_callable($resolver)) {
            return $resolver(...$args);
        }
    }

    return null;
}

/**
 * @return Component
 */
function container()
{
    static $container;

    if (!is_object($container)) {
        $container = new Component;

        $container['factory'] = function (string $name, Closure $resolver) {
            resolver($name, $resolver);
        };
    }

    return $container;
}
