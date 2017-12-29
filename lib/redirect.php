<?php
    namespace Octo;

    use GuzzleHttp\Psr7\MessageTrait;

    class Redirect
    {
        use Framework;

        /**
         * @var string
         */
        protected $url;

        /**
         * @param string $url
         */
        public function __construct(string $url)
        {
            $this->url = $this->url('/' . $url);
        }

        /**
         * @param array $data
         *
         * @return Redirect
         */
        public function with(array $data): self
        {
            session('with')->setData($data);

            return $this;
        }

        public function go()
        {
            header("Location: " . $this->url);

            exit;
        }

        /**
         * @param string $k
         * @param null $v
         *
         * @return Redirect
         */
        public function flash(string $k, $v = null): self
        {
            session('flash')->set($k, $v);

            return $this;
        }

        /**
         * @param string $url
         *
         * @return string
         */
        protected function url(string $url = '/')
        {
            return (string) Registry::get('octo.subdir', '') . $url;
        }

        /**
         * @param string $route
         *
         * @return MessageTrait|static
         */
        public static function route(string $route, array $params = [])
        {
            $uri = getContainer()->router()->urlFor($route, $params);

            return self::for($uri);
        }

        /**
         * @param string $uri
         *
         * @return MessageTrait|static
         */
        public static function for(string $uri)
        {
            return getContainer()->redirectResponse($uri);
        }
    }
