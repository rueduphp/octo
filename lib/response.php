<?php
    namespace Octo;

    class S3
    {
        protected $bucket, $s3Client = null, $lastRemoteFile = null;

        protected static $instance = null;

        function __construct($accessKeyId = null, $secretAccessKey = null, $bucket = null)
        {
            $accessKeyId     = is_null($accessKeyId)     ? optGet('s3.key',     Config::get('s3.key'))                                 : $accessKeyId;
            $secretAccessKey = is_null($secretAccessKey)  ? optGet('s3.secret', Config::get('s3.secret'))                              : $secretAccessKey;
            $bucket          = is_null($bucket)          ? optGet('s3.bucket',  Config::get('s3.bucket', SITE_NAME . '_buxket'))       : $bucket;

            $this->bucket   = $bucket;

            $this->s3Client = \Aws\S3\S3Client::factory([
                'key'       => $accessKeyId,
                'secret'    => $secretAccessKey,
                'signature' => Config::get('s3.signature', 'v4'),
                'region'    => Config::get('s3.region', 'eu-west-1'),
                'version'   => Config::get('s3.version', 'latest')
            ]);

            self::$instance = $this;
        }

        public static function getInstance()
        {
            if (!self::$instance) {
                new S3(
                    optGet('s3.key',       Config::get('s3.key')),
                    optGet('s3.secret',    Config::get('s3.secret')),
                    optGet('s3.bucket',    Config::get('s3.bucket', SITE_NAME . '_buxket'))
                );
            }

            return self::$instance;
        }

        function get($remoteFile)
        {
            $localFile              = $this->getLocalFile($remoteFile);
            $this->lastRemoteFile   = $remoteFile;
            $download               = false;

            if (!file_exists($localFile)) {
                $download = true;
            } else {
                $iterator       = $this->s3Client->getIterator('ListObjects', [
                    'Bucket'    => $this->bucket,
                    'Prefix'    => $remoteFile,
                    'Delimiter' => '/',
                ]);

                foreach ($iterator as $object) {
                    $remoteDate = date("U", strtotime($object['LastModified']));
                    $localDate  = filemtime($localFile);

                    if ($remoteDate > $localDate) {
                        $download = true;
                    }

                    break;
                }
            }

            if ($download) {
                try {
                    $result = $this->s3Client->getObject([
                        'Bucket'    => $this->bucket,
                        'Key'       => $remoteFile,
                    ]);
                } catch (\Exception $e) {
                    error_log("Error recovering $remoteFile from S3: " . $e->getMessage());

                    return null;
                }

                File::put($localFile, $result['Body']);
                touch($localFile, strtotime($result['LastModified']));
            }

            return $localFile;
        }

        function save($remoteFile, $content)
        {
            $this->lastRemoteFile = $remoteFile;
            $this->s3Client->upload($this->bucket, $remoteFile, $content);
        }

        public function getLast()
        {
            return $this->lastRemoteFile;
        }

        public function getList($path = "")
        {
            $files = [];

            $options = [
                'Bucket' => $this->bucket,
            ];

            if ($path){
                $options['Prefix'] = $path;
                $options['Delimiter'] = '/';
            }

            $iterator = $this->s3Client->getIterator('ListObjects', $options);

            foreach ($iterator as $object) {
                $files[] = [
                    'timestamp' => date("U", strtotime($object['LastModified'])),
                    'filename'  => $object['Key'],
                ];
            }

            return $files;
        }

        public function listBuckets()
        {
            $buckets = $this->s3Client->listBuckets();

            return $buckets["Buckets"];
        }

        public function delete($remoteFile)
        {
            File::delete($this->getLocalFile($remoteFile));

            $this->s3Client->deleteObject([
                'Bucket'    => $this->bucket,
                'Key'       => $remoteFile,
            ]);
        }

        private function getLocalFile($file)
        {
            $key = sha1($this->bucket . $file);
            $dir = Config::get('s3.cache', session_save_path());

            return $dir . DS . $key;
        }
    }

class Acd
    {
        private $dir;
        private $tmp = ['write' => [], 'del' => []];
        private $cache = [];

        public function __construct($ns = 'core')
        {
            $this->dir = Config::get('acd.dir', '/home/multimedia/acd/fmr');

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }

            $this->dir = $this->dir . DS . $ns;

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }
        }

        public function __destruct()
        {
            if (!empty($this->tmp['del'])) {
                foreach ($this->tmp['del'] as $k) {
                    $this->unwrite($k);
                }
            }

            if (!empty($this->tmp['write'])) {
                foreach ($this->tmp['write'] as $infos) {
                    $k      = array_shift($infos);
                    $v      = array_shift($infos);
                    $expire = array_shift($infos);

                    $this->write($k, $v, $expire);
                }
            }
        }

        public function set($k, $v, $expire = null)
        {
            $this->tmp['write'][] = func_get_args();
            $this->cache[$k] = $v;

            return $this;
        }

        public function write($k, $v, $expire = null)
        {
            $file = $this->dir . DS . $k . '.fmr';
            $ageFile = $this->dir . DS . $k . '.age';

            if (File::exists($file) && File::exists($ageFile)) {
                File::delete($file);
                File::delete($ageFile);
            }

            file_put_contents($file, serialize($v));

            $expire = is_null($expire) ? strtotime('+10 year') : time() + $expire;
            file_put_contents($ageFile, $expire);

            return $this;
        }

        public function setnx($key, $value)
        {
            if (!$this->has($key)) {
                $this->set($key, $value);

                return true;
            }

            return false;
        }

        public function setExpireAt($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function add($k, $v, $expire = null)
        {
            return $this->set($k, $v, $expire);
        }

        public function setExp($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function setExpire($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function expire($k, $expire)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $expire);
        }

        public function expireAt($k, $timestamp)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $timestamp);
        }

        public function get($k, $d = null)
        {
            $cached = isAke($this->cache, $k, null);

            if ($cached) {
                return $cached;
            }

            $file       = $this->dir . DS . $k . '.fmr';
            $ageFile    = $this->dir . DS . $k . '.age';

            if (file_exists($file) && file_exists($ageFile)) {
                $age = file_get_contents($ageFile);

                if ($age >= time()) {
                    return unserialize(file_get_contents($file));
                } else {
                    File::delete($file);
                    File::delete($ageFile);
                }
            }

            return $d;
        }

        public function getOr($k, callable $c, $e = null)
        {
            if ($this->has($k)) {
                return $this->get($k);
            }

            $res = $c();

            $this->set($k, $res, $e);

            return $res;
        }

        public function watch($k, callable $exists = null, callable $notExists = null)
        {
            if ($this->has($k)) {
                if (is_callable($exists)) {
                    return $exists($this->get($k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        }

        public function session($k, $v = 'dummyget', $e = null)
        {
            $user       = session('web')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function aged($k, callable $c, $a)
        {
            $k = sha1($this->dir) . '.' . $k;

            return ageCache($k, $c, $a);
        }

        public function has($k)
        {
            $cached = isAke($this->cache, $k, null);

            if ($cached) {
                return true;
            }

            $file       = $this->dir . DS . $k . '.fmr';
            $ageFile    = $this->dir . DS . $k . '.age';

            if (file_exists($file) && file_exists($ageFile) ) {
                $age = file_get_contents($ageFile);

                if ($age >= time()) {
                    return true;
                } else {
                    File::delete($file);
                    File::delete($ageFile);
                }
            }

            return false;
        }

        public function whenExpire($k)
        {
            $ageFile    = $this->dir . DS . $k . '.age';
            $file       = $this->dir . DS . $k . '.fmr';

            if (file_exists($ageFile)) {
                $age = file_get_contents($ageFile);

                if ($age >= time()) {
                    return $age;
                } else {
                    File::delete($file);
                    File::delete($ageFile);
                }
            }

            return null;
        }

        public function age($k)
        {
            $file = $this->dir . DS . $k . '.fmr';

            if (file_exists($file)) {
                return File::age($file);
            }

            return null;
        }

        public function delete($k)
        {
            unset($this->cache[$k]);

            $this->tmp['del'][] = $k;

            return $this;
        }

        public function unwrite($k)
        {
            $file       = $this->dir . DS . $k . '.fmr';
            $ageFile    = $this->dir . DS . $k . '.age';

            if (file_exists($file)) {
                File::delete($file);
                File::delete($ageFile);

                return true;
            }

            return false;
        }

        public function del($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return $new;
        }

        public function increment($k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old - $by;

            $this->set($k, $new);

            return $new;
        }

        public function decrement($k, $by = 1)
        {
            return $this->decr($k, $by);
        }

        public function keys($pattern = '*')
        {
            $keys = glob($this->dir . DS . $pattern . '.fmr', GLOB_NOSORT);

            foreach ($keys as $key) {
                $k = str_replace([$this->dir . DS, '.fmr'], '', $key);

                yield $k;
            }
        }

        public function flush($pattern = '*')
        {
            $keys = glob($this->dir . DS . $pattern . '.fmr', GLOB_NOSORT);

            $affected = 0;

            foreach ($keys as $key) {
                File::delete($key);
                File::delete(str_replace('.fmr', '.age', $key));
                $affected++;
            }

            return $affected;
        }

        public function clean($pattern = '*')
        {
            $keys = glob($this->dir . DS . $pattern . '.fmr', GLOB_NOSORT);

            $affected = 0;

            foreach ($keys as $key) {
                $age = file_get_contents(str_replace('.fmr', '.age', $key));

                if ($age < time()) {
                    File::delete($key);
                    File::delete(str_replace('.fmr', '.age', $key));
                    $affected++;
                }
            }

            return $affected;
        }

        public function readAndDelete($key, $default = null)
        {
            if ($this->has($key)) {
                $value = $this->get($key);

                $this->delete($key);

                return $value;
            }

            return $default;
        }

        public function rename($keyFrom, $keyTo, $default = null)
        {
            $value = $this->readAndDelete($keyFrom, $default);

            return $this->set($keyTo, $value);
        }

        public function copy($keyFrom, $keyTo)
        {
            return $this->set($keyTo, $this->get($keyFrom));
        }

        public function getSize($key)
        {
            return strlen($this->get($key));
        }

        public function length($key)
        {
            return strlen($this->get($key));
        }

        public function hset($hash, $key, $value)
        {
            $key = "hash.$hash.$key";

            return $this->set($key, $value);
        }

        public function hsetnx($hash, $key, $value)
        {
            if (!$this->hexists($hash, $key)) {
                $this->hset($hash, $key, $value);

                return true;
            }

            return false;
        }

        public function hget($hash, $key, $default = null)
        {
            $key = "hash.$hash.$key";

            return $this->get($key, $default);
        }

        public function hstrlen($hash, $key)
        {
            if ($value = $this->hget($hash, $key)) {
                return strlen($value);
            }

            return 0;
        }

        public function hgetOr($hash, $k, callable $c)
        {
            if ($this->hexists($hash, $k)) {
                return $this->hget($hash, $k);
            }

            $res = $c();

            $this->hset($hash, $k, $res);

            return $res;
        }

        public function hwatch($hash, $k, callable $exists = null, callable $notExists = null)
        {
            if ($this->hexists($hash, $k)) {
                if (is_callable($exists)) {
                    return $exists($this->hget($hash, $k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        }

        public function hReadAndDelete($hash, $key, $default = null)
        {
            if ($this->hexists($hash, $key)) {
                $value = $this->hget($hash, $key);

                $this->hdelete($hash, $key);

                return $value;
            }

            return $default;
        }

        public function hdelete($hash, $key)
        {
            $key = "hash.$hash.$key";

            return $this->delete($key);
        }

        public function hdel($hash, $key)
        {
            return $this->hdelete($hash, $key);
        }

        public function hhas($hash, $key)
        {
            $key = "hash.$hash.$key";

            return $this->has($key);
        }

        public function hexists($hash, $key)
        {
            return $this->hhas($hash, $key);
        }

        public function hincr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old + $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        public function hdecr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old - $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        public function hgetall($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            foreach ($keys as $row) {
                $key = str_replace(['.fmr', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

                yield $key;
                yield unserialize(File::read($row));
            }
        }

        public function hvals($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            foreach ($keys as $row) {
                yield unserialize(File::read($row));
            }
        }

        public function hlen($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            return count($keys);
        }

        public function hremove($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            foreach ($keys as $row) {
                File::delete($row);
            }

            return true;
        }

        public function hkeys($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            foreach ($keys as $row) {
                $key = str_replace(['.fmr', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

                yield $key;
            }
        }

        public function sadd($key, $value)
        {
            $tab = $this->get($key, []);
            $tab[] = $value;

            return $this->set($key, $tab);
        }

        public function scard($key)
        {
            $tab = $this->get($key, []);

            return count($tab);
        }

        public function sinter()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sunion()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sinterstore()
        {
            $args = func_get_args();

            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        public function sunionstore()
        {
            $args = func_get_args();

            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        public function sismember($hash, $key)
        {
            return in_array($key, $this->get($hash, []));
        }

        public function smembers($key)
        {
            return $this->get($key, []);
        }

        public function srem($hash, $key)
        {
            $tab = $this->get($hash, []);

            $new = [];

            $exists = false;

            foreach ($tab as $row) {
                if ($row != $key) {
                    $new[] = $row;
                } else {
                    $exists = true;
                }
            }

            if ($exists) {
                $this->set($hash, $new);

                return true;
            }

            return false;
        }

        public function smove($from, $to, $key)
        {
            if ($this->sismember($from, $key)) {
                $this->srem($from, $key);

                if (!$this->sismember($to, $key)) {
                    $this->sadd($to, $key);
                }

                return true;
            }

            return false;
        }

        public function until($k, callable $c, $maxAge = null, $args = [])
        {
            $keyAge = $k . '.maxage';
            $v      = $this->get($k);

            if ($v) {
                if (is_null($maxAge)) {
                    return $v;
                }

                $age = $this->get($keyAge);

                if (!$age) {
                    $age = $maxAge - 1;
                }

                if ($age >= $maxAge) {
                    return $v;
                } else {
                    $this->delete($k);
                    $this->delete($keyAge);
                }
            }

            $data = call_user_func_array($c, $args);

            $this->set($k, $data);

            if (!is_null($maxAge)) {
                if ($maxAge < 1000000000) {
                    $maxAge = ($maxAge * 60) + time();
                }

                $this->set($keyAge, $maxAge);
            }

            return $data;
        }
    }


    class Response
    {
        protected $status = 200;

        protected $headers = [];

        protected $body;

        public static $codes = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',

            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',

            226 => 'IM Used',

            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',

            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',

            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',

            426 => 'Upgrade Required',

            428 => 'Precondition Required',
            429 => 'Too Many Requests',

            431 => 'Request Header Fields Too Large',

            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',

            510 => 'Not Extended',
            511 => 'Network Authentication Required'
        );

        public function status($code = null)
        {
            if ($code === null) {
                return $this->status;
            }

            if (array_key_exists($code, self::$codes)) {
                $this->status = $code;
            } else {
                throw new \Exception('Invalid status code.');
            }

            return $this;
        }

        public function header($name, $value = null)
        {
            if (is_array($name)) {
                foreach ($name as $k => $v) {
                    $this->headers[$k] = $v;
                }
            } else {
                $this->headers[$name] = $value;
            }

            return $this;
        }

        public function headers()
        {
            return $this->headers;
        }

        public function write($str)
        {
            $this->body .= $str;

            return $this;
        }

        public function clear()
        {
            $this->status   = 200;
            $this->headers  = [];
            $this->body     = '';

            return $this;
        }

        public function cache($expires = false)
        {
            if ($expires === false) {
                $this->headers['Expires'] = 'Mon, 26 Jul 1997 05:00:00 GMT';
                $this->headers['Cache-Control'] = array(
                    'no-store, no-cache, must-revalidate',
                    'post-check=0, pre-check=0',
                    'max-age=0'
                );
                $this->headers['Pragma'] = 'no-cache';
            } else {
                $expires = is_int($expires) ? $expires : strtotime($expires);
                $this->headers['Expires'] = gmdate('D, d M Y H:i:s', $expires) . ' GMT';
                $this->headers['Cache-Control'] = 'max-age='.($expires - time());

                if (isset($this->headers['Pragma']) && $this->headers['Pragma'] == 'no-cache'){
                    unset($this->headers['Pragma']);
                }
            }

            return $this;
        }

        public function sendHeaders()
        {
            if (strpos(php_sapi_name(), 'cgi') !== false) {
                header(
                    sprintf(
                        'Status: %d %s',
                        $this->status,
                        self::$codes[$this->status]
                    ),
                    true
                );
            } else {
                header(
                    sprintf(
                        '%s %d %s',
                        isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1',
                        $this->status,
                        self::$codes[$this->status]
                    ),
                    true,
                    $this->status
                );
            }

            foreach ($this->headers as $field => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        header($field . ': ' . $v, false);
                    }
                } else {
                    header($field . ': ' . $value);
                }
            }

            if (($length = strlen($this->body)) > 0) {
                header('Content-Length: ' . $length);
            }

            return $this;
        }

        public function send()
        {
            if (!headers_sent()) {
                $this->sendHeaders();
            }

            echo $this->body;
        }

        public function getBody()
        {
            return $this->body;
        }
    }
