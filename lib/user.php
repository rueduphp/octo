<?php
    namespace Octo;

    class User
    {
        public function id()
        {
            return forever();
        }

        public function session($k, $v = null)
        {
            $k = 'user.' . $this->id() . '.' . $k;

            if ($v) {
                fmr('user')->set($k, serialize($v));
                fmr('user')->expire($k, 3600);

                return true;
            }

            return fmr('user')->get($k);
        }

        public function getOption($k, $d = null)
        {
            $k = $this->id() . '.' . $k;

            return fmr('user')->get($k, $d);
        }

        public function setOption($k, $v)
        {
            $k = $this->id() . '.' . $k;

            return fmr('user')->set($k, $v);
        }

        public function hasOption($k)
        {
            return 'octodummy' != $this->getOption($k, 'octodummy');
        }

        public function flash($key, $val = null)
        {
            $k = 'flash.' . forever() . '.' . $key;

            if ($val) {
                fmr('user')->set($k, $val);
            } else {
                $v = fmr('user')->get($k, null);
                fmr('user')->del($k);

                return $v;
            }

            return $val;
        }

        public function webFlash($key, $val = null)
        {
            $k = 'webflash.' . forever() . '.' . $key;

            if ($val) {
                fmr('flash')->set($k, $val);
            } else {
                $v = fmr('flash')->get($k, null);
                fmr('flash')->del($k);

                return $v;
            }

            return $val;
        }

        public function identify()
        {
            $user = session('web')->getUser();

            if (!$user) {
                $user = session('log')->getUser();

                if (!$user) {
                    $session = session('log');
                    $forever = forever();
                    $user = null;

                    $account = $this->getOption('account');

                    if ($account) {
                        $user = System::Account()->find((int) $account, false);
                    }

                    if (!$user) {
                        $user = System::Visitor()->firstOrCreate(['forever' => $forever])->toArray();
                        $user['accounted']  = false;
                        $user['visitor']    = true;
                    } else {
                        $user['accounted']  = true;
                        $user['visitor']    = false;
                    }

                    $user['ip'] = $this->ip();

                    $session->setUser($user);
                }
            } else {
                session('log')->setUser($user);
            }

            return $this;
        }

        public function getIdentifier()
        {
            $u = session('web')->getUser();

            $isLogged = !is_null($u);

            return $isLogged ? $u['forever'] : forever();
        }

        public function log($action, $data = [])
        {
            $user   = session('log')->getUser();
            $u      = session('web')->getUser();

            $isLogged = !is_null($u);

            if (!$user) {
                return $this->identify()->log($action, $data);
            } else {
                if ($isLogged) {
                    $user['id'] = $u['id'];
                }

                $user['browser']['agent']   = isAke($_SERVER, 'HTTP_USER_AGENT', null);
                $user['browser']['referer'] = isAke($_SERVER, 'HTTP_REFERER', null);

                $row = [
                    'action'        => $action,
                    'id_user'       => $user['id'],
                    'cookie'        => forever(),
                    'browser'       => isAke($user, 'browser', []),
                    'location'      => isAke($user, 'location', []),
                    'ip'            => Arrays::get($user, 'ip.ip'),
                    'isp'           => Arrays::get($user, 'ip.isp'),
                    'city'          => Arrays::get($user, 'ip.city'),
                    'country'       => Arrays::get($user, 'ip.country'),
                    'country_code'  => Arrays::get($user, 'ip.country_code'),
                    'region'        => Arrays::get($user, 'ip.region_name'),
                    'zip'           => Arrays::get($user, 'ip.zip'),
                    'timezone'      => Arrays::get($user, 'ip.timezone'),
                    'connected'     => $isLogged,
                    'session'       => session_id(),
                    'anonymous'     => !$user['accounted']
                ];

                foreach ($data as $k => $v) {
                    $row[$k] = $v;
                }

                System::Track()->create($row)->save();
            }
        }

        public function logs()
        {
            $rows = System::Track()->sortByDesc('created_at')->get();

            $csv = [];

            foreach ($rows as $row) {
                $date       = $row['created_at'];
                $browser    = isAke(isAke($row, 'browser', []), 'agent', '');
                $screen     = isAke(isAke($row, 'browser', []), 'screen', '');
                $referer    = isAke(isAke($row, 'browser', []), 'referer', '/');
                $language   = isAke(isAke($row, 'browser', []), 'language', 'en');
                $latitude   = isAke(isAke($row, 'location', []), 'lat', 0);
                $longitude  = isAke(isAke($row, 'location', []), 'lng', 0);

                $row['browser']     = $browser;
                $row['screen']      = $screen;
                $row['referer']     = $referer;
                $row['language']    = $language;
                $row['latitude']    = floatval($latitude);
                $row['longitude']   = floatval($longitude);

                unset($row['location']);

                $row['connected']   = $row['connected'] ? 1 : 0;
                $row['anonymous']   = $row['anonymous'] ? 1 : 0;
                $row['session']     = isAke($row, 'session', '');
                $row['id']          = isAke($row, 'id', '');
                $row['action']      = isAke($row, 'action', '');
                $row['cookie']      = isAke($row, 'cookie', '');
                $row['user']        = 1 == $row['anonymous'] ? isAke($row, 'id_user', '') : '';

                unset($row['id_user']);

                if (empty($csv)) {
                    $csv[] = implode(
                        '|',
                        [
                            'date',
                            'action',
                            'cookie',
                            'browser',
                            'ip',
                            'isp',
                            'city',
                            'zip',
                            'country',
                            'country_code',
                            'region',
                            'timezone',
                            'connected',
                            'anonymous',
                            'session',
                            'id',
                            'page',
                            'screen',
                            'referer',
                            'language',
                            'latitude',
                            'longitude',
                            'user'
                        ]
                    );
                }

                $csv[] = implode(
                    '|',
                    [
                        $date,
                        $row['action'],
                        $row['cookie'],
                        $row['browser'],
                        $row['ip'],
                        $row['isp'],
                        $row['city'],
                        $row['zip'],
                        $row['country'],
                        $row['country_code'],
                        $row['region'],
                        $row['timezone'],
                        $row['connected'],
                        $row['anonymous'],
                        $row['session'],
                        $row['id'],
                        $row['page'],
                        $row['screen'],
                        $row['referer'],
                        $row['language'],
                        $row['latitude'],
                        $row['longitude'],
                        $row['user']
                    ]
                );
            }

            die(implode("\n", $csv));
        }

        public function get()
        {
            $this->identify();

            $user = session('log')->getUser();

            if (!$user) {
                return $this->identify()->get();
            }

            return $user;
        }

        public function userModel()
        {
            $user = session('log')->getUser();

            if (!$user) {
                return $this->identify()->get();
            }

            if ($user['accounted']) {
                return System::Account()->find((int) $user['id']);
            } else {
                return System::Visitor()->find((int) $user['id']);
            }
        }

        public function ip()
        {
            $lng = $this->preferedLanguage();

            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($_SERVER['X_FORWARDED_FOR'])) {
                $ip = $_SERVER['X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }

            if ($ip == '127.0.0.1') {
                return ['ip' > $ip, 'language' => $lng];
            }

            $url = "http://ip-api.com/json/$ip";

            $json = dwnCache($url);
            $json = str_replace(
                array(
                    'query',
                    'countryCode',
                    'regionName'
                ),
                array(
                    'ip',
                    'country_code',
                    'region_name'
                ),
                $json
            );

            $data = json_decode($json, true);

            $data['ip']         = $ip;
            $data['language']   = $lng;

            return $data;
        }

        public function preferedLanguage()
        {
            return \Locale::acceptFromHttp(
                isAke(
                    $_SERVER,
                    "HTTP_ACCEPT_LANGUAGE",
                    Config::get(
                        'app.language',
                        def(
                            'app.language',
                            'en'
                        )
                    )
                )
            );
        }

        public function token($new = null)
        {
            $user = $this->userModel();

            if (is_null($new) && is_object($user)) {
                return $user->getToken();
            }

            $token = token();

            $user->setToken($token)->save();

            return $token;
        }

        public function isAuthByToken()
        {
            $merged = array_merge(
                $_POST,
                array_merge(
                    $_GET,
                    $_REQUEST
                )
            );

            $token  = isAke($merged, 'token', null);

            if ($token) {
                $user = $this->userModel();

                if (is_object($user)) {
                    return $user->getToken() == $token;
                }
            }

            return false;
        }
    }
