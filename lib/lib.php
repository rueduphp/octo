<?php
    namespace Octo;

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
                $controller = Registry::get('app.controller', null);
                $content    = str_replace(['{{', '}}'], ['<?php $controller->e("', '");?>'], $content);
                $content    = str_replace(['[[', ']]'], ['<?php $controller->trad("', '");?>'], $content);

                $content    = Router::compile($content);
            } else {
                $controller = o($args);
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

        call_user_func_array('preg_match_all', [
            $pattern, $subject, & $m,
            ($flags & PREG_PATTERN_ORDER) ? $flags : ($flags | PREG_SET_ORDER),
            $offset,
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
                $refClass = new \ReflectionClass($class);

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

    function o(array $o = [])
    {
        return lib('object', [$o]);
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

    function isCli()
    {
        return PHP_SAPI === 'cli' || defined('STDIN');
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

    function aget($array, $k, $d = null)
    {
        return Arrays::get($array, $k, $d);
    }

    function aset($array, $k, $v = null)
    {
        return Arrays::set($array, $k, $v);
    }

    function adel($array, $k)
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

    function server($k = null, $d = null)
    {
        if (empty($k)) {
            return lib('object', [oclean($_SERVER)]);
        }

        return isAke(oclean($_SERVER), $k, $d);
    }

    function post($k = null, $d = null)
    {
        if (empty($k)) {
            return Post::notEmpty();
        }

        return Post::get($k, $d);
    }

    function item($attributes = [])
    {
        $attributes = is_object($attributes) ? $attributes->toArray() : $attributes;

        return lib('fluent', [$attributes]);
    }

    function request($k = null, $d = null)
    {
        return Input::method($k, $d);
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
            return lib('object', [$data]);
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

    function i64($field)
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

    function session($context = 'web')
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

    function includer($file)
    {
        $return = [];

        if (file_exists($file)) {
            return include $file;
        }

        return $return;
    }

    function includers(array $files)
    {
        $return = [];

        foreach ($files as $file) {
            $return[$file] = includer($file);
        }

        return $return;
    }

    function isAke($array, $k, $d = [])
    {
        if (true === is_object($array)) {
            $array = (array) $array;
        }

        return Arrays::get($array, $k, $d);
    }

    function uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    function forever($ns = 'user')
    {
        if (php_sapi_name() == 'cli' || PHP_SAPI == 'cli') {
            return sha1(SITE_NAME . '::cli');
        }

        $ns         = SITE_NAME . '_' . $ns;
        $cookie     = isAke($_COOKIE, $ns, null);

        if (!$cookie) {
            $cookie = uuid();
        }

        setcookie($ns, $cookie, strtotime('+1 year'), '/');

        return $cookie;
    }

    function locale($context = 'web')
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

    function lng($context = 'web')
    {
        return locale($context);
    }

    function coll(array $data = [])
    {
        return new Collection($data);
    }

    function kh($ns = null)
    {
        return new Cache($ns);
    }

    function fmr($ns = null, $dir = null)
    {
        return new Cache($ns, $dir);
    }

    function lite($ns = null)
    {
        return new Cachelite($ns);
    }

    function mem($ns = null, $dir = null)
    {
        return new OctaliaMemory($ns, $dir);
    }

    function str_replace_first($from, $to, $subject)
    {
        $from = '/' . preg_quote($from, '/') . '/';

        return preg_replace($from, $to, $subject, 1);
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
            // $this->_hooks[\'validate\'] = function () use ($data) {
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

    function lib($lib, $args = [], $singleton = false)
    {
        try {
            $class = '\\Octo\\' . Strings::camelize($lib);

            if (!class_exists($class)) {
                $file = __DIR__ . DS . Strings::lower($lib) . '.php';

                if (file_exists($file)) {
                    require_once $file;
                }
            }

            return maker($class, $args, $singleton);
        } catch (\Exception $e) {
            return maker($lib, $args, $singleton);
        }
    }

    function str()
    {
        static $stringInstance;

        if (!$stringInstance) {
            $stringInstance = new Inflector;
        }

        return $stringInstance;
    }

    function reg()
    {
        static $registryInstance;

        if (!$registryInstance) {
            $registryInstance = new Now;
        }

        return $registryInstance;
    }

    function memoryFactory($class, $count = 1, $lng = 'fr_FR')
    {
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
            'rows' => $rows,
            'entity' => $entity
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

                return $res;
            } else {
                return $rows;
            }
        });

        $factories->macro('store', function ($subst = []) use ($factories) {
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

            if (count($rows) == 1) {
                return $em->model(current($rows));
            }

            return $rows;
        });

        return $factories;
    }

    function factory($class, $count = 1, $lng = 'fr_FR')
    {
        $model = maker($class);
        $faker = faker($lng);

        $entity = $model->orm();

        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = $model->factory($faker);
        }

        $factories = o([
            'rows' => $rows,
            'entity' => $entity
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

                return $res;
            } else {
                return $rows;
            }
        });

        $factories->macro('store', function ($subst = []) use ($factories) {
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

            if (count($rows) == 1) {
                return $em->model(current($rows));
            }

            return $rows;
        });

        return $factories;
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
            $file, $args
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

    function vue($file, $args = [], $status = 200)
    {
        $path = path('app') . '/views/' . str_replace('.', '/', $file) . '.phtml';

        $vue = o([
            'withs'     => [],
            'is_vue'    => true,
            'status'    => (int) $status,
            'args'      => $args,
            'path'      => $path
        ]);

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
                $pathFile = path('app') . '/views/' . str_replace('.', '/', $inc) . '.phtml';

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

                $layoutContent = str_replace(
                    "{{ $section }}",
                    $sectionContent,
                    $layoutContent
                );
            }

            $layoutContent = str_replace(
                ['$this'],
                ['$self'],
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
                'tpl' => $page, 'partial' => $vue
                ],
                $page->getArgs(),
                $vue->getArgs()
            );

            echo evaluate(
                $vue->getPath(),
                $args
            );
        });

        $vue->macro('with', function ($k, $v) use ($vue) {
            $withs      = $vue->withs;
            $withs[$k]  = $v;

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

    function paths()
    {
        return (new Now)->get('octo.paths', []);
    }

    function systemBoot($dir = null)
    {
        forever();

        require_once __DIR__ . DS . 'di.php';
        require_once __DIR__ . DS . 'cachei.php';

        define('OCTO_MAX', 9223372036854775808);
        define('OCTO_MIN', OCTO_MAX * -1);

        if (!class_exists('Octo\Route')) {
            Alias::facade('Route', 'Routes', 'Octo');
        }

        if (!class_exists('Octo\Strings')) {
            Alias::facade('Strings', 'Inflector', 'Octo');
        }

        if (!class_exists('Octo\Dir')) {
            Alias::facade('Dir', 'File', 'Octo');
        }

        if (!class_exists('Octo\Date')) {
            Alias::facade('Date', 'Time', 'Octo');
        }

        if (!class_exists('Octo\Db')) {
            Alias::facade('Db', 'Entitytable', 'Octo');
        }

        if (!class_exists('Octo\Octal')) {
            Alias::facade('Octal', 'Entitykv', 'Octo');
        }

        if (!class_exists('Octo\Octus')) {
            Alias::facade('Octus', 'Manager', 'Illuminate\Database\Capsule');
        }

        if (!class_exists('Octo\Testing')) {
            entityFacade('testing');
        }

        if (!class_exists('Octo\System')) {
            entityFacade('system');
        }

        if (!class_exists('Octo\Admin')) {
           entityFacade('admin');
        }

        if (!class_exists('Octo\Rest')) {
            entityFacade('rest');
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

        Registry::set('octo.subdir', $subdir);

        if (!defined('OCTO_STANDALONE')) {
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

                defined('URLSITE')  || define('URLSITE', $urlSite);
            }

            ini_set('error_log', path('storage') . DS . 'logs' . DS . 'error.log');
        }

        define('OCTO_DAY_KEY', sha1((OCTO_MAX / 7) + strtotime('today') . forever()));

        path('public', realpath($dir));

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

        $app = context('app');

        $app->make(function () {
            return call_user_func_array('\\Octo\\maker', func_get_args());
        });

        $app->register(function ($alias, $class, $args = []) use ($app) {
            if (is_object($args)) {
                $args = [];
            }

            $instance       = maker($class, $args);
            $app[$alias]    = $instance;
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

        $PDOoptions = [
            \PDO::ATTR_CASE                 => \PDO::CASE_NATURAL,
            \PDO::ATTR_ERRMODE              => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_ORACLE_NULLS         => \PDO::NULL_NATURAL,
            \PDO::ATTR_STRINGIFY_FETCHES    => false,
            \PDO::ATTR_EMULATE_PREPARES     => false
        ];

        $dsns = [
            'mysql' => 'mysql:host=##host##;port=##port##;dbname=##database##',
            'sqlite' => 'sqlite:##path##'
        ];

        $database = path('app') . '/config/database.php';

        if (File::exists($database)) {
            $confDbs = include $database;

            $driver = appenv('DATABASE_DRIVER', 'mysql');
            $confDb = isAke($confDbs, $driver, []);
            $dsn    = isAke($dsns, $driver, null);

            if ($dsn) {
                switch ($driver) {
                    case 'mysql':
                        $host   = isAke($confDb, 'host', 'localhost');
                        $port   = isAke($confDb, 'port', 3306);
                        $db     = isAke($confDb, 'database', 'Octo');
                        $user   = isAke($confDb, 'user', 'root');
                        $pwd    = isAke($confDb, 'password', 'root');

                        $dsn = str_replace(
                            ['##host##', '##port##', '##database##'],
                            [$host, $port, $db],
                            $dsn
                        );

                        $pdo = new \PDO($dsn, $user, $pwd, $PDOoptions);

                    break;

                    case 'sqlite':
                        $path   = isAke($confDb, 'path', path('app') . '/database/app.db');
                        $dsn = str_replace(
                            '##path##',
                            $path,
                            $dsn
                        );

                        $pdo = new \PDO($dsn, null, null, $PDOoptions);

                    break;
                }

                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

                $app['pdo'] = $pdo;
            }
        }

        loadEvents();

        listening('system.bootstrap');

        services();

        middlewares();

        rights();
    }

    function modelEvent($model, $event, $next = [])
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

    function getRow($instance)
    {
        return make([], $instance);
    }

    function collectify(array $rows = [])
    {
        $collection = [];

        foreach ($rows as $row) {
            $collection[] = make($row);
        }

        return coll($collection);
    }

    function make(array $array = [], $instance = null)
    {
        return lib('ghost', [$array, $instance]);
    }

    function magic($class, array $array = [])
    {
        return lib('magic', [$array, $class]);
    }

    function context($context, array $array = [])
    {
        return lib('context', [$array, $context]);
    }

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

    function shutdown(callable $callable = null)
    {
        $callables = Registry::get('core.shutdown', []);

        if (is_callable($callable)) {
            $callables[] = $callable;
            Registry::set('core.shutdown', $callables);
        } else {
            foreach ($callables as $callable) {
                if (is_callable($callable)) {
                    $callable();
                }
            }
        }
    }

    function tern($a, $b)
    {
        return $a ? $a : $b;
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

    function get($k, $d = null)
    {
        $data   = segment('core');
        $value  = aget($data, $k, $d);

        return value($value);
    }

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

    function getMacro($k, array $args = [], $d = null)
    {
        if (has($k)) {
            $data   = segment('core');
            $cb     = aget($data, $k, $d);

            if (is_callable($cb)) {
                return call_user_func_array($cb, $args);
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

        if ('octodummy' == $res) {
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

    function single($class, $resolver = null, array $args = [])
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

    function middlewares($when = 'before')
    {
        listening('middlewares.booting');

        $middlewares        = Registry::get('core.middlewares', []);
        $request            = make($_REQUEST, "request");
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
                call_user_func_array([$middleware, $method], [$request, context("app")]);
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
            if ($alias == $className) {
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

    function csrf_field($echo = true)
    {
        $tokenName = Config::get('token_name', 'octo_token');
        $token = csrf_make();
        $field = '<input type="hidden" name="' . $tokenName . '" id="' . $tokenName . '" value="' . $token . '">';

        if ($echo) {
            echo $field;
        } else {
            return $field;
        }
    }

    function csrf_make()
    {
        $token = token();

        session('csrf')->setOldToken(session('csrf')->getToken())->setToken($token);

        return $token;
    }

    function csrf_match()
    {
        $tokenName = Config::get('token_name', 'octo_token');

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
            return App::getInstance();
        }

        return App::getInstance()->make($make, $params);
    }

    function injector($make, $params = [])
    {
        return (new Now)->make($make, $params);
    }

    function maker($make, $args = [], $singleton = true)
    {
        static $binds = [];

        $args = !is_array($args) ? $args->toArray() : $args;

        $callable = isAke($binds, $make, null);

        if ($callable && is_callable($callable) && $singleton) {
            return call_user_func_array($callable, $args);
        }

        $ref = new \ReflectionClass($make);
        $canMakeInstance = $ref->isInstantiable();

        if ($canMakeInstance) {
            $maker = $ref->getConstructor();

            if ($maker) {
                if (empty($args)) {
                    $params = $maker->getParameters();

                    $instanceParams = [];

                    foreach ($params as $param) {
                        $classParam = $param->getClass();

                        if ($classParam) {
                            $p = maker($classParam->getName());
                        } else {
                            $p = $param->getDefaultValue();
                        }

                        $instanceParams[] = $p;
                    }

                    if (!empty($instanceParams)) {
                        $i = $ref->newInstanceArgs($instanceParams);
                    } else {
                        $i = $ref->newInstance();
                    }
                } else {
                    $i = $ref->newInstanceArgs($args);
                }

                $binds[$make] = resolver($i);

                return $i;
            } else {
                $i = $ref->newInstance();

                $binds[$make] = resolver($i);

                return $i;
            }
        } else {
            exception('Dic', "The class $make is not intantiable.");
        }

        exception('Dic', "The class $make is not set.");
    }

    function callMethod()
    {
        $args       = func_get_args();
        $object     = array_shift($args);
        $method     = array_shift($args);
        $fnParams   = $args;
        $reflection = new \ReflectionClass(get_class($object));
        $ref        = $reflection->getMethod($method);
        $params     = $ref->getParameters();

        if (empty($args) || count($args) != count($params)) {
            foreach ($params as $param) {
                $classParam = $param->getClass();

                if ($classParam) {
                    $p = maker($classParam->getName());
                } else {
                    $p = $param->getDefaultValue();
                }

                $fnParams[] = $p;
            }
        }

        $closure = $ref->getClosure($object);

        return call_user_func_array($closure, $fnParams);
    }

    function loadFiles($pattern)
    {
        $files = glob($pattern);

        foreach ($files as $file) {
            require_once $file;
        }
    }

    function resolver($object)
    {
        if (is_callable($object)) {
            $object = $object();
        }

        if (is_string($object)) {
            $object = maker($object);
        }

        $class = get_class($object);

        return function () use ($object) {
            return $object;
        };
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

    function startsWith($str, $val)
    {
        return fnmatch($val . '*', $str);
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

        if ('octodummy' == $value) {
            return Config::get($key);
        }

        Config::set($key, $value);
    }

    function tpl($file, $args = [])
    {
        $content = '';

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
        $lng = lng();
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

    function options()
    {
        $options = o();

        $options->macro('set', function ($k, $v) use ($options) {
            $option = em('systemOption')
            ->firstOrCreate(['name' => $k])
            ->setValue($v)
            ->save();

            return $options;
        });

        $options->macro('get', function ($k, $d = null) {
            $option = em('systemOption')
            ->where('name', $k)
            ->first(true);

            return $option ? $option->value : $d;
        });

        $options->macro('has', function ($k) {
            $option = em('systemOption')
            ->where('name', $k)
            ->first(true);

            return $option ? true : false;
        });

        $options->macro('delete', function ($k) {
            $option = em('systemOption')
            ->where('name', $k)
            ->first(true);

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
            ->save();

            return $settings;
        });

        $settings->macro('get', function ($k, $d = null) {
            $k = sha1(forever() . $k);

            $setting = em('systemSetting')
            ->where('name', $k)
            ->first(true);

            return $setting ? $setting->value : $d;
        });

        $settings->macro('has', function ($k) {
            $k = sha1(forever() . $k);

            $setting = em('systemSetting')
            ->where(['name', '=', $k])
            ->first(true);

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

    function on($event, callable $cb, $args = [])
    {
        if (is_callable($event)) {
            $res = call_user_func_array($event, $args);
        } else {
            $res = listen($event, $args);
        }

        return call_user_func_array($cb, [$res]);
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

    function code($text)
    {
        return str_replace(['<', '>'], ['&lt;', '&gt;'], $text);
    }

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

        return maker(\Intervention\Image\ImageManager::class, [$config]);
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

    function singleton($make, $params = [])
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
                $ref = new \ReflectionClass('Octo\\' . $class);
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
                $ref = new \ReflectionClass($to . '\\' . $class);
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

        $fn->macro('call', function () {
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

    function call($callback, array $args)
    {
        if (is_string($callback) && fnmatch('*::*', $callback)) {
            $callback = explode('::', $callback, 2);
        }

        if (is_array($callback) && isset($callback[1]) && is_object($callback[0])) {
            if ($count = count($args)) {
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

    function hash($str)
    {
        if (function_exists('hash_algos')) {
            foreach (['sha512', 'sha384', 'sha256', 'sha224', 'sha1', 'md5'] as $hash) {
                if (in_array($hash, hash_algos())) {
                    return \hash($hash, $str);
                }
            }
        }

        return sha1($token_base);
    }

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

    function model($type, $data = [])
    {
        $class = 'Octo\\' . Strings::camelize($type);

        if (!class_exists($class)) {
            $classCode = File::read(__DIR__ . DS . 'object.php');
            list($dummy, $classCode) = explode('namespace ' . __NAMESPACE__ . ';', $classCode, 2);

            $classCode = str_replace_first('class Object', 'class ' . Strings::camelize($type), $classCode);

            $code = "namespace " . __NAMESPACE__ . " { $classCode };";

            eval($code);
        }

        return new $class($data);
    }

    function isRoute($name, array $args = [])
    {
        return Routes::isRoute($name, $args);
    }

    function urlFor($name, array $args = [])
    {
        $url = Routes::url($name, $args);

        if (!$url) {
            $url = $name;
        }

        return WEBROOT . '/' . trim($url, '/');
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

                return Router::render(
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

        while (0 < $seconds) {
            $seconds--;
            sleep(1);
        }

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

        return after($closure, $args, $timestamp);
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

        return after(
            $closure,
            [serializeClosure($callback), $args, $next, $future],
            $next
        );
    }

    function withoutError(callable $callback, array $args = [])
    {
        set_error_handler(function () {});

        $result = call($callback, $args);

        restore_error_handler();

        return $result;
    }

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

    function polymorph(Object $object)
    {
        return em(
            $object->db(),
            $object->polymorph_type
        )->find((int) $object->polymorph_id);
    }

    function polymorphs(Object $object, $parent)
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

    function tsToTime($timestamp, $tz = null)
    {
        return Date::createFromTimestamp($timestamp, $tz);
    }

    function event($name, callable $closure = null, array $args = [])
    {
        $events = Registry::get('core.events', []);

        $eventBag = isAke($events, $name, []);

        if (!is_callable($closure)) {
            if (!empty($eventBag)) {
                $res = [];

                foreach ($eventBag as $event) {
                    $res[] = call_user_func_array($event, $args);
                }

                return $res;
            }
        } else {
            if (!isset($events[$name])) {
                $events[$name] = [];
            }

            $events[$name][] = $closure;

            Registry::set('core.events', $events);

            $fluent = o();

            $fluent->macro('new', function($name, callable $closure = null, array $args = []) {
                return event($name, $closure, $args);
            });

            $fluent->macro('fire', function($name, array $args = []) {
                return event($name, null, $args);
            });

            return $fluent;
        }
    }

    function actual($key = null, $value = null)
    {
        $actuals = Registry::get('core.actuals', []);

        if (is_null($key)) {
            return $actuals;
        }

        if (is_null($value)) {
            return isAke($actuals, $key, null);
        }

        $actuals[$key] = $value;

        Registry::set('core.actuals', $actuals);
    }

    function fire($event, array $args = [])
    {
        return event($event, null, $args);
    }

    function listening($event, $concern = null, $return = false)
    {
        if (Fly::has($event)) {
            $result = Fly::listen($event, $concern);

            if ($return) {
                return $result;
            }
        }

        return $concern;
    }

    function subscribe($event, callable $callable, $back = null)
    {
       Fly::on($event, $callable);

       return $back;
    }

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
        return call_user_func_array('\\app', func_get_args());
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

    function createModel(Octalia $model, array $fields = [])
    {
        if ($model->count() == 0) {
            $row = [];

            if (!empty($fields)) {
                foreach ($fields as $field) {
                    $row[$field] = $field;
                }
            }

            $model->create($row)->save();
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

    function cache_start($default = null)
    {
        $bt = debug_backtrace();
        array_shift($bt);
        $last = array_shift($bt);
        $key = sha1(serialize($last) . File::read($last['file']) . filemtime($last['file']));

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

    function multiCurl($data, $options = [])
    {
        $curly = [];
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

    function once($k, $v = 'octodummy')
    {
        $key = sha1(forever()) . '.' . Strings::urlize($k, '.');

        if ('octodummy' != $v) {
            return fmr('once')->set($key, $v);
        }

        $value = fmr('once')->get($key);

        fmr('once')->del($key);

        return $value;
    }

    function keep($k, $v = 'octodummy')
    {
        $key = sha1(forever()) . '.' . Strings::urlize($k, '.');

        if ('octodummy' != $v) {
            return fmr('keep')->set($key, $v);
        }

        return fmr('keep')->get($key);
    }

    function unkeep($k)
    {
        $key = sha1(forever()) . '.' . Strings::urlize($k, '.');

        return fmr('keep')->del($key);
    }

    function engine($database = 'core', $table = 'core', $driver = 'odb')
    {
        $engine = Config::get('octalia.engine', $driver);

        if (function_exists('\\Octo\\' . $engine)) {
            return call_user_func_array('\\Octo\\' . $engine, [$database, $table]);
        } else {
            exception('core', "Engine $engine does not exist.");
        }
    }

    function entity($model, array $data = [])
    {
        return em($model)->model($data);
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

    function guest()
    {
        $u = session('web')->getUser();

        return is_null($u);
    }

    function role()
    {
        if (!guest()) {
            $user = session('web')->getUser();
            $role = em('systemRole')->find((int) $user['role_id']);

            if ($role) {
                return $role;
            }
        }

        return o()->setLabel('guest');
    }

    function dom()
    {
        require_once __DIR__ . DS . 'dom.php';
    }

    function route($route = null)
    {
        if ($route) {
            Registry::set('core.route', $route);
        }

        return Registry::get('core.route');
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

            $gate->macro('allows', function ($k) {
                $key = 'gate.' . Strings::urlize($k, '.');

                if (!guest()) {
                    $cb = Registry::get($key);

                    if ($cb) {
                        return $cb(session('web')->getUser());
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

    function mailto(array $config)
    {
        $mailer     = mailer();

        $to         = isAke($config, 'to', null);
        $toName     = isAke($config, 'to_name', $to);

        $from       = isAke($config, 'from', appenv('MAILER_FROM', 'admin@localhost'));
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

    function message()
    {
        require_once (__DIR__ . '/swift/swift_required.php');

        return \Swift_Message::newInstance();
    }

    function mailer()
    {
        require_once __DIR__ . '/swift/swift_required.php';

        $mailer = appenv('MAILER_DRIVER', 'php'); /* smtp, sendmail, php */

        switch ($mailer) {
            case 'smtp':
                $transport = \Swift_SmtpTransport::newInstance(
                    appenv('SMTP_HOST', 'localhost'),
                    appenv('SMTP_PORT', 443),
                    'ssl'
                )
                ->setUsername(appenv('SMTP_USER', 'root'))
                ->setPassword(appenv('SMTP_PASSWORD', 'root'));

                break;
            case 'sendmail':
                $transport = \Swift_SendmailTransport::newInstance(
                    appenv('SENDMAIL_PATH', '/usr/lib/sendmail')
                );

                break;
            case 'php':
            default:
                $transport = \Swift_MailTransport::newInstance();

                break;
        }

        return \Swift_Mailer::newInstance($transport);
    }

    function memory()
    {
        $memory = Registry::get('db.memory');

        if (!$memory) {
            $memory = new \PDO('sqlite::memory:');
            $memory->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $memory->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

            Registry::set('db.memory', $memory);
        }

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

    function myarray($name, $row = null)
    {
        $arrays = Registry::get('core.arrays', []);

        $array = isAke($name, $arrays, []);

        if ($row) {
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
        return coll(myarray($name));
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
            aset($output, $key, $value);

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

    function resolverClass($class, $sep = '@')
    {
        return function() use ($class, $sep) {
            $segments   = explode($sep, $class);
            $method     = count($segments) == 2 ? $segments[1] : 'supply';
            $callable   = [app($segments[0]), $method];
            $data       = func_get_args();

            return call_user_func_array($callable, $data);
        };
    }

    function strArray($strArray)
    {
        return !is_array($strArray)
        ? strstr($strArray, ',')
            ? explode(
                ',',
                str_replace(
                    [' ,', ', '],
                    '',
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

    function auth($em = 'user')
    {
        return guard($em);
    }

    function load_entity($class)
    {
        Autoloader::entity($class);
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

        $cache->macro('watch', function ($k, callable $exists = null, callable $notExists = null) use ($mc) {
            if ($exists instanceof Dyn) {
                $exists = null;
            }

            if ($notExists instanceof Dyn) {
                $notExists = null;
            }

            if ($mc->has($k)) {
                if (is_callable($exists)) {
                    return $exists($mc->get($k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        });

        $cache->macro('aged', function ($k, callable $c, $maxAge = null, $args = []) use ($mc) {
            if ($maxAge instanceof Dyn) {
                $maxAge = null;
            }

            if ($args instanceof Dyn) {
                $args = [];
            }

            $keyAge = $k . '.maxage';
            $v      = $mc->get($k);

            if ($v) {
                if (is_null($maxAge)) {
                    return $v;
                }

                $age = $mc->get($keyAge);

                if (!$age) {
                    $age = $maxAge - 1;
                }

                if ($age >= $maxAge) {
                    return $v;
                } else {
                    $mc->delete($k);
                    $mc->delete($keyAge);
                }
            }

            $data = call_user_func_array($c, $args);

            $mc->set($k, $data);

            if (!is_null($maxAge)) {
                if ($maxAge < 3600 * 24 * 30) {
                    $maxAge = ($maxAge * 60) + microtime(true);
                }

                $mc->set($keyAge, $maxAge);
            }

            return $data;
        });

        $cache->macro('incr', function ($k, $by = 1) use ($mc) {
            if ($by instanceof Dyn) {
                $by = 1;
            }

            if (!$mc->has($k)) {
                $old = 0;
            } else {
                $old = $mc->get($k);
            }

            $new = $old + $by;

            $mc->set($k, $new);

            return $new;
        });

        $cache->macro('decr', function ($k, $by = 1) use ($mc) {
            if ($by instanceof Dyn) {
                $by = 1;
            }

            if (!$mc->has($k)) {
                $old = 0;
            } else {
                $old = $mc->get($k);
            }

            $new = $old - $by;

            $mc->set($k, $new);

            return $new;
        });

        $cache->macro('has', function ($k) use ($mc) {
            $val = $mc->get($k);

            return $mc->getResultCode() != \Memcached::RES_NOTFOUND;
        });

        $cache->macro('getOr', function ($k, callable $c, $e = 0) use ($mc) {
            if ($e instanceof Dyn) {
                $e = 0;
            }

            $val = $mc->get($k);

            if ($mc->getResultCode() == \Memcached::RES_NOTFOUND) {
                $res = $c();

                $mc->set($k, $res, (int) $e);

                return $res;
            } else {
                return $val;
            }
        });

        return $cache;
    }

    function mc($host = 'localhost', $port = 11211, $ns = 'octo.core')
    {
        $i = maker('Memcached', [$ns]);

        $i->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);

        if (empty($i->getServerList())) {
            $i->addServer($host, $port);
        }

        return $i;
    }

    function guard($ns = 'web', $em = 'user')
    {
        $class = o();

        $class->macro('policy', function ($policy, callable $callable) use ($class) {
            $policies = Registry::get('guard.policies', []);
            $policies[$policy] = $callable;

            Registry::set('guard.policies', $policies);

            return $class;
        });

        $class->macro('login', function ($user) use ($ns) {
            $user = !is_array($user) ? $user->toArray() : $user;

            session($ns)->setUser($user);
        });

        $class->macro('logout', function () use ($ns) {
            session($ns)->erase('user');
        });

        $class->macro('id', function () use ($ns) {
            $user = session($ns)->getUser();

            if ($user) {
                return $user['id'];
            }

            return null;
        });

        $class->macro('email', function () use ($ns) {
            $user = session($ns)->getUser();

            if ($user) {
                return isAke($user, 'email', null);
            }

            return null;
        });

        $class->macro('on', function () use ($class) {
            return call_user_func_array([$class, 'policy'], func_get_args());
        });

        $class->macro('user', function ($model = true) use ($ns, $em) {
            $user = session($ns)->getUser();

            if ($user && $model) {
                return em($em)->find((int) $user['id']);
            }

            return $user;
        });

        $class->macro('logWithId', function ($id, $route = 'home') use ($ns, $em)  {
            $user = em($em)->find((int) $id);

            if ($user) {
                session($ns)->setUser($user->toArray());
                go(urlFor($route));
            } else {
                ptption('guard', "Unknown id.");
            }
        });

        $class->macro('logByUser', function ($user, $route = 'home') use ($ns) {
            $user = !is_array($user) ? $user->toArray() : $user;
            session($ns)->setUser($user);
            go(urlFor($route));
        });

        $class->macro('allows', function () use ($ns) {
            $user = session($ns)->getUser();

            if ($user) {
                $user = item($user);

                $args = func_get_args();

                $policy = array_shift($args);

                $policies = Registry::get('guard.policies', []);

                $policy = isAke($policies, $policy, null);

                if (is_callable($policy)) {
                    return call_user_func_array($policy, array_merge([$user], $args));
                }
            }

            return false;
        });

        return $class;
    }

    function be($user, $ns = 'web')
    {
        $user = !is_array($user) ? $user->toArray() : $user;

        session($ns)->setUser($user);
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
