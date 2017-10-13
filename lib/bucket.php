<?php
    namespace Octo;

    class Bucket
    {
        private $bucket, $response, $url;

        public function __construct($bucket, $url = null)
        {
            $this->bucket   = $bucket;
            $this->session  = session('data_bucket_' . $bucket);

            $this->url = is_null($url)
            ? str_replace('https://', 'http://', URLSITE) . 'bucket/'
            : $url . '/';
        }

        public function all($pattern)
        {
            $data = $this->session->getData();

            if (!is_null($data)) {
                return $data;
            }

            $this->call(
                'all',
                array(
                    "pattern" => $pattern
                )
            );

            $tab        = json_decode($this->response, true);
            $res        = isAke($tab, 'message');
            $collection = [];

            if (is_array($res)) {
                if (!empty($res)) {
                    foreach ($res as $key => $row) {
                        $this->_set($key, $row);
                        array_push($collection, $row);
                    }
                }
            }

            $this->session->setData($collection);

            return $collection;
        }

        public function keys($pattern)
        {
            $keys = $this->session->getKeys();

            if (!empty($keys)) {
                $seg = isAke($keys, sha1($pattern), null);

                if (!empty($seg)) {
                    return $seg;
                }
            }

            $this->call(
                'keys',
                array(
                    "pattern" => $pattern
                )
            );

            $tab        = json_decode($this->response, true);
            $res        = isAke($tab, 'message', []);
            $collection = [];

            if (Arrays::is($res)) {
                if (count($res)) {
                    foreach ($res as $row) {
                        array_push($collection, $row);
                    }
                }
            }

            if (empty($keys)) {
                $keys = [];
            }

            $keys[sha1($pattern)] = $collection;
            $this->session->setKeys($keys);

            return $collection;
        }

        public function get($key)
        {
            $hash = sha1($key);
            $values = $this->session->getValues();

            if (!empty($values)) {
                $value = isAke($values, $hash);

                if (!empty($value)) {
                    return $value;
                }
            }

            $this->call(
                'get',
                array(
                    "key" => $key
                )
            );

            $tab    = json_decode($this->response, true);
            $value  = isAke($tab, 'message', null);

            if (empty($values)) {
                $values = [];
            }

            $values[$hash] = $value;
            $this->session->setValues($values);

            return $value;
        }

        public function _set($key, $value)
        {
            $hash = sha1($key);
            $values = $this->session->getValues();

            if (empty($values)) {
                $values = [];
            }

            $values[$hash] = $value;
            $this->session->setValues($values);

            return $this;
        }

        public function set($key, $value, $expire = 0)
        {
            $this->call(
                'set',
                array(
                    "key" => $key,
                    "value" => $value,
                    "expire" => $expire
                )
            );

            $hash   = sha1($key);
            $values = $this->session->getValues();

            if (empty($values)) {
                $values = [];
            }

            $values[$hash] = $value;
            $this->session->setValues($values);

            return $this;
        }

        public function expire($key, $val, $ttl = 3600)
        {
            return $this->set($key, $val, time() + $ttl);
        }

        public function del($key)
        {
            $this->call(
                'del',
                array(
                    "key" => $key
                )
            );

            $hash   = sha1($key);
            $values = $this->session->getValues();

            if (empty($values)) {
                $values = [];
            }

            $values[$hash] = null;
            $this->session->setValues($values);
            $this->session->setKeys([]);
            $this->session->setData(null);

            return $this;
        }

        public function incr($key, $by = 1)
        {
            $val = $this->get($key);

            if (!strlen($val)) {
                $val = 1;
            } else {
                $val = (int) $val;
                $val += $by;
            }

            $this->set($key, $val);

            return $val;
        }

        public function decr($key, $by = 1)
        {
            $val = $this->get($key);

            if (!strlen($val)) {
                $val = 0;
            } else {
                $val = (int) $val;
                $val -= $by;
                $val = 0 > $bal ? 0 : $val;
            }

            $this->set($key, $val);

            return $val;
        }

        private function check()
        {
            $this->call('check');
        }

        public function upload($file)
        {
            $tab        = explode(DS, $file);
            $fileName   = Arrays::last($tab);
            $tab        = explode('.', $fileName);
            $extension  = Inflector::lower(Arrays::last($tab));
            $name       = uuid() . '.' . $extension;
            $data       = File::read($file);

            $this->call(
                'upload',
                array(
                    "data" => $data,
                    "name" => $name
                )
            );

            $tab    = json_decode($this->response, true);
            $res    = isAke($tab, 'message', null);

            return $res;
        }

        public function data($data, $extension)
        {
            $name   = uuid() . '.' . Inflector::lower($extension);

            $this->call(
                'upload',
                array(
                    "data" => $data,
                    "name" => $name
                )
            );

            $tab    = json_decode($this->response, true);
            $res    = isAke($tab, 'message', null);

            return $res;
        }

        public function backup($file)
        {
            if (File::exists($file)) {
                $tab    = explode(DS, $file);
                $name   = date("Y_m_d_H_i_s") . '_' . Arrays::last($tab);

                $this->call(
                    'upload',
                    array(
                        "data" => File::read($file),
                        "name" => $name
                    )
                );

                $tab    = json_decode($this->response, true);
                $res    = isAke($tab, 'message', null);

                return $res;
            }

            return false;
        }

        private function call($action, $params = [])
        {
            $params['bucket'] = $this->bucket;
            $params['action'] = $action;

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

            $this->response = curl_exec($ch);
        }

        public function __call($m, $a)
        {
            $this->call($m, current($a));

            return isAke(json_decode($this->response, true), 'message', null);
        }
    }
