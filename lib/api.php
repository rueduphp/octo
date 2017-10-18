<?php
    namespace Octo;

    class Api
    {
        private $resource, $token;

        public function __construct($resource)
        {
            $this->resource = $resource;
        }

        public function auth($key)
        {
            $db = engine('api', 'resource');

            $auth = $db
            ->where(['resource', '=', $this->resource])
            ->where(['key', '=', $key])
            ->first(true);

            if (!empty($auth)) {
                $this->token = sha1(serialize($auth->toArray()) . date('dmY'));
                $auth->setToken($this->token)->save();
            }

            return $this->isAuth();
        }

        public function isAuth()
        {
            return !is_null($this->token);
        }

        public static function clean()
        {
            return engine('api', 'auth')->where(['expire', '<', time()])->delete();
        }

        public static function check($resourceApi)
        {
            $token = request()->getToken();

            if (is_null($token)) {
                self::unauthorized();
            }

            $auth = engine('api', 'auth')->where(['token', '=', $token])->first(true);

            if (empty($auth)) {
                self::unauthorized();
            } else {
                $expire = $auth->expire;

                if ('never' != $expire) {
                    if (time() > $expire) {
                        self::unauthorized();
                    }
                }

                $resource = $auth->resource;

                if ($resource != $resourceApi) {
                    self::unauthorized();
                }

                return $auth;
            }
        }

        public static function can($auth, $resource, $action)
        {
            if (empty($auth)) {
                self::unauthorized();
            }

            if (2 == (int) $auth->is_admin) {
                $rigth = engine('api', 'right')
                ->where(['resource', '=', $resource])
                ->where(['action', '=', $action])
                ->where(['user_id', '=', $auth->user_id])
                ->first(true);

                if (empty($right)) {
                    self::unauthorized();
                }

                $can = (int) $right->can;

                if (2 == $can) {
                    self::unauthorized();
                }
            }
        }

        public static function render(array $data, $type = 'json')
        {
            header("HTTP/1.0 200 OK");

            if ($type == 'json') {
                self::renderJson($data);
            } elseif ($type == 'xml') {
                self::renderXml($data);
            }
        }

        public static function result($result, $type = 'json')
        {
            self::render(['status' => 200, 'data' => $result], $type);
        }

        public static function renderJson(array $data)
        {
            header('content-type: application/json; charset=utf-8');

            die(json_encode($data));
        }

        public static function message($code = 200)
        {
            $message = self::getMessage($code, 'OK');

            header("HTTP/1.0 $code $message");

            self::renderJson([
                'status' => $code,
                'message' => $message
            ]);
        }

        public static function ok()
        {
            header("HTTP/1.0 200 OK");

            self::renderJson([
                'status' => 200,
                'message' => 'OK'
            ]);
        }

        public static function created()
        {
            header("HTTP/1.0 201 Created");

            self::renderJson([
                'status' => 201,
                'message' => 'Created'
            ]);
        }

        public static function accepted()
        {
            header("HTTP/1.0 202 Accepted");

            self::renderJson([
                'status' => 202,
                'message' => 'Accepted'
            ]);
        }

        public static function partialContent()
        {
            header("HTTP/1.0 206 Partial Content");

            self::renderJson([
                'status' => 206,
                'message' => 'Partial Content'
            ]);
        }

        public static function movedPermanently()
        {
            header("HTTP/1.0 301 Moved Permanently");

            self::renderJson([
                'status' => 301,
                'message' => 'Moved Permanently'
            ]);
        }

        public static function notModified()
        {
            header("HTTP/1.0 304 Not Modified");

            self::renderJson([
                'status' => 304,
                'message' => 'Not Modified'
            ]);
        }

        public static function permanentRedirect()
        {
            header("HTTP/1.0 308 Permanent Redirect");

            self::renderJson([
                'status' => 308,
                'message' => 'Permanent Redirect'
            ]);
        }

        public static function badRequest()
        {
            header("HTTP/1.0 400 Bad Request");

            self::renderJson([
                'status' => 400,
                'message' => 'Bad Request'
            ]);
        }

        public static function unauthorized()
        {
            header("HTTP/1.0 401 Unauthorized");

            self::renderJson([
                'status' => 401,
                'message' => 'Unauthorized'
            ]);
        }

        public static function paymentRequired()
        {
            header("HTTP/1.0 402 Payment Required");

            self::renderJson([
                'status' => 402,
                'message' => 'Payment Required'
            ]);
        }

        public static function forbidden()
        {
            header("HTTP/1.0 403 Forbidden");

            self::renderJson([
                'status' => 403,
                'message' => 'Forbidden'
            ]);
        }

        public static function NotFound()
        {
            header("HTTP/1.0 404 Not Found");

            self::renderJson([
                'status' => 404,
                'message' => 'Not Found'
            ]);
        }

        public static function resourceNotAllowed()
        {
            header("HTTP/1.0 405 Resource Not Allowed");

            self::renderJson([
                'status' => 405,
                'message' => 'Resource Not Allowed'
            ]);
        }

        public static function notAcceptable()
        {
            header("HTTP/1.0 406 Not Acceptable");

            self::renderJson([
                'status' => 406,
                'message' => 'Not Acceptable'
            ]);
        }

        public static function conflict()
        {
            header("HTTP/1.0 409 Conflict");

            self::renderJson([
                'status' => 409,
                'message' => 'Conflict'
            ]);
        }

        public static function preconditionFailed()
        {
            header("HTTP/1.0 412 Precondition Failed");

            self::renderJson([
                'status' => 412,
                'message' => 'Precondition Failed'
            ]);
        }

        public static function badContentType()
        {
            header("HTTP/1.0 415 Bad Content Type");

            self::renderJson([
                'status' => 415,
                'message' => 'Bad Content Type'
            ]);
        }

        public static function requestedRangeNotSatisfiable()
        {
            header("HTTP/1.0 416 Requested Range Not Satisfiable");

            self::renderJson([
                'status' => 416,
                'message' => 'Requested Range Not Satisfiable'
            ]);
        }

        public static function expectationFailed()
        {
            header("HTTP/1.0 417 Expectation Failed");

            self::renderJson([
                'status' => 417,
                'message' => 'Expectation Failed'
            ]);
        }

        public static function internalServerError()
        {
            header("HTTP/1.0 500 Internal Server Error");

            self::renderJson([
                'status' => 500,
                'message' => 'Internal Server Error'
            ]);
        }

        public static function notImplemented()
        {
            header("HTTP/1.0 501 Not Implemented");

            self::renderJson([
                'status' => 501,
                'message' => 'Not Implemented'
            ]);
        }

        public static function badGateway()
        {
            header("HTTP/1.0 502 Bad Gateway");

            self::renderJson([
                'status' => 502,
                'message' => 'Bad Gateway'
            ]);
        }

        public static function serviceUnavailable()
        {
            header("HTTP/1.0 503 Service Unavailable");

            self::renderJson([
                'status' => 503,
                'message' => 'Service Unavailable'
            ]);
        }

        public static function getMessage($code, $defaultMessage = 'unknown')
        {
            static $statusMessage = [
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
                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',
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
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Large',
                415 => 'Unsupported Media Type',
                416 => 'Request Range Not Satisfiable',
                417 => 'Expectation Failed',
                418 => 'I\'m a teapot',
                421 => 'Misdirected Request',
                422 => 'Unprocessable Entity',
                423 => 'Locked',
                424 => 'Failed Dependency',
                425 => 'Reserved for WebDAV advanced collections expired proposal',
                426 => 'Upgrade Required',
                428 => 'Precondition Required',
                429 => 'Too Many Requests',
                431 => 'Request Header Fields Too Large',
                451 => 'Unavailable For Legal Reasons',
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported',
                507 => 'Insufficient Storage',
                508 => 'Loop Detected',
                510 => 'Not Extended',
                511 => 'Network Authentication Required'
            ];

            return isAke($statusMessage, $code, $defaultMessage);
        }
    }
