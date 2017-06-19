<?php
    namespace Octo;

    use PHPUnit\Framework\TestCase as PTC;

    abstract class TestCase extends PTC
    {
        protected $app;

        abstract public function makeApplication();

        public function em($entity, $new = true)
        {
            return dbMemory($entity, $new);
        }

        public function signIn($user = null)
        {
            $user = $user ? : $this->create(App\entities\UserEntity::class);
            Auth::login($user);
        }

        public function make($class, $count = 1, $args = [], $lng = 'fr_FR')
        {
            if (is_array($count)) {
                $args = $count;
                $count = 1;
            }

            return $this->factory($class, $count, $lng)->raw($args);
        }

        public function create($class, $count = 1, $args = [], $lng = 'fr_FR')
        {
            if (is_array($count)) {
                $args = $count;
                $count = 1;
            }

            return $this->factory($class, $count, $lng)->store($args);
        }

        public function factory($class, $count = 1, $lng = 'fr_FR')
        {
            if (!is_numeric($count) || $count < 1) {
                exception('Factory', 'You must create at least one row.');
            }

            $model = maker($class, [], false);
            $faker = faker($lng);

            $entity = $this->em(
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

                    return count($res) == 1 ? current($res) : coll($res);
                } else {
                    return count($rows) == 1 ? current($rows) : coll($rows);
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

                return $em
                ->resetted()
                ->in(
                    'id',
                    coll($rows)->pluck('id')
                )->get();
            });

            return $factories;
        }

        protected function setUp()
        {
            if (!$this->app) {
                $this->refreshApplication();
            }
        }

        protected function refreshApplication()
        {
            putenv('APPLICATION_ENV=testing');

            $this->app = $this->makeApplication();
        }

        protected function tearDown()
        {
            if ($this->app) {
                $this->app = null;
            }

            if (property_exists($this, 'serverVariables')) {
                $this->serverVariables = [];
            }
        }

        public function get($url, $options = [])
        {
            return $this->request('GET', $url, $options);
        }

        public function post($url, $options = [])
        {
            return $this->request('POST', $url, $options);
        }

        public function put($url, $options = [])
        {
            return $this->request('PUT', $url, $options);
        }

        public function delete($url, $options = [])
        {
            return $this->request('DELETE', $url, $options);
        }

        public function head($url, $options = [])
        {
            return $this->request('HEAD', $url, $options);
        }

        public function options($url, $options = [])
        {
            return $this->request('OPTIONS', $url, $options);
        }

        public function patch($url, $options = [])
        {
            return $this->request('PATCH', $url, $options);
        }

        public function request($type, $url, $options = [])
        {
            if (!fnmatch('*://*', $url)) {
                if (isset($_ENV['APPLICATION_URL'])) {
                    $url = $_ENV['APPLICATION_URL'] . $url;
                }
            }

            $response = client()->request(Strings::upper($type), $url, $options);

            $customResponse = dyn($response);

            $customResponse->macro('content', function ($response) {
                return $response->getBody()->getContents();
            });

            $customResponse->macro('header', function ($key, $response) {
                if ($response->hasHeader($key)) {
                    $header = $response->getHeader($key);

                    return is_array($header) && count($header) == 1 ? current($header) : $header;
                }

                return null;
            });

            return $customResponse;
        }

        public function mock()
        {
            $args       = func_get_args();
            $native     = array_shift($args);
            $instance   = maker($native, $args);

            return maker(Mock::class, [$instance]);
        }

        public function __call($m, $a)
        {
            $method = '\\Octo\\' . $m;

            if (function_exists($method)) {
                return call_user_func_array($method, $a);
            }
        }

        public static function __callStatic($m, $a)
        {
            $method = '\\Octo\\' . $m;

            if (function_exists($method)) {
                return call_user_func_array($method, $a);
            }
        }
    }
