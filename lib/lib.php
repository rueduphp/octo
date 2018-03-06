<?php
    namespace Octo;

    use Carbon\Carbon;
    use Closure;
    use GuzzleHttp\Psr7\Response;
    use Illuminate\Support\Debug\Dumper;
    use function is_object;
    use Psr\Http\Message\ServerRequestInterface;
    use ReflectionFunction;
    use Zend\Expressive\Router\FastRouteRouter;

    if (file_exists(__DIR__ . "/../vendor/autoload.php")) {
        include_once __DIR__ . "/../vendor/autoload.php";
    }

    require_once __DIR__ . '/base.php';
    require_once __DIR__ . '/helpers.php';

    /* constantes */
    defined('APPLICATION_ENV')  || define('APPLICATION_ENV', 'production');
    defined('SITE_NAME')        || define('SITE_NAME', 'Octo');
    defined('DS')               || define('DS', DIRECTORY_SEPARATOR);
    defined('PS')               || define('PS', PATH_SEPARATOR);
    defined('MB_STRING')        || define('MB_STRING', (int) function_exists('mb_get_info'));
    defined('IS_CLI')           || define('IS_CLI', php_sapi_name() === 'cli' || defined('STDIN'));

    /* Helpers */
    if (!defined('OCTO_STANDALONE')) {
        clearstatcache();

        error_reporting(-1);
    }

    require_once __DIR__ . '/autoloader.php';

    spl_autoload_register(function ($class) {
        return (new Autoloader)->loader($class);
    });

    function view($html = null, $code = 200, $title = 'Octo')
    {
        static $viewClass = null;

        if (empty($html)) {
            if (is_null($viewClass)) {
                $viewClass = o();

                $viewClass->macro('assign', function ($k, $v) {
                    $vars = Registry::get('views.vars', []);
                    $vars[$k] = value($v);

                    Registry::set('views.vars', $vars);

                    return view();
                });
            }

            return $viewClass;
        }

        $tpl = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . '</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.5.0/css/font-awesome.min.css"><link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Oswald:100,300,400,700,900"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css"><style>body{font-family:"Oswald";}</style></head><body id="app-layout"><div class="container-fluid">##html##</div><script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script></body></html>';

        $html = str_replace('##html##', $html, $tpl);

        abort($code, $html);
    }

    function render($file, $context = 'controller', $args = [], $code = 200)
    {
        if (fnmatch('*#*', $file)) {
            list($c, $a) = explode('#', $file, 2);

            $file = path('app') . DS . 'views' . DS . $c . DS . $a . '.phtml';
        }

        if (fnmatch('*@*', $file)) {
            list($c, $a) = explode('@', $file, 2);

            $file = path('app') . DS . 'views' . DS . $c . DS . $a . '.phtml';
        }

        if (fnmatch('*:*', $file)) {
            list($c, $a) = explode(':', $file, 2);

            $file = path('app') . DS . 'views' . DS . $c . DS . $a . '.phtml';
        }

        if (fnmatch('*.*', $file)) {
            list($c, $a) = explode('.', $file, 2);

            $file = path('app') . DS . 'views' . DS . $c . DS . $a . '.phtml';
        }

        if (file_exists($file)) {
            $content = File::read($file);

            if ('controller' == $context) {
                $content    = str_replace(['{{', '}}'], ['<?php $controller->e("', '");?>'], $content);
                $content    = str_replace(['[[', ']]'], ['<?php $controller->trad("', '");?>'], $content);

                $content    = Router::compile($content);
            }

            $content = str_replace('$this->', '$controller->', $content);

            ob_start();

            eval(' namespace Octo; ?>' . $content . '<?php ');

            $html = ob_get_contents();

            ob_end_clean();

            abort($code, $html);
        } else {
            exception('render', "The file $file does not exist.");
        }
    }

    /**
     * @return Instanciator
     */
    function instanciator($cache = null)
    {
        static $instanciator = null;

        if (is_null($instanciator)) {
            $instanciator = new Instanciator;
        }

        if (!is_null($cache)) {
            $instanciator->setCache($cache);
        }

        return $instanciator;
    }

    function phpRenderer($folder = null)
    {
        $folder = is_null($folder) ? actual('fast.view.path') : $folder;

        if (is_dir($folder)) {
            actual('fast.view.path', $folder);

            $renderer = maker(FastPhpRenderer::class);

            actual('fast.renderer', $renderer);

            return $renderer;
        }

        exception("FastPhpRenderer", "The folder $folder does not exist.");
    }

    function twigRenderer($folder = null, array $config = [])
    {
        $debug = 'production' !== appenv('APPLICATION_ENV', 'production');

        $cachePath = appenv('CACHE_PATH', path('app') . '/storage/cache') . '/twig';

        $defaultConfig = [
            'debug'         => $debug,
            'auto_reload'   => $debug,
            'cache'         => $debug ? false : $cachePath
        ];

        $conf = array_merge($defaultConfig, $config);

        $container = getContainer();

        $folder = is_null($folder) ? $container->define('view.path') : $folder;

        if (is_dir($folder)) {
            $container->define('view.path', $folder);

            $loader = new \Twig_Loader_Filesystem($folder);

            $renderer = new FastTwigRenderer($loader, $conf);

            actual('fast.renderer', $renderer);

            return $renderer;
        }

        exception('twig', "The folder $folder does not exist.");
    }

    function controller()
    {
        return Registry::get('app.controller', null);
    }

    function is_home()
    {
        return !empty(Registry::get('is_home')) || isAke($_SERVER, 'REQUEST_URI', '') == '/';
    }

    function current_url()
    {
        return URLSITE . isAke($_SERVER, 'REQUEST_URI', '');
    }

    function contains($key, $string)
    {
        return fnmatch("*$key*", $string);
    }

    function fnmatch($pattern, $string)
    {
        return preg_match(
            "#^" . strtr(
                preg_quote(
                    $pattern,
                    '#'
                ),
                array(
                    '\*' => '.*',
                    '\?' => '.'
                )
            ) . "$#i",
            $string
        );
    }

    function matchAll($subject, $pattern, $flags = 0, $offset = 0)
    {
        if ($offset > strlen($subject)) {
            return [];
        }

        $m = null;

        call_user_func_array('preg_match_all', [
            $pattern, $subject, &$m,
            ($flags & PREG_PATTERN_ORDER) ? $flags : ($flags | PREG_SET_ORDER),
            $offset
        ]);

        return $m;
    }

    function otype($arg)
    {
        $typeMap = [
            'boolean' => 'boolean',
            'string'  => 'string',
            'NULL'    => 'null',
            'double'  => 'number',
            'float'   => 'number',
            'integer' => 'number'
        ];

        $type = gettype($arg);

        if (isset($typeMap[$type])) {
            return $typeMap[$type];
        } elseif ($type === 'array') {
            if (empty($arg)) {
                return 'array';
            }

            reset($arg);

            return key($arg) === 0 ? 'array' : 'object';
        } elseif ($arg instanceof \stdClass) {
            return 'object';
        } elseif ($arg instanceof \Closure) {
            return 'expression';
        } elseif ($arg instanceof \ArrayAccess && $arg instanceof \Countable) {
            return count($arg) == 0 || $arg->offsetExists(0) ? 'array' : 'object';
        } elseif (method_exists($arg, '__toString')) {
            return 'string';
        }

        throw new \InvalidArgumentException(
            'Unable to determine type from ' . get_class($arg)
        );
    }

    /**
     * @param null $concern
     * @param array $args
     *
     * @return Work
     */
    function job($concern = null, array $args = [])
    {
        /** @var Work $work */
        $work = getContainer()->resolve(Work::class);

        if (!is_null($concern) && is_string($concern)) {
            return $work->new($concern, $args);
        }

        return $work;
    }

    function osum(array $data, $field)
    {
        return coll($data)->sum($field);
    }

    function oavg(array $data, $field)
    {
        return coll($data)->avg($field);
    }

    function omin(array $data, $field)
    {
        return coll($data)->min($field);
    }

    function omax(array $data, $field)
    {
        return coll($data)->max($field);
    }

    function osort(array $data, $field)
    {
        return array_values(coll($data)->sortBy($field)->toArray());
    }

    function osortdesc(array $data, $field)
    {
        return array_values(coll($data)->sortByDesc($field)->toArray());
    }

    function callField($val, $method)
    {
        return Strings::uncamelize(str_replace($method, '', $val));
    }

    /**
     * @param string $k
     * @param callable $c
     * @param int|null $maxAge
     * @param array $args
     * @return mixed
     */
    function until($k, callable $c, $maxAge = null, $args = [])
    {
        return fmr('until')->until($k, $c, $maxAge, $args);
    }

    function octodb($table, $db = 'Octo')
    {
        return lib('eav', [$db, $table]);
    }

    function octolite($table, $db = 'Octo')
    {
        return lib('eav', [$db, $table, 'sqlite']);
    }

    function odb($db, $table)
    {
        return lib('Octalia', [$db, $table]);
    }

    function ldb($db, $table)
    {
        return lib('octalia', [$db, $table, lib('cachelite', ["$db.$table"])]);
    }

    function ndb($db, $table)
    {
        return lib('octalia', [$db, $table, lib('now', ["ndb.$db.$table"])]);
    }

    function dbMemory($model, $new = false)
    {
        $models = Registry::get('dbMemory.models', []);

        $model = Strings::uncamelize($model);

        if (!isset($models[$model]) || true === $new) {
            if (fnmatch('*_*', $model)) {
                list($database, $table) = explode('_', $model, 2);
            } else {
                $database   = Strings::uncamelize(Config::get('application.name', 'core'));
                $table      = $model;
            }

            $models[$model] = ndb($database, $table);

            Registry::set('dbMemory.models', $models);
        }

        return $models[$model];
    }

    function sdb($db, $table, $driver = null)
    {
        return lib('octalia', [$db, $table, lib('cachesql', ["$db.$table"])]);
    }

    function mdb($db, $table, $driver = null)
    {
        return lib('octalia', [$db, $table, lib('cachemongo', ["$db.$table"])]);
    }

    function rdb($db, $table, $driver = null)
    {
        return lib('octalia', [$db, $table, lib('cacheredis', ["$db.$table"])]);
    }

    function cachingDb($db, $table, $driver = null)
    {
        return lib('octalia', [$db, $table, lib('caching', ["$db.$table"])]);
    }

    function ramdb($db, $table)
    {
        return lib('Octalia', [$db, $table, null, Config::get('ram.dir', '/home/ram')]);
    }

    function octalia($table, $db = 'Octo')
    {
        return lib('Octalia', [$db, $table]);
    }

    function newInstance($class, array $params = [])
    {
        switch (count($params)) {
            case 0:
                return new $class();
            case 1:
                return new $class($params[0]);
            case 2:
                return new $class($params[0], $params[1]);
            case 3:
                return new $class($params[0], $params[1], $params[2]);
            case 4:
                return new $class($params[0], $params[1], $params[2], $params[3]);
            case 5:
                return new $class($params[0], $params[1], $params[2], $params[3], $params[4]);
            default:
                $refClass = new Reflector($class);

                return $refClass->newInstanceArgs($params);
        }
    }

    function def($k, $v = null, $define = false)
    {
        if (defined($k)) {
            $v = eval("return $k;");
        } else {
            if (true === $define) {
                define($k, $v);
            }
        }

        return $v;
    }

    function acd($ns = 'core')
    {
        static $acds = [];

        $acd = isAke($acds, $ns, null);

        if (!$acd) {
            $acd = lib('acd', [$ns]);
            $acds[$ns] = $acd;
        }

        return $acd;
    }

    function viewCacheObject($object, callable $callable, $driver = null)
    {
        $maxAge = $object->updated_at->timestamp;
        $key    = 'vco:' . $object->db() . ':' . $object->table() . ':' . $object->id;

        viewCache($key, $maxAge, $callable, $driver);
    }

    function viewCache($key, $maxAge, callable $callable, $driver = null)
    {
        $continue   = true;

        $driver     = is_null($driver) ? fmr('view') : $driver;

        $keyAge     = $key . ':maxage';
        $v          = $driver->get($key);

        if ($v) {
            $age = $driver->get($keyAge);

            if (!$age) {
                $age = $maxAge - 1;
            }

            if ($age >= $maxAge) {
                $continue = false;
                echo $v;
            } else {
                $driver->delete($key);
                $driver->delete($keyAge);
            }
        }

        if (true === $continue) {
            ob_start();

            $callable();

            $content = ob_get_clean();

            $driver->set($key, $content);
            $driver->set($keyAge, $maxAge);

            echo $content;
        }
    }

    /**
     * @param array $o
     *
     * @return \Octo\Objet
     */
    function o(array $o = [])
    {
        return lib('objet', [$o]);
    }

    /**
     * @param array $o
     *
     * @return \Octo\Node
     */
    function node(array $o = [], $node = true)
    {
        require_once __DIR__ . DS . 'node.php';

        $data = new NodeData($o);

        return true === $node ? new Node($data) : $data;
    }

    /**
     * @param Node $node
     *
     * @return Tree
     */
    function tree(Node $node)
    {
        return new Tree($node);
    }

    /**
     * @param string $name
     * @param array $o
     *
     * @return \Octo\Object
     */
    function actualo($name, array $o = [])
    {
        $object = actual($name, null);

        if (is_null($object)) {
            /* @var \Octo\Object $object */
            $object = lib('objet', [$o]);

            $object->fn('persist', function () use ($name, $object) {
                actual($name, $object);
            });
        }

        return $object;
    }

    /**
     * @param array $o
     *
     * @return FastObject
     */
    function fo(array $o = [])
    {
        return new FastObject($o);
    }

    function exif($file)
    {
        if (is_file($file)) {
            $info = exif_read_data($file);

            if (isset($info['GPSLatitude']) && isset($info['GPSLongitude']) &&
                isset($info['GPSLatitudeRef']) && isset($info['GPSLongitudeRef']) &&
                in_array($info['GPSLatitudeRef'], array('E','W','N','S')) && in_array($info['GPSLongitudeRef'], array('E','W','N','S'))) {

                $GPSLatitudeRef  = strtolower(trim($info['GPSLatitudeRef']));
                $GPSLongitudeRef = strtolower(trim($info['GPSLongitudeRef']));

                $lat_degrees_a = explode('/', $info['GPSLatitude'][0]);
                $lat_minutes_a = explode('/', $info['GPSLatitude'][1]);
                $lat_seconds_a = explode('/', $info['GPSLatitude'][2]);
                $lng_degrees_a = explode('/', $info['GPSLongitude'][0]);
                $lng_minutes_a = explode('/', $info['GPSLongitude'][1]);
                $lng_seconds_a = explode('/', $info['GPSLongitude'][2]);

                $lat_degrees = $lat_degrees_a[0] / $lat_degrees_a[1];
                $lat_minutes = $lat_minutes_a[0] / $lat_minutes_a[1];
                $lat_seconds = $lat_seconds_a[0] / $lat_seconds_a[1];
                $lng_degrees = $lng_degrees_a[0] / $lng_degrees_a[1];
                $lng_minutes = $lng_minutes_a[0] / $lng_minutes_a[1];
                $lng_seconds = $lng_seconds_a[0] / $lng_seconds_a[1];

                $lat = (float) $lat_degrees + ((($lat_minutes * 60) + ($lat_seconds)) / 3600);
                $lng = (float) $lng_degrees + ((($lng_minutes * 60) + ($lng_seconds)) / 3600);

                $GPSLatitudeRef  == 's' ? $lat *= -1 : '';
                $GPSLongitudeRef == 'w' ? $lng *= -1 : '';

                $datetime  = isset($info["DateTime"]) ? $info["DateTime"] : 'NA';

                return array(
                    'datetime'  => $datetime,
                    'lat'       => floatval($lat),
                    'lng'       => floatval($lng)
                );
            }
        }

        return false;
    }

    function modelFacade($repo, $class)
    {
        if (fnmatch('*_*', $repo)) {
            $tab        = explode('_', $repo);
            $database   = array_shift($tab);
            $table      = array_shift($tab);
        } else {
            $table = $repo;
            $database = SITE_NAME;
        }

        $className = str_replace('Octo\\', '', $class);

        if (!class_exists('Octo\\' . $className)) {
            $code = 'namespace Octo; class ' . $className . ' {public $database = "' . $database . '"; public $table = "' . $table . '";
        public function _db()
        {
            return $this->database;
        }

        public function _table()
        {
            return $this->table;
        }

        public static function __callStatic($method, $args)
        {
            $db = call_user_func_array([new self, \'_db\'], []);
            $table = call_user_func_array([new self, \'_table\'], []);

            if ($method == "table") return $table;

            return call_user_func_array([db($db, $table), $method], $args);
        }}';

            eval($code);
        }
    }

    function make_entity($entity)
    {
        $class = $entity . 'Entity';

        if (!class_exists('Octo\\' . $class)) {
            $code = 'namespace Octo; class ' . $class . ' extends Octal {}';

            eval($code);
        }
    }

    function entityFacade($db)
    {
        $db = Strings::camelize($db);

        if (!class_exists('Octo\\' . $db)) {
            $database = Strings::lower($db);
            $code = 'namespace Octo; class ' . $db . '
            {
                public static function __callStatic($method, $args)
                {
                    $table = Strings::uncamelize($method);

                    if (empty($args)) {
                        return engine("' . $database . '", $table);
                    } elseif (count($args) == 1) {
                        $id = array_shift($args);

                        if (is_numeric($id)) {
                            return engine("' . $database . '", $table)->find($id);
                        }
                    }
                }
            }';

            eval($code);
        }
    }

    function octalFacade($repo, $class)
    {
        if (fnmatch('*_*', $repo)) {
            $tab        = explode('_', $repo);
            $database   = array_shift($tab);
            $table      = array_shift($tab);
        } else {
            $table = $repo;
            $database = SITE_NAME;
        }

        $className = str_replace('Octo\\', '', $class);

        if (!class_exists('Octo\\' . $className)) {
            $code = 'namespace Octo; class ' . $className . ' {public $database = "' . $database . '"; public $table = "' . $table . '";
        public function _db()
        {
            return $this->database;
        }

        public function _table()
        {
            return $this->table;
        }

        public static function __callStatic($method, $args)
        {
            $db = call_user_func_array([new self, \'_db\'], []);
            $table = call_user_func_array([new self, \'_table\'], []);

            if ($method == "table") return $table;

            return call_user_func_array([engine($db, $table), $method], $args);
        }}';

            eval($code);
        }
    }

    function isPhp($version)
    {
        static $isphp;

        $version = (string) $version;

        if (!isset($isphp[$version])) {
            $isphp[$version] = version_compare(PHP_VERSION, $version, '>=');
        }

        return $isphp[$version];
    }

    function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }

        return false;
    }

    /**
     * @param null $concern
     *
     * @return bool|resource
     *
     * @throws \ReflectionException
     */
    function makeResource($concern = null)
    {
        $resource = fopen("php://memory", 'r+');

        if (is_object($concern) && is_invokable($concern)) {
            $concern = instanciator()->call([$concern, '__invoke']);
        }

//        try {
            fwrite($resource, serialize($concern));
//        } catch (\Exception $e) {
//            return $resource;
//        }

        return $resource;
    }

    function makeFromResource($resource, $default = null, bool $unserialize = true)
    {
        if (is_resource($resource)) {
            rewind($resource);

            $cnt = [];

            while (!feof($resource)) {
                $cnt[] = fread($resource, 1024);
            }

            $data = implode('', $cnt);

            return $unserialize ? unserialize($data) : $data;
        }

        return $default;
    }

    function cloudMail($to, $subject, $body, $headers, $sep = "\n")
    {
        $ch = curl_init(optGet("cloud.mail.url"));

        $data = array(
            'to'        => base64_encode($to),
            'sujet'     => base64_encode($subject),
            'message'   => base64_encode($body),
            'entetes'   => base64_encode(implode($sep, $headers))
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $mail = curl_exec($ch);

        curl_close($ch);

        return $mail == 'OK' ? true : false;
    }

    function email(array $config)
    {
        $from       = isAke($config, 'from', 'contact@' . $_SERVER["SERVER_NAME"]);
        $to         = isAke($config, 'to', 'contact@' . $_SERVER["SERVER_NAME"]);
        $subject    = isAke($config, 'subject', SITE_NAME);
        $text       = isAke($config, 'text', null);
        $html       = isAke($config, 'html', null);

        $command = 'curl -X POST https://api.sparkpost.com/api/v1/transmissions -H "Authorization: ' . optGet("api.mail.key") . '" -H "Content-Type: application/json" -d \'{"content": {"from": "' . $from . '","subject": ' . json_encode($subject) . ',';

        if ($text && $html) {
            $command .= '"text": ' . json_encode($text) . ', "html": ' . json_encode($html);
        } else {
            if ($text) {
                $command .= '"text": ' . json_encode($text);
            }

            if ($html) {
                $command .= '"html": ' . json_encode($html);
            }
        }

        $command .= '},"recipients": [{ "address": "' . $to . '" }]}\'';

        exec($command);

        return true;
    }

    function optGet($k, $d = null)
    {
        return fmr('opt')->get($k, $d);
    }

    function optSet($k, $v = null)
    {
        return fmr('opt')->set($k, $v);
    }

    function optHas($k)
    {
        return fmr('opt')->has($k);
    }

    function optDel($k)
    {
        return fmr('opt')->delete($k);
    }

    function user($k = null, $d = null)
    {
        if (empty($k)) {
            return lib('user');
        } else {
            $user = session('web')->getUser();

            if ($user) {
                return isAke($user, $k, $d);
            }
        }

        return $d;
    }

    function logFile($path, $message, $type = 'INFO')
    {
        if (is_array($message)) $message = implode(PHP_EOL, $message);

        $type = Strings::upper($type);

        $file = $path . DS . date('Y_m_d') . '.logs';

        File::append($file, date('H:i:s') . ':' . $type . ' => ' . $message);
    }

    function log($message, $type = 'INFO')
    {
        if (is_array($message)) $message = implode(PHP_EOL, $message);

        $type = Strings::upper($type);

        $db = em('systemLog');

        $db->store([
            'message'   => $message,
            'type'      => $type
        ]);
    }

    function logs($type = 'INFO')
    {
        $type = Strings::upper($type);

        $db = em('systemLog');

        return $db
        ->where('type', $type)
        ->sortByDesc('id')
        ->get();
    }

    function trackView($page, array $data = [])
    {
        $data['page'] = $page;
        user()->log('view', $data);
    }

    function trackClick($page, array $data = [])
    {
        $data['page'] = $page;
        user()->log('click', $data);
    }

    function dyn($class = null)
    {
        return lib('dyn', [$class]);
    }

    /**
     * @param string $table
     * @return Bank
     */
    function bank($table)
    {
        /** @var Fast $app */
        $app = actual("fast");

        return new Bank('core', $table, $app->get(FastStorageInterface::class));
    }

    function async($cmd)
    {
        return backgroundTask($cmd);
    }

    function backgroundTask($cmd)
    {
        if (substr(php_uname(), 0, 7) == "Windows"){
            pclose(popen("start /B " . $cmd, "r"));
        } else {
            exec($cmd . " > /dev/null &");
        }
    }

    function aget($array, string $k, $d = null)
    {
        return Arrays::get($array, $k, $d);
    }

    function aset(&$array, string $k, $v = null)
    {
        return Arrays::set($array, $k, $v);
    }

    function adel(&$array, $k)
    {
        Arrays::forget($array, $k);
    }

    function apull(&$array, $key, $default = null)
    {
        $value = Arrays::get($array, $key, $default);

        Arrays::forget($array, $key);

        return $value;
    }

    function dwn($url)
    {
        return lib('geo')->dwn($url);
    }

    function dwnCache($url)
    {
        return lib('geo')->dwnCache($url);
    }

    function server($key = null, $default = null)
    {
        if (empty($key)) {
            return lib('objet', [oclean($_SERVER)]);
        }

        return isAke(oclean($_SERVER), $key, $default);
    }

    function post($key = null, $default = null)
    {
        if (empty($key)) {
            return Post::notEmpty();
        }

        return Post::get($key, $default);
    }

    function item($attributes = [])
    {
        $attributes = arrayable($attributes) ? $attributes->toArray() : $attributes;

        return lib('fluent', [$attributes]);
    }

    function q($attributes = [])
    {
        return item($attributes);
    }

    function record($attributes = [], $entity)
    {
        $attributes = arrayable($attributes) ? $attributes->toArray() : $attributes;

        return lib('record', [$attributes]);
    }

    function request($k = null, $d = null)
    {
        if (is_string($k)) {
            return getRequest()->getAttribute($k, $d);
        } elseif (is_array($k)) {
            $collection = [];

            foreach ($k as $field) {
                $collection[$k] = getRequest()->getAttribute($field, null);
            }

            return $collection;
        }

        return getRequest();
    }

    function customRequest($name, $cb = null)
    {
        $requests = Registry::get('core.requests', []);

        if (is_callable($cb)) {
            $requests[$name] = $cb;

            Registry::set('core.requests', $requests);

            return true;
        }

        $request = isAke($requests, $name, null);

        if (is_callable($request)) {
            $request();
        }
    }

    function sess($k = null, $d = null)
    {
        $data = [];

        if (session_id()) {
            $data = oclean($_SESSION);
        }

        if (empty($k)) {
            return lib('objet', [$data]);
        } else {
            return isAke($data, $k, $d);
        }
    }

    function oclean($str)
    {
        return is_array($str) ? array_map('\\Octo\\oclean', $str)
        : str_replace('\\', '\\\\', strip_tags(trim(htmlspecialchars((get_magic_quotes_gpc()
        ? stripslashes($str) : $str), ENT_QUOTES))));
    }

    /**
     * @param string $field
     *
     * @return null|string
     *
     * @throws \Exception
     */
    function i64(string $field): ?string
    {
        if (Arrays::exists($_FILES, $field)) {
            $fileupload         = $_FILES[$field]['tmp_name'];
            $fileuploadName     = $_FILES[$field]['name'];

            if (strlen($fileuploadName)) {
                $data = file_get_contents($fileupload);

                if (!strlen($data)) {
                    return null;
                }

                $tab    = explode(".", $fileuploadName);
                $ext    = Strings::lower(Arrays::last($tab));

                File::delete($fileupload);

                return 'data:image/' . $ext . ';base64,' . base64_encode($data);
            }
        }

        return null;
    }

    function src64($src)
    {
        $tab    = explode(".", $src);
        $ext    = Strings::lower(Arrays::last($tab));

        return 'data:image/' . $ext . ';base64,' . base64_encode(dwnCache($src));
    }

    function base64($data, $mime = 'image/jpg')
    {
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    function upload($field, $dest = null)
    {
        if (Arrays::exists($_FILES, $field)) {
            $fileupload         = $_FILES[$field]['tmp_name'];
            $fileuploadName     = $_FILES[$field]['name'];

            if (strlen($fileuploadName)) {
                $data = file_get_contents($fileupload);

                if (!strlen($data)) {
                    return null;
                }

                if (empty($dest)) {
                    $tab    = explode(".", $fileuploadName);
                    $bucket = new Bucket(SITE_NAME, URLSITE . '/bucket');
                    $ext    = Strings::lower(Arrays::last($tab));
                    $res    = $bucket->data($data, $ext);

                    return $res;
                } else {
                    $dest = realpath($dest);

                    if (is_dir($dest) && is_writable($dest)) {
                        $destFile = $dest . DS . $fileuploadName;
                        File::put($destFile, $data);

                        return $destFile;
                    }
                }
            }
        }

        return null;
    }

    function fgc($f)
    {
        return file_get_contents($f);
    }

    function session($context = 'web'): Session
    {
        return lib('session', [$context]);
    }

    function my($context = 'web')
    {
        return lib('my', [$context]);
    }

    if (!function_exists('humanize')) {
        function humanize($word, $key)
        {
            return Strings::lower($word) . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        }
    }

    /**
     * @param string $word
     * @param string $key
     *
     * @return string
     */
    function prefixed(string $word, string $key): string
    {
        return humanize($word, $key);
    }

    if (!function_exists('setter')) {
        function setter($key)
        {
            return humanize('set', $key);
        }
    }

    if (!function_exists('getter')) {
        function getter($key)
        {
            return humanize('get', $key);
        }
    }

    function ageCache($k, callable $c, $maxAge = null, $args = [])
    {
        $dir = Config::get('dir.ephemere', session_save_path()) . '/aged';

        if (!is_dir($dir)) {
            Dir::mkdir($dir);
        }if (!is_dir($dir)) {
            Dir::mkdir($dir);
        }

        $hash = sha1($k);

        $f = substr($hash, 0, 2);
        $s = substr($hash, 2, 2);

        $dir .= DS . $f;

        if (!is_dir($dir)) {
            Dir::mkdir($dir);
        }

        $dir .= DS . $s;

        if (!is_dir($dir)) {
            Dir::mkdir($dir);
        }

        $file = $dir . DS . $k;

        if (file_exists($file)) {
            if (is_null($maxAge)) {
                return unserialize(File::read($file));
            } else {
                if (filemtime($file) >= $maxAge) {
                    return unserialize(File::read($file));
                } else {
                    File::delete($file);
                }
            }
        }

        $data = call_user_func_array($c, $args);

        File::put($file, serialize($data));

        if (!is_null($maxAge)) {
            if ($maxAge < 1000000000) {
                $maxAge = ($maxAge * 60) + time();
            }

            touch($file, $maxAge);
        }

        return $data;
    }

    function transform($value, callable $callback, $default = null)
    {
        if (filled($value)) {
            return $callback($value);
        }

        if (is_callable($default)) {
            return $default($value);
        }

        return $default;
    }

    function byRef()
    {
        $args = func_get_args();

        return tap(...$args);
    }

    /**
     * @param $value
     * @param callable|null $callback
     *
     * @return Tap
     *
     * @throws \ReflectionException
     */
    function tap($value, ?callable $callback = null)
    {
        if (is_null($callback)) {
            return new Tap($value);
        }

        callCallable($callback, $value);

        return $value;
    }

    /**
     * @param $value
     * @param callable|null $callback
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    function adapt($value, ?callable $callback = null)
    {
        return is_null($callback) ? $value : callCallable($callback, $value);
    }

    function catchRollback($value, callable $callback)
    {
        try {
            return callCallable($callback, $value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    function catchIt($value, callable $callback)
    {
        try {
            return callCallable($callback, $value);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $times
     * @param callable $callback
     * @param int $sleep
     *
     * @return mixed|null
     *
     * @throws \Exception
     */
    function times($times, callable $callback, $sleep = 0)
    {
        $times--;

        beginning:
        try {
            return callCallable($callback);
        } catch (\Exception $e) {
            if (!$times) {
                throw $e;
            }

            $times--;

            if ($sleep) {
                usleep($sleep * 1000);
            }

            goto beginning;
        }
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    function blank($value)
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof \Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    function filled($value)
    {
        return !blank($value);
    }

    function ldd()
    {
        lvd(...func_get_args());

        exit;
    }

    function lvd()
    {
        array_map(function ($x) {
            (new Dumper)->dump($x);
        }, func_get_args());
    }

    function dd()
    {
        array_map(
            function($str) {
                echo '<pre style="background: #ffffdd; padding: 5px; color: #aa4400; font-family: Ubuntu; font-weight: bold; font-size: 22px; border: solid 2px #444400">';
                print_r($str);
                echo '</pre>';
                hr();
            },
            func_get_args()
        );

        exit;
    }

    function vd()
    {
        array_map(
            function($str) {
                echo '<pre style="background: #ffffdd; padding: 5px; color: #aa4400; font-family: Ubuntu; font-weight: bold; font-size: 22px; border: solid 2px #444400">';
                print_r($str);
                echo '</pre>';
                hr();
            },
            func_get_args()
        );
    }

    function hr($str = null)
    {
        $str = is_null($str) ? '&nbsp;' : $str;
        echo $str . '<hr />';
    }

    function displayCodeLines()
    {
        $back   = '';

        // $traces = Thin\Input::globals('dbg_stack', []);
        $traces = debug_backtrace();
        array_pop($traces);

        if (!empty($traces)) {
            foreach($traces as $trace) {
                $file = isAke($trace, 'file', false);
                $line = isAke($trace, 'line', false);

                if (false !== $file && false !== $line && $file != __FILE__) {
                    $start      = $line > 5 ? $line - 5 : $line;
                    $code       = File::readLines($file, $start, $line + 5);

                    $lines      = explode("\n", $code);

                    $codeLines  = [];

                    $i          = $start;

                    foreach ($lines as $codeLine) {
                        if ($i == $line) {
                            array_push($codeLines, $i . '. <span style="background-color: gold; color: black;">' . $codeLine . '</span>');
                        } else {
                            array_push($codeLines, $i . '. ' . $codeLine);
                        }

                        $i++;
                    }

                    if (strlen($back)) {
                        $back .= "\n";
                    }

                    $back .= "File: $file [<em>line: <u>$line</u></em>]\n\nCode\n*******************************\n<div style=\"font-weight: normal; font-family: Consolas;\">" . implode("\n", $codeLines) . "</div>\n*******************************\n";
                }
            }
        }

        return $back;
    }

    function go($url)
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        } else {
            echo '<script type="text/javascript">';
            echo "\t" . 'window.location.href = "' . $url . '";';
            echo '</script>';
            echo '<noscript>';
            echo "\t" . '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
            echo '</noscript>';
            exit;
        }
    }

    /**
     * @param $string
     *
     * @return bool
     */
    function isUtf8($string)
    {
        if (!is_string($string)) {
            return false;
        }

        return !strlen(
            preg_replace(
                ',[\x09\x0A\x0D\x20-\x7E]'
                . '|[\xC2-\xDF][\x80-\xBF]'
                . '|\xE0[\xA0-\xBF][\x80-\xBF]'
                . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
                . '|\xED[\x80-\x9F][\x80-\xBF]'
                . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'
                . '|[\xF1-\xF3][\x80-\xBF]{3}'
                . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'
                . ',sS',
                '',
                $string
            )
        );
    }

    /**
     * @return string
     */
    function token()
    {
        return sha1(
            str_shuffle(
                chr(
                    mt_rand(
                        32,
                        126
                    )
                ) . uniqid() . microtime(true)
            )
        );
    }

    /**
     * @param string $start
     * @param string $end
     * @param string $string
     * @param null|string $default
     *
     * @return null|string
     */
    function cut($start, $end, $string, $default = null)
    {
        if (strstr($string, $start) && strstr($string, $end) && isset($start) && isset($end)) {
            list($dummy, $string) = explode($start, $string, 2);

            if (isset($string) && strstr($string, $end)) {
                list($string, $dummy) = explode($end, $string, 2);

                return $string;
            }
        }

        return $default;
    }

    /**
     * @param string $k
     * @param callable $c
     * @param null $maxAge
     * @param array $args
     *
     * @return mixed
     */
    function xCache($k, callable $c, $maxAge = null, $args = [])
    {
        $dir = Config::get('x.cache.dir', realpath(path('storage') . '/cache/x'));

        if (!is_dir($dir)) {
            Dir::mkdir($dir);
        }

        $hash = sha1($k);

        $f = substr($hash, 0, 2);
        $s = substr($hash, 2, 2);

        $dir .= DS . $f;

        if (!is_dir($dir)) {
            Dir::mkdir($dir);
        }

        $dir .= DS . $s;

        if (!is_dir($dir)) {
            Dir::mkdir($dir);
        }

        $file = $dir . DS . $k;

        if (file_exists($file)) {
            if (is_null($maxAge)) {
                return unserialize(File::read($file));
            } else {
                if (filemtime($file) >= time()) {
                    return unserialize(File::read($file));
                } else {
                    File::delete($file);
                }
            }
        }

        $data = call_user_func_array($c, $args);

        File::put($file, serialize($data));

        if (!is_null($maxAge)) {
            if ($maxAge < 1444000000) {
                $maxAge = ($maxAge * 60) + time();
            }

            touch($file, $maxAge);
        }

        return $data;
    }

    /**
     * @param string $file
     *
     * @return array|mixed
     */
    function includer($file)
    {
        $return = [];

        if (file_exists($file)) {
            return include $file;
        }

        return $return;
    }

    /**
     * @param array $files
     *
     * @return array
     */
    function includers(array $files)
    {
        $return = [];

        foreach ($files as $file) {
            $return[$file] = includer($file);
        }

        return $return;
    }

    /**
     * @param array $array
     * @param string $k
     * @param mixed|array $d
     *
     * @return mixed
     */
    function isAke($array, $k, $d = [])
    {
        if (true === is_object($array)) {
            if (arrayable($array)) {
                $array = $array->toArray();
            } else {
                $array = (array) $array;
            }
        }

        return Arrays::get($array, $k, $d);
    }

    /**
     * @return string
     */
    function uuid()
    {
        $seed = mt_rand(0, 2147483647) . '#' . mt_rand(0, 2147483647);

        // Hash the seed and convert to a byte array
        $val = md5($seed, true);
        $byte = array_values(unpack('C16', $val));

        // extract fields from byte array
        $tLo = ($byte[0] << 24) | ($byte[1] << 16) | ($byte[2] << 8) | $byte[3];
        $tMi = ($byte[4] << 8) | $byte[5];
        $tHi = ($byte[6] << 8) | $byte[7];
        $csLo = $byte[9];
        $csHi = $byte[8] & 0x3f | (1 << 7);

        // correct byte order for big edian architecture
        if (pack('L', 0x6162797A) == pack('N', 0x6162797A)) {
            $tLo = (($tLo & 0x000000ff) << 24) | (($tLo & 0x0000ff00) << 8)
                | (($tLo & 0x00ff0000) >> 8) | (($tLo & 0xff000000) >> 24);
            $tMi = (($tMi & 0x00ff) << 8) | (($tMi & 0xff00) >> 8);
            $tHi = (($tHi & 0x00ff) << 8) | (($tHi & 0xff00) >> 8);
        }

        // apply version number
        $tHi &= 0x0fff;
        $tHi |= (3 << 12);

        // cast to string
        $uuid = sprintf(
            '%08x-%04x-%04x-%02x%02x-%02x%02x%02x%02x%02x%02x',
            $tLo,
            $tMi,
            $tHi,
            $csHi,
            $csLo,
            $byte[10],
            $byte[11],
            $byte[12],
            $byte[13],
            $byte[14],
            $byte[15]
        );

        return $uuid;
    }

    /**
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    function callCallable()
    {
        $args       = func_get_args();
        $callable   = array_shift($args);

        $params = !is_array($callable)
            ? array_merge([$callable], $args)
            : array_merge($callable, $args)
        ;

        return $callable instanceof Closure ?
            instanciator()->makeClosure(...$params) :
            instanciator()->call(...$params)
        ;
    }

    /**
     * @return bool
     */
    function isCli(): bool
    {
        return PHP_SAPI === 'cli' || php_sapi_name() === 'cli' || defined('STDIN');
    }

    /**
     * @param string $ns
     *
     * @return string
     */
    function sessionKey(string $ns = 'session'): string
    {
        if (true === isCli()) {
            return sha1(File::read(__FILE__) . $ns);
        }

        $ns = SITE_NAME . '_' . $ns;

        $key = isAke($_COOKIE, $ns, null);

        if (!$key) {
            $key = sha1(uniqid(sha1(uniqid(null, true)), true));
        }

        setcookie($ns, $key, strtotime('+1 hour'), '/');

        return $key;
    }

    /**
     * @param string $ns
     *
     * @return string
     */
    function rememberKey(string $ns = 'user'): string
    {
        return forever($ns);
    }

    /**
     * @param string $ns
     *
     * @return string
     */
    function forever(string $ns = 'user'): string
    {
        if (true === isCli()) {
            return sha1(File::read(__FILE__) . $ns);
        }

        $ns         = SITE_NAME . '_' . $ns;
        $cookie     = isAke($_COOKIE, $ns, null);

        if (!$cookie) {
            $cookie = sha1(uniqid(sha1(uniqid(null, true)), true));
        }

        setcookie($ns, $cookie, strtotime('+1 year'), '/');

        return $cookie;
    }

    /**
     * @param string $context
     * @return string
     */
    function locale(string $context = 'web'): string
    {
        $language       = session($context)->getLanguage();
        $isCli          = false;
        $fromBrowser    = isAke($_SERVER, 'HTTP_ACCEPT_LANGUAGE', false);

        if (false === $fromBrowser) {
            $isCli = true;
        }

        if ($isCli) {
            return defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en';
        }

        $var = defined('LANGUAGE_VAR') ? LANGUAGE_VAR : 'lng';

        if (is_null($language)) {
            $language = isAke(
                $_REQUEST,
                $var,
                \Locale::acceptFromHttp($fromBrowser)
            );

            session($context)->setLanguage($language);
        }

        if (fnmatch('*_*', $language)) {
            list($language, $d) = explode('_', $language, 2);
            session($context)->setLanguage($language);
        }

        return $language;
    }

    /**
     * @param string $context
     * @return string
     */
    function lng(string $context = 'web'): string
    {
        return locale($context);
    }

    /**
     * @param array $data
     *
     * @return Collection
     */
    function coll($data = []): Collection
    {
        $data = arrayable($data) ? $data->toArray() : $data;

        return new Collection($data);
    }

    /**
     * @param string|null $ns
     * @param string|null $dir
     *
     * @return Cache
     */
    function kh(?string $ns = null, ?string $dir = null)
    {
        return fmr($ns, $dir);
    }

    /**
     * @param null|string $ns
     * @param null|string $dir
     *
     * @return Cache
     *
     * @throws Exception
     */
    function fmr(?string $ns = null, ?string $dir = null)
    {
        if ($cache = conf($ns . '.fmr.instance')) {
            return $cache;
        }

        return new Cache($ns, $dir);
    }

    /**
     * @param string $ns
     *
     * @return Caching
     */
    function caching(string $ns = 'core')
    {
        return new Caching($ns);
    }

    /**
     * @param string|null $ns
     * @param array $data
     *
     * @return Now
     */
    function stock(?string $ns = null, $data = [])
    {
        return new Now($ns, $data);
    }

    /**
     * @param string $from
     * @param string $to
     * @param string $subject
     *
     * @return string
     */
    function srf(string $from, string $to, string $subject): string
    {
        return str_replace_first($from, $to, $subject);
    }

    /**
     * @param null|string $ns
     *
     * @return Cachelite
     */
    function lite(?string $ns = null)
    {
        return new Cachelite($ns);
    }

    /**
     * @param null|string $ns
     * @param null|string $dir
     *
     * @return OctaliaMemory
     */
    function mem(?string $ns = null, ?string $dir = null)
    {
        return new OctaliaMemory($ns, $dir);
    }

    /**
     * @param string $from
     * @param string $to
     * @param string $subject
     *
     * @return string
     */
    function str_replace_first(string $from, string $to, string $subject): string
    {
        $from = '/' . preg_quote($from, '/') . '/';

        return preg_replace($from, $to, $subject, 1);
    }

    function replaceFirst($from, $to, $subject)
    {
        $parts = explode($from, $subject);

        $replaced = [];

        while (!empty($parts)) {
            $part = array_shift($parts);

            if (empty($replaced)) {
                $replaced[] = $part . $to;
            } else {
                if (!empty($parts)) {
                    $replaced[] = $part . $from;
                } else {
                    $replaced[] = $part;
                }
            }

        }

        return implode('', $replaced);
    }

    function loadModel($class, $data)
    {
        $typeModel  = str_replace(['Octo\\', 'Lib'], '', get_class($class));
        $db         = $class->db();
        $table      = $class->table();
        $dir        = Config::get('model.dir', realpath(path('app') . '/models')) . DS . $typeModel;

        $modelFile = $dir . DS . Strings::lower($db) . DS . ucfirst(Strings::lower($table)) . '.php';

        if (!is_dir(Config::get('model.dir', realpath(path('app') . '/models')))) {
            Dir::mkdir(Config::get('model.dir', realpath(path('app') . '/models')));
        }

        if (!is_dir($dir)) {
            Dir::mkdir($dir);
        }

        if (!is_dir($dir . DS . Strings::lower($db))) {
           Dir::mkdir($dir . DS . Strings::lower($db));
        }

        if (!File::exists($modelFile)) {
            $tpl = '<?php
    namespace Octo;

    class ' . $typeModel . ucfirst(Strings::lower($db)) . ucfirst(Strings::lower($table)) . 'Model extends Model {
        /* Make hooks of model */
        public function _hooks()
        {
            $obj = $this;
            // $this->_hooks[\'beforeCreate\'] = function () use ($obj) {};
            // $this->_hooks[\'beforeRead\'] = ;
            // $this->_hooks[\'beforeUpdate\'] = ;
            // $this->_hooks[\'beforeDelete\'] = ;
            // $this->_hooks[\'afterCreate\'] = ;
            // $this->_hooks[\'afterRead\'] = ;
            // $this->_hooks[\'afterUpdate\'] = ;
            // $this->_hooks[\'afterDelete\'] = ;
            // $this->_hooks[\'validate\'] = function () use ($obj) {
            //     return true;
            // };
        }
    }';

            File::put($modelFile, $tpl);
        }

        $instanciate = '\\Octo\\' . $typeModel . ucfirst(Strings::lower($db)) . ucfirst(Strings::lower($table)) . 'Model';

        if (!class_exists($instanciate)) {
            require_once $modelFile;
        }

        return new $instanciate($class, $data);
    }

    /**
     * @param $event
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    function payloadFromEvent($event)
    {
        if (method_exists($event, 'broadcastWith')) {
            return array_merge(
                $event->broadcastWith(), ['socket' => dataget($event, 'socket')]
            );
        }

        $payload = [];

        foreach ((new \ReflectionClass($event))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $payload[$property->getName()] = formatProperty($property->getValue($event));
        }

        unset($payload['broadcastQueue']);

        return $payload;
    }

    function formatProperty($value)
    {
        return arrayable($value) ? $value->toArray() : $value;
    }

    function libonce($lib, $args = [])
    {
        return lib($lib, $args, true);
    }

    /**
     * @param string $lib
     * @param array $args
     * @param bool $singleton
     *
     * @return mixed|object
     */
    function lib(string $lib, $args = [], bool $singleton = false)
    {
        $args = arrayable($args) ? $args->toArray() : $args;

        $singleton = empty($args);

        try {
            $class = '\\Octo\\' . Strings::camelize($lib);

            if (!class_exists($class)) {
                $file = __DIR__ . DS . Strings::lower($lib) . '.php';

                if (!file_exists($file)) {
                    $file = __DIR__ . DS . $lib . '.php';
                }

                if (file_exists($file)) {
                    require_once $file;
                }
            }

            return instanciator()->make($class, $args, $singleton);
        } catch (\Exception $e) {
            return instanciator()->make($lib, $args, $singleton);
        }
    }

    /**
     * @return Inflector
     */
    function str()
    {
        return maker(Inflector::class);
    }

    /**
     * @return Now
     */
    function reg()
    {
        return maker(Now::class);
    }

    function memoryFactory($class, $count = 1, $lng = 'fr_FR')
    {
        if (!is_numeric($count) || $count < 1) {
            exception('Factory', 'You must create at least one row.');
        }

        $model = maker($class, [], false);
        $faker = faker($lng);

        $entity = dbMemory(
            lcfirst(
                Strings::camelize(
                    $model->orm()->db . '_' . $model->orm()->table
                )
            )
        );

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = $model->factory($faker);
        }

        $factories = o([
            'rows'      => $rows,
            'entity'    => $entity
        ]);

        $factories->macro('raw', function ($subst = []) use ($factories) {
            $rows = $factories->getRows();

            if (!empty($subst)) {
                $res = [];

                foreach ($rows as $row) {
                    foreach ($subst as $k => $v) {
                        $row[$k] = $v;
                    }

                    $res[] = $row;
                }

                return count($res) == 1 ? current($res) : coll($res);
            } else {
                return count($rows) == 1 ? current($rows) : coll($rows);
            }
        });

        $factories->macro('store', function ($subst = []) use ($factories) {
            $em     = $factories->getEntity();
            $rows   = [];

            foreach ($factories->getRows() as $row) {
                if (!empty($subst)) {
                    foreach ($subst as $k => $v) {
                        $row[$k] = $v;
                    }
                }

                $rows[] = $em->persist($row);
            }

            if (count($rows) == 1) {
                return $em->model(current($rows));
            }

            return $em
            ->resetted()
            ->in(
                'id',
                coll($rows)->pluck('id')
            )->get();
        });

        return $factories;
    }

    /**
     * @param Fast|null $context
     *
     * @return Fast
     */
    function fast($context = null)
    {
        if ($context instanceof Fast) {
            actual('fast', $context);
        }

        $fast = actual('fast');

        if (is_null($fast)) {
            $fast = new Fast;
            actual('fast', $fast);
        }

        return $fast;
    }

    /**
     * @param string $class
     * @param int $count
     * @param string $lng
     *
     * @return Objet
     */
    function factory($class, $count = 1, $lng = 'fr_FR')
    {
        if (!is_numeric($count) || $count < 1) {
            exception('Factory', 'You must create at least one row.');
        }

        $model = maker($class);
        $faker = faker($lng);

        /** @var Octalia $entity */
        $entity = $model->orm();

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = $model->factory($faker);
        }

        $factories = o([
            'rows'      => $rows,
            'entity'    => $entity
        ]);

        $factories->macro('raw', function ($subst = []) use ($factories) {
            $rows = $factories->getRows();

            if (!empty($subst)) {
                $res = [];

                foreach ($rows as $row) {
                    foreach ($subst as $k => $v) {
                        $row[$k] = $v;
                    }

                    $res[] = $row;
                }

                return count($res) === 1 ? current($res) : coll($res);
            } else {
                return count($rows) === 1 ? current($rows) : coll($rows);
            }
        });

        $factories->macro('store', function ($subst = []) use ($factories) {
            /** @var Octalia $em */
            $em = $factories->getEntity();
            $rows = [];

            foreach ($factories->getRows() as $row) {
                if (!empty($subst)) {
                    foreach ($subst as $k => $v) {
                        $row[$k] = $v;
                    }
                }

                $rows[] = $em->persist($row);
            }

            if (count($rows) === 1) {
                return $em->model(current($rows));
            }

            return $em
            ->resetted()
            ->in(
                'id',
                coll($rows)->pluck('id')
            )->get();
        });

        return $factories;
    }

    /**
     * @return mixed|null
     */
    function makeFactory()
    {
        $args = func_get_args();
        $closure = array_shift($args);

        if ($closure instanceof Closure) {
            return instanciator()->makeClosure($closure, $args);
        }

        return null;
    }

    /**
     * @return null|Lazy
     */
    function lazyFactory()
    {
        $args       = func_get_args();
        $closure    = array_shift($args);

        if ($closure instanceof Closure) {
            return lazy(function () use ($closure, $args) {
                return instanciator()->makeClosure($closure, $args);
            });
        }

        return null;
    }

    function status($code = 200)
    {
        $headerMessage  = Api::getMessage($code);
        $protocol       = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';

        if (!headers_sent()) {
            header($protocol . " $code $headerMessage");
        }
    }

    function abort($code = 403, $message = 'Forbidden')
    {
        $message = value($message);

        status($code);

        if (is_array($message)) {
            $message = json_encode($message);
            header('content-type: application/json; charset=utf-8');
        }

        extract(Registry::get('views.vars', []));

        die($message);
    }

    function response($message = 'Forbidden', $code = 200)
    {
        abort($code, $message);
    }

    function partial($file, $args = [])
    {
        vue(
            $file,
            $args
        )->partial(
            actual('vue')
        );
    }

    function layout($file, $page = null, $sections = null)
    {
        $page = is_null($page) ? actual('vue') : $page;

        $sections   = !is_array($sections)
        ? ['content', 'js', 'css']
        : array_merge(['content', 'js', 'css'], $sections);

        vue(
            $file,
            $page->getArgs()
        )->layout(
            $page,
            $sections
        );
    }

    function codeEvaluation($code, array $args = [])
    {
        ob_start();

        extract($args);

        eval(' ?>' . $code . '<?php ');

        $eval = ob_get_contents();

        ob_end_clean();

        return $eval;
    }

    /**
     * @param string $path
     * @param string $namespace
     */
    function addVueDirectory(string $path, string $namespace)
    {
        $directories = Registry::get('vue.directories', []);

        $directories[$namespace] = $path;

        Registry::set('vue.directories', $directories);
    }

    /**
     * @return array
     */
    function getVueDirectories()
    {
        return Registry::get('vue.directories', []);
    }

    /**
     * @param string $file
     *
     * @return null|string
     */
    function searchVueFile(string $file): ?string
    {
        if (!fnmatch('*::*', $file)) {
            return path('app') . '/views/' . str_replace('.', '/', $file) . '.phtml';
        }

        $path = null;

        foreach (getVueDirectories() as $namespace => $directory) {
            if (fnmatch($namespace . '::*', $file)) {
                $computed = str_replace($namespace . '::', '', $file);
                $path = $directory . '/views/' . str_replace('.', '/', $computed) . '.phtml';

                if (File::exists($path)) {
                    return $path;
                }
            }
        }

        return $path;

    }

    function vue($file, $args = [], $status = 200)
    {
        if (!File::exists($file)) {
            $path = searchVueFile($file);
        } else {
            $path = $file;
        }

        $vue = o([
            'withs'     => [],
            'is_vue'    => true,
            'status'    => (int) $status,
            'args'      => $args,
            'path'      => $path
        ]);

        $vue->macro('setvar', function ($key, $value) use ($vue) {
            $args       = $vue->args;
            $args[$key] = $value;

            $vue->args = $args;

            return $vue;
        });

        $vue->macro('render', function () use ($vue) {
            $withs = $vue->withs;

            if (!empty($withs)) {
                foreach ($withs as $k => $v) {
                    $setter = setter($k);
                    session()->$setter($v);
                }
            }

            $args = array_merge(['tpl' => $vue], $vue->getArgs());

            $html = evaluate(
                $vue->getPath(),
                $args
            );

            response(
                $html,
                (int) $vue->getStatus()
            );
        });

        $vue->macro('inline', function () use ($vue) {
            $session = maker(FastSessionInterface::class);
            $withs = $vue->withs;

            if (!empty($withs)) {
                foreach ($withs as $k => $v) {
                    $session[$k] = $v;
                }
            }

            $renderer = getContainer()->getRenderer();

            $args = array_merge([
                    'renderer' => $renderer,
                    'tpl' => $vue
                ],
                $vue->getArgs()
            );

            return evaluateInline(
                $vue->getPath(),
                $args
            );
        });

        $vue->macro('layout', function ($page, $sections) use ($vue) {
            $args = array_merge([
                'self'      => actual('controller'),
                'layout'    => $vue,
                'tpl'       => $page
                ],
                $vue->getArgs()
            );

            $layoutContent  = File::read($vue->getPath());
            $pageContent    = File::read($page->getPath());

            $includes = explode("@include(", $pageContent);
            array_shift($includes);

            foreach ($includes as $include) {
                $inc = cut("'", "'", $include);

                if (!File::exists($inc)) {
                    $pathFile = searchVueFile($inc);
                } else {
                    $pathFile = $inc;
                }

                if (File::exists($pathFile)) {
                    $incContent = File::read($pathFile);
                    $pageContent = str_replace("@include('$inc')", $incContent, $pageContent);
                }
            }

            $sections = explode("@section(", $pageContent);
            array_shift($sections);

            foreach ($sections as $sub) {
                $section = cut("'", "'", $sub);

                $sectionContent = cut(
                    "@section('$section')",
                    "@endsection",
                    $pageContent
                );

                $layoutContent = str_replace([
                        "{{ $section }}",
                        '{{' . $section . '}}',
                        '{{ ' . $section . '}}',
                        '{{' . $section . ' }}'
                    ],
                    $sectionContent,
                    $layoutContent
                );
            }

            $layoutContent = str_replace(
                '$this',
                '$self',
                $layoutContent
            );

            response(
                codeEvaluation(
                    $layoutContent,
                    $args
                ),
                (int) $vue->getStatus()
            );
        });

        $vue->macro('partial', function ($page) use ($vue) {
            $args = array_merge([
                    'tpl'       => $page,
                    'partial'   => $vue
                ],
                $page->getArgs(),
                $vue->getArgs()
            );

            echo evaluate(
                $vue->getPath(),
                $args
            );
        });

        $vue->macro('with', function ($key, $value) use ($vue) {
            $withs          = $vue->withs;
            $withs[$key]    = $value;

            $vue->withs = $withs;

            return $vue;
        });

        $vue->macro('can', function () {
            $guard = guard();

            $check = call_user_func_array([$guard, 'allows'], func_get_args());

            if ($check) {
                return true;
            }

            return false;
        });

        $vue->macro('cannot', function () {
            $guard = guard();

            $check = call_user_func_array([$guard, 'allows'], func_get_args());

            if ($check) {
                return false;
            }

            return true;
        });

        actual(
            'vue',
            $vue
        );

        return $vue;
    }

    function path($k = null, $v = null, $d = null)
    {
        $paths = paths();

        if (is_null($k)) {
            return coll($paths);
        }

        if (is_null($v)) {
            return isAke($paths, $k, $d);
        }

        $paths[$k] = $v;

        (new Now)->set('octo.paths', $paths);
    }

    function public_path(string $path = '')
    {
        return path('public') . ($path ? DS . ltrim($path, DS) : $path);
    }

    function cache_path(string $path = '')
    {
        return path('cache') . ($path ? DS . ltrim($path, DS) : $path);
    }

    function app_path(string $path = '')
    {
        return path('app') . ($path ? DS . ltrim($path, DS) : $path);
    }

    function storage_path(string $path = '')
    {
        return path('storage') . ($path ? DS . ltrim($path, DS) : $path);
    }

    function paths()
    {
        return (new Now)->get('octo.paths', []);
    }

    /**
     * @param string $path
     * @param string $manifestDirectory
     *
     * @return string
     *
     * @throws \Exception
     */
    function mix(string $path, string $manifestDirectory = ''): string
    {
        static $manifests = [];

        if (!startsWith($path, '/')) {
            $path = "/{$path}";
        }

        if ($manifestDirectory && !startsWith($manifestDirectory, '/')) {
            $manifestDirectory = "/{$manifestDirectory}";
        }

        if (file_exists(public_path($manifestDirectory . '/hot'))) {
            $url = file_get_contents(public_path($manifestDirectory . '/hot'));

            if (startsWith($url, ['http://', 'https://'])) {
                return Inflector::after($url, ':') . $path;
            }

            return "//localhost:8080{$path}";
        }

        $manifestPath = public_path($manifestDirectory . '/mix-manifest.json');

        if (!isset($manifests[$manifestPath])) {
            if (!file_exists($manifestPath)) {
                throw new \Exception('The Mix manifest does not exist.');
            }

            $manifests[$manifestPath] = json_decode(file_get_contents($manifestPath), true);
        }

        $manifest = $manifests[$manifestPath];

        if (!isset($manifest[$path])) {
            return $path;
        }

        return $manifestDirectory . $manifest[$path];
    }

    /**
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    function nextable()
    {
        $args = func_get_args();

        $first  = array_shift($args);
        $next   = array_shift($args);

        if (is_callable($first) && is_callable($next)) {
            $params = array_merge([$first], $args);

            $result = callCallable(...$params);

            $params = array_merge([$next, $result], $args);

            return callCallable(...$params);
        }

        return null;
    }

    /**
     * @param null|string $dir
     *
     * @throws Exception
     */
    function systemBoot(?string $dir = null)
    {
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();

        forever();

        require_once __DIR__ . DS . 'fast.php';
        require_once __DIR__ . DS . 'di.php';
        require_once __DIR__ . DS . 'debug.php';
        require_once __DIR__ . DS . 'cachei.php';

        if (!defined('OCTO_MAX'))       define('OCTO_MAX',          9223372036854775808);
        if (!defined('OCTO_MIN'))       define('OCTO_MIN',          OCTO_MAX * -1);
        if (!defined('OCTO_CACHE_TTL')) define('OCTO_CACHE_TTL',    strtotime('+1 year') - time());

        if (!class_exists('Octo\Route')) {
            Alias::facade('Route', 'Routes', 'Octo');
        }

        if (!class_exists('Octo\Strings')) {
            Alias::facade('Strings', 'Inflector', 'Octo');
        }

        if (!class_exists('Octo\Dir')) {
            Alias::facade('Dir', 'File', 'Octo');
        }

        if (!class_exists('Octo\Kh')) {
           staticFacade('\\Octo\\Cache', 'Kh');
        }

        if (!class_exists('Octo\Hash')) {
           staticFacade('\\Octo\\Hasher', 'Hash');
        }

        if (!class_exists('Octo\Queue')) {
           staticFacade('\\Octo\\Bus', 'Queue');
        }

        if (!class_exists('Octo\Registry')) {
           staticFacade('\\Octo\\Now', 'Registry');
        }

        Autoloader::alias('Str', Inflector::class);
        Autoloader::alias('Dater', Carbon::class);

        $dirs = Arrays::last(
            explode(
                DS,
                $dir
            )
        );

        $addDir = str_replace(
            'public',
            '',
            $dirs
        );

        $subdir = '';

        if (!defined('FROM_ROOT')) {
            if (strlen($addDir) > 0) {
                $subdir = '/' . $addDir;
            }
        } else {
            $subdir = '/' . FROM_ROOT;

            if (strlen($addDir) > 0) {
                $subdir .= '/' . $addDir;
            }
        }

        Registry::set('core.routes.prefix', '');
        Registry::set('core.routes.before', null);

        Registry::set('octo.subdir', $subdir);

        if (!defined('OCTO_STANDALONE') && true !== getenv('OCTO_STANDALONE')) {
            defined('WEBROOT') || define('WEBROOT', Registry::get('octo.subdir', '/'));

            date_default_timezone_set(Config::get('timezone', 'Europe/Paris'));

            if (!IS_CLI && isset($_SERVER["SERVER_PORT"])) {
                $protocol = 'http';

                if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                    $protocol .= 's';
                }

                if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
                    $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . ':' . $_SERVER["SERVER_PORT"] . WEBROOT;
                } else {
                    $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . WEBROOT;
                }

                defined('URLSITE') || define('URLSITE', $urlSite);
            }

            ini_set('error_log', path('storage') . DS . 'logs' . DS . 'error.log');
        }

        if (!defined('OCTO_DAY_KEY')) define('OCTO_DAY_KEY', sha1((OCTO_MAX / 7) + strtotime('today') . forever()));

        path('public', realpath($dir));

        $app = context('app');

        $app->on(function ($event, callable $callable, $priority = 0) {
            $events = Registry::get('context.app.events', []);

            if (!isset($events[$event])) {
                $events[$event] = [];
            }

            $priority = !is_int($priority) ? 0 : $priority;

            $ev = $events[$event][] = new Listener($callable, $priority);

            Registry::set('context.app.events', $events);

            return $ev;
        });

        $app->emit(function () {
            $args   = func_get_args();
            $event  = array_shift($args);

            $events = Registry::get('context.app.events', []);

            $eventsToCall = isAke($events, $event, []);

            if (!empty($eventsToCall)) {
                $collection = [];

                foreach ($eventsToCall as $eventToCall) {
                    $collection[] = [
                        'event'     => $eventToCall,
                        'priority'  => $eventToCall->priority
                    ];
                }

                $listeners = array_values(coll($collection)->sortByDesc('priority')->toArray());

                $results = [];

                foreach ($listeners as $listenerCalled) {
                    $listener = $listenerCalled['event'];

                    $continue = true;

                    if ($listener->called) {
                        if ($listener->once === true) {
                            $continue = false;
                        }
                    }

                    if (!$continue) {
                        break;
                    } else {
                        $listener->called = true;
                        $result = call_user_func_array($listener->callable, $args);

                        if ($listener->halt) {
                            Registry::set('context.app.events', []);

                            return $result;
                        } else {
                            $results[] = $result;
                        }
                    }
                }

                return $results;
            }
        });

        $app->class(function () {
            $args   = func_get_args();
            $lib    = array_shift($args);

            array_pop($args);

            return lib($lib, $args);
        });

        $app->make(function () {
            $args = func_get_args();
            array_pop($args);

            return maker(...$args);
        });

        $app->init(function ($dir) {
            $ini = parse_ini_file($dir . '/../.env');

            defined('APPLICATION_ENV') || define('APPLICATION_ENV', isset($ini['APPLICATION_ENV']) ? $ini['APPLICATION_ENV'] : 'production');
            defined('SITE_NAME') || define('SITE_NAME', isset($ini['SITE_NAME']) ? $ini['SITE_NAME']         : 'project');

            $root = realpath($dir . '/../');

            $nameDir = Arrays::last(explode(DS, $root));

            if (fnmatch('/' . $nameDir . '/*', $_SERVER['REQUEST_URI'])) {
                define('FROM_ROOT', $nameDir);
            }

            path('base',       $root);
            path('app',        realpath($root . '/app'));
            path('public',     realpath($dir));

            $errors = [];

            if (!is_writable($dir  . '/../app/storage/data')) {
                $errors[] = $dir  . '/../app/storage/data';
            }

            if (!is_writable($dir  . '/../app/storage/cache')) {
                $errors[] = $dir  . '/../app/storage/cache';
            }

            if (!is_writable($dir  . '/../app/storage/tmp')) {
                $errors[] = $dir  . '/../app/storage/tmp';
            }

            if (!empty($errors)) {
                $html = "<h1><i class='fa fa-warning fa-2x'></i> Some errors occured</h1>";
                $html .= '<h3>Please chmod 0777 these directories :</h3>';
                $html .= '<ul>';

                foreach ($errors as $error) {
                    $html .= '<li>' . realpath($error) . '</li>';
                }

                $html .= '</ul>';

                view($html, 500, 'Octo Error Report');
            }
        });

        $app->register(function ($class, $args = []) use ($app) {
            if (is_object($args)) {
                $args = [];
            }

            $instance       = maker($class, $args);
            $app[$class]    = $instance;
        });

        $app->apply(function (callable $callable) {
            $app = App::create();

            return call_user_func_array($callable, [$app]);
        });

        $app->run(function ($namespace = 'App', $cli = false) {
            if (is_object($namespace)) {
                $namespace = 'App';
            }

            if (is_object($cli)) {
                $cli = false;
            }

            File::load(path('app') . '/lib/*.php');

            lib('timer')->start();

            File::load(path('app') . '/config/*config*.php');
            File::load(path('app') . '/config/*routes*.php');
            File::load(path('app') . '/routes/*.php');

            if (!$cli) {
                try {
                    lib('router')->run($namespace);
                } catch (\Exception $e) {
                    dd($e);
                }
            }
        });

        $app->cli(function($app) {
            $app->run('App', true);
        });

        $app->request(function () {
            return \GuzzleHttp\Psr7\ServerRequest::fromGlobals();
        });

        $app->response(function () {
            return new Response;
        });

        $app->render(function(\Psr\Http\Message\ResponseInterface $response) {
            \Http\Response\send($response);
        });

        if (!empty($_POST)) {
            bag('Post');
            Post::fill(oclean($_POST));
        }

        register_shutdown_function(function () {
            Queue::listen();
            Later::shutdown();
            middlewares('after');
            listening('system.shutdown');
            shutdown();
        });

        $bootstrap = path('app') . '/config/bootstrap.php';

        if (File::exists($bootstrap)) {
            require_once $bootstrap;
        }

        $autoload = path('app') . '/config/autoload.php';

        if (File::exists($autoload)) {
            $all        = include $autoload;
            $aliases    = isAke($all, 'aliases', []);
            $mapped     = isAke($all, 'mapped', []);

            Autoloader::aliasing($aliases);
            Autoloader::mapping($mapped);
        }

        Routes::getPost('burst/(.*)-(.*).(.*)', function ($file, $hash, $ext) {
            $asset = path('public') . '/'. $file . '.' . $ext;

            if (!headers_sent() && File::exists($asset)) {
                switch (strtolower($ext)) {
                    case 'css':
                        header('Content-type: text/css');
                        break;
                    case 'js':
                        header('Content-type: text/javascript');
                        break;
                }

                die(File::read($asset));
            }
        });

        loadEvents();

        listening('system.bootstrap');

        services();

        middlewares();

        rights();
    }

    function burst($asset)
    {
        if (!File::exists($asset)) {
            $asset = path('public') . $asset;
        }

        if (File::exists($asset)) {
            $age = md5(filemtime($asset));
            $ext = Arrays::last(explode('.', $asset));
            $tab = explode('.' . $ext, $asset);

            array_pop($tab);

            $segment = implode('.' . $ext, $tab);

            $file = str_replace_first(path('public'), '', $segment);

            return '/burst' . $file . '-' . $age . '.' . $ext;
        }

        return $asset;
    }

    /**
     * @param Object $model
     * @param string $event
     * @param array $next
     * @return mixed|Object
     */
    function modelEvent(Object $model, string $event, $next = [])
    {
        $allEvents = Registry::get('octalia.events', []);

        $class = get_class($model);

        if (is_callable($next)) {
            $allEvents[sha1($class)][$event] = $next;

            Registry::set('octalia.events', $allEvents);
        } else {
            $events = isAke($allEvents, sha1($class), []);

            $cb = isAke($events, $event, null);

            if (is_callable($cb)) {
                return call_user_func_array($cb, array_merge([$model], $next));
            }
        }

        return $model;
    }

    /**
     * @param $instance
     * @return Ghost
     */
    function getRow($instance)
    {
        return make([], $instance);
    }

    /**
     * @param array $rows
     *
     * @return Collection
     */
    function collectify(array $rows = [])
    {
        $collection = [];

        foreach ($rows as $row) {
            $collection[] = make($row);
        }

        return coll($collection);
    }

    /**
     * @param array $array
     * @param null $instance
     * @return Ghost
     */
    function make(array $array = [], $instance = null)
    {
        return lib('ghost', [$array, $instance]);
    }

    /**
     * @param $class
     * @param array $array
     *
     * @return Magic
     */
    function magic($class, array $array = [])
    {
        return lib('magic', [$array, $class]);
    }

    /**
     * @param $context
     * @param array $array
     *
     * @return Context
     */
    function context($context, array $array = [])
    {
        return lib('context', [$array, $context]);
    }

    /**
     * @param $mock
     * @param array $args
     * @return Mockery
     */
    function mockery($mock, array $args = [])
    {
        $class = maker($mock, $args);

        $methods = get_class_methods($class);

        $mock = lib(
            'mockery',
            [
                [],
                Inflector::camelize(
                    'mock_' .
                    Inflector::urlize(get_class($class), '_')
                )
            ]
        );

        foreach ($methods as $method) {
            call_user_func_array([$mock, $method], [function () use ($class, $method) {
                return call_user_func_array([$class, $method], func_get_args());
            }]);
        }

        return $mock;
    }

    /**
     * @param callable|null $callable
     * @throws \ReflectionException
     */
    function shutdown(callable $callable = null)
    {
        $callables = Registry::get('core.shutdown', []);

        if (is_callable($callable)) {
            $callables[] = $callable;
            Registry::set('core.shutdown', $callables);
        } else {
            foreach ($callables as $callable) {
                if (is_callable($callable)) {
                    callCallable($callable);
                }
            }
        }
    }

    function tern($a, $b)
    {
        return $a ?: $b;
    }

    function queue()
    {
        return call_user_func_array([lib('later'), 'set'], func_get_args());
    }

    function listenQueue()
    {
        return call_user_func_array([lib('later'), 'listen'], func_get_args());
    }

    function bgQueue()
    {
        return call_user_func_array([lib('later'), 'background'], func_get_args());
    }

    function _($segment, $args = [], $locale = null)
    {
        echo trans($segment, $args, $locale);
    }

    function trans($segment, $args = [], $locale = null)
    {
        $translation = $segment;

        $keys   = explode('.', $segment);
        $key    = array_shift($keys);
        $keys   = implode('.', $keys);

        $path   = tern(path('lang'), path('app') . '/lang/');

        $lng    = tern($locale, lng());

        $file   = $path . $lng . '/' . Inflector::lower($key) . '.php';

        if (File::exists($file)) {
            $segments       = include($file);
            $translation    = aget($segments, $keys);

            if (!empty($args) && !empty($translation)) {
                $args = coll($args)->sortBy(function($a) {
                    return mb_strlen($a) * -1;
                });

                foreach ($args as $k => $v) {
                    $translation = str_replace('{{ ' . $k . ' }}', $v, $translation);
                }
            }
        }

        return $translation;
    }

    function rights()
    {
        listening('rights.booting');

        $acl = path('app') . '/config/acl.php';

        $rights = lib('ghost', [[], 'rights']);

        $rights->add(function ($type, $data) use ($rights) {
            $rights[$type] = obj('acl_' . $type, $data);

            return $rights;
        });

        if (File::exists($acl)) {
            $datas = include $acl;

            foreach ($datas as $k => $v) {
                $rights->add($k, $v);
            }
        }

        return $rights;
    }

    function obj($instance, array $array = [])
    {
        $key = sha1($instance);

        $objects = Registry::get('core.objects', []);

        $object = isAke($objects, $key, null);

        if (!$object) {
            $class = Strings::camelize('octo_' . Strings::uncamelize($instance));

            if (!class_exists($class)) {
                $code = 'class ' . $class . ' extends \\Octo\\Collection {}';
                eval($code);
            }

            $class = '\\' . $class;

            $object = new $class($array);

            $objects[$key] = $object;

            Registry::set('core.objects', $objects);
        }

        return $object;
    }

    function classify($instance, array $array = [])
    {
        $class = Strings::camelize('octo_' . Strings::uncamelize($instance));

        if (!class_exists($class)) {
            $code = 'class ' . $class . ' extends \\Octo\\Ghost {}';
            eval($code);
        }

        $class = '\\' . $class;

        return new $class($array, sha1($class));
    }

    function inMemory($array = null)
    {
        static $inMemoryData = [];

        if ($array) {
            $inMemoryData = $array;
        }

        return $inMemoryData;
    }

    function segment($ns = 'core', $array = null)
    {
        $data       = inMemory();
        $segment    = aget($data, $ns, []);

        if ($array) {
            $segment = $array;
            aset($data, $ns, $segment);
            inMemory($data);
        }

        return $segment;
    }

    function set($k, $v)
    {
        $data = segment('core');
        aset($data, $k, $v);

        segment('core', $data);
    }

    /**
     * @param $k
     * @param null $d
     * @return mixed|null
     * @throws \ReflectionException
     */
    function get($k, $d = null)
    {
        $data   = segment('core');
        $value  = aget($data, $k, $d);

        return value($value);
    }

    /**
     * @param $k
     * @param null $d
     * @return mixed|null
     * @throws \ReflectionException
     */
    function getDel($k, $d = null)
    {
        if (has($k)) {
            $data   = segment('core');
            $value  = aget($data, $k, $d);
            forget($k);

            return value($value);
        }

        return $d;
    }

    /**
     * @param string $k
     * @param array $args
     * @param null $d
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    function getMacro(string $k, array $args = [], $d = null)
    {
        if (has($k)) {
            $data   = segment('core');
            $cb     = aget($data, $k, $d);

            if (is_callable($cb)) {
                $params = array_merge([$cb], $args);

                return callCallable(...$params);
            }
        }

        return $d;
    }

    function has($k)
    {
        return 'octodummy' != get($k, 'octodummy');
    }

    function forget($k)
    {
        if (has($k)) {
            $data = segment('core');
            adel($data, $k);

            segment('core', $data);

            return true;
        }

        return false;
    }

    function del($k)
    {
        return forget($k);
    }

    function incr($k, $by = 1)
    {
        $old = get($k, 0);
        $new = $old + $by;

        set($k, $new);

        return $new;
    }

    function increment($k, $by = 1)
    {
        return incr($k, $by);
    }

    function decr($k, $by = 1)
    {
        $old = get($k, 0);
        $new = $old - $by;

        set($k, $new);

        return $new;
    }

    function decrement($k, $by = 1)
    {
        return decr($k, $by);
    }

    function getOr($k, callable $c)
    {
        $res = get($k, 'octodummy');

        if ('octodummy' === $res) {
            set($k, $res = $c());
        }

        return $res;
    }

    function i18n()
    {
        $made = Registry::get('i18n.made', false);
        $cb = null;

        if (!$made) {
            $cb = function () {
                Registry::set('i18n.made', true);
                $i18n = classify('i18n');

                return $i18n;
            };
        }

        return single('i18n', $cb);
    }

    /**
     * @param $concern
     *
     * @return Proxy
     */
    function proxy($concern)
    {
        return new Proxy($concern);
    }

    function provider($service = null, array $args = [])
    {
        $provider = classify('providers');

        if ($service) {
            $callable = $provider[$service];

            if (!$callable) {
                $callable = $provider[$service] = function () use ($service, $args) {
                    return maker($service, $args);
                };
            }

            return call_user_func_array(
                $callable,
                array_merge(
                    [$provider],
                    $args
                )
            );
        }

        return $provider;
    }

    function eventer()
    {
        $made = Registry::get('eventer.made', false);
        $cb = null;

        if (!$made) {
            $cb = function () {
                Registry::set('eventer.made', true);
                $eventer = classify('eventer');

                $eventer->on(function () use ($eventer) {
                    $args       = func_get_args();
                    $event      = array_shift($args);
                    $call       = array_shift($args);
                    $priority   = array_shift($args);
                    $priority   = $priority || 0;

                    $events = Registry::get('eventer.events', []);

                    if (!$call instanceof \Closure) {
                        $call = resolverClass($call);
                    }

                    $priorities = isAke($events, $event, []);

                    $segment = isset($priorities[$priority]) ? $priorities[$priority] : [];

                    $segment[] = $call;

                    $events[$event][$priority] = $segment;

                    Registry::set('eventer.events', $events);

                    return $eventer;
                });

                $eventer->listen(function () {
                    $events = Registry::get('eventer.events', []);

                    $args = func_get_args();

                    $event = array_shift($args);

                    $fireEvents = isAke($events, $event, []);

                    $results = [];

                    if (!empty($fireEvents)) {
                        foreach ($fireEvents as $priority => $eventsLoaded) {
                            $key = $event . '_' . $priority;

                            $results[$key] = [];

                            foreach ($eventsLoaded as $eventLoaded) {
                                if ($eventLoaded && is_callable($eventLoaded)) {
                                    $result = call_user_func_array($eventLoaded, $args);

                                    if ($result instanceof Object && $result->getStopPropagation() == 1) {
                                        return $results;
                                    }

                                    $results[$key][] = $result;
                                }
                            }
                        }
                    }

                    return $results;
                });

                $eventer->forget(function ($event, $priority = 'octodummy') use ($eventer) {
                    $events = Registry::get('eventer.events', []);

                    if ('octodummy' == $priority) {
                        unset($events[$event]);
                    } else {
                        unset($events[$event][$priority]);
                    }

                    Registry::set('eventer.events', $events);

                    return $eventer;
                });

                return $eventer;
            };
        }

        return single('eventer', $cb);
    }

    function stopPropagation($value = null)
    {
        $o = o();

        return $o->setStopPropagation(1)->setValue(value($value));
    }

    function emit()
    {
        return call_user_func_array([eventer(), 'listen'], func_get_args());
    }

    function setting()
    {
        $made = Registry::get('settings.made', false);
        $cb = null;

        if (!$made) {
            $cb = function () {
                Registry::set('settings.made', true);
                $settings = classify('settings');

                $settings->set(function ($k, $v) use ($settings) {
                    $k = sha1(forever() . $k);

                    em('systemSetting')
                    ->firstOrCreate(['name' => $k])
                    ->setValue($v)
                    ->save();

                    return $settings;
                });

                $settings->get(function ($k, $d = null) {
                    $k = sha1(forever() . $k);

                    $setting = em('systemSetting')
                    ->where(['name', '=', $k])
                    ->first(true);

                    return $setting ? $setting->value : $d;
                });

                $settings->has(function ($k) {
                    $k = sha1(forever() . $k);

                    $setting = em('systemSetting')
                    ->where(['name', '=', $k])
                    ->first(true);

                    return $setting ? true : false;
                });

                $settings->delete(function ($k) {
                    $k = sha1(forever() . $k);

                    $setting = em('systemSetting')
                    ->where(['name', '=', $k])
                    ->first(true);

                    return $setting ? $setting->delete() : false;
                });

                return $settings;
            };
        }

        return single('settings', $cb);
    }

    function objectify($instance, array $array = [])
    {
        return single($instance, function () use ($instance, $array) {
            return classify($instance, $array);
        });
    }

    function resolve($class, array $args = [])
    {
        return single($class, null, $args);
    }

    function singlify($class, $resolver = null, array $args = [])
    {
        $key = sha1($class);
        $singletons = Registry::get('core.singletons', []);

        if ($resolver && is_callable($resolver)) {
            $single = call_user_func_array($resolver, $args);

            $singletons[$key] = $single;

            Registry::set('core.singletons', $singletons);
        } else {
            $single = isAke($singletons, $key, null);

            if (!$single) {
                $single = maker($class, $args);

                if ($single) {
                    $singletons[$key] = $single;

                    Registry::set('core.singletons', $singletons);
                }
            }
        }

        return $single;
    }

    function ionly()
    {
        $keys = func_get_args();
        $inputs = [];

        foreach ($keys as $key) {
            $inputs[$key] = isAke($_REQUEST, $key, null);
        }

        return lib('ghost', [$inputs, null]);
    }

    function middleware($class)
    {
        $middlewares    = Registry::get('core.middlewares', []);
        $middlewares[]  = $class;

        Registry::set('core.middlewares', $middlewares);
    }

    function middlewares($when = 'before')
    {
        listening('middlewares.booting');

        $middlewares        = Registry::get('core.middlewares', []);
        $request            = make($_REQUEST, 'request');
        $middlewaresFile    = path('app') . '/config/middlewares.php';

        if (File::exists($middlewaresFile)) {
            $middlewaresFromConfig  = include $middlewaresFile;
            $middlewares            = array_merge($middlewares, $middlewaresFromConfig);
        }

        foreach ($middlewares as $middlewareClass) {
            $middleware = maker($middlewareClass, [], false);
            $methods    = get_class_methods($middleware);
            $method     = lcfirst(Strings::camelize('apply_' . $when));

            if (in_array($method, $methods)) {
                call_user_func_array([$middleware, $method], [$request, context('app')]);
            }
        }
    }

    function aliases($className)
    {
        static $aliases = [];

        $aliasesFile = path('app') . '/config/aliases.php';

        if (file_exists($aliasesFile)) {
            $aliasesFromConfig  = include $aliasesFile;
            $aliases            = array_merge($aliases, $aliasesFromConfig);
        }

        foreach ($aliases as $alias => $class) {
            if ($alias === $className) {
                return class_alias($class, $alias);
            }
        }

        return false;
    }

    function loadEvents()
    {
        $events = path('app') . '/config/events.php';

        if (File::exists($events)) {
            require_once $events;
        }

        subscribers();
    }

    function subscriber($subscriberClass)
    {
        $subscriber = maker($subscriberClass);

        $events = $subscriber->getEvents();

        foreach ($events as $event => $method) {
            Fly::on($event, $subscriberClass . '@' . $method);
        }
    }

    function subscribers()
    {
        $subscribersFile = path('app') . '/config/subscribers.php';

        if (File::exists($subscribersFile)) {
            $subscribers = include $subscribersFile;

            foreach ($subscribers as $subscriberClass) {
                subscriber($subscriberClass);
            }
        }
    }

    function services()
    {
        listening('services.booting');

        $services = Registry::get('core.services', []);

        require_once __DIR__ . DS . 'serviceprovider.php';

        $servicesFile = path('app') . '/config/services.php';

        if (File::exists($servicesFile)) {
            $servicesFromConfig = include $servicesFile;
            $services           = array_merge($services, $servicesFromConfig);
        }

        foreach ($services as $serviceClass) {
            $service = maker($serviceClass);

            $service->register(context('app'));
        }
    }

    function ioc($class)
    {
        $app            = context('app');
        $service        = maker($class);

        callMethod($service, 'register', $app);
        $provides = callMethod($service, 'provides');

        foreach ($provides as $alias) {
            $app[$alias] = $service;
        }
    }

    function perms($path)
    {
        return substr(
            sprintf(
                '%o',
                fileperms($path)
            ),
            -4
        );
    }

    function timezones()
    {
        $zones  = [];
        $ts     = time();
        $actual = date_default_timezone_get();

        foreach (timezone_identifiers_list() as $k => $zone) {
            date_default_timezone_set($zone);

            $zones[$k]['zone']  = $zone;
            $zones[$k]['text']  = '(GMT' . date('P', $ts) . ') ' . $zones[$k]['zone'];
            $zones[$k]['order'] = str_replace(
                '-',
                '1',
                str_replace(
                    '+',
                    '2',
                    date('P', $ts)
                )
            ) . $zone;
        }

        usort($zones, function ($a, $b) {
            return strcmp(
                $a['order'],
                $b['order']
            );
        });

        date_default_timezone_set($actual);

        return $zones;
    }

    function register($class, callable $resolver)
    {
        return app()->bind($class, $resolver);
    }

    function old($key, $default = null)
    {
        return isAke($_REQUEST, $key, $default);
    }

    function csrf_token()
    {
        return session('csrf')->getToken();
    }

    function csrf_field($name = 'octo_token', $echo = true)
    {
        $tokenName = Config::get('token_name', $name);
        $token = csrf_make();
        $field = '<input type="hidden" name="' . $tokenName . '" id="' . $tokenName . '" value="' . $token . '">';

        if ($echo) {
            echo $field;
        } else {
            return $field;
        }
    }

    /**
     * @param string $tokenName
     * @param string $sessionKey
     *
     * @return string
     */
    function csrf()
    {
        $session        = instanciator()->singleton(FastSessionInterface::class);
        $middleware     = new Fastmiddlewarecsrf($session);
        $token          = $middleware->generateToken();
        $tokenName      = $middleware->getFormKey();

        return '<input type="hidden" name="' . $tokenName . '" id="' . $tokenName . '" value="' . $token . '">';
    }

    function csrf_make()
    {
        $token = token();

        session('csrf')
        ->setOldToken(
            session('csrf')
            ->getToken()
        )->setToken($token);

        return $token;
    }

    function csrf_match($name = 'octo_token')
    {
        $tokenName = Config::get('token_name', $name);

        return posted($tokenName) == session('csrf')->getToken();
    }

    function slug($title, $separator = '-')
    {
        return Strings::urlize($title, $separator);
    }

    function findMethod($method)
    {
        $reflFunc = new \ReflectionFunction($method);

        return $reflFunc->getFileName() . ':' . $reflFunc->getStartLine();
    }

    function app($make = null, $params = [])
    {
        if (empty($make)) {
            return getContainer();
        }

        return getContainer()->resolve($make, $params);
    }

    function injector($make, $params = [])
    {
        return maker($make, $params, false);
    }

    function sameClosures()
    {
        $closures = func_get_args();

        $hashes = [];

        foreach ($closures as $closure) {
            $ref = new ReflectionFunction($closure);

            $hashed = sha1($ref->getFileName() . $ref->getStartLine() . $ref->getEndLine());

            if (!empty($hashes)) {
                if (!in_array($hashed, $hashes)) {
                    return false;
                }
            } else {
                $hashes[] = $hashed;
            }
        }

        return true;
    }

    function next()
    {
        static $nextables = [];

        $args   = func_get_args();

        $name  = array_shift($args);
        $next  = array_shift($args);

        if (!isset($nextables[$name])) {
            $nextables[$name] = [];
        }

        $nextables[$name][] = call_user_func_array($next, $args);

        $return = o();

        $return->fn('next', function () {
            return call_user_func_array('\\Octo\\next', func_get_args());
        });

        return $return;
    }

    function container($concern, $callable = 'octodummy')
    {
        if ('octodummy' != $callable) {
            wire($concern, $callable);
        } else {
            $what = autowire($concern, true);

            if ($what && is_callable($what)) {
                if ($what instanceof \Closure) {
                    return call_user_func($what);
                }

                return maker($concern);
            }

            return $what;
        }
    }

    function caller(array $args)
    {
        return instanciator()->factory(...$args);
    }

    function foundry()
    {
        return instanciator()->factory(...func_get_args());
    }

    function single()
    {
        return instanciator()->singleton(...func_get_args());
    }

    function singleton()
    {
        return instanciator()->singleton(...func_get_args());
    }

    function wire($concern, $callable)
    {
        instanciator()->wire($concern, $callable);
    }

    function autowire($concern, $raw = false)
    {
        return instanciator()->autowire($concern, $raw);
    }

    /**
     * @return Fast
     */
    function getContainer(): Fast
    {
        return fast();
    }

    /**
     * @return \PDO
     */
    function getPdo()
    {
        return actual('pdo');
    }

    /**
     * @return Module
     */
    function getModule()
    {
        return actual('fast.module');
    }

    /**
     * @return FastPhpRenderer|FastTwigRenderer
     */
    function getRenderer()
    {
        return getContainer()->getRenderer();
    }

    /**
     * @return ServerRequestInterface
     */
    function getRequest()
    {
        return getContainer()->getRequest();
    }

    /**
     * @return Response
     */
    function getResponse()
    {
        return getContainer()->getResponse();
    }

    /**
     * @return Session
     */
    function getSession()
    {
        return getContainer()->getSession();
    }

    /**
     * @return Octalia|Orm
     */
    function getDb()
    {
        return orm();
    }

    /**
     * @return Cache
     */
    function getCache()
    {
        return app()->resolve(FastCache::class);
    }

    /**
     * @return FastLog
     *
     * @throws \ReflectionException
     */
    function getLog()
    {
        return app()->resolve(FastLog::class);
    }

    /**
     * @return FastRouteRouter
     */
    function getRouter()
    {
        return actual('fast.router');
    }

    /**
     * @param $target
     * @param $key
     * @param null $default
     *
     * @return array|mixed|null
     *
     * @throws \ReflectionException
     */
    function dataget($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        while (!is_null($segment = array_shift($key))) {
            if ($segment === '*') {
                if ($target instanceof Collection) {
                    $target = $target->all();
                } elseif (!is_array($target)) {
                    return value($default);
                }

                $result = Arrays::pluck($target, $key);

                return in_array('*', $key) ? Arrays::collapse($result) : $result;
            }

            if (Arrays::accessible($target) && Arrays::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }

    function superdi()
    {
        $superdi = Registry::get('core.superdi');

        if (!$superdi) {
            $superdi = o();

            $superdi->macro('registry', function ($key, $value = 'octodummy') use ($superdi) {
                if ($value === $superdi && func_num_args() === 2) {
                    $value = 'octodummy';
                }

                /* Polymorphism  */
                if (is_array($key)) {
                    foreach ($key as $k => $v) {
                        $superdi->dataset($k, $v);
                    }

                    return $superdi;
                }

                if ('octodummy' === $value) {
                    return $superdi->dataget($key);
                }

                return $superdi->dataset($key, $value);
            });

            $superdi->macro('dataget', function ($key, $default = null) use ($superdi) {
                if ($default === $superdi && func_num_args() === 2) {
                    $default = null;
                }

                return isAke(Registry::get('core.superdi.data', []), $key, $default);
            });

            $superdi->macro('datahas', function ($key) {
                return 'octodummy' !== isAke(Registry::get('core.superdi.data', []), $key, 'octodummy');
            });

            $superdi->macro('dataset', function ($key, $value) use ($superdi) {
                $data = Registry::get('core.superdi.data', []);

                $data[$key] = $value;

                Registry::set('core.superdi.data', $data);

                return $superdi;
            });

            $superdi->macro('datadel', function ($key) use ($superdi) {
                $data = Registry::get('core.superdi.data', []);

                unset($data[$key]);

                Registry::set('core.superdi.data', $data);

                return $superdi;
            });

            $superdi->macro('call', function () {
                $args = func_get_args();
                array_pop($args);

                return call_user_func_array('\\Octo\\callMethod', $args);
            });

            $superdi->macro('resolve', function () {
                $args = func_get_args();
                array_pop($args);

                return call_user_func_array('\\Octo\\foundry', $args);
            });

            $superdi->macro('factory', function () {
                $args = func_get_args();
                array_pop($args);

                return call_user_func_array('\\Octo\\foundry', $args);
            });

            $superdi->macro('singleton', function () {
                $args = func_get_args();
                array_pop($args);

                return call_user_func_array('\\Octo\\maker', $args);
            });

            $superdi->macro('register', function ($concern, $callable, $c = null) use ($superdi) {
                if ($c === $superdi && func_num_args() === 3) {
                    $c = null;
                }

                wire($concern, $callable);

                if ($c) {
                    $c->set($concern, true);
                }

                return $superdi;
            });

            $superdi->macro('define', function ($concern, $callable) use ($superdi) {
                return $superdi->register($concern, $callable);
            });

            $superdi->macro('mock', function () use ($superdi) {
                $args = func_get_args();
                array_pop($args);

                $mock = $superdi->resolve(...$args);

                return dyn($mock);
            });

            Registry::set('core.superdi', $superdi);
        }

        return $superdi;
    }

    function maker()
    {
        return instanciator()->make(...func_get_args());
    }

    /**
     * @return mixed|null
     * @throws \ReflectionException
     */
    function callMethod()
    {
        return instanciator()->call(...func_get_args());
    }

    /**
     * @return Listener
     */
    function getEvent()
    {
        return actual('fired.event');
    }

    function loadFiles($pattern)
    {
        $files = glob($pattern);

        foreach ($files as $file) {
            if (fnmatch('*.php', $file) || fnmatch('*.inc', $file)) {
                require_once $file;
            }
        }
    }

    function resolver($object)
    {
        return instanciator()->resolver($object);
    }

    function makeOnce(\Closure $callable)
    {
        $key        = lib('closures')->makeId($callable);
        $records    = Registry::get('make.once', []);
        $dummy      = sha1('octodummy' . date('dmY'));

        $result     = isAke($records, $key, $dummy);

        if ($result == $dummy) {
            $result         = $callable();
            $records[$key]  = $result;

            Registry::set('make.once', $records);
        }

        return $result;
    }

    function fromTs($timestamp)
    {
        return lib('time')->createFromTimestamp($timestamp);
    }

    function conf($key, $default = null)
    {
        return Config::get($key, appenv($key, $default));
    }

    function replace(array $replace, $buffer)
    {
        return preg_replace(
            array_keys($replace),
            array_values($replace),
            $buffer
        );
    }

    function appenv($key, $default = null)
    {
        $env = path('base') . '/.env';

        if (File::exists($env)) {
            $ini = makeOnce(
                function () use ($env) {
                    return parse_ini_file($env);
                }
            );

            return isAke($ini, $key, $default);
        }

        $env = getenv($key);

        if (false !== $env) {
            return $env;
        }

        return $default;
    }

    function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return value($default);
        }

        switch (Strings::lower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return;
        }

        if (strlen($value) > 1 && startsWith($value, '"') && endsWith($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @param string $haystack
     * @param $needles
     *
     * @return bool
     */
    function startsWith(string $haystack, $needles)
    {
        if (is_string($needles)) {
            return fnmatch($needles . '*', $haystack) ? true : false;
        }

        foreach ((array) $needles as $needle) {
            if ($needle !== '' && substr($haystack, 0, strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }

    function endsWith($str, $val)
    {
        return fnmatch('*' . $val, $str);
    }

    function test(callable $function, array $args = [])
    {
        return call_user_func_array($function, $args);
    }

    function gravatar($email, $size)
    {
        static $is_gravatar_loaded = false;
        static $gravatar;

        $key = 'gravatar.' . sha1(serialize(func_get_args()));

        return session('web')
        ->getOr($key, function () use ($email, $size, &$is_gravatar_loaded, &$gravatar) {
            $account = null;

            if (is_numeric($email)) {
                $account = em('systemAccount')->find((int) $email);

                if ($account) {
                    $email = $account->getEmail();
                }
            } else {
                $account = em('systemAccount')
                ->where('email', Strings::lower($email))
                ->first(true);
            }

            $default = URLSITE . 'assets/img/nobody.svg';

            if ($account) {
                $default = $account->getImg();

                if ($default) {
                    return $default;
                }
            }

            if (!$is_gravatar_loaded) {
                $is_gravatar_loaded = true;
                $gravatar = lib('gravatar');
                $gravatar->setDefaultImage(URLSITE . 'assets/img/nobody.svg');
            }

            $gravatar->setAvatarSize($size);
            $gravatar->enableSecureImages();

            return $gravatar->buildGravatarURL($email);
        });
    }

    function octo_role($user = null)
    {
        $user = empty($user) ? auth()->user() : $user;

        if ($role_id    = isAke($user, 'role_id', null)) {
            $role       = em('systemRole')->find((int) $role_id);

            return $role->name;
        }

        return null;
    }

    function octo_can($action)
    {
        if (is_admin()) {
            return true;
        }

        $account_id = auth()->user('id');

        if ($account_id) {
            $account = em('systemAccount')
            ->find((int) $account_id);

            if ($account) {
                $permission = em('systemPermission')
                ->where('action', $action)
                ->where('account_id', (int) $account_id)
                ->first();

                if ($permission) {
                    return true;
                }
            }
        }

        return false;
    }

    function octo_cannot($action)
    {
        return !octo_can($action);
    }

    function freeSpace($path = null)
    {
        $path = is_null($path) ? path('octalia') : $apth;

        if (is_dir($path)) {
            $key = 'freespace.' . str_replace(DS, '.', $path);
            $space = fmr()->get($key);

            if (!$space) {
                $space = disk_free_space($path);

                fmr()->set($key, $space, 3600);
            }

            return $space;
        }

        return 0;
    }

    function timeAgo(Time $dt, $time_zone, $lang_code = 'en')
    {
        $sec = $dt->diffInSeconds(Time::now($time_zone));
        Time::setLocale($lang_code);
        $string = Strings::ucfirst(Time::now($time_zone)->subSeconds($sec)->diffForHumans());

        return $string;
    }

    function ohash($str, $salt = null)
    {
        $salt = empty($salt) ? osalt() : $salt;
        $hash = hash(base64_encode($str . $salt));

        return strrev($hash);
    }

    function osalt()
    {
        return strrev(
            hash(
                base64_encode(
                    token()
                )
            )
        );
    }

    require_once __DIR__ . DS . 'traits.php';

    /* Classes */

    class Exception extends \Exception {}

    class FrontController extends Controller {}

    function db($db = null, $table = null)
    {
        return new Db($db, $table);
    }

    function mock($native, array $args = [])
    {
        return lib('mock', [$native, $args]);
    }

    function treatCast($row)
    {
        if (!empty($row) && Arrays::isAssoc($row)) {
            foreach ($row as $k => $v) {
                if (fnmatch('*_id', $k) && !empty($v)) {
                    if (is_numeric($v)) {
                        if (fnmatch('*.*', $v)) $row[$k] = (float) $v;
                        else $row[$k] = (int) $v;
                    } elseif (is_string($v)) {
                        if ($v == 'true') {
                            $row[$k] = true;
                        } elseif ($v == 'false') {
                            $row[$k] = false;
                        } elseif ($v == 'null') {
                            $row[$k] = null;
                        }
                    }
                }
            }
        }

        return $row;
    }

    function getQueryTime()
    {
        $collection = coll(Registry::get('octalia.queries', []));

        return $collection->sum('time');
    }

    function snake($value, $delimiter = '_')
    {
        return Strings::snake($value, $delimiter);
    }

    function dget($target, $key, $default = null, $sep = '.')
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode($sep, $key);

        while (($segment = array_shift($key)) !== null) {
            if ($segment === '*') {
                if ($target instanceof Collection) {
                    $target = $target->all();
                } elseif (!is_array($target)) {
                    return value($default);
                }

                $result = Arrays::pluck($target, $key);

                return in_array('*', $key) ? Arrays::collapse($result) : $result;
            }

            if (Arrays::accessible($target) && Arrays::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }

    function dset(&$target, $key, $value, $overwrite = true, $sep = '.')
    {
        $segments = is_array($key) ? $key : explode($sep, $key);

        if (($segment = array_shift($segments)) === '*') {
            if (!Arrays::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    dset($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (Arrays::accessible($target)) {
            if ($segments) {
                if (!Arrays::exists($target, $segment)) {
                    $target[$segment] = [];
                }

                dset($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !Arrays::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (! isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                dset($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                dset($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }

    function config($key, $value = 'octodummy')
    {
        /* Polymorphism  */
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Config::set($k, $v);
            }

            return true;
        }

        if ('octodummy' === $value) {
            return Config::get($key);
        }

        Config::set($key, $value);
    }

    function tpl($file, $args = [])
    {
        $content = null;

        if (file_exists($file) && is_readable($file)) {
            extract($args);

            ob_start();

            include $file;

            $content = ob_get_contents();

            ob_end_clean();
        }

        return $content;
    }

    function lang($k, $args =  [], $default = null)
    {
        $lng        = lng();
        $dictionary = null;
        $translated = Registry::get('translated.' . $lng, []);

        if (isset($translated[$k])) {
            return $translated[$k];
        }

        if (!Registry::has('lang.loaded.' . $lng)) {
            $file = path('translations') . DS . $lng . '.php';

            if (!file_exists($file)) {
                $lng    = Config::get('default.language', 'fr');
                $file   = path('translations') . DS . $lng . '.php';
            }

            if (!file_exists($file)) {
                return $default ? : $k;
            } else {
                $dictionary = include($file);
                Registry::set('lang.loaded.' . $lng, true);
                Registry::set('lang.dictionary.' . $lng, $dictionary);
            }
        }

        if (empty($dictionary)) {
            $dictionary = Registry::get('lang.dictionary.' . $lng, []);
        }

        $val = isAke($dictionary, $k, $default);

        if (!empty($args)) {
            foreach ($args as $key => $value) {
                $val = str_replace("%$key%", $value, $val);
            }
        }

        $translated[$k] = $val;

        Registry::get('translated.' . $lng, $translated);

        return $val;
    }

    function sqlite($db = null)
    {
        $db = is_null($db) ? Config::get('sqlite.db', path('storage') . DS . 'lite') : $db;

        return new Wrapper('sqlite:' . $db);
    }

    function octus($db = ':memory:')
    {
        $octus = new Octus;

        $octus->addConnection([
            'driver'    => 'sqlite',
            'database'  => $db,
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci'
        ]);

        $octus->setAsGlobal();
        $octus->bootEloquent();

        return $octus;
    }

    function eloquent(array $settings = [])
    {
        $eloquent = new Octus;

        $eloquent->addConnection($settings);

        $eloquent->setAsGlobal();
        $eloquent->bootEloquent();

        return $eloquent;
    }

    function octia($db = null)
    {
        $db = is_null($db) ? Config::get('sqlite.db', path('storage') . DS . 'lite') : $db;
        $octia = new Octia;

        $octia->addConnection([
            'driver'    => 'sqlite',
            'database'  => $db,
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci'
        ]);

        $octia->setAsGlobal();
        $octia->bootEloquent();
    }

    function promise()
    {
        $promise = o();

        $promise->macro('success', function (callable $success = null) {
            if (is_callable($success)) {
                return $success();
            }

            return true;
        });

        $promise->macro('error', function (callable $error = null) {
            if (is_callable($error)) {
                return $error();
            }

            return false;
        });

        return $promise;
    }

    function option($key, $value = 'octodummy')
    {
        $options = Registry::get('core.options', []);

        if ('octodummy' === $value) {
            return aget($options, $key);
        }

        aset($options, $key, $value);

        Registry::set('core.options', $options);
    }

    function options()
    {
        $options = o();

        $options->macro('set', function ($k, $v) use ($options) {
            em('systemOption')
                ->firstOrCreate(['name' => $k])
                ->setValue($v)
                ->save()
            ;

            return $options;
        });

        $options->macro('get', function ($k, $d = null) {
            $option = em('systemOption')
                ->where('name', $k)
                ->first(true)
            ;

            return $option ? $option->value : $d;
        });

        $options->macro('has', function ($k) {
            $option = em('systemOption')
                ->where('name', $k)
                ->first(true)
            ;

            return $option ? true : false;
        });

        $options->macro('delete', function ($k) {
            $option = em('systemOption')
                ->where('name', $k)
                ->first(true)
            ;

            return $option ? $option->delete() : false;
        });

        return $options;
    }

    function settings()
    {
        $settings = o();

        $settings->macro('set', function ($k, $v) use ($settings) {
            $k = sha1(forever() . $k);

            em('systemSetting')
                ->firstOrCreate(['name' => $k])
                ->setValue($v)
                ->save()
            ;

            return $settings;
        });

        $settings->macro('get', function ($k, $d = null) {
            $k = sha1(forever() . $k);

            $setting = em('systemSetting')
                ->where('name', $k)
                ->first(true)
            ;

            return $setting ? $setting->value : $d;
        });

        $settings->macro('has', function ($k) {
            $k = sha1(forever() . $k);

            $setting = em('systemSetting')
                ->where(['name', '=', $k])
                ->first(true)
            ;

            return $setting ? true : false;
        });

        $settings->macro('delete', function ($k) {
            $k = sha1(forever() . $k);

            $setting = em('systemSetting')
            ->where(['name', '=', $k])
            ->first(true);

            return $setting ? $setting->delete() : false;
        });

        return $settings;
    }

    function events()
    {
        $events = o();

        $events->macro('on', function ($event, callable $cb) use ($events) {
            $_events = Registry::get('core.events', []);

            $_events[$event] = $cb;

            Registry::set('core.events', $_events);

            return $events;
        });

        $events->macro('broadcast', function ($event, array $args = []) {
            $events = Registry::get('core.events', []);

            $event = isAke($events, $event, null);

            if (is_callable($event)) {
                return call_user_func_array($event, $args);
            }

            return null;
        });

        $events->macro('fire', function ($event, array $args = []) {
            $events = Registry::get('core.events', []);

            $event = isAke($events, $event, null);

            if (is_callable($event)) {
                return call_user_func_array($event, $args);
            }

            return null;
        });

        return $events;
    }

    function listen($event, array $args = [])
    {
        $events = Registry::get('core.events', []);

        $e = isAke($events, $event, null);

        if ($e) {
            return call_user_func_array($e, $args);
        }

        return null;
    }

    /**
     * @return null|Listener
     */
    function on()
    {
        return subscribe(...func_get_args());
    }

    function only()
    {
        /** @var array $keys */
        $keys = func_get_args();

        /** @var array $items */
        $items = array_shift($keys);

        return Arrays::only($items, $keys);
    }

    function except()
    {
        /** @var array $keys */
        $keys = func_get_args();

        /** @var array $items */
        $items = array_shift($keys);

        return Arrays::except($items, $keys);
    }

    function broadcast($event, callable $cb)
    {
        $events = Registry::get('core.events', []);

        $events[$event] = $cb;

        Registry::set('core.events', $events);
    }

    function isWindows()
    {
        $keys = [
            'CYGWIN_NT-5.1',
            'WIN32',
            'WINNT',
            'Windows'
        ];

        return array_key_exists(PHP_OS, $keys);
    }

    function staticEtag()
    {
        $controller = actual('controller.file');
        $tpl        = actual('view.file');

        if ($controller && $tpl) {
            if (File::exists($controller) && File::exists($tpl)) {
                $ages = [filemtime($controller), filemtime($tpl)];

                return etag(max($ages));
            }
        }
    }

    function etag($time)
    {
        $etag = 'W/"' . md5($time) . '"';

        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $time) . " GMT");
        header('Cache-Control: public, max-age=604800');
        header("Etag: $etag");

        if ((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $time) || (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $etag === trim($_SERVER['HTTP_IF_NONE_MATCH']))) {
            status(304);

            exit;
        }
    }

    function isFakeMail($mail)
    {
        $fakes = ['0815.ru0clickemail.com', '0wnd.net', '0wnd.org', '10minutemail.com', '20minutemail.com', '2prong.com', '3d-painting.com', '4warding.com', '4warding.net', '4warding.org', '9ox.net', 'a-bc.net', 'amilegit.com', 'anonbox.net', 'anonymbox.com', 'antichef.com', 'antichef.net', 'antispam.de', 'baxomale.ht.cx', 'beefmilk.com', 'binkmail.com', 'bio-muesli.net', 'bobmail.info', 'bodhi.lawlita.com', 'bofthew.com', 'brefmail.com', 'bsnow.net', 'bugmenot.com', 'bumpymail.com', 'casualdx.com', 'chogmail.com', 'cool.fr.nf', 'correo.blogos.net', 'cosmorph.com', 'courriel.fr.nf', 'courrieltemporaire.com', 'curryworld.de', 'cust.in', 'dacoolest.com', 'dandikmail.com', 'deadaddress.com', 'despam.it', 'devnullmail.com', 'dfgh.net', 'digitalsanctuary.com', 'discardmail.com', 'discardmail.de', 'disposableaddress.com', 'disposemail.com', 'dispostable.com', 'dm.w3internet.co.uk example.com', 'dodgeit.com', 'dodgit.com', 'dodgit.org', 'dontreg.com', 'dontsendmespam.de', 'dump-email.info', 'dumpyemail.com', 'e4ward.com', 'email60.com', 'emailias.com', 'emailinfive.com', 'emailmiser.com', 'emailtemporario.com.br', 'emailwarden.com', 'ephemail.net', 'explodemail.com', 'fakeinbox.com', 'fakeinformation.com', 'fastacura.com', 'filzmail.com', 'fizmail.com', 'frapmail.com', 'garliclife.com', 'get1mail.com', 'getonemail.com', 'getonemail.net', 'girlsundertheinfluence.com', 'gishpuppy.com', 'great-host.in', 'gsrv.co.uk', 'guerillamail.biz', 'guerillamail.com', 'guerillamail.net', 'guerillamail.org', 'guerrillamail.com', 'guerrillamailblock.com', 'haltospam.com', 'hotpop.com', 'ieatspam.eu', 'ieatspam.info', 'ihateyoualot.info', 'imails.info', 'inboxclean.com', 'inboxclean.org', 'incognitomail.com', 'incognitomail.net', 'ipoo.org', 'irish2me.com', 'jetable.com', 'jetable.fr.nf', 'jetable.net', 'jetable.org', 'junk1e.com', 'kaspop.com', 'kulturbetrieb.info', 'kurzepost.de', 'lifebyfood.com', 'link2mail.net', 'litedrop.com', 'lookugly.com', 'lopl.co.cc', 'lr78.com', 'maboard.com', 'mail.by', 'mail.mezimages.net', 'mail4trash.com', 'mailbidon.com', 'mailcatch.com', 'maileater.com', 'mailexpire.com', 'mailin8r.com', 'mailinator.com', 'mailinator.net', 'mailinator2.com', 'mailincubator.com', 'mailme.lv', 'mailnator.com', 'mailnull.com', 'mailzilla.org', 'mbx.cc', 'mega.zik.dj', 'meltmail.com', 'mierdamail.com', 'mintemail.com', 'moncourrier.fr.nf', 'monemail.fr.nf', 'monmail.fr.nf', 'mt2009.com', 'mx0.wwwnew.eu', 'mycleaninbox.net', 'mytrashmail.com', 'neverbox.com', 'nobulk.com', 'noclickemail.com', 'nogmailspam.info', 'nomail.xl.cx', 'nomail2me.com', 'no-spam.ws', 'nospam.ze.tc', 'nospam4.us', 'nospamfor.us', 'nowmymail.com', 'objectmail.com', 'obobbo.com', 'onewaymail.com', 'ordinaryamerican.net', 'owlpic.com', 'pookmail.com', 'proxymail.eu', 'punkass.com', 'putthisinyourspamdatabase.com', 'quickinbox.com', 'rcpt.at', 'recode.me', 'recursor.net', 'regbypass.comsafe-mail.net', 'safetymail.info', 'sandelf.de', 'saynotospams.com', 'selfdestructingmail.com', 'sendspamhere.com', 'shiftmail.com', '****mail.me', 'skeefmail.com', 'slopsbox.com', 'smellfear.com', 'snakemail.com', 'sneakemail.com', 'sofort-mail.de', 'sogetthis.com', 'soodonims.com', 'spam.la', 'spamavert.com', 'spambob.net', 'spambob.org', 'spambog.com', 'spambog.de', 'spambog.ru', 'spambox.info', 'spambox.us', 'spamcannon.com', 'spamcannon.net', 'spamcero.com', 'spamcorptastic.com', 'spamcowboy.com', 'spamcowboy.net', 'spamcowboy.org', 'spamday.com', 'spamex.com', 'spamfree24.com', 'spamfree24.de', 'spamfree24.eu', 'spamfree24.info', 'spamfree24.net', 'spamfree24.org', 'spamgourmet.com', 'spamgourmet.net', 'spamgourmet.org', 'spamherelots.com', 'spamhereplease.com', 'spamhole.com', 'spamify.com', 'spaminator.de', 'spamkill.info', 'spaml.com', 'spaml.de', 'spammotel.com', 'spamobox.com', 'spamspot.com', 'spamthis.co.uk', 'spamthisplease.com', 'speed.1s.fr', 'suremail.info', 'tempalias.com', 'tempemail.biz', 'tempemail.com', 'tempe-mail.com', 'tempemail.net', 'tempinbox.co.uk', 'tempinbox.com', 'tempomail.fr', 'temporaryemail.net', 'temporaryinbox.com', 'thankyou2010.com', 'thisisnotmyrealemail.com', 'throwawayemailaddress.com', 'tilien.com', 'tmailinator.com', 'tradermail.info', 'trash2009.com', 'trash-amil.com', 'trashmail.at', 'trash-mail.at', 'trashmail.com', 'trash-mail.com', 'trash-mail.de', 'trashmail.me', 'trashmail.net', 'trashymail.com', 'trashymail.net', 'tyldd.com', 'uggsrock.com', 'wegwerfmail.de', 'wegwerfmail.net', 'wegwerfmail.org', 'wh4f.org', 'whyspam.me', 'willselfdestruct.com', 'winemaven.info', 'wronghead.com', 'wuzupmail.net', 'xoxy.net', 'yogamaven.com', 'yopmail.com', 'yopmail.fr', 'yopmail.net', 'yuurok.com', 'zippymail.info', 'jnxjn.com', 'trashmailer.com', 'klzlk.com', 'nospamforus','kurzepost.de', 'objectmail.com', 'proxymail.eu', 'rcpt.at', 'trash-mail.at', 'trashmail.at', 'trashmail.me', 'trashmail.net', 'wegwerfmail.de', 'wegwerfmail.net', 'wegwerfmail.org', 'jetable', 'link2mail', 'meltmail', 'anonymbox', 'courrieltemporaire', 'sofimail', '0-mail.com', 'moburl.com', 'get2mail', 'yopmail', '10minutemail', 'mailinator', 'dispostable', 'spambog', 'mail-temporaire','filzmail','sharklasers.com', 'guerrillamailblock.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.biz', 'guerrillamail.org', 'guerrillamail.de','mailmetrash.com', 'thankyou2010.com', 'trash2009.com', 'mt2009.com', 'trashymail.com', 'mytrashmail.com','mailcatch.com','trillianpro.com','junk.','joliekemulder','lifebeginsatconception','beerolympics','smaakt.naar.gravel','q00.','dispostable','spamavert','mintemail','tempemail','spamfree24','spammotel','spam','mailnull','e4ward','spamgourmet','mytempemail','incognitomail','spamobox','mailinator.com', 'trashymail.com', 'mailexpire.com', 'temporaryinbox.com', 'MailEater.com', 'spambox.us', 'spamhole.com', 'spamhole.com', 'jetable.org', 'guerrillamail.com', 'uggsrock.com', '10minutemail.com', 'dontreg.com', 'tempomail.fr', 'TempEMail.net', 'spamfree24.org', 'spamfree24.de', 'spamfree24.info', 'spamfree24.com', 'spamfree.eu', 'kasmail.com', 'spammotel.com', 'greensloth.com', 'spamspot.com', 'spam.la', 'mjukglass.nu', 'slushmail.com', 'trash2009.com', 'mytrashmail.com', 'mailnull.com', 'jetable.org','10minutemail.com', '20minutemail.com', 'anonymbox.com', 'beefmilk.com', 'bsnow.net', 'bugmenot.com', 'deadaddress.com', 'despam.it', 'disposeamail.com', 'dodgeit.com', 'dodgit.com', 'dontreg.com', 'e4ward.com', 'emailias.com', 'emailwarden.com', 'enterto.com', 'gishpuppy.com', 'goemailgo.com', 'greensloth.com', 'guerrillamail.com', 'guerrillamailblock.com', 'hidzz.com', 'incognitomail.net ', 'jetable.org', 'kasmail.com', 'lifebyfood.com', 'lookugly.com', 'mailcatch.com', 'maileater.com', 'mailexpire.com', 'mailin8r.com', 'mailinator.com', 'mailinator.net', 'mailinator2.com', 'mailmoat.com', 'mailnull.com', 'meltmail.com', 'mintemail.com', 'mt2009.com', 'myspamless.com', 'mytempemail.com', 'mytrashmail.com', 'netmails.net', 'odaymail.com', 'pookmail.com', 'shieldedmail.com', 'smellfear.com', 'sneakemail.com', 'sogetthis.com', 'soodonims.com', 'spam.la', 'spamavert.com', 'spambox.us', 'spamcero.com', 'spamex.com', 'spamfree24.com', 'spamfree24.de', 'spamfree24.eu', 'spamfree24.info', 'spamfree24.net', 'spamfree24.org', 'spamgourmet.com', 'spamherelots.com', 'spamhole.com', 'spaml.com', 'spammotel.com', 'spamobox.com', 'spamspot.com', 'tempemail.net', 'tempinbox.com', 'tempomail.fr', 'temporaryinbox.com', 'tempymail.com', 'thisisnotmyrealemail.com', 'trash2009.com', 'trashmail.net', 'trashymail.com', 'tyldd.com', 'yopmail.com', 'zoemail.com','deadaddress','soodo','tempmail','uroid','spamevader','gishpuppy','privymail.de','trashmailer.com','fansworldwide.de','onewaymail.com', 'mobi.web.id', 'ag.us.to', 'gelitik.in', 'fixmail.tk'];

        foreach($fakes as $koMail) {
            list($dummy, $mailDomain) = explode('@', Strings::lower($mail));

            if (strcasecmp($mailDomain, $koMail) === 0){
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $text
     *
     * @return string
     */
    function code(string $text): string
    {
        return str_replace(['<', '>'], ['&lt;', '&gt;'], $text);
    }

    /**
     * @return FastEvent
     *
     * @throws \ReflectionException
     */
    function getEventManager()
    {
        return instanciator()->singleton(FastEvent::class);
    }

    /**
     * @param $value
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    function value($value)
    {
        return File::value($value);
    }

    function arrayed(array $array, $k = null, $d = null)
    {
        if (empty($k)) {
            return !empty($array);
        }

        if (is_array($k)) {
            $collection = [];

            foreach ($k as $key) {
                $collection[$key] = isAke($array, $key, $d);
            }

            return $collection;
        }

        return isAke($array, $k, $d);
    }

    function posted($k = null, $d = null)
    {
        return arrayed($_POST, $k, $d);
    }

    function requested($k = null, $d = null)
    {
        return arrayed($_REQUEST, $k, $d);
    }

    function getted($k = null, $d = null)
    {
        return arrayed($_GET, $k, $d);
    }

    function checkReferer()
    {
        return (
            isset($_SERVER['HTTP_REFERER'])
            && strstr($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false
        );
    }

    function getUrl()
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "" && $_SERVER['HTTPS'] != "off")
        ? "https"
        : "http";

        if ($_SERVER["SERVER_PORT"] != "80") {
            return $protocol . "://" . $_SERVER['HTTP_HOST']  . ':' . $_SERVER["SERVER_PORT"] . $_SERVER['REQUEST_URI'];
        }

        return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    function image($config = null)
    {
        $config = !is_array($config) ? ['driver' => 'imagick'] : $config;

        return instanciator()->make(\Intervention\Image\ImageManager::class, [$config]);
    }

    function imgResize($source_file, $dest_dir, $max_w, $max_h, $stamp_file = null)
    {
        $return = false;

        if (substr($dest_dir, 0,-1) != "/") $dest_dir .= "/";

        if (is_file($source_file) && is_dir($dest_dir)) {
            $pos = strrpos($source_file, "/");

            if ($pos !== false) $filename = substr($source_file, $pos+1);
            else $filename = $source_file;

            $im_size    = getimagesize($source_file);
            $w          = $im_size[0];
            $h          = $im_size[1];
            $im_type    = $im_size[2];

            if ($h < $max_h) {
                if ($w < $max_w) {
                    $new_w = $w;
                    $new_h = $h;
                } else {
                    $new_w = $max_w;
                    $new_h = round($max_w*$h/$w);
                }
            } else {
                $new_w = $max_w;
                $new_h = round($max_w*$h/$w);

                if ($new_h > $max_h) {
                    $new_h = $max_h;
                    $new_w = round($max_h * $w / $h);
                }
            }

            if (!is_null($stamp_file) && is_file($stamp_file)) {
                $margin_right = 10;
                $margin_bottom = 10;

                $stamp_size = getimagesize($stamp_file);
                $sw         = $stamp_size[0];
                $sh         = $stamp_size[1];
                $s_type     = $stamp_size[2];

                $new_sw = round($sw * $new_w / MAX_W_BIG);
                $new_sh = $new_sw * $sh / $sw;

                switch($s_type) {
                    case IMAGETYPE_JPEG : $tmp_stamp = imagecreatefromjpeg($stamp_file); break;
                    case IMAGETYPE_PNG : $tmp_stamp = imagecreatefrompng($stamp_file); break;
                    case IMAGETYPE_GIF : $tmp_stamp = imagecreatefromgif($stamp_file); break;
                }

                $new_stamp = imagecreatetruecolor($new_sw, $new_sh);

                if ($s_type == IMAGETYPE_PNG) {
                    imagesavealpha($new_stamp, true);

                    $trans_colour = imagecolorallocatealpha($new_stamp, 0, 0, 0, 127);

                    imagefill($new_stamp, 0, 0, $trans_colour);

                    $im = imagecreatetruecolor($new_sw, $new_sh);
                    $bg = imagecolorallocate($im, 0, 0, 0);

                    imagecolortransparent($new_stamp, $bg);
                    imagedestroy($im);
                }

                imagecopyresampled($new_stamp, $tmp_stamp, 0, 0, 0, 0, $new_sw, $new_sh, $sw, $sh);
            }

            switch($im_type) {
                case IMAGETYPE_JPEG : $tmp_image = imagecreatefromjpeg($source_file); break;
                case IMAGETYPE_PNG : $tmp_image = imagecreatefrompng($source_file); break;
                case IMAGETYPE_GIF : $tmp_image = imagecreatefromgif($source_file); break;
            }

            $new_image = imagecreatetruecolor($new_w, $new_h);

            if ($im_type == IMAGETYPE_PNG) {
                imagesavealpha($new_image, true);

                $trans_colour = imagecolorallocatealpha($new_image, 0, 0, 0, 127);

                imagefill($new_image, 0, 0, $trans_colour);

                $im = imagecreatetruecolor($new_w, $new_h);
                $bg = imagecolorallocate($im, 0, 0, 0);

                imagecolortransparent($new_image, $bg);
                imagedestroy($im);
            }

            if (imagecopyresampled($new_image, $tmp_image, 0, 0, 0, 0, $new_w, $new_h, $w, $h)) {
                if (isset($tmp_stamp)) imagecopy($new_image, $new_stamp, $new_w - $new_sw - $margin_right, $new_h - $new_sh - $margin_bottom, 0, 0, $new_sw, $new_sh);

                switch($im_type) {
                    case IMAGETYPE_JPEG : imagejpeg($new_image, $dest_dir . $filename, 90); break;
                    case IMAGETYPE_PNG : imagepng($new_image, $dest_dir . $filename, 9); break;
                    case IMAGETYPE_GIF : imagegif($new_image, $dest_dir . $filename); break;
                }

                if(chmod($dest_dir . $filename, 0664)) $return = $dest_dir . $filename;
            }

            if (isset($new_image)) imagedestroy($new_image);
            if (isset($tmp_image)) imagedestroy($tmp_image);
            if (isset($new_stamp)) imagedestroy($new_stamp);
            if (isset($tmp_stamp)) imagedestroy($tmp_stamp);
        }

        return $return;
    }

    function qr($text, $size = 250, $url = true)
    {
        $size = 500 < $size ? 500 : $size;

        $img = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl=" . urlencode($text) . "&choe=UTF-8";

        if ($url) {
            return $img;
        } else {
            $data = file_get_contents($img);

            if (!headers_sent()) {
                header('Content-type: image/png');

                die($data);
            } else {
                $storage = path('public') . DS . 'qr';
                $png = DS . sha1(serialize(func_get_args())) . '.png';

                if (!is_dir($storage)) {
                    Dir::mkdir($storage);
                }

                $storage .= $png;

                File::put($storage, $data);

                return WEBROOT . DS . 'qr' . $png;
            }
        }
    }

    function array_shift(&$array, $d = null)
    {
        $val = \array_shift($array);

        return $val ?: $d;
    }

    function array_pop(&$array, $d = null)
    {
        $val = \array_pop($array);

        return $val ?: $d;
    }

    function toInt($number)
    {
        settype($number, 'integer');

        return (int) $number;
    }

    function toFloat($number)
    {
        settype($number, 'float');

        return (float) $number;
    }

    function toString($string)
    {
        settype($string, 'string');

        return (string) $string;
    }

    function helper($lib, $args = [], $dir = null)
    {
        $dir    = empty($dir) ? path('app') . DS . 'helpers' : $dir;
        $class  = '\\Octo\\' . Strings::camelize($lib) . 'Helper';

        if (!class_exists($class)) {
            $file = $dir . DS . Strings::lower($lib) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        }

        return maker($class, $args);
    }

    function appli($lib, $args = [], $dir = null)
    {
        $dir    = empty($dir) ? path('app') . DS . 'lib' : $dir;
        $class  = '\\Octo\\' . Strings::camelize($lib) . 'App';

        if (!class_exists($class)) {
            $file = $dir . DS . Strings::lower($lib) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        }

        return maker($class, $args);
    }

    /**
     * @param $type
     * @param $message
     * @param string $extends
     */
    function exception($type, $message, $extends = '\\Exception')
    {
        $what   = ucfirst(Strings::camelize($type . '_exception'));
        $class  = 'Octo\\' . $what;

        if (!class_exists($class)) {
            $code = 'namespace Octo; class ' . $what . ' extends ' . $extends . ' {}';
            eval($code);
        }

        $trace = debug_backtrace();

        Registry::set('BACKTRACE', $trace[0]);

        throw new $class($message);
    }

    function compacto()
    {
        $bt     = debug_backtrace();
        $call   = current($bt);
        $code   = explode("\n", File::read($call['file']));
        $line   = $code[$call["line"] - 1];
        $keys   = explode(
            ',',
            str_replace(
                [' ', '$'],
                '',
                cut(
                    'compacto(',
                    ')',
                    $line
                )
            )
        );

        $assoc = [];

        $args = func_get_args();

        foreach ($keys as $key) {
            $assoc[$key] = array_shift($args);
        }

        return o($assoc);
    }

    function instance($make = null, $params = [])
    {
        return app($make, $params);
    }

    function singler($make, $params = [])
    {
        return App::getInstance()->singleton($make, $params);
    }

    function bind($class, $resolver = null, $shared = false)
    {
        return App::getInstance()->bind($class, $resolver, $shared);
    }

    function core($alias, callable $resolver = null, $params = [], $singleton = true)
    {
        $instances = Registry::get('core.instances', []);

        $make = isAke($instances, $alias, null);

        if ($make) {
            if (is_callable($make)) {
                return call_user_func_array($make, $params);
            } else {
                return $make;
            }
        } else {
            if (is_string($resolver)) {
                $instance = instance($resolver, $params);
            } else {
                if ($singleton) {
                    $instance = call_user_func_array($resolver, $params);
                } else {
                    $instance = $resolver;
                }
            }

            $instances[$alias] = $instance;

            Registry::set('core.instances', $instances);

            return $instance;
        }

        return false;
    }

    function macro($metas = [])
    {
        return new Macro($metas);
    }

    function get_request($url, $username = null, $password = null, $extra_headers = null)
    {
        $ch = curl_init($url);

        return curl_request($ch, $username, $password, $extra_headers);
    }

    function post_request($url, $data = null, $username = null, $password = null, $extra_headers = null)
    {
        $ch = curl_init($url);

        if (is_array($data)) {
            $data = http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        return curl_request($ch, $username, $password, $extra_headers);
    }

    function curl_request($ch, $username, $password, $extra_headers)
    {
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($username !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        }

        if (!empty($extra_headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $extra_headers);
        }

        $response   = curl_exec($ch);
        $error      = curl_error($ch);

        if (!empty($error)) {
            exception('curl', $error);
        }

        curl_close($ch);

        return $response;
    }

    function staticFacade($from, $to)
    {
        if (!class_exists('Octo\\' . $to)) {
            $code = 'namespace Octo; class ' . $to . ' {public static function __callStatic($method, $args)
        {
            return call_user_func_array([(new ' . $from . '), $method], $args);
        }}';
            eval($code);
        }
    }

    function bag($bag)
    {
        if (!class_exists('Octo\\' . $bag)) {
            $code = 'namespace Octo; class ' . $bag . ' {public static function __callStatic($method, $args)
        {
            return call_user_func_array([lib("now", ["bag_' . Strings::uncamelize($bag) . '"]), $method], $args);
        }}';
            eval($code);
        }

        $className = 'Octo\\' . $bag;

        return new $className;
    }

    function shell()
    {
        foreach (get_defined_functions() as $group) {
            foreach ($group as $function) {
                if (fnmatch('Octo*', $function)) {
                    $native = str_replace_first('octo', '', $function);

                    if (!function_exists($native)) {
                        $fn = str_replace('\\', '', $native);

                        $code = 'namespace {
                            function '. $fn .' ()
                            {
                                return call_user_func_array("' . $function . '", func_get_args());
                            };
                        };';

                        eval($code);
                    }
                }
            }
        }

        spl_autoload_register(function ($class) {
            if (!class_exists($class) && class_exists('Octo\\' . $class)) {
                $ref = new Reflector('Octo\\' . $class);
                $fileName = $ref->getFileName();

                $classCode = File::read($fileName);
                list($dummy, $classCode) = explode('namespace Octo;', $classCode, 2);

                $code = "namespace { $classCode }";

                eval($code);
            }
        });
    }

    function extender($from, $to)
    {
        foreach (get_defined_functions() as $group) {
            foreach ($group as $function) {
                if (fnmatch($to . '*', $function)) {
                    $native = str_replace_first($to, '', $function);

                    if (!function_exists($native)) {
                        $fn = str_replace('\\', '', $native);

                        $code = 'namespace ' . $from . ' {
                            function '. $fn .' ()
                            {
                                return call_user_func_array("' . $function . '", func_get_args());
                            };
                        };';

                        eval($code);
                    }
                }
            }
        }

        spl_autoload_register(function ($class) use ($from, $to) {
            if (!class_exists($class) && class_exists($to . '\\' . $class)) {
                $ref = new Reflector($to . '\\' . $class);
                $fileName = $ref->getFileName();

                $classCode = File::read($fileName);
                list($dummy, $classCode) = explode('namespace ' . $to . ';', $classCode, 2);

                $code = "namespace $from { $classCode };";

                eval($code);
            }
        });
    }

    function reallyInt($int)
    {
        if (is_numeric($int)) {
            $int += 0;

            return is_int($int);
        }

        return false;
    }

    function dbJson($file)
    {
        if (File::exists($file)) {
            $file = File::read($file);
        }

        return coll(json_decode($file, true));
    }

    function dbCsv($file)
    {
        if (File::exists($file)) {
            $rows = array_map('str_getcsv', file($file));

            array_walk($rows, function(&$a) use ($rows) {
                $a = array_combine($rows[0], $a);
            });

            array_shift($rows);

            return coll($rows);
        }

        return coll([]);
    }

    function b64_encode($str)
    {
        return strtr(base64_encode($str), '+/=', '-_,');
    }

    function b64_decode($str)
    {
        return base64_decode(strtr($str, '-_,', '+/='));
    }

    function zip($source, $destination, $include_dir = false)
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        if (file_exists($destination)) {
            File::delete($destination);
        }

        $zip = new \ZipArchive();

        if (!$zip->open($destination, \ZipArchive::CREATE)) {
            return false;
        }

        if (is_dir($source) === true) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);

                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) continue;

                if (is_dir($file) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                } elseif (is_file($file) === true) {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        } elseif (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }

        return $zip->close();
    }

    function shorten($string = null, $char = 80)
    {
        if (empty($string)) {
            return $string;
        }

        $st = strip_tags($string);

        if (strlen($st) < $char) {
            $string = preg_replace('/\s\s+/', ' ', $st);
            $string = ltrim(rtrim($string));

            return $string;
        } else {
            $string = preg_replace('/\s\s+/', ' ', $st);
            $string = ltrim(rtrim($string));
            $string = substr($string, 0, $char);
            $string = substr($string, 0, strrpos($string, ' '));

            return $string;
        }
    }

    function substrToString($needle, $haystack)
    {
        if (contains($needle, $haystack)) {
            return substr($haystack, 0, strpos($haystack, $needle));
        }

        return $haystack;
    }

    function mergeObjects($obj1, $obj2)
    {
        return (object) array_merge((array) $obj1, (array) $obj2);
    }

    function truncate($string, $limit = 150, $breaker = false, $break = " ", $end = "&hellip;")
    {
        if (mb_strlen($string) <= $limit) {
            return $string;
        }

        if ($breaker && false !== ($breakpoint = mb_strpos($string, $break, $limit))) {
            if ($breakpoint < mb_strlen($string) - 1) {
                $string = mb_substr($string, 0, $breakpoint) . $break;
            }
        } else {
            $string = mb_substr($string, 0, $limit) . $end;
        }

        return $string;
    }

    function timer($cmd = 'start', $ns = 'core')
    {
        $timers     = Registry::get('timers', []);
        $pauses     = Registry::get('pauses', []);
        $resumes    = Registry::get('resumes', []);

        $return = null;

        switch ($cmd) {
            case 'start':
                $timers[$ns] = Timer::getMS();
                break;
            case 'pause':
                $pauses[$ns] = Timer::getMS();
                unset($resumes[$ns]);
                break;
            case 'resume':
                if (isset($pauses[$ns])) {
                    $resumes[$ns] = Timer::getMS();
                    unset($pauses[$ns]);
                }

                break;
            case 'get':
                $return = isAke($timers, $ns, 0);

                if (0 < $return) {
                    $pause  = isAke($pauses, $ns, 0);
                    $resume = isAke($resumes, $ns, 0);

                    $timeToRemove = $resume - $pause;

                    unset($pauses[$ns]);
                    unset($resumes[$ns]);

                    $return = Timer::getMS() - $return - $timeToRemove;
                }

                break;
            case 'stop':
                $return = isAke($timers, $ns, 0);

                if (0 < $return) {
                    $pause  = isAke($pauses, $ns, 0);
                    $resume = isAke($resumes, $ns, 0);

                    $timeToRemove = $resume - $pause;

                    $return = Timer::getMS() - $return;
                }

                unset($timers[$ns]);
                unset($pauses[$ns]);
                unset($resumes[$ns]);

                break;
        }

        Registry::set('timers', $timers);
        Registry::set('pauses', $pauses);
        Registry::set('resumes', $resumes);

        return $return ? round($return / Config::get('timer.format', 1000000), 8) : $return;
    }

    function back($url = null)
    {
        $back = o();
        $url = empty($url) ? isAke($_SERVER, 'HTTP_REFERER', URLSITE) : $url;

        $back->macro('with', function (array $meta = []) use ($back) {
            foreach ($meta as $k => $v) {
                session()->once($k, $v);
            }

            return $back;
        });

        $back->macro('go', function () use ($url) {
            status(302);
            header('Location: ' . $url);

            exit;
        });

        return $back;
    }

    function redirect($url)
    {
        $url = !fnmatch('*://*', $url) ? WEBROOT . '/' . trim($url, '/') : $url;

        return back($url);
    }

    function flasher()
    {
        $flasher = o();

        $flasher->macro('success', function ($v = 'octodummy') {
            if ($v instanceof Objet) {
                $v = 'octodummy';
            }

            $key = "success";
            return flash($key, $v);
        });

        $flasher->macro('error', function ($v = 'octodummy') {
            if ($v instanceof Objet) {
                $v = 'octodummy';
            }

            $key = "error";
            return flash($key, $v);
        });

        $flasher->macro('hasSuccess', function () {
            return hasflash('error');
        });

        $flasher->macro('hasError', function () {
            return hasflash('error');
        });

        return $flasher;
    }

    function hasflash($k)
    {
        $session = session('flash');

        $getter = getter($k);

        $value = $session->$getter('octodummy');

        return 'octodummy' !== $value;
    }

    function flash($k, $v = 'octodummy')
    {
        $session = session('flash');

        if ($v != 'octodummy') {
            $setter = setter($k);

            return $session->$setter($v);
        }

        $getter = getter($k);

        $value = $session->$getter();

        $session->erase($k);

        return $value;
    }

    function cufa()
    {
        $args = func_get_args();

        $callable = array_shift($args);

        return call(
            $callable,
            $args
        );
    }

    function closure()
    {
        $args       = func_get_args();
        $callable   = array_shift($args);

        $fn = o([
            'callable' => $callable,
            'args' => $args
        ]);

        $fn->macro('call', function () use ($fn) {
            $nextArgs   = func_get_args();
            $next       = array_shift($nextArgs);
            $res        = call_user_func_array(
                $fn->getCallable(),
                $fn->getArgs()
            );

            if (is_callable($next)) {
                $res = call_user_func_array(
                    $next,
                    array_merge([$res], $nextArgs)
                );
            }

            return $res;
        });

        return $fn;
    }

    /**
     * @param mixed $object
     *
     * @return Lazy
     */
    function lazy($object)
    {
        if (!is_callable($object)) {
            $object = function () use ($object) {
                return $object;
            };
        }

        return new Lazy($object);
    }

    /**
     * @param $callback
     * @param array $args
     *
     * @return mixed
     */
    function call($callback, array $args)
    {
        if (is_string($callback) && fnmatch('*::*', $callback)) {
            $callback = explode('::', $callback, 2);
        }

        if (is_array($callback) && isset($callback[1]) && is_object($callback[0])) {
            if (!empty(count($args))) {
                $args   = array_values($args);
            }

            list($instance, $method) = $callback;

            return $instance->{$method}(...$args);
        } elseif (is_array($callback) && isset($callback[1]) && is_string($callback[0])) {
            list($class, $method) = $callback;
            $class = '\\' . ltrim($class, '\\');

            return $class::{$method}(...$args);
        } elseif (is_string($callback) || $callback instanceOf \Closure) {
            is_string($callback) && $callback = ltrim($callback, '\\');
        }

        return $callback(...$args);
    }

    /**
     * @return array
     */
    function recursive_merge()
    {
        $tabs = func_get_args();

        $tab1 = array_shift($tabs);
        $tab2 = array_shift($tabs);

        if (arrayable($tab1)) $tab1 = $tab1->toArray();
        if (arrayable($tab2)) $tab2 = $tab2->toArray();

        $merged = array_unique(
            array_merge(
                $tab1,
                $tab2
            )
        );

        if (!empty($tabs)) {
            $params = array_merge([$merged], $tabs);

            return recursive_merge(...$params);
        }

        return $merged;
    }

    /**
     * @param object|string $class
     * @param array $data
     *
     * @return object
     */
    function hydrator($class, array $data)
    {
        $instance = !is_object($class) ? foundry($class) : $class;

        $methods = get_class_methods($instance);

        foreach ($data as $key => $value) {
            $method = setter($key);

            if (in_array($method, $methods)) {
                $instance->{$method}($value);
            } else {
                $instance->{$key} = $value;
            }
        }

        return $instance;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    function hash($str)
    {
        if (function_exists('hash_algos')) {
            foreach (['sha512', 'sha384', 'sha256', 'sha224', 'sha1', 'md5'] as $hash) {
                if (in_array($hash, hash_algos())) {
                    return \hash($hash, $str);
                }
            }
        }

        return sha1($str);
    }

    /**
     * @param string $value
     * @param int $flags
     * @param string $encoding
     *
     * @return array|\ArrayAccess|\Iterator|string
     */
    function e($value, $flags = ENT_QUOTES, $encoding = 'UTF-8')
    {
        static $cleaned = [];

        if (is_bool($value) || is_int($value) || is_float($value) || in_array($value, $cleaned, true)) {
            return $value;
        }

        if (is_string($value)) {
            $value = htmlentities($value, $flags, $encoding, false);
        } elseif (is_array($value) || ($value instanceof \Iterator && $value instanceof \ArrayAccess)) {
            is_object($value) && $cleaned[] = $value;

            foreach ($value as $k => $v) {
                $value[$k] = e($v, $flags, $encoding);
            }
        } elseif ($value instanceof \Iterator || get_class($value) == 'stdClass') {
            $cleaned[] = $value;

            foreach ($value as $k => $v) {
                $value->{$k} = e($v, $flags, $encoding);
            }
        }

        return $value;
    }

    /**
     * @param string $type
     * @param array $data
     *
     * @return mixed
     */
    function model($type, array $data = [])
    {
        $class = 'Octo\\' . Strings::camelize($type);

        if (!class_exists($class)) {
            $classCode = File::read(__DIR__ . DS . 'objet.php');
            list($dummy, $classCode) = explode('namespace ' . __NAMESPACE__ . ';', $classCode, 2);

            $classCode = str_replace_first('class Objet', 'class ' . Strings::camelize($type), $classCode);

            $code = "namespace " . __NAMESPACE__ . " { $classCode };";

            eval($code);
        }

        return new $class($data);
    }

    /**
     * @param string $name
     * @param array $args
     *
     * @return bool
     */
    function isRoute(string $name, array $args = [])
    {
        return Routes::isRoute($name, $args);
    }

    /**
     * @param string $routeName
     * @param array $params
     *
     * @return string
     */
    function urlFor(string $routeName, array $params = []): string
    {
        /**
         * @var $fastRouter FastRouteRouter
         */
        $fastRouter = getContainer()->define("router");

        return $fastRouter->generateUri($routeName, $params);
    }

    function url($name, array $args = [])
    {
        $url = Routes::url($name, $args);

        if (!$url) {
            $url = $name;
        }

        return URLSITE . '/' . trim($url, '/');
    }

    function forward($what, $method = 'get', callable $before = null, callable $after = null)
    {
        if (fnmatch('*.*', $what)) {
            list($controllerName, $action) = explode('.', $what, 2);
        }

        if (fnmatch('*@*', $what)) {
            list($controllerName, $action) = explode('@', $what, 2);
        }

        if (fnmatch('*#*', $what)) {
            list($controllerName, $action) = explode('#', $what, 2);
        }

        if (fnmatch('*:*', $what)) {
            list($controllerName, $action) = explode(':', $what, 2);
        }

        $actualController = actual('controller');

        if (!empty($actualController)) {
            if (is_object($actualController)) {
                $classC     = get_class($actualController);
                $tab        = explode('\\', $classC);
                $namespace  = array_shift($tab);

                $controllerFile = path('app') . DS . 'controllers' . DS . $controllerName . '.php';

                if (!is_file($controllerFile)) {
                    exception('Controller', 'The controller ' . $controllerName . ' does not exist.');
                }

                require_once $controllerFile;

                $class = '\\' . $namespace . '\\App' . ucfirst(Strings::lower($controllerName)) . 'Controller';

                $actions = get_class_methods($class);

                $a = $action;

                $action = Strings::lower($method) . ucfirst(
                    Strings::camelize(
                        strtolower($action)
                    )
                );

                $controller         = new $class;
                $controller->_name  = $controllerName;
                $controller->action = $a;

                if (!in_array($action, $actions)) {
                    exception('Controller', 'The action ' . $action . ' does not exist.');
                }

                if (is_callable($before)) {
                    call($before, [$actualController, $controller]);
                }

                $return = $controller->$action();

                if ($return instanceof Object) {
                    return $return->go();
                }

                Router::render(
                    $controller,
                    Registry::get('cb.404')
                );
            }
        }
    }

    function reflectClosure(callable $closure)
    {
        $reflection = dyn(new \ReflectionFunction($closure));

        $reflection->macro('getCode', function () use ($reflection) {
            $file   = $reflection->getFileName();
            $start  = $reflection->getStartLine();
            $end    = $reflection->getEndLine();

            $lines = file($file);

            $code = [];

            for ($i = $start; $i < $end - 1; $i++) {
                $seg = $lines[$i];

                if ("\n" != $seg && "\r" != $seg && "\r\n" != $seg) $code[] = $seg;
            }

            return $code;
        });

        return $reflection;
    }

    function serializeClosure(callable $closure)
    {
        $reflection = reflectClosure($closure);
        $arguments  = $reflection->getParameters();

        $params = [];

        foreach ($arguments as $arg) {
            $arg        = (array) $arg;
            $key        = current(array_values($arg));
            $params[]   = '$' . $key;
        }

        $toSerialize = [
            'namespace' => $reflection->getNamespaceName(),
            'code'      => $reflection->getCode(),
            'params'    => $params
        ];

        return serialize($toSerialize);
    }

    function unserializeClosure($string)
    {
        $closure = null;

        $infos = unserialize($string);

        $eval = 'namespace '. isAke($infos, 'namespace', __NAMESPACE__) .' {' . "\n";
        $eval .= 'return $closure = function (' . implode(', ', isAke($infos, 'params', [])) . ') {' . "\n";
        $eval .= implode("\n", isAke($infos, 'code', []));
        $eval .= '};};';

        eval($eval);

        return $closure;
    }

    function after(callable $closure, array $args = [], $when = null)
    {
        $when = empty($when) ? now() : $when;

        $afters = Registry::get('afters', []);

        $after = ['callback' => serializeClosure($closure), 'params' => $args, 'when' => $when];

        $afters[] = $after;

        Registry::set('afters', $afters);
    }

    function now()
    {
        return time();
    }

    function secondsTo($expression)
    {
        $now = now();

        $to = strtotime($expression);

        if ($now >= $to) {
            exception('argument', 'Please provide an expression in future.');
        }

        return $to - $now;
    }

    function secondsFrom($expression)
    {
        $now = now();

        $from = strtotime($expression);

        if ($now <= $from) {
            exception('argument', 'Please provide an expression in past.');
        }

        return $now - $from;
    }

    function waitUntil($expression = 'next minute', callable $callback = null, array $args = [])
    {
        $seconds = !is_numeric($expression) ? secondsTo($expression) : $expression + 0;

        sleep($seconds);

        return is_callable($callback) ? call($callback, $args) : true;
    }

    function toNumber($number)
    {
        if (is_numeric($number)) {
            return $number + 0;
        }

        return 0;
    }

    function at($expression, callable $closure, array $args = [])
    {
        $timestamp  = !is_numeric($expression) ? strtotime($expression) : $expression + 0;

        $seconds = date('s', $timestamp);

        $timestamp += 60 - toNumber($seconds);

        after($closure, $args, $timestamp);
    }

    function cron($when, callable $callback, array $args = [])
    {
        if (is_string($when)) {
            $cron   = Cron\CronExpression::factory($when);
            $next   = $cron->getNextRunDate()->getTimestamp();
            $future = $cron->getNextRunDate('now', 1)->getTimestamp();
        } elseif (is_array($when)) {
            $next   = current($when) + 0;
            $future = end($when) + $next;
        }

        $closure = function ($callback, $args, $next, $future) {
            $cb = unserializeClosure($callback);

            call($cb, $args);

            cron([$future, ($future - $next)], $cb, $args);
        };

        after(
            $closure,
            [serializeClosure($callback), $args, $next, $future],
            $next
        );
    }

    function with()
    {
        $args = func_get_args();

        $callback = array_shift($args);

        if (is_callable($callback)) {
            return $callback(...$args);
        }

        return $callback;
    }

    function withoutError(callable $callback, array $args = [])
    {
        set_error_handler(function () {});

        $result = call($callback, $args);

        restore_error_handler();

        return $result;
    }

    /**
     * @param $trait
     *
     * @return array
     */
    function allTraits($trait)
    {
        $traits = class_uses($trait);

        foreach ($traits as $trait) {
            $traits += allTraits($trait);
        }

        return $traits;
    }

    function allClasses($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_merge([$class => $class], class_parents($class)) as $class) {
            $results += allTraits($class);
        }

        return array_unique($results);
    }

    /**
     * @param string $lng
     *
     * @return \Faker\Generator
     */
    function faker($lng = 'fr_FR')
    {
        return \Faker\Factory::create(
            Config::get(
                'faker.provider',
                $lng
            )
        );
    }

    function providers($alias, callable $resolver = null)
    {
        return app()->provider($alias, $resolver);
    }

    function serviceProvider($alias, callable $resolver = null)
    {
        $app = app();

        $res = call_user_func_array($resolver, [$alias, $app]);

        if ($res instanceof \Closure) {
            return $app->provider($alias, $res);
        }

        return $res;
    }

    function start_session($ns = 'web')
    {
        if (!session_id() && !headers_sent() && !isset($_SESSION)) {
            session_start();
        }

        return session($ns);
    }

    function lower($str)
    {
        return Strings::lower($str);
    }

    function upper($str)
    {
        return Strings::upper($str);
    }

    function polymorph(Objet $object)
    {
        return em(
            $object->db(),
            $object->polymorph_type
        )->find((int) $object->polymorph_id);
    }

    function polymorphs(Objet $object, $parent)
    {
        return engine(
            $object->db(),
            $parent
        )->where(
            'polymorph_type',
            $object->table()
        )->where(
            'polymorph_id',
            (int) $object->id
        );
    }

    /**
     * @param $timestamp
     * @param null $tz
     *
     * @return mixed
     */
    function tsToTime($timestamp, $tz = null)
    {
        return Date::createFromTimestamp($timestamp, $tz);
    }

    function pois(string $address, $type = 'restaurant')
    {
        return lib('geo')->placesByAddress($address, $type);
    }

    /**
     * @param string $className
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    function fire(string $className)
    {
        return getEventManager()->fire($className);
    }

    /**
     * @return mixed|null|FastEvent
     *
     * @throws \ReflectionException
     */
    function event()
    {
        $params     = func_get_args();
        $className  = array_shift($params);

        $manager = getEventManager();

        if (is_null($className)) {
            return $manager;
        }

        if (is_object($className)) {
            $instance = $className;
        } elseif (is_string($className)) {
            $instance = instanciator()->make($className, $params, false);
        }

        try {
            if (method_exists($instance, 'fire')) {
                $args = array_merge([$instance, 'fire'], $params);
            } elseif (method_exists($instance, 'handle')) {
                $args = array_merge([$instance, 'handle'], $params);
            }

            $res = instanciator()->call(...$args);

            if (method_exists($instance, 'onSuccess')) {
                instanciator()->call($instance, 'onSuccess', $res);
            }

            return $res;
        } catch (\Exception $e) {
            if (method_exists($instance, 'onFail')) {
                return instanciator()->call($instance, 'onFail');
            }
        }
    }

    /**
     * @return mixed|null|FastEvent
     * @throws \ReflectionException
     */
    function dispatch()
    {
        return event(...func_get_args());
    }

    function objectifier()
    {
        $value = call_user_func_array('\\Octo\\actual', func_get_args());

        return o($value);
    }

    /**
     * @param $orm
     *
     * @return FastOrmInterface
     */
    function orm($orm = null)
    {
        if (is_object($orm)) {
            return actual('core.orm', $orm);
        }

        return actual('core.orm');
    }

    /**
     * @return mixed|null
     */
    function self()
    {
        $args = func_get_args();

        return actual(...$args);
    }

    /**
     * @param null|Live $live
     *
     * @return null|Live
     */
    function live(?Live $live = null)
    {
        if ($live instanceof Live) {
            actual('core.live', $live);
        }

        return actual('core.live');
    }

    /**
     * @param null|Trust $trust
     *
     * @return Trust|null
     */
    function trust(?Trust $trust = null)
    {
        if ($trust instanceof Trust) {
            actual('core.trust', $trust);
        }

        return actual('core.trust');
    }

    /**
     * @return mixed|null
     */
    function actual()
    {
        $args   = func_get_args();
        $num    = func_num_args();
        $key    = array_shift($args);
        $value  = array_shift($args);

        $actuals = Registry::get('core.actuals', []);

        if (is_null($key)) {
            return $actuals;
        }

        if (1 === $num) {
            return isAke($actuals, $key, null);
        }

        $value = value($value);

        $actuals[$key] = $value;

        Registry::set('core.actuals', $actuals);

        return $value;
    }

    /**
     * @param string $event
     * @param null $concern
     * @param bool $return
     *
     * @return array|null
     */
    function listening(string $event, $concern = null, $return = false)
    {
        if (Fly::has($event)) {
            $result = Fly::listen($event, $concern);

            if (true === $return) {
                return $result;
            }
        }

        return $concern;
    }

    /**
     * @param $event
     * @param callable $callable
     * @param null $back
     *
     * @return Listener|null
     */
    function subscribe(string $event, callable $callable, $back = null)
    {
       $e = Fly::on($event, $callable);

       return $back ?: $e;
    }

    /**
     * @param $name
     * @return mixed|Object
     */
    function validator($name)
    {
        $validators = Registry::get('core.validators', []);

        $validator = isAke($validators, $name, null);

        if ($validator) {
            return $validator;
        }

        $validator = o();

        $validator->macro('rule', function ($field, callable $checker) use ($name, $validator) {
            $allRules = Registry::get('core.validators.rules', []);

            $rules = isAke($allRules, $name, []);

            $rules[$field] = $checker;

            $allRules[$name] = $rules;

            Registry::set('core.validators.rules', $allRules);

            return $validator;
        });

        $validator->macro('check', function (array $data = []) use ($name) {
            if (is_object($data)) {
                $data = [];
            }

            $errors = [];

            $data = empty($data) ? $_POST : $data;

            $allRules = Registry::get('core.validators.rules', []);

            $rules = isAke($allRules, $name, []);

            if (!empty($rules)) {
                $check = lib('checker', [$data]);

                foreach ($rules as $field => $checker) {
                    $status = $checker(isAke($data, $field, null), $data, $check);

                    if (true !== $status) {
                        $errors[$field] = $status;
                    }

                    $check->fresh();
                }
            }

            return empty($errors) ? true : $errors;
        });

        $validators[$name] = $validator;

        Registry::set('core.validators', $validators);

        return $validator;
    }

    function laravel()
    {
        return \app(...func_get_args());
    }

    function laravel5($app, $name = 'laravel_app', callable $config = null)
    {
        Timer::start();

        $basePath = \base_path();
        $appPath = \app_path();

        systemBoot($basePath . '/public');

        if (!is_dir($basePath . '/database/octalia')) {
            Dir::mkdir($basePath . '/database/octalia');
        }

        if (!is_dir($basePath . '/database/octalia/models')) {
            Dir::mkdir($basePath . '/database/octalia/models');
        }

        if (!is_dir($basePath . '/database/octalia/factories')) {
            Dir::mkdir($basePath . '/database/octalia/factories');
        }

        if (!is_dir($basePath . '/storage/cache')) {
            Dir::mkdir($basePath . '/storage/cache');
        }

        if (!is_dir($basePath . '/database/octalia/data')) {
            Dir::mkdir($basePath . '/database/octalia/data');
        }

        if (!is_dir($appPath . '/Entities')) {
            Dir::mkdir($appPath . '/Entities');
        }

        Registry::set('laravel', $app);

        Config::set('application.name',     $name);
        Config::set('application.dir',      realpath($basePath . '/app'));

        Config::set('model.dir',            realpath($basePath . '/database/octalia/models'));
        Config::set('factories.dir',        realpath($basePath . '/database/octalia/factories'));
        Config::set('octalia.dir',          realpath($basePath . '/database/octalia/data'));
        Config::set('dir.cache',            realpath($basePath . '/storage/cache'));

        path('config',                      realpath($basePath . '/config'));
        path('app',                         realpath($basePath . '/app'));
        path('models',                      realpath($basePath . '/database/octalia/models'));
        path('factories',                   realpath($basePath . '/database/octalia/factories'));
        path('translations',                realpath($basePath . '/storage/translations'));
        path('storage',                     realpath($basePath . '/storage'));
        path('public',                      realpath($basePath . '/public'));

        path('octalia',                     Config::get('octalia.dir', session_save_path()));
        path('cache',                       Config::get('dir.cache', session_save_path()));

        if (is_callable($config)) {
            $config($app);
        }
    }

    function tinker()
    {
        laravel5(laravel());
    }

    /**
     * @param Octalia $model
     * @param array $fields
     *
     * @return bool
     */
    function createModel(Octalia $model, array $fields = [])
    {
        if ($model->count() == 0) {
            $row = [];

            if (!empty($fields)) {
                foreach ($fields as $field) {
                    $row[$field] = $field;
                }
            }

            $model->store($row);
            $model->forget();

            return true;
        }

        exception('system', 'This model ever exists.');
    }

    function getHelpers($from = '', $to = 'octo')
    {
        static $methods = null;

        $methods = is_null($methods) ? get_defined_functions() : $methods;

        foreach ($methods as $group) {
            foreach ($group as $function) {
                if (fnmatch($to . '*', $function)) {
                    if (strlen($from)) $native = str_replace_first($to, $from, $function);
                    else $native = str_replace_first($to . '\\', $from, $function);

                    if (!function_exists($native)) {
                        $fn = str_replace($from . '\\', '', $native);
                    } else {
                        $fn = 'octo_' . $native;
                    }

                    if (!function_exists($from . '\\' . $fn)) {
                        $code = 'namespace ' . $from . ' {
                            function ' . $fn . ' ()
                            {
                                return call_user_func_array("\\' . str_replace('\\', '\\\\', $function) . '", func_get_args());
                            };
                        };';

                        eval($code);
                    }
                }
            }
        }
    }

    /**
     * @param null $default
     * @return mixed|null
     */
    function cache_start($default = null)
    {
        $bt = debug_backtrace();

        array_shift($bt);

        $last   = array_shift($bt);
        $key    = sha1(serialize($last) . File::read($last['file']) . filemtime($last['file']));

        return fmr()->start($key, $default);
    }

    function cache_end()
    {
        return fmr()->end();
    }

    function input()
    {
        $args = func_get_args();

        $method = array_shift($args);

        $input = lib('input');

        return $method ? call_user_func_array([$input, $method], $args) : $input;
    }

    /**
     * @param array $data
     * @param array $options
     *
     * @return array
     */
    function multiCurl($data, $options = [])
    {
        $curly  = [];
        $result = [];

        $mh = curl_multi_init();

        foreach ($data as $k => $v) {
            $curly[$k] = curl_init();

            $url = (is_array($v) && !empty($v['url'])) ? $v['url'] : $v;

            curl_setopt($curly[$k], CURLOPT_URL, $url);
            curl_setopt($curly[$k], CURLOPT_HEADER, 0);
            curl_setopt($curly[$k], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curly[$k], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curly[$k], CURLOPT_SSL_VERIFYPEER, 0);

            if (is_array($v)) {
                if (!empty($v['post'])) {
                    curl_setopt($curly[$k], CURLOPT_POST, 1);
                    curl_setopt($curly[$k], CURLOPT_POSTFIELDS, $v['post']);
                }
            }

            if (!empty($options)) {
                curl_setopt_array($curly[$k], $options);
            }

            curl_multi_add_handle($mh, $curly[$k]);
        }

        $running = null;

        do {
            curl_multi_exec($mh, $running);
        } while($running > 0);

        foreach($curly as $k => $c) {
            $result[$k] = curl_multi_getcontent($c);
            curl_multi_remove_handle($mh, $c);
        }

        curl_multi_close($mh);

        return $result;
    }

    /**
     * @param string|null $ns
     * @return \Predis\Client
     */
    function redis($ns = null)
    {
        static $kh = [];

        $k = $ns;

        if (!$ns) {
            $k = 'core';
        }

        if (!isset($kh[$k])) {
            $kh[$k] = lib('redis', [$ns]);
        }

        return $kh[$k];
    }

    function oredis()
    {
        return lib('redis')->client();
    }

    function di()
    {
        return new Utils;
    }

    function dic()
    {
        $dic = Registry::get('core.dic');

        if (!$dic) {
            $dic = o();

            $dic->macro('get', function ($k, $d = null) {
                $key = 'dic.' . Strings::urlize($k, '.');

                return Registry::get($key, $d);
            });

            $dic->macro('set', function ($k, $v) {
                $key = 'dic.' . Strings::urlize($k, '.');

                return Registry::get($key, $v);
            });

            Registry::set('core.dic', $dic);
        }

        return $dic;
    }

    /**
     * @param $k
     * @param string $v
     *
     * @return $this|mixed
     *
     * @throws Exception
     * @throws \Exception
     */
    function once($k, $v = 'octodummy')
    {
        $key = sha1(forever()) . '.' . Strings::urlize($k, '.');

        if ('octodummy' != $v) {
            return fmr('once')->set($key, value($v));
        }

        $value = fmr('once')->get($key);

        fmr('once')->del($key);

        return $value;
    }

    /**
     * @param $k
     * @param string $v
     *
     * @return $this|mixed
     *
     * @throws Exception
     * @throws \Exception
     */
    function keep($k, $v = 'octodummy')
    {
        $key = sha1(forever()) . '.' . Strings::urlize($k, '.');

        if ('octodummy' != $v) {
            return fmr('keep')->set($key, value($v));
        }

        return fmr('keep')->get($key);
    }

    /**
     * @param $k
     *
     * @return bool
     *
     * @throws Exception
     * @throws \Exception
     */
    function unkeep($k)
    {
        $key = sha1(forever()) . '.' . Strings::urlize($k, '.');

        return fmr('keep')->del($key);
    }

    function getEngine()
    {
        $driver = actual('octalia_driver');

        if ($driver && is_object($driver)) {
            $class = get_class($driver);

            if ($class === Now::class) {
                return 'Octo\\ndb';
            } else if ($class === Cacheredis::class) {
                return 'Octo\\rdb';
            } else if ($class === Caching::class) {
                return 'Octo\\cachingDb';
            }
        }

        return 'Octo\\odb';
    }

    function engine($database = 'core', $table = 'core', $driver = 'odb')
    {
        $engine = conf('octalia.engine', $driver);

        if (function_exists('\\Octo\\' . $engine)) {
            return call_user_func_array('\\Octo\\' . $engine, [$database, $table]);
        } else {
            exception('core', "Engine $engine does not exist.");
        }
    }

    function modeler($model)
    {
        return em($model);
    }

    function entity($model, array $data = [])
    {
        return em($model)->model($data);
    }

    /**
     * @param string $db
     * @param string $table
     *
     * @return Octalia
     *
     * @throws Exception
     */
    function driverDb(string $db,string  $table): Octalia
    {
        $driver = orm()->driver;

        return new Octalia($db, $table, $driver);
    }

    function em($model, $engine = 'engine', $force = false)
    {
        $models = Registry::get('em.models', []);

        $model = Strings::uncamelize($model);

        if (!isset($models[$model]) || true === $force) {
            if (fnmatch('*_*', $model)) {
                list($database, $table) = explode('_', $model, 2);
            } else {
                $database   = Strings::uncamelize(Config::get('application.name', 'core'));
                $table      = $model;
            }

            $models[$model] = call_user_func_array('\\Octo\\' . $engine, [$database, $table]);

            Registry::set('em.models', $models);
        }

        return $models[$model];
    }

    function roulette(array $a)
    {
        $sum    = 0.0;
        $total  = array_sum(array_values($a));
        $r      = (float) rand() / (float) getrandmax();

        if ($r == $sum) {
            return array_keys($a)[0];
        }

        foreach ($a as $key => $percentage) {
            $newsum = $sum + (float) $percentage / (float) $total;

            if ($r > $sum && $r <= $newsum) {
                return $key;
            }

            $sum = $newsum;
        }

        return array_keys($a)[count($a) - 1];
    }

    function guest($ns = 'web')
    {
        $u = session($ns)->getUser();

        return is_null($u);
    }

    function role($ns = 'web')
    {
        if (!guest($ns)) {
            $user = session($ns)->getUser();
            $role = em('systemRole')->find((int) $user['role_id']);

            if ($role) {
                return $role;
            }
        }

        return o(['label' => 'guest']);
    }

    function dom()
    {
        require_once __DIR__ . DS . 'dom.php';
    }

    function route(string $name, array $params = [])
    {
        return getContainer()->router()->urlFor($name, $params);
    }

    function headers()
    {
        $instance = Registry::get('headers.instance');

        if (!$instance) {
            $instance = o();

            $instance->macro('new', function ($k, $v) {
                $headers = Registry::get('response.headers', []);

                $headers[$k] = $v;

                return Registry::set('response.headers', $headers);
            });

            $instance->macro('remove', function ($k) {
                $headers = Registry::get('response.headers', []);

                unset($headers[$k]);

                return Registry::set('response.headers', $headers);
            });

            Registry::set('headers.instance', $instance);
        }

        return $instance;
    }

    function gate()
    {
        $gate = Registry::get('core.gate');

        if (!$gate) {
            $gate = o();

            $gate->macro('new', function ($k, $cb) {
                $key = 'gate.' . Strings::urlize($k, '.');

                return Registry::set($key, $cb);
            });

            $gate->macro('allows', function ($k, $ns = 'web') {
                $key = 'gate.' . Strings::urlize($k, '.');

                if (!guest($ns)) {
                    $cb = Registry::get($key);

                    if ($cb) {
                        return $cb(session($ns)->getUser());
                    }
                }

                return false;
            });

            Registry::set('core.gate', $gate);
        }

        return $gate;
    }

    /*
        $mailer = mailer();
        $message = message()
        ->setSubject('subject')
        ->setTo(['client@site.com' => 'Client'])
        ->setFrom(['contact@site.com' => 'Contact'])
        ->setBody('This is a message!!', 'text/html');

        $status = $mailer->send($message);
    */

    function mailto($config)
    {
        $config     = arrayable($config) ? $config->toArray() : $config;
        $mailer     = mailer();

        $to         = isAke($config, 'to', null);
        $toName     = isAke($config, 'to_name', $to);

        $from       = isAke($config, 'from', conf('MAILER_FROM', 'admin@localhost'));
        $fromName   = isAke($config, 'from_name', $from);

        $subject    = isAke($config, 'subject', null);
        $body       = isAke($config, 'body', null);

        $message    = message()
        ->setTo([$to => $toName])
        ->setSubject($subject)
        ->setFrom([$from => $fromName])
        ->setBody($body, 'text/html');

        return $mailer->send($message);
    }

    /**
     * @param null|\Swift_Message $swift
     *
     * @return Mailable
     */
    function message(?\Swift_Message $swift = null)
    {
        return foundry(Mailable::class, $swift);
    }

    /**
     * @return \Swift_Mailer
     */
    function mailer(): \Swift_Mailer
    {
        return getContainer()['mailer'] ?? (new Sender())->sendmail();
    }

    function mailerSwift()
    {
        $mailer = conf('MAILER_DRIVER', 'php'); /* smtp, sendmail, php */

        switch ($mailer) {
            case 'smtp':
                $transport = \Swift_SmtpTransport::newInstance(
                    conf('SMTP_HOST', 'localhost'),
                    conf('SMTP_PORT', 443),
                    conf('SMTP_SECURITY', 'ssl')
                )
                ->setUsername(conf('SMTP_USER', ''))
                ->setPassword(conf('SMTP_PASSWORD', ''));

                break;
            case 'sendmail':
                $transport = \Swift_SendmailTransport::newInstance(
                    conf('SENDMAIL_PATH', '/usr/lib/sendmail')
                );

                break;
            case 'php':
            case 'memory':
                $transport = \Swift_MailTransport::newInstance();

            break;
        }

        return \Swift_Mailer::newInstance($transport);
    }

    function memory()
    {
        $PDOoptions = [
            \PDO::ATTR_CASE                 => \PDO::CASE_NATURAL,
            \PDO::ATTR_ERRMODE              => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_ORACLE_NULLS         => \PDO::NULL_NATURAL,
            \PDO::ATTR_DEFAULT_FETCH_MODE   => \PDO::FETCH_ASSOC,
            \PDO::ATTR_STRINGIFY_FETCHES    => false,
            \PDO::ATTR_EMULATE_PREPARES     => false
        ];

        $memory = foundry(\PDO::class, 'sqlite::memory:', null, null, $PDOoptions);
        $memory->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [Statement::class, [$memory]]);

        return $memory;
    }

    function formatSize($size)
    {
        $mod = 1024;
        $units = explode(' ', 'B KB MB GB TB PB');

        for ($i = 0; $size > $mod; $i++) {
            $size /= $mod;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    function foldersize($path, $format = true)
    {
        $total_size = 0;
        $files = scandir($path);

        foreach($files as $t) {
            if (is_dir(rtrim($path, '/') . '/' . $t)) {
                if ($t <> "." && $t <> "..") {
                    $size = foldersize(rtrim($path, '/') . '/' . $t);

                    $total_size += $size;
                }
            } else {
                $size = filesize(rtrim($path, '/') . '/' . $t);
                $total_size += $size;
            }
        }

        return $format ? formatSize($total_size) : $total_size;
    }

    function myarray($name, $row = 'octodummy')
    {
        $arrays = Registry::get('core.arrays', []);

        $array = isAke($name, $arrays, []);

        if ($row != 'octodummy') {
            $array[] = $row;

            $arrays[$name] = $array;

            Registry::set('core.arrays', $arrays);
        }

        return $array;
    }

    function myarrayToCollection($name)
    {
        return coll(myarray($name));
    }

    /* Alias of myarrayToCollection */
    function ma2c($name)
    {
        return myarrayToCollection($name);
    }

    function globalized($name, $default = null)
    {
        $key = 'globalize.' . Strings::urlize($name, '.');

        return Registry::get($key, $default);
    }

    function globalize($name, $value)
    {
        $key = 'globalize.' . Strings::urlize($name, '.');

        return Registry::set($key, $value);
    }

    function mylite($db = null)
    {
        $db = is_null($db) ? Config::get('sqlite.db', path('storage') . DS . 'lite') : $db;

        $mylite  =  new \PDO('sqlite:' . $db);

        $mylite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $mylite->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $mylite;
    }

    function mysql($host = '127.0.0.1', $database = null, $user = 'root', $password = '')
    {
        $dsn = 'mysql:dbname=' . Config::get('mysql.database', $database) . ';host=' . Config::get('mysql.host', $host);

        $mysql =  new \PDO(
            $dsn,
            Config::get('mysql.user', $user),
            Config::get('mysql.password', $password)
        );

        $mysql->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $mysql->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return $mysql;
    }

    function deNamespace($className)
    {
        $className = trim($className, '\\');

        if ($lastSeparator = strrpos($className, '\\')) {
            $className = substr($className, $lastSeparator + 1);
        }

        return $className;
    }

    function getNamespace($className)
    {
        $className = trim($className, '\\');

        if ($lastSeparator = strrpos($className, '\\')) {
            return substr($className, 0, $lastSeparator + 1);
        }

        return '';
    }

    function acl($data = null)
    {
        if (empty($data)) {
            return coll(Registry::get('core.acl', []));
        } else {
            Registry::set('core.acl', array_values($data->toArray()));
        }
    }

    function is_admin()
    {
        return role()->getLabel() == 'admin';
    }

    function can($resource)
    {
        $role = role();

        if ($role->getLabel() == 'admin') {
            return true;
        }

        $row = acl()
        ->where('resource', $resource)
        ->where('role_id', $role)
        ->first();

        return $row ? true : false;
    }

    function stream($name, $contents = 'octodummy')
    {
        $streams = Registry::get('core.streams', []);

        if ('octodummy' == $contents) {
            $stream = isAke($streams, $name, null);
        } else {
            $stream = fopen('php://memory','r+');
            fwrite($stream, $contents);
            fseek($stream, 0);

            $streams[$name] = $stream;

            Registry::set('core.streams', $streams);
        }

        return $stream;
    }

    function undot($collection)
    {
        $collection = (array) $collection;
        $output = [];

        foreach ($collection as $key => $value) {
            $output = aset($output, $key, $value);

            if (is_array($value) && !strpos($key, '.')) {
                $nested = undot($value);

                $output[$key] = $nested;
            }
        }

        return $output;
    }

    function notify($model, $instance, array $args = [])
    {
        if ($model->exists()) {
            if (is_string($instance)) {
                $instance = maker($instance);
            }

            $channels = $instance->channels();

            foreach ($channels as $channel) {
                call_user_func_array([$instance, $channel], $args);

                $dbRow = em('systemNotification')->store([
                    'model'     => get_class($model),
                    'model_id'  => $model->id,
                    'type'      => get_class($instance),
                    'channel'   => $channel,
                    'read'      => false
                ]);
            }
        }
    }

    function is_invokable($concern)
    {
        if (is_string($concern) && class_exists($concern)) {
            $methods = get_class_methods($concern);

            return in_array('__invoke', $methods);
        }

        return false;
    }

    /**
     * @param string $class
     * @param string $sep
     *
     * @return Closure|mixed|object
     *
     * @throws \ReflectionException
     */
    function resolverClass(string $class, string $sep = '@')
    {
        if (is_invokable($class)) {
            return instanciator()->factory($class);
        }

        return function() use ($class, $sep) {
            $segments   = explode($sep, $class);
            $method     = count($segments) === 2 ? $segments[1] : 'handle';
            $callable   = [instanciator()->factory($segments[0]), $method];
            $data       = func_get_args();
            $params     = array_merge($callable, $data);

            return instanciator()->call(...$params);
        };
    }

    function octo($k = null, $v = 'octodummy')
    {
        $app = context('app');

        if ($k) {
            if ('octodummy' == $v) {
                return $app[$k];
            }

            $app[$k] = $v;
        }

        return $app;
    }

    function aliasToApp($alias, $class, $action = 'construct')
    {
        octo($alias, resolverAction($class, $action));
    }

    function resolverAction($class, $action = 'construct')
    {
        $resolver = function () use ($class, $action) {
            $instance = maker($class);

            if ($action != 'construct') {
                return call_user_func_array([$instance, $action], func_get_args());
            }

            return $instance;
        };

        return $resolver;
    }

    function strArray($strArray)
    {
        return !is_array($strArray)
        ? strstr($strArray, ',')
            ? explode(
                ',',
                str_replace(
                    [' ,', ', '],
                    ',',
                    $strArray
                )
            )
            : [$strArray]
        : $strArray;
    }

    function kryptid($length = 15)
    {
        static $kryptid = null;

        if (is_null($kryptid)) {
            $kryptid = new Hashids(
                hash('azertyuiop1234567890'),
                (int) $length,
                'abcdefghijkmnpqrstuvwxyz0123456789'
            );
        }

        return $kryptid;
    }

    function zipFolder($source, $destination)
    {
        if(is_file($destination)) unlink($destination);

        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        $zip = new \ZipArchive();

        if (!$zip->open($destination, \ZIPARCHIVE::CREATE)) {
            return false;
        }

        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source) === true) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);

                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) continue;

                $file = realpath($file);

                if (is_dir($file) === true) {
                    $zip
                    ->addEmptyDir(
                        str_replace($source . '/', '', $file . '/')
                    );
                } else if (is_file($file) === true) {
                    $zip
                    ->addFromString(
                        str_replace($source . '/', '', $file),
                        file_get_contents($file)
                    );
                }
            }
        } else if (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }

        return $zip->close();
    }

    function fetch($array, $key)
    {
        return lib('arrays')->fetch($array, $key);
    }

    function pluck($array, $key)
    {
        return array_map(
            function($row) use ($key)  {
                return is_object($row) ? $row->$key : $row[$key];
            },
            $array
        );
    }

    function is_false($bool)
    {
        return false === $bool;
    }

    function is_true($bool)
    {
        return true === $bool;
    }

    function evaluate($path, $args = [])
    {
        $ob_get_level = ob_get_level();

        ob_start();

        extract($args);

        $self = actual('controller');

        try {
            include $path;
        } catch (\Exception $e) {
            while (ob_get_level() > $ob_get_level) {
                ob_end_clean();
            }

            view('<h1>An error occured !</h1><p>' . $e->getMessage() . '</p>', 500, 'An error occured');
        } catch (\Throwable $e) {
            while (ob_get_level() > $ob_get_level) {
                ob_end_clean();
            }

            view('<h1>An error occured !</h1><p>' . $e->getMessage() . '</p>', 500, 'An error occured');
        }

        return ltrim(ob_get_clean());
    }

    function evaluateInline($path, $args = [])
    {
        $ob_get_level = ob_get_level();

        ob_start();

        extract(Registry::get('core.globals.view', []));
        extract($args);

        $module = $self = actual('fast.module');

        try {
            include ($path);
        } catch (\Exception $e) {
            while (ob_get_level() > $ob_get_level) {
                ob_end_clean();
            }

            view('<h1>An error occured !</h1><p>' . $e->getMessage() . ' on ' . $e->getFile() . ' [line ' . $e->getLine() . ']</p>', 500, 'An error occured');
        } catch (\Throwable $e) {
            while (ob_get_level() > $ob_get_level) {
                ob_end_clean();
            }

            view('<h1>An error occured !</h1><p>' . $e->getMessage() . ' on ' . $e->getFile() . ' [line ' . $e->getLine() . ']</p>', 500, 'An error occured');
        }

        return ltrim(ob_get_clean());
    }

    function compactCallback()
    {
        $args = func_get_args();

        return function () use ($args) {
            return $args;
        };
    }

    function auth()
    {
        return getContainer()->defined('lock', function (Fast $app) {
            return $app->resolve(Lock::class);
        });
    }

    function load_entity($class)
    {
        Autoloader::entity($class);
    }

    function handler($concern, $object)
    {
        $handlers = Registry::get('core.$handlers', Registry::get('core.all.binds', []));

        $handlers[$concern] = $object;
        Registry::set('core.$handlers', $handlers);
    }

    function handled($concern)
    {
        $handlers = Registry::get('core.$handlers', Registry::get('core.all.binds', []));

        return isAke($handlers, $concern, null);
    }

    function recall()
    {
        $args       = func_get_args();
        $callback   = array_shift($args);

        if (!is_callable($callback)) {
            $callback = toClosure($callback);
        }

        $key        = hash(serializeClosure($callback));
        $minutes    = end($args);
        $minutes    = is_numeric($minutes) ? $minutes * 60 : null;

        $cache      = fmr('recall');

        if ($cache->has($key)) {
            return $cache->get($key);
        }

        $value = call_user_func_array($callback, $args);

        $cache->set($key, $value, $minutes);

        return $value;
    }

    function remember($key, $callback, $minutes = null)
    {
        $minutes = !is_null($minutes) ? $minutes * 60 : null;

        if (!is_callable($callback)) {
            $callback = toClosure($callback);
        }

        return fmr()->getOr($key, $callback, $minutes);
    }

    function toClosure($concern)
    {
        return voidToCallback($concern);
    }

    function voidToCallback($concern)
    {
        return function () use ($concern) {
            return $concern;
        };
    }

    function mcache($host = 'localhost', $port = 11211, $ns = 'octo.core')
    {
        $mc = mc($host, $port, $ns);

        $cache = dyn($mc);

        $cache->macro('watch', function ($k, callable $exists = null, callable $notExists = null) use ($cache) {
            if ($exists instanceof Dyn) {
                $exists = null;
            }

            if ($notExists instanceof Dyn) {
                $notExists = null;
            }

            if ($cache->has($k)) {
                if (is_callable($exists)) {
                    return $exists($cache->get($k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        });

        $cache->macro('aged', function ($k, callable $c, $maxAge = null, $args = []) use ($cache) {
            if ($maxAge instanceof Dyn) {
                $maxAge = null;
            }

            if ($args instanceof Dyn) {
                $args = [];
            }

            $keyAge = $k . '.maxage';
            $v      = $cache->get($k);

            if ($v) {
                if (is_null($maxAge)) {
                    return $v;
                }

                $age = $cache->get($keyAge);

                if (!$age) {
                    $age = $maxAge - 1;
                }

                if ($age >= $maxAge) {
                    return $v;
                } else {
                    $cache->delete($k);
                    $cache->delete($keyAge);
                }
            }

            $data = call_user_func_array($c, $args);

            $cache->set($k, $data);

            if (!is_null($maxAge)) {
                if ($maxAge < 3600 * 24 * 30) {
                    $maxAge = ($maxAge * 60) + microtime(true);
                }

                $cache->set($keyAge, $maxAge);
            }

            return $data;
        });

        $cache->macro('incr', function ($k, $by = 1) use ($cache) {
            if ($by instanceof Dyn) {
                $by = 1;
            }

            if (!$cache->has($k)) {
                $old = 0;
            } else {
                $old = $cache->get($k);
            }

            $new = $old + $by;

            $cache->set($k, $new);

            return $new;
        });

        $cache->macro('decr', function ($k, $by = 1) use ($cache) {
            if ($by instanceof Dyn) {
                $by = 1;
            }

            if (!$cache->has($k)) {
                $old = 0;
            } else {
                $old = $cache->get($k);
            }

            $new = $old - $by;

            $cache->set($k, $new);

            return $new;
        });

        $cache->macro('has', function ($k) use ($cache) {
            $val = $cache->get($k);

            return $cache->getResultCode() != \Memcached::RES_NOTFOUND;
        });

        $cache->macro('getOr', function ($k, callable $c, $e = 0) use ($cache) {
            if ($e instanceof Dyn) {
                $e = 0;
            }

            $val = $cache->get($k);

            if ($cache->getResultCode() == \Memcached::RES_NOTFOUND) {
                $res = $c();

                $cache->set($k, $res, (int) $e);

                return $res;
            } else {
                return $val;
            }
        });

        return $cache;
    }

    function mc($host = 'localhost', $port = 11211, $ns = 'octo.core')
    {
        $i = maker(Memcached::class, [$ns]);

        $i->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);

        if (empty($i->getServerList())) {
            $i->addServer($host, $port);
        }

        return $i;
    }

    function guard($ns = 'web', $em = 'user')
    {
        $class = o();

        $class->macro('login', function ($user) use ($class) {
            $user = arrayable($user) ? $user->toArray() : $user;

            return $class->reveal()->login($user);
        });

        $class->macro('logout', function () use ($class) {
            return $class->reveal()->logout();
        });

        $class->macro('id', function () use ($class) {
            return $class->reveal()->id();
        });

        $class->macro('email', function () use ($class) {
            return $class->reveal()->email();
        });

        $class->macro('on', function () use ($class) {
            return call_user_func_array([$class, 'policy'], func_get_args());
        });

        $class->macro('user', function ($model = true) use ($class) {
            return $class->reveal()->user($model);
        });

        $class->macro('logWithId', function ($id, $route = 'home') use ($class)  {
            $auth = $class->reveal();
            $user = em($auth->entity)->find((int) $id);

            if ($user) {
                $auth->login($user);
                go(urlFor($route));
            } else {
                exception('guard', "Unknown id.");
            }
        });

        $class->macro('logByUser', function ($user, $route = 'home') use ($class) {
            $user = arrayable($user) ? $user->toArray() : $user;

            $class->reveal()->login($user);

            go(urlFor($route));
        });

        $class->macro('reveal', function () {
            $auth = actual('auth.class');

            return is_object($auth) ? $auth : actual('auth.class', new Auth);
        });

        $class->macro('policy', function ($policy, callable $callable) use ($class) {
            $policies           = Registry::get('guard.policies', []);
            $policies[$policy]  = $callable;

            Registry::set('guard.policies', $policies);

            return $class;
        });

        $class->macro('allows', function () use ($class) {
            $auth = $class->reveal();
            $user = $auth->user();

            if ($user) {
                $user       = item($user);
                $args       = func_get_args();
                $policy     = array_shift($args);
                $policies   = Registry::get('guard.policies', []);
                $policy     = isAke($policies, $policy, null);

                if (is_callable($policy)) {
                    return call_user_func_array($policy, array_merge([$user], $args));
                }
            }

            return false;
        });

        return $class;
    }

    /**
     * @param string $str
     * @param array $data
     *
     * @return string
     *
     * @throws \Exception
     * @throws \ReflectionException
     */
    function blader(string $str, array $data = []): string
    {
        $blade          = instanciator()->singleton(FastBladeCompiler::class);
        $parsed_string  = $blade->compileString($str);

        ob_start() && extract($data, EXTR_SKIP);

        try {
            eval('?>' . $parsed_string);
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $str = ob_get_contents();
        ob_end_clean();

        return $str;
    }

    /**
     * @param $value
     * @return array
     */
    function wrap($value): array
    {
        return !is_array($value) ? [$value] : $value;
    }

    function be($user)
    {
        $user = arrayable($user) ? $user->toArray() : $user;

        live()['user'] = $user;
    }

    /**
     * @return \GuzzleHttp\Client
     *
     * @throws \ReflectionException
     */
    function client()
    {
        return instanciator()->singleton(\GuzzleHttp\Client::class, [], false);
    }

    function itOr($a, $b)
    {
        return $a ?: $b;
    }

    function infoClass($classname)
    {
        $parts     = explode('\\', $classname);
        $classname = array_pop($parts);
        $namespace = implode('\\', $parts);

        return o([
            'namespace' => $namespace,
            'classname' => $classname,
        ]);
    }

    function addressToCoords($address)
    {
        return lib('geo')->getCoordsMap($address);
    }

    /**
     * @param $concern
     * @return bool
     */
    function arrayable($concern)
    {
        return is_object($concern) && in_array('toArray', get_class_methods($concern));
    }

    /**
     * @param $key
     * @param $minutes
     * @param $ifNot
     *
     * @return mixed
     *
     * @throws Exception
     * @throws \Exception
     * @throws \ReflectionException
     */
    function cacher($key, $minutes, $ifNot)
    {
        return fmr()->remember($key, $ifNot, 60 * $minutes);
    }

    function isJson($value)
    {
        if (!is_scalar($value) && !method_exists($value, '__toString')) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * @param array ...$args
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    function getClass(...$args)
    {
        $class = array_shift($args);

        if (is_object($class)) {
            return $class;
        }

        return new $class(...$args);
    }


    class OctoLab
    {
        public static function __callStatic($m, $a)
        {
            if (function_exists('\\Octo\\' . $m)) {
                return call_user_func_array('\\Octo\\' . $m, $a);
            } elseif (function_exists('\\' . $m)) {
                return call_user_func_array('\\' . $m, $a);
            }

            throw new \BadMethodCallException("Method {$m} does not exist.");
        }

        public function __call($m, $a)
        {
            if (function_exists('\\Octo\\' . $m)) {
                return call_user_func_array('\\Octo\\' . $m, $a);
            } elseif (function_exists('\\' . $m)) {
                return call_user_func_array('\\' . $m, $a);
            }

            throw new \BadMethodCallException("Method {$m} does not exist.");
        }
    }

    /**
     * Events helpers classes
     */

    class InternalEvents extends Fire {}

    class On
    {
        public static function __callStatic($m, $a)
        {
            $event      = array_shift($a);
            $priority   = array_shift($a);

            InternalEvents::listen(Inflector::uncamelize($m), $event, $priority);
        }
    }

    class Emit
    {
        public static function __callStatic($m, $a)
        {
            $event = Inflector::uncamelize($m);
            $args  = array_merge([$event], $a);

            return forward_static_call_array(['Octo\InternalEvents', 'fire'], $args);
        }
    }

    function initArray($rows)
    {
        if (!is_array($rows)) {
            $rows = func_get_args();
        }

        return array_combine(
            $rows,
            array_fill(
                0,
                count($rows),
                null
            )
        );
    }

    function paired()
    {
        return coll(func_get_args())->paired()->toArray();
    }

    function ip($trusted = [])
    {
        $realIp = $_SERVER['REMOTE_ADDR'];

        foreach($trusted as &$t) {
            if (filter_var(
                $t,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE |
                FILTER_FLAG_NO_RES_RANGE |
                FILTER_FLAG_IPV4 |
                FILTER_FLAG_IPV6
            ) === false) {
                $t = null;
            }
        }

        unset($t);

        $trusted = array_filter($trusted);

        if (filter_var(
            $_SERVER['SERVER_ADDR'],
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE |
            FILTER_FLAG_NO_RES_RANGE |
            FILTER_FLAG_IPV4 |
            FILTER_FLAG_IPV6
        ) !== false) {
            $trusted[] = $_SERVER['SERVER_ADDR'];
        }

        $ip_fields = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_CF_CONNECTING_IP'
        ];

        foreach ($ip_fields as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $proxy_list = explode(',', $_SERVER[$key]);

                $proxy_list = array_reverse($proxy_list);

                $last   = null;
                $lan    = false;

                if (filter_var(
                    $_SERVER['REMOTE_ADDR'],
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4 |
                    FILTER_FLAG_IPV6
                ) !== false) {
                    if (filter_var(
                        $_SERVER['REMOTE_ADDR'],
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE |
                        FILTER_FLAG_IPV4 |
                        FILTER_FLAG_IPV6
                    ) === false) {
                        $last = $_SERVER['REMOTE_ADDR'];
                        $lan = true;
                    }
                }

                foreach ($proxy_list as $k => &$ip) {
                    $ip = trim($ip);

                    if (is_null($last) || filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV4 |
                        FILTER_FLAG_IPV6
                    ) === false) {
                        break;
                    }

                    if ($lan && filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE |
                        FILTER_FLAG_IPV4 |
                        FILTER_FLAG_IPV6
                    ) === false) {
                        $last = $ip;

                        continue;
                    }

                    (in_array($last, $trusted) || $lan) && $realIp = $ip;
                    !in_array($ip, $trusted) && $lan = false;

                    if (in_array($ip, $trusted)) {
                        $last = $ip;
                    } else {
                        $last = null;
                    }
                }
            }
        }

        return $realIp;
    }
