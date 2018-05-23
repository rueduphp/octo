<?php

use App\Modules\StaticModule;
use GuzzleHttp\Psr7\MessageTrait;
use Illuminate\Database\Connection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\DatabasePresenceVerifier;
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
use function Octo\in_paths;
use function Octo\inners;
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
    $paths['cache']     = $dirApp . '/storage/cache';
    $paths['log']       = $dirApp . '/storage/log';
    $paths['lang']      = $dirApp . '/lang';
    $paths['views']     = $dirApp . '/views';

    inners();

    if (false === $cli) {
        startSession();
    }

    addConfig('db');
    addConfig('mail');

    (new Octo\Sender())
        ->setHost(config('mail.host'))
        ->setPort(config('mail.port'))
        ->smtp();

    dic('mail', function () {
        return new \Octo\Mailable;
    });

    l('config', app(Configurator::class));

    directives();

    dic('eventer', \Octo\getEventManager());

    startDb();

    $app = App::create();

    aliases();

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

        $response = $app->run();

        $app->render($response);
    }
}

function bootMiddlewares($app)
{
    $app
        ->addMiddleware(Octo\Fastmiddlewaretrailingslash::class)
        ->addMiddleware(Octo\Fastmiddlewarecsrf::class)
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
    $db = CoreConf::get('db');

    $PDOoptions = [
        PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
        PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES    => false,
        PDO::ATTR_EMULATE_PREPARES     => false,
    ];

    $pdo = new PDO(
        "{$db['driver']}:host={$db['host']};dbname={$db['database']}",
        $db['username'],
        $db['password'],
        $PDOoptions
    );

    Capsule::instance($pdo);

    l('config')->set([
        'database' => [
            'connections' => [
                'driver'        => $db['driver'],
                'host'          => $db['host'],
                'port'          => $db['port'],
                'database'      => $db['database'],
                'username'      => $db['username'],
                'password'      => $db['password'],
                'unix_socket'   => '',
                'charset'       => 'utf8',
                'collation'     => 'utf8_unicode_ci',
                'prefix'        => '',
                'strict'        => true,
                'engine'        => null,
            ]
        ]
    ]);

    l(
        'db',
        new \Illuminate\Database\DatabaseManager(
            l(),
            new Illuminate\Database\Connectors\ConnectionFactory(l())
        )
    );

    dic('db', l('db'));

    dic('orm', new Orm($pdo));

    $verifier = new DatabasePresenceVerifier(l('db'));

    Validator::setPresenceVerifier($verifier);

    Dynamicmodel::migrate();
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
 * @param string $name
 * @param array $args
 * @return string
 * @throws ReflectionException
 */
function path(string $name, array $args = [])
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
    $context = Octo\getCore('blade.context', []);

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
 * @param  \DateTimeZone|string|null $tz
 * @return \Carbon\Carbon
 */
function now($tz = null)
{
    return \Carbon\Carbon::now($tz);
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
    return session()->getOldInput($key, $default);
}

/**
 * @param mixed ...$args
 * @return mixed|object
 * @throws ReflectionException
 */
function response(...$args)
{
    return maker(GuzzleHttp\Psr7\Response::class, $args);
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
    return Octo\gi()->make($abstract, $parameters, $singleton);
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
 * @return mixed|Octo\Cache
 * @throws Octo\Exception
 * @throws Exception
 */
function cache(...$arguments)
{
    /** @var Octo\Cache $cache */
    $cache = app(Octo\Facades\Cache::class);

    if (empty($arguments)) {
        return $cache;
    }

    if (is_string($arguments[0]) && count($arguments) === 1) {
        return $cache->get($arguments[0]);
    }

    if (!is_array($arguments[0])) {
        throw new Exception(
            'When setting a value in the cache, you must pass an array of key / value pairs.'
        );
    }

    return $cache->set(key($arguments[0]), reset($arguments[0]), $arguments[1] ?? null);
}

/**
 * @param null|string $key
 * @param string $value
 * @return mixed|null|Octo\Fillable
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
        return CoreConf::get($key);
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

    return app()->abort($code, $message);
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
    $condition = value($condition);

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
    $condition = value($condition);

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
 * @return MessageTrait
 * @throws ReflectionException
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
 * @return null|string
 */
function action(...$args): ?string
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
function view(string $file, array $parameters = [], ?string $path = null)
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

function locale()
{
    return dic('locale');
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
 * @return Connection
 */
function db()
{
    return dic('db');
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
    $class = '\\App\\Repositories\\' . Inflector::camelize($name);

    return app($class);
}

/**
 * @param string $name
 * @return object
 */
function middleware(string $name)
{
    $class = '\\App\\Middlewares\\' . Inflector::camelize($name);

    return app($class);
}

/**
 * @param string $name
 * @return object
 */
function observer(string $name)
{
    $class = '\\App\\Observers\\' . Inflector::camelize($name);

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

    $class = '\\App\\Factories\\' . Inflector::camelize($name);

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
    $class = '\\App\\Entities\\' . Inflector::camelize($name);

    return app($class);
}

/**
 * @param string $name
 * @return object
 */
function service(string $name)
{
    $class = '\\App\\Services\\' . Inflector::camelize($name);

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
