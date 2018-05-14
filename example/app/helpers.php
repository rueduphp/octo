<?php
use function Octo\systemBoot;
use function Octo\in_paths;
use function Octo\inners;
use function Octo\startSession;
use function Octo\getSession;

/**
 * @throws ReflectionException
 * @throws \Octo\Exception
 */
function bootstrap()
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

    $PDOoptions = [
        PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
        PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES    => false,
        PDO::ATTR_EMULATE_PREPARES     => false
    ];

    $pdo = new PDO(
        "mysql:host=mysql;dbname=octo",
        'octo',
        'octo',
        $PDOoptions
    );

    Octo\Capsule::instance($pdo);

    $app = \Octo\App::create();

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

    $app->addModule(\App\Modules\StaticModule::class);

    $response = $app->run();

    $app->render($response);
}

function aliases()
{
    Octo\Setup::alias('App\Session', 'session');
}

/**
 * @param string $name
 * @param array $args
 * @return string
 * @throws ReflectionException
 */
function path(string $name, array $args = [])
{
    /** @var \Octo\FastTwigExtension $twig */
    $twig = Octo\gi()->make(\Octo\FastTwigExtension::class);

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
    /** @var \Octo\FastTwigExtension $twig */
    $twig = Octo\gi()->make(\Octo\FastTwigExtension::class);

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
    /** @var \Octo\FastTwigExtension $twig */
    $twig = Octo\gi()->make(\Octo\FastTwigExtension::class);

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
    /** @var \Octo\FastTwigExtension $twig */
    $twig = Octo\gi()->make(\Octo\FastTwigExtension::class);

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
    /** @var \Octo\FastTwigExtension $twig */
    $twig = Octo\gi()->make(\Octo\FastTwigExtension::class);
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

    return \Octo\Url::root() . '/' . $path;
}
