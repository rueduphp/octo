<?php
    namespace Octo;

    if (file_exists(__DIR__ . "/../vendor/autoload.php")) {
        include_once __DIR__ . "/../vendor/autoload.php";
    }

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

        set_exception_handler(function($exception) {
            $file = $exception->getFile();
            $tab = explode(DS, $file);
            $file = end($tab);

            if ('bootstrap.php' != $file && 'index.php' != $file) {
                vd('EXCEPTION', $exception, debug_backtrace());
            } else {
                view('<h1>Octo Report Error</h1><div class="alert alert-danger">'.$exception->getMessage().'</div>');
            }
        });

        set_error_handler(function($type, $message, $file, $line) {
            $exception      = new \ErrorException($message, $type, 0, $file, $line);

            $typeError      = in_array(
                $type,
                [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]
            ) ? 'FATAL ERROR' : 'ERROR';

            if (!fnmatch('Undefined offset:*', $message) && !fnmatch('*StreamConnection.php*', $file) && !fnmatch('*connected*', $message)) {
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

                if ($file == realpath(__FILE__) && fnmatch("*mkdir(): Permission denied*", $message)) {
                    throw new Exception('mkdir');
                }

                if ($file == realpath(__FILE__) && fnmatch("*chmod(): Operation not permitted*", $message)) {
                    throw new Exception('chmod');
                }

                dd(
                    '<div style="text-align: center; padding: 5px; color: black; border: solid 1px black; background: #f2f2f2;">' . $typeError . '</div>',
                    '<div style="padding: 5px; color: red; border: solid 1px red; background: #f2f2f2;">' . $message . '</div>',
                    '<div style="padding: 5px; color: navy; border: solid 1px navy; background: #f2f2f2;">' . $file . ' [<em>line: <u>' . $line . '</u></em>]</div>',
                    '<div style="font-family: Consolas; font-weight: 400; padding: 5px; color: green; border: solid 1px green; background: #f2f2f2;">' . implode("\n", $codeLines) . '</div>',
                    '<div style="text-align: center; padding: 5px; color: black; border: solid 1px black; background: #f2f2f2;">BACKTRACE</div>',
                    '<div style="padding: 5px; color: purple; border: solid 1px purple; background: #f2f2f2;">' . displayCodeLines() . '</div>'
                );
            }
        });

        register_shutdown_function(function() {
            $exception = error_get_last();

            if ($exception) {
                $message    = isAke($exception, 'message', 'NA');
                $type       = isAke($exception, 'type', 1);
                $line       = isAke($exception, 'line', 1);
                $file       = isAke($exception, 'file');
                $exception  = new \ErrorException($message, $type, 0, $file, $line);

                $typeError  = in_array(
                    $type,
                    [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]
                ) ? 'FATAL ERROR' : 'ERROR';

                if (fnmatch('*Allowed memory size*', $message)) {
                    dd($file . '['.$message.']', 'Ligne:' . $line);
                } elseif (!fnmatch('*undefinedVariable*', $message) && !fnmatch('*connected*', $message) && file_exists($file)) {
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

                    dd(
                        '<div style="text-align: center; padding: 5px; color: black; border: solid 1px black; background: #f2f2f2;">' . $typeError . '</div>',
                        '<div style="padding: 5px; color: red; border: solid 1px red; background: #f2f2f2;">' . $message . '</div>',
                        '<div style="padding: 5px; color: navy; border: solid 1px navy; background: #f2f2f2;">' . $file . ' [<em>line: <u>' . $line . '</u></em>]</div>',
                        '<div style="font-family: Consolas; font-weight: 400; padding: 5px; color: green; border: solid 1px green; background: #f2f2f2;">' . implode("\n", $codeLines) . '</div>',
                        '<div style="text-align: center; padding: 5px; color: black; border: solid 1px black; background: #f2f2f2;">BACKTRACE</div>',
                        '<div style="padding: 5px; color: purple; border: solid 1px purple; background: #f2f2f2;">' . displayCodeLines() . '</div>'
                    );
                }
            }
        });
    }

    spl_autoload_register(function ($class) {
        $tab    = explode('\\', $class);
        $ns     = array_shift($tab);
        $lib    = array_shift($tab);

        if ('Octo' == $ns && !class_exists($class)) {
            if (!empty($tab)) {
                $file = __DIR__ . DS . strtolower($lib) . DS . implode(DS, $tab) . '.php';
            } else {
                $file = __DIR__ . DS . strtolower($lib) . '.php';
            }

            if (file_exists($file)) {
                require_once $file;

                return;
            }
        }

        $check = str_replace('\\', '', $class);

        if (fnmatch('OctoLib*', $check)) {
            $dir = realpath(path('app') . '/lib');
            $rep = str_replace('Octo\\Lib\\', '', $class);
            $name = Strings::uncamelize($rep);
            $file = $dir;

            $check = count(explode('\\', $rep));

            if (2 <= $check) {
                $tab = explode('\\', $rep);
            } else {
                $tab = explode('_', $name);
            }

            foreach ($tab as $part) {
                $file .= DS . ucwords($part);
            }

            $file .= '.php';

            if (File::exists($file)) {
                require_once $file;
            }
        } elseif (fnmatch('OctoModel*', $check) && 'OctoModel' != $check) {
            $repo = Strings::uncamelize(str_replace('OctoModel', '', $check));

            modelFacade($repo, $class);
        } elseif (fnmatch('OctoDb*', $check) && 'OctoDb' != $check) {
            $repo = Strings::uncamelize(str_replace('OctoDb', '', $check));

            modelFacade($repo, $class);
        } elseif (fnmatch('OctoData*', $check) && 'OctoData' != $check) {
            $repo = Strings::uncamelize(str_replace('OctoData', '', $check));

            modelFacade($repo, $class);
        } elseif (fnmatch('OctoOctal*', $check) && 'OctoOctal' != $check) {
            $repo = Strings::uncamelize(str_replace('OctoOctal', '', $check));

            octalFacade($repo, $class);
        }
    });

    function view($html, $code = 200, $title = 'Octo')
    {
        $tpl = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . '</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.5.0/css/font-awesome.min.css"><link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Oswald:100,300,400,700,900"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css"><style>body{font-family:"Oswald";}</style></head><body id="app-layout"><div class="container-fluid">##html##</div><script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script></body></html>';

        $html = str_replace('##html##', $html, $tpl);

        abort($code, $html);
    }

    function redirect($url = '/')
    {
        if (!fnmatch('*://*', $url)) {
            $url = WEBROOT . '/' . trim($url, '/');
        }

        header("Location: $url");

        exit(1);
    }

    function controller()
    {
        return Registry::get('app.controller');
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

    function odb($db, $table, $driver = null)
    {
        return lib('Octalia', [$db, $table, $driver]);
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
                        return odb("' . $database . '", $table);
                    } elseif (count($args) == 1) {
                        $id = array_shift($args);

                        if (is_numeric($id)) {
                            return odb("' . $database . '", $table)->find($id);
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

            return call_user_func_array([odb($db, $table), $method], $args);
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
            $command .= '"text": ' . json_encode($text) . ', "html": ' . json_encode($html) . '';
        } else {
            if ($text) {
                $command .= '"text": ' . json_encode($text) . '';
            }

            if ($html) {
                $command .= '"html": ' . json_encode($html) . '';
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

    function log($message, $type = 'INFO', $ns = 'core')
    {
        if (is_array($message)) $message = implode(PHP_EOL, $message);

        $type = Strings::upper($type);

        $db = odb('logs', $ns);
        $db->create([
            'message'   => $message,
            'type'      => $type
        ])->save();
    }

    function getLogs($type = 'INFO', $ns = 'core')
    {
        $type = Strings::upper($type);

        $db = odb('logs', $ns);

        return $db->where(['type', '=', $type])->sortByDesc('id')->get();
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
        } else {
            return isAke(oclean($_SERVER), $k, $d);
        }
    }

    function post($k = null, $d = null)
    {
        if (empty($k)) {
            return Post::notEmpty();
        } else {
            return Post::get($k, $d);
        }
    }

    function request($k = null, $d = null)
    {
        if (empty($k)) {
            return lib('object', [oclean($_REQUEST)]);
        } else {
            return isAke(oclean($_REQUEST), $k, $d);
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
        if (Arrays::exists($field, $_FILES)) {
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

    function upload($field, $dest = null)
    {
        if (Arrays::exists($field, $_FILES)) {
            $fileupload         = $_FILES[$field]['tmp_name'];
            $fileuploadName     = $_FILES[$field]['name'];

            if (strlen($fileuploadName)) {
                $data = file_get_contents($fileupload);

                if (!strlen($data)) {
                    return null;
                }

                if (empty($dest)) {
                    $tab    = explode(".", $fileuploadName);
                    $bucket = new Bucket(SITE_NAME, URLSITE . 'bucket');
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

    function cut($start, $end, $string)
    {
        if (strstr($string, $start) && strstr($string, $end) && isset($start) && isset($end)) {
            list($dummy, $string) = explode($start, $string, 2);

            if (isset($string) && strstr($string, $end)) {
                list($string, $dummy) = explode($end, $string, 2);

                return $string;
            }
        }

        return null;
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

    function isAke($tab, $key, $default = [])
    {
        if (true === is_object($tab)) {
            $tab = (array) $tab;
        }

        return is_array($tab) ?
            Arrays::isAssoc($tab) ?
                Arrays::exists($key, $tab) ?
                    $tab[$key] :
                $default :
            $default :
        $default;
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
                \Locale::acceptFromHttp($_SERVER["HTTP_ACCEPT_LANGUAGE"])
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

    function lib($lib, $args = [])
    {
        $class = '\\Octo\\' . Strings::camelize($lib);

        if (!class_exists($class)) {
            $file = __DIR__ . DS . Strings::lower($lib) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        }

        return (new App)->make($class, $args);
    }

    function factory()
    {
        $factory    = lib('OctaliaFactory')->construct(\Faker\Factory::create('fr_FR'));
        $arguments  = func_get_args();

        $db = $arguments[0];

        if (isset($arguments[1]) && is_string($arguments[1])) {
            return $factory->of($db, $arguments[1])->times(isset($arguments[2]) ? $arguments[2] : 1);
        } elseif (isset($arguments[1])) {
            return $factory->of($db)->times($arguments[1]);
        } else {
            return $factory->of($db);
        }
    }

    function event()
    {
        return lib('event');
    }

    function auth($ns = 'web')
    {
        return lib('guard', [$ns]);
    }

    function abort($code = 403, $message = 'Forbidden')
    {
        $headerMessage = Api::getMessage($code);

        if (!headers_sent()) {
            header("HTTP/1.0 $code $headerMessage");
        }

        die($message);
    }

    function path($k = null, $v = null, $d = null)
    {
        $paths = (new Now)->get('octo.paths', []);

        if (is_null($k)) {
            return $paths;
        }

        if (is_null($v)) {
            return isAke($paths, $k, $d);
        }

        $paths[$k] = $v;

        (new Now)->set('octo.paths', $paths);
    }

    function paths()
    {
        return Registry::get('octo.paths', []);
    }

    function systemBoot($dir)
    {
        forever();

        define('OCTO_MAX', 9223372036854775808);
        define('OCTO_MIN', -9223372036854775808);

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

        if (!class_exists('Octo\Octia')) {
            Alias::facade('Octia', 'Manager', 'Illuminate\Database\Capsule');
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

        if (!class_exists('Octo\Stubber')) {
           staticFacade('\\Kahlan\\Plugin\\Stub', 'Stubber');
        }

        if (!class_exists('Octo\Refactor')) {
           staticFacade('\\Kahlan\\Plugin\\Monkey', 'Refactor');
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
            defined('WEBROOT')  || define('WEBROOT', Registry::get('octo.subdir', '/'));

            date_default_timezone_set(Config::get('timezone', 'Europe/Paris'));

            if (!IS_CLI) {
                $protocol = 'http';

                if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                    $protocol .= 's';
                }

                if ($_SERVER["SERVER_PORT"] != "80") {
                    $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . ':' . $_SERVER["SERVER_PORT"] . WEBROOT;
                } else {
                    $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . WEBROOT;
                }

                defined('URLSITE')  || define('URLSITE', $urlSite);
            }

            ini_set('error_log', path('storage') . DS . 'logs' . DS . 'error.log');

            lib('flood')->check();
        }

        define('OCTO_DAY_KEY', sha1((OCTO_MAX / 7) + strtotime('today') . forever()));

        path('public', realpath($dir));

        bag('Post');
        Post::fill(oclean($_POST));

        register_shutdown_function(function () {
            on('shutdown', function () {
                Queue::listen();
                Later::shutdown();
            });
        });
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
        $token = csrf_make();
        $field = '<input type="hidden" name="octo_token" id="octo_token" value="' . $token . '">';

        if ($echi) {
            echo $field;
        }

        return $field;
    }

    function csrf_make()
    {
        $token = token();

        session('csrf')->setOldToken(session('csrf')->getToken())->setToken($token);

        return $token;
    }

    function slug($title, $separator = '-')
    {
        return Strings::urlize($title, $separator);
    }

    function findMethod($method)
    {
        $reflFunc = new \ReflectionFunction($method);
        echo $reflFunc->getFileName() . ':' . $reflFunc->getStartLine();

        exit;
    }

    function app($make = null, $params = [])
    {
        if (empty($make)) {
            return App::getInstance();
        }

        return App::getInstance()->make($make, $params);
    }

    function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return File::value($default);
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

        return session()->getOr($key, function () use ($email, $size, &$is_gravatar_loaded, &$gravatar) {
            $account = null;

            if (is_numeric($email)) {
                $account = System::Account()->find((int) $email);

                if ($account) {
                    $email = $account->getEmail();
                }
            } else {
                $account = System::Account()->where(['email', '=', Strings::lower($email)])->first(true);
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

    function is_admin($user = null)
    {
        $user = empty($user) ? auth()->user() : $user;

        if ($role_id    = isAke($user, 'role_id', null)) {
            $role       = System::Role()->find((int) $role_id);

            return $role->name == 'admin';
        }

        return false;
    }

    function octo_role($user = null)
    {
        $user = empty($user) ? auth()->user() : $user;

        if ($role_id    = isAke($user, 'role_id', null)) {
            $role       = System::Role()->find((int) $role_id);

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
            $account = System::Account()
            ->find((int) $account_id);

            if ($account) {
                $permission = System::Permission()
                ->where(['action', '=', $action])
                ->where(['account_id', '=', (int) $account_id])
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

    function free_space($path = null)
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

    function time_ago(Time $dt, $time_zone, $lang_code = 'en')
    {
        $sec = $dt->diffInSeconds(Time::now($time_zone));
        Time::setLocale($lang_code);
        $string = Strings::ucfirst(Time::now($time_zone)->subSeconds($sec)->diffForHumans());

        return $string;
    }

    function ohash($str, $salt = null)
    {
        $salt = empty($salt) ? osalt() : $salt;
        $hash = hash($str . $salt);

        return strrev($hash);
    }

    function osalt()
    {
        return strrev(hash(token()));
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
                    return File::value($default);
                }

                $result = Arrays::pluck($target, $key);

                return in_array('*', $key) ? Arrays::collapse($result) : $result;
            }

            if (Arrays::accessible($target) && Arrays::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return File::value($default);
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

        return Config::set($key, $value);
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

    function _($k, $a = [], $d = null)
    {
        $lng = lng();

        $file = path('translations') . DS . $lng . '.php';

        if (!file_exists($file)) {
            $lng    = Config::get('default.language', 'fr');
            $file   = path('translations') . DS . $lng . '.php';
        }

        try {
            Lang::load($file, $lng);

            return Lang::get($lng, $k, $a, $d);
        } catch (\Exception $e) {
            return $d;
        }
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

    function listen($event, $args = [])
    {
        $events = Registry::get('core_events', []);

        $e = isAke($events, $event, null);

        if ($e) {
            return call_user_func_array($e, $args);
        }

        return null;
    }

    function on($event, callable $cb, $args = [])
    {
        $res = listen($event, $args);

        return call_user_func_array($cb, [$res]);
    }

    function broadcast($event, callable $cb)
    {
        $events = Registry::get('core_events', []);

        $events[$event] = $cb;

        Registry::set('core_events', $events);
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

    function etag($time)
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

    function isFakeMail($mail)
    {
        $fakes = ['0815.ru0clickemail.com', '0wnd.net', '0wnd.org', '10minutemail.com', '20minutemail.com', '2prong.com', '3d-painting.com', '4warding.com', '4warding.net', '4warding.org', '9ox.net', 'a-bc.net', 'amilegit.com', 'anonbox.net', 'anonymbox.com', 'antichef.com', 'antichef.net', 'antispam.de', 'baxomale.ht.cx', 'beefmilk.com', 'binkmail.com', 'bio-muesli.net', 'bobmail.info', 'bodhi.lawlita.com', 'bofthew.com', 'brefmail.com', 'bsnow.net', 'bugmenot.com', 'bumpymail.com', 'casualdx.com', 'chogmail.com', 'cool.fr.nf', 'correo.blogos.net', 'cosmorph.com', 'courriel.fr.nf', 'courrieltemporaire.com', 'curryworld.de', 'cust.in', 'dacoolest.com', 'dandikmail.com', 'deadaddress.com', 'despam.it', 'devnullmail.com', 'dfgh.net', 'digitalsanctuary.com', 'discardmail.com', 'discardmail.de', 'disposableaddress.com', 'disposemail.com', 'dispostable.com', 'dm.w3internet.co.uk example.com', 'dodgeit.com', 'dodgit.com', 'dodgit.org', 'dontreg.com', 'dontsendmespam.de', 'dump-email.info', 'dumpyemail.com', 'e4ward.com', 'email60.com', 'emailias.com', 'emailinfive.com', 'emailmiser.com', 'emailtemporario.com.br', 'emailwarden.com', 'ephemail.net', 'explodemail.com', 'fakeinbox.com', 'fakeinformation.com', 'fastacura.com', 'filzmail.com', 'fizmail.com', 'frapmail.com', 'garliclife.com', 'get1mail.com', 'getonemail.com', 'getonemail.net', 'girlsundertheinfluence.com', 'gishpuppy.com', 'great-host.in', 'gsrv.co.uk', 'guerillamail.biz', 'guerillamail.com', 'guerillamail.net', 'guerillamail.org', 'guerrillamail.com', 'guerrillamailblock.com', 'haltospam.com', 'hotpop.com', 'ieatspam.eu', 'ieatspam.info', 'ihateyoualot.info', 'imails.info', 'inboxclean.com', 'inboxclean.org', 'incognitomail.com', 'incognitomail.net', 'ipoo.org', 'irish2me.com', 'jetable.com', 'jetable.fr.nf', 'jetable.net', 'jetable.org', 'junk1e.com', 'kaspop.com', 'kulturbetrieb.info', 'kurzepost.de', 'lifebyfood.com', 'link2mail.net', 'litedrop.com', 'lookugly.com', 'lopl.co.cc', 'lr78.com', 'maboard.com', 'mail.by', 'mail.mezimages.net', 'mail4trash.com', 'mailbidon.com', 'mailcatch.com', 'maileater.com', 'mailexpire.com', 'mailin8r.com', 'mailinator.com', 'mailinator.net', 'mailinator2.com', 'mailincubator.com', 'mailme.lv', 'mailnator.com', 'mailnull.com', 'mailzilla.org', 'mbx.cc', 'mega.zik.dj', 'meltmail.com', 'mierdamail.com', 'mintemail.com', 'moncourrier.fr.nf', 'monemail.fr.nf', 'monmail.fr.nf', 'mt2009.com', 'mx0.wwwnew.eu', 'mycleaninbox.net', 'mytrashmail.com', 'neverbox.com', 'nobulk.com', 'noclickemail.com', 'nogmailspam.info', 'nomail.xl.cx', 'nomail2me.com', 'no-spam.ws', 'nospam.ze.tc', 'nospam4.us', 'nospamfor.us', 'nowmymail.com', 'objectmail.com', 'obobbo.com', 'onewaymail.com', 'ordinaryamerican.net', 'owlpic.com', 'pookmail.com', 'proxymail.eu', 'punkass.com', 'putthisinyourspamdatabase.com', 'quickinbox.com', 'rcpt.at', 'recode.me', 'recursor.net', 'regbypass.comsafe-mail.net', 'safetymail.info', 'sandelf.de', 'saynotospams.com', 'selfdestructingmail.com', 'sendspamhere.com', 'shiftmail.com', '****mail.me', 'skeefmail.com', 'slopsbox.com', 'smellfear.com', 'snakemail.com', 'sneakemail.com', 'sofort-mail.de', 'sogetthis.com', 'soodonims.com', 'spam.la', 'spamavert.com', 'spambob.net', 'spambob.org', 'spambog.com', 'spambog.de', 'spambog.ru', 'spambox.info', 'spambox.us', 'spamcannon.com', 'spamcannon.net', 'spamcero.com', 'spamcorptastic.com', 'spamcowboy.com', 'spamcowboy.net', 'spamcowboy.org', 'spamday.com', 'spamex.com', 'spamfree24.com', 'spamfree24.de', 'spamfree24.eu', 'spamfree24.info', 'spamfree24.net', 'spamfree24.org', 'spamgourmet.com', 'spamgourmet.net', 'spamgourmet.org', 'spamherelots.com', 'spamhereplease.com', 'spamhole.com', 'spamify.com', 'spaminator.de', 'spamkill.info', 'spaml.com', 'spaml.de', 'spammotel.com', 'spamobox.com', 'spamspot.com', 'spamthis.co.uk', 'spamthisplease.com', 'speed.1s.fr', 'suremail.info', 'tempalias.com', 'tempemail.biz', 'tempemail.com', 'tempe-mail.com', 'tempemail.net', 'tempinbox.co.uk', 'tempinbox.com', 'tempomail.fr', 'temporaryemail.net', 'temporaryinbox.com', 'thankyou2010.com', 'thisisnotmyrealemail.com', 'throwawayemailaddress.com', 'tilien.com', 'tmailinator.com', 'tradermail.info', 'trash2009.com', 'trash-amil.com', 'trashmail.at', 'trash-mail.at', 'trashmail.com', 'trash-mail.com', 'trash-mail.de', 'trashmail.me', 'trashmail.net', 'trashymail.com', 'trashymail.net', 'tyldd.com', 'uggsrock.com', 'wegwerfmail.de', 'wegwerfmail.net', 'wegwerfmail.org', 'wh4f.org', 'whyspam.me', 'willselfdestruct.com', 'winemaven.info', 'wronghead.com', 'wuzupmail.net', 'xoxy.net', 'yogamaven.com', 'yopmail.com', 'yopmail.fr', 'yopmail.net', 'yuurok.com', 'zippymail.info', 'jnxjn.com', 'trashmailer.com', 'klzlk.com', 'nospamforus','kurzepost.de', 'objectmail.com', 'proxymail.eu', 'rcpt.at', 'trash-mail.at', 'trashmail.at', 'trashmail.me', 'trashmail.net', 'wegwerfmail.de', 'wegwerfmail.net', 'wegwerfmail.org', 'jetable', 'link2mail', 'meltmail', 'anonymbox', 'courrieltemporaire', 'sofimail', '0-mail.com', 'moburl.com', 'get2mail', 'yopmail', '10minutemail', 'mailinator', 'dispostable', 'spambog', 'mail-temporaire','filzmail','sharklasers.com', 'guerrillamailblock.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.biz', 'guerrillamail.org', 'guerrillamail.de','mailmetrash.com', 'thankyou2010.com', 'trash2009.com', 'mt2009.com', 'trashymail.com', 'mytrashmail.com','mailcatch.com','trillianpro.com','junk.','joliekemulder','lifebeginsatconception','beerolympics','smaakt.naar.gravel','q00.','dispostable','spamavert','mintemail','tempemail','spamfree24','spammotel','spam','mailnull','e4ward','spamgourmet','mytempemail','incognitomail','spamobox','mailinator.com', 'trashymail.com', 'mailexpire.com', 'temporaryinbox.com', 'MailEater.com', 'spambox.us', 'spamhole.com', 'spamhole.com', 'jetable.org', 'guerrillamail.com', 'uggsrock.com', '10minutemail.com', 'dontreg.com', 'tempomail.fr', 'TempEMail.net', 'spamfree24.org', 'spamfree24.de', 'spamfree24.info', 'spamfree24.com', 'spamfree.eu', 'kasmail.com', 'spammotel.com', 'greensloth.com', 'spamspot.com', 'spam.la', 'mjukglass.nu', 'slushmail.com', 'trash2009.com', 'mytrashmail.com', 'mailnull.com', 'jetable.org','10minutemail.com', '20minutemail.com', 'anonymbox.com', 'beefmilk.com', 'bsnow.net', 'bugmenot.com', 'deadaddress.com', 'despam.it', 'disposeamail.com', 'dodgeit.com', 'dodgit.com', 'dontreg.com', 'e4ward.com', 'emailias.com', 'emailwarden.com', 'enterto.com', 'gishpuppy.com', 'goemailgo.com', 'greensloth.com', 'guerrillamail.com', 'guerrillamailblock.com', 'hidzz.com', 'incognitomail.net ', 'jetable.org', 'kasmail.com', 'lifebyfood.com', 'lookugly.com', 'mailcatch.com', 'maileater.com', 'mailexpire.com', 'mailin8r.com', 'mailinator.com', 'mailinator.net', 'mailinator2.com', 'mailmoat.com', 'mailnull.com', 'meltmail.com', 'mintemail.com', 'mt2009.com', 'myspamless.com', 'mytempemail.com', 'mytrashmail.com', 'netmails.net', 'odaymail.com', 'pookmail.com', 'shieldedmail.com', 'smellfear.com', 'sneakemail.com', 'sogetthis.com', 'soodonims.com', 'spam.la', 'spamavert.com', 'spambox.us', 'spamcero.com', 'spamex.com', 'spamfree24.com', 'spamfree24.de', 'spamfree24.eu', 'spamfree24.info', 'spamfree24.net', 'spamfree24.org', 'spamgourmet.com', 'spamherelots.com', 'spamhole.com', 'spaml.com', 'spammotel.com', 'spamobox.com', 'spamspot.com', 'tempemail.net', 'tempinbox.com', 'tempomail.fr', 'temporaryinbox.com', 'tempymail.com', 'thisisnotmyrealemail.com', 'trash2009.com', 'trashmail.net', 'trashymail.com', 'tyldd.com', 'yopmail.com', 'zoemail.com','deadaddress','soodo','tempmail','uroid','spamevader','gishpuppy','privymail.de','trashmailer.com','fansworldwide.de','onewaymail.com', 'mobi.web.id', 'ag.us.to', 'gelitik.in', 'fixmail.tk'];

        foreach($fakes as $koMail) {
            list($dummy, $mailDomain) = explode('@', Strings::lower($mail));

            if(strcasecmp($mailDomain, $koMail) === 0){
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

    function posted($k = null, $d = null)
    {
        if (empty($k)) {
            return !empty($_POST);
        }

        return isAke($_POST, $k, $d);
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

                if (!is_dir($storage)) {
                    Dir::mkdir($storage);
                }

                $storage .= DS . sha1(serialize(func_get_args())) . '.png';

                File::put($storage, $data);

                return WEBROOT . DS . 'qr' . DS . sha1(serialize(func_get_args())) . '.png';
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

    function int($number)
    {
        settype($number, 'integer');

        return (int) $number;
    }

    function float($number)
    {
        settype($number, 'float');

        return (float) $number;
    }

    function string($string)
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

        return (new App)->make($class, $args);
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

        return (new App)->make($class, $args);
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

        if ($username !== NULL) {
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

                $code = "namespace { $classCode };";

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
                $ref = new \ReflectionClass($to. '\\' . $class);
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
        if (file_exists($file)) {
            $file = File::read($file);
        }

        return coll(json_decode($file, true));
    }

    function dbCsv($file, $sep = ';')
    {
        if (file_exists($file)) {
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
        $str = base64_encode($str);
        $str = preg_replace('/\//', '_', $str);
        $str = preg_replace('/\+/', '.', $str);
        $str = preg_replace('/\=/', '-', $str);

        return trim($str, '-');
    }

    function b64_decode($str)
    {
        $str = preg_replace('/\_/', '/', $str);
        $str = preg_replace('/\./', '+', $str);
        $str = preg_replace('/\-/', '=', $str);
        $str = base64_decode($str);

        return $str;
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

        if (!$zip->open($destination, \ZIPARCHIVE::CREATE)) {
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
        if(empty($string)) {
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
        return (object)array_merge((array)$obj1, (array)$obj2);
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

        $back->macro('with', function (array $meta = []) {
            foreach ($meta as $k => $v) {
                flash($k, $v);
            }
        });

        $back->macro('go', function () use ($url) {
            header('Location: ' . $url);

            exit;
        });

        return $back;
    }

    function to($url)
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

    function call($callback, array $args)
    {
        if (is_string($callback) && fnmatch('*::*', $callback)) {
            $callback = explode('::', $callback, 2);
        }

        if (is_array($callback) && isset($callback[1]) && is_object($callback[0])) {
            if ($count = count($args)) {
                $args = array_values($args);
            }

            list($instance, $method) = $callback;

            return $instance->{$method}(...$args);
        } elseif (is_array($callback) && isset($callback[1]) && is_string($callback[0])) {
            list($class, $method) = $callback;
            $class = '\\' . ltrim($class, '\\');

            return $class::{$method}(...$args);
        } elseif (is_string($callback) or $callback instanceOf \Closure) {
            is_string($callback) and $callback = ltrim($callback, '\\');
        }

        return $callback(...$args);
    }

    function hash($str)
    {
        if (function_exists('hash_algos')) {
            foreach (array('sha512', 'sha384', 'sha256', 'sha224', 'sha1', 'md5') as $hash) {
                if (in_array($hash, hash_algos())) {
                    return \hash($hash, $str);
                }
            }
        }

        return md5($token_base);
    }

    function e($value, $flags = ENT_QUOTES, $encoding = 'UTF-8')
    {
        static $cleaned = [];

        if (is_bool($value) or is_int($value) or is_float($value) or in_array($value, $cleaned, true)) {
            return $value;
        }

        if (is_string($value)) {
            $value = htmlentities($value, $flags, $encoding, false);
        } elseif (is_array($value) or ($value instanceof \Iterator && $value instanceof \ArrayAccess)) {
            is_object($value) && $cleaned[] = $value;

            foreach ($value as $k => $v) {
                $value[$k] = e($v, $flags, $encoding);
            }
        } elseif ($value instanceof \Iterator or get_class($value) == 'stdClass') {
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

    function urlFor($name, array $args = [])
    {
        return Route::url($name, $args);
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
            exception('Please provide an expression in future.');
        }

        return $to - $now;
    }

    function secondsFrom($expression)
    {
        $now = now();

        $from = strtotime($expression);

        if ($now <= $from) {
            exception('Please provide an expression in past.');
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

        return is_callable($cb) ? call($callback, $args) : true;
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

    function faker()
    {
        return \Faker\Factory::create(Config::get('faker.provider', 'fr_FR'));
    }

    function provider($alias, callable $resolver = null)
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
