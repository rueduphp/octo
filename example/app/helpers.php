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
use Octo\Elegant;
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
use function Octo\systemBoot;
use function Octo\in_paths;
use function Octo\inners;
use function Octo\startSession;
use function Octo\getSession;
use Octo\Url;
use Zend\Expressive\Router\FastRouteRouter;

/**
 * @throws ReflectionException
 * @throws \Octo\Exception
 */
function bootApp()
{
    systemBoot(realpath(__DIR__ . '/../'));

    $paths = in_paths();

    $paths['app']       = __DIR__;
    $paths['base']      = realpath(__DIR__ . '/../');
    $paths['public']    = realpath(__DIR__ . '/../public');
    $paths['cache']     = __DIR__ . '/storage/cache';
    $paths['storage']   = __DIR__ . '/storage';
    $paths['log']       = __DIR__ . '/storage/log';
    $paths['lang']      = __DIR__ . '/lang';
    $paths['views']     = __DIR__ . '/views';

    inners();

    startSession();

    $db = bag('db')->put([
        'driver'    => 'mysql',
        'host'      => 'mysql',
        'port'      => '3306',
        'database'  => 'octo',
        'username'  => 'octo',
        'password'  => 'octo',
    ]);


    l('config', app(Configurator::class));

    FastBladeDirectives::register();

    startDb($db);

    $app = App::create();

    aliases();

    $app
        ->set(Octo\Fastmiddlewarecsrf::class, function () {
            $session = getSession();

            return new Octo\Fastmiddlewarecsrf($session);
        })
    ;

    $app
        ->addMiddleware(Octo\Fastmiddlewaretrailingslash::class)
        ->addMiddleware(Octo\Fastmiddlewarecsrf::class)
        ->addMiddleware(Octo\Fastmiddlewarerouter::class)
        ->addMiddleware(Octo\Fastmiddlewaredispatch::class)
        ->addMiddleware(Octo\Fastmiddlewarenotfound::class)
    ;

    $app->addModule(StaticModule::class);

    $response = $app->run();

    $app->render($response);
}

/**
 * @param Fillable $db
 * @throws ReflectionException
 */
function startDb(Fillable $db)
{
    $PDOoptions = [
        PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
        PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES    => false,
        PDO::ATTR_EMULATE_PREPARES     => false
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
                'charset'       => 'utf8mb4',
                'collation'     => 'utf8mb4_unicode_ci',
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

    \Octo\Dynamicmodel::migrate();
}

function aliases()
{
    Setup::alias('App\Session',    'session');
    Setup::alias('App\Form',       'form');
    Setup::alias('App\Html',       'html');
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

function asset($path)
{
    $path = trim($path, '/');

    return url('assets/' . $path);
}

function url($path)
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
function app($abstract = null, array $parameters = [], $singleton = true)
{
    if (is_null($abstract)) {
        return Octo\getContainer();
    }

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
    /** @var Octo\Fillable $config */
    $config = app(Octo\Facades\Config::class);

    if (is_null($key)) {
        return $config;
    }

    if (is_array($key)) {
        foreach ($key as $k => $v) {
            $config[$k] = $v;
        }

        return $config;
    }

    if ('octodummy' === $value) {
        return $config->get($key);
    }

    return $config->set($key, $value);
}
/**
 * @param null|string $key
 * @param string $value
 * @return mixed|null|Octo\Fillable
 */
function paths(?string $key = null, $value = 'octodummy')
{
    /** @var Octo\Fillable $paths */
    $paths = dic()['paths'];

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
 * @return mixed|object|Fast
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
 * @param int $code
 * @param string $message
 * @return \GuzzleHttp\Psr7\Response
 * @throws ReflectionException
 */
function abort($code = 403, $message = 'Forbidden')
{
    $message = value($message);

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
    return session()->user($key, $default);
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
function auth(string $namespace = 'web', string $userKey = 'user')
{
    $session = Octo\ultimate($namespace, $userKey);

    return Setup::auth($session);
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
    $class = '\\App\\Repositories\\' . i()->camelize($name);

    return app($class);
}

/**
 * @param string $name
 * @return Elegant
 */
function model(string $name): Elegant
{
    $class = '\\App\\Models\\' . i()->camelize($name);

    return app($class);
}

/**
 * @param string $name
 * @return Dynamicentity
 */
function eav(string $name): Dynamicentity
{
    $class = '\\App\\EAV\\' . i()->camelize($name);

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
