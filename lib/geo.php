<?php
    namespace Octo;

    class Geo
    {
        /**
         * @param $hexa
         * @return array
         * @throws Exception
         */
        public function ghexa($hexa)
        {
            $data = $this->dwnCache("https://www.google.fr/maps/preview/entity?authuser=0&hl=fr&pb=!1m18!1s$hexa!3m12!1m3!1d16294.179092557824!2d2.3078046803197836!3d48.86649036092792!2m3!1f0!2f0!3f0!3m2!1i1360!2i298!4f13.1!4m2!3d48.87284474327185!4d2.3193597793579213!5e4!6shotel!12m3!2m2!1i392!2i106!13m40!2m2!1i203!2i100!3m1!2i4!6m6!1m2!1i86!2i86!1m2!1i408!2i256!7m26!1m3!1e1!2b0!3e3!1m3!1e2!2b1!3e2!1m3!1e2!2b0!3e3!1m3!1e3!2b0!3e3!1m3!1e4!2b0!3e3!1m3!1e3!2b1!3e2!2b1!4b0!9b0!14m4!1swApXVtLhKMiaU9OMuNAP!3b1!7e81!15i10555!15m1!2b1!22m1!1e81&pf=p");

            $data = str_replace("\n", "", $data);
            $data = str_replace("\r", "", $data);
            $data = str_replace("\t", "", $data);
            $data = str_replace(")]}'", "", $data);

            $data = json_decode($data, true);

            $row = Arrays::get($data, '0.1.0');

            $latRow     = Arrays::get($row, '14.9.2');
            $lngRow     = Arrays::get($row, '14.9.3');
            $name       = Arrays::get($row, '14.11');
            $ws         = Arrays::get($row, '14.7.0');
            $tel        = Arrays::get($row, '14.3.0');
            $address    = Arrays::get($row, '14.2.0') . ', ' . Arrays::get($row, '14.2.1');
            $price      = Arrays::get($row, '14.4.2');
            $rate       = floatval(Arrays::get($row, '14.4.7'));
            $avis       = (int) Arrays::get($row, '14.4.8');
            $type       = Arrays::get($row, '14.13.0');
            $hexa       = Arrays::get($row, '14.10');
            $label      = Arrays::get($row, '14.18');
            $link       = Arrays::get($row, '14.4.3.0');

            if (strstr($link, 'ludocid')) {
                $cid        = 'g' . cut('ludocid=', '#', $link);
            } else {
                $cid        = 'g' . cut('.com/', '/', Arrays::get($row, '6.1'));
            }

            $horaires   = Arrays::get($row, '14.34.1');

            $abstract   = Arrays::get($row, '14.32.0.1') . '. ' . Arrays::get($row, '14.32.1.1');

            $obj = [
                'coords'    => Arrays::get($data, '3.0.0'),
                'hexa'      => $hexa,
                'cid'       => $cid,
                'type'      => $type,
                'label'     => $label,
                'abstract'  => $abstract,
                'name'      => $name,
                'lat'       => $latRow,
                'lng'       => $lngRow,
                'website'   => $ws,
                'phone'     => $tel,
                'address'   => $address,
                'rate'      => $rate,
                'avis'      => $avis,
                'price'     => $price,
                'schedule'  => $this->schedule($horaires),
                'img_in'    => 'http:' . Arrays::get($row, '14.37.0.1.0.0'),
                'img_out'   => 'http:' . Arrays::get($row, '14.37.0.2.6.0'),
                'place_id'  => Arrays::get($row, '14.78')
            ];

            if (strlen($obj['place_id'])) {
                $obj['external'] = json_decode($this->dwnCache('https://maps.googleapis.com/maps/api/place/details/json?placeid=' . $obj['place_id'] . '&key=AIzaSyBIfV0EMXrTDjrvD92QX5bBiyFmBbT-W8E&cb=pp'), true);
            }

            if ($obj['img_in'] == 'http:') unset($obj['img_in']);
            if ($obj['img_out'] == 'http:') unset($obj['img_out']);
            if ($obj['abstract'] == '. ') unset($obj['abstract']);

            return $obj;
        }

        public function addressByLatLng($lat, $lng)
        {
            $url = "https://www.google.com/maps/preview/reveal?authuser=0&hl=en&pb=!2m12!1m3!1d48113.26392487962!2d2.5015467909161497!3d44.430221399384884!2m3!1f0!2f0!3f0!3m2!1i1440!2i445!4f13.1!3m2!2d$lng!3d$lat";

            $data = $this->dwnCache($url);

            $data = str_replace("\n", "", $data);
            $data = str_replace("\r", "", $data);
            $data = str_replace("\t", "", $data);
            $data = str_replace(")]}'", "", $data);

            $data = json_decode($data, true);

            $res = [];

            $res['country']             = Arrays::get($data, '0.1');
            $res['administrative']      = Arrays::get($data, '6.1');
            $res['region']              = Arrays::get($data, '2.44.0.2.1.0');
            $res['info']                = Arrays::get($data, '2.44.0.1.1.0');
            $res['zip']                 = Arrays::get($data, '2.2.0');
            $res['city']                = Arrays::get($data, '0.0');
            $res['formatted_address']   = Arrays::get($data, '1.0');
            $res['hexa']                = Arrays::get($data, '1.1');
            $res['timezone']            = Arrays::get($data, '2.30');
            $res['temperature']         = Arrays::get($data, '2.69.2');
            $res['weather']             = Arrays::get($data, '2.69.1');
            $res['place_id']            = Arrays::get($data, '2.78');

            if (strlen($res['hexa'])) {
                $res['hexa'] = $this->ghexa($res['hexa']);
            }

            if (fnmatch('*,*', $res['country'])) {
                $tab            = explode(', ', Arrays::get($data, '0.1'));
                $res['country'] = Arrays::last($tab);
                $res['city']    = Arrays::get($data, '2.82.3');
                $res['address'] = Arrays::get($data, '2.82.2');
                $res['street']  = Arrays::get($data, '2.82.1');

                array_shift($tab);
                array_pop($tab);

                $res['zip'] = implode(', ', $tab);
            }

            if ($res['zip'] == 'France' || empty($res['zip'])) {
                if (fnmatch('*, *, *', Arrays::get($data, '1.0'))) {
                    $tab            = explode(', ', Arrays::get($data, '1.0'));
                    $res['country'] = array_pop($tab);
                    $res['address'] = array_shift($tab);

                    if (count($tab) == 2) {
                        $res['address'] = array_shift($tab);
                        $res['zip']     = str_replace(' ' . $res['city'], '', $tab[0]);
                    }

                    if (count($tab) == 1) {
                        $res['zip']     = str_replace(' ' . $res['city'], '', $tab[0]);
                    }
                }
            }

            if (fnmatch('*, *', $res['city'])) {
                list($c, $s) = explode(', ', $res['city'], 2);
                $res['region'] = $s;
                $res['city'] = $c;
            }

            if (fnmatch('* *', $res['zip'])) {
                list($c, $s) = explode(' ', $res['zip'], 2);

                if (is_numeric($s)) {
                    $res['zip'] = $s;
                    $res['region'] = $c;
                }
            }

            ksort($res);

            return $res;
        }

        /**
         * @param $url
         * @param null $max
         * @return mixed
         * @throws Exception
         * @throws \Exception
         */
        public function dwnCache($url, $max = null)
        {
            if (APPLICATION_ENV === 'testing') {
                return lib('geo')->dwn($url);
            } else {
                return fmr('geo')->until('url.' . sha1($url), function () use ($url) {
                    return lib('geo')->dwn($url);
                }, $max);
            }
        }

        public function dwn($url)
        {
            $userAgent  = "Mozilla/5.0 (Linux; U; Android 4.2.1; fr-fr; LG-L160L Build/IML74K) AppleWebkit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30";

            $ip         = rand(200, 225) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
            $ch         = curl_init();

            $headers    = [];

            curl_setopt($ch, CURLOPT_URL,       $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept-Language: fr,fr-fr;q=0.8,en-us;q=0.5,en;q=0.3']);

            $headers[] = "REMOTE_ADDR: $ip";
            $headers[] = "HTTP_X_FORWARDED_FOR: $ip";

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($ch);
            curl_close ($ch);

            return $result;
        }

        private function schedule($rows)
        {
            if (!is_array($rows)) {
                return [];
            }

            $schedule = [];

            foreach($rows as $row) {
                $day = $row[0];
                $schedules = $row[1];

                switch ($day) {
                    case 'lundi':
                        $order = 1;
                        break;
                    case 'mardi':
                        $order = 2;
                        break;
                    case 'mercredi':
                        $order = 3;
                        break;
                    case 'jeudi':
                        $order = 4;
                        break;
                    case 'vendredi':
                        $order = 5;
                        break;
                    case 'samedi':
                        $order = 6;
                        break;
                    case 'dimanche':
                        $order = 7;
                        break;
                    default:
                        return [];
                }

                $schedule[] = ['day' => $day, 'schedules' => $schedules, 'order' => $order];
            }

            $schedule = array_values(coll($schedule)->sortBy("order")->toArray());

            $return = [];

            foreach ($schedule as $d) {
                $return[$d['day']] = $d['schedules'];
            }

            return $return;
        }

        /**
         * @param $address
         * @return array
         * @throws Exception
         * @throws \Exception
         */
        public function getCoordsMap($address)
        {
            $lat = $lng = 0;

            $id_hex = $zip = $city = $number = $street = $addr = null;

            $key = 'g.maps.' . sha1($address);

            $url    = 'https://www.google.fr/search?tbm=map&fp=1&authuser=0&hl=fr&q=' . urlencode($address);

            $json   = $this->dwnCache($url);

            $json = str_replace(["\t", "\n", "\r"], '', $json);

            list($dummy, $segTab) = explode(")]}'", $json, 2);

            $code = '$tab = ' . $segTab . ';';

            $place_id = 'Ch' . cut('null,"Ch', '"', $json);

            if (strstr($json, 'ludocid')) {
                $id_poi = (string) cut('ludocid%3D', '%', $json);

                if (!strlen($id_poi)) {
                    $id_poi = (string) cut('ludocid\u003d', '#', $json);
                }

                $d = $this->dwnCache("https://www.google.com/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!4s$id_poi!2m2!1sfr!2sUS!6e1!13m1!4b1");

                $d = str_replace(["\t", "\n", "\r"], '', $d);

                list($dummy, $segTab) = explode(")]}'", $d, 2);

                $dtab = [];

                eval('$dtab = ' . $segTab . ';');

                if (isset($dtab[1])) {
                    $i = $dtab[1];

                    $id_hex = Arrays::get($i, '0.0');
                    $lat    = Arrays::get($i, '0.2.0');
                    $lng    = Arrays::get($i, '0.2.1');
                    $addr   = Arrays::get($i, '13');
                    $type   = Arrays::get($i, '12');

                    if (fnmatch('*, *, *', $addr)) {
                        list($streetAddress, $zipCity, $country) = explode(', ', $addr, 3);
                    } elseif (fnmatch('*, *', $addr)) {
                        $zipCity = null;
                        list($streetAddress, $country) = explode(', ', $addr, 2);
                    } else {
                        $country = $addr;
                        $streetAddress = $zipCity = null;
                    }

                    if (fnmatch('* * *', $streetAddress)) {
                        list($number, $street) = explode(' ', $streetAddress, 2);

                        if (!is_numeric($number)) {
                            $street = $streetAddress;
                            $number = null;
                        }
                    }

                    if (fnmatch('* *', $zipCity)) {
                        list($zip, $city) = explode(' ', $zipCity, 2);

                        if (!is_numeric($zip)) {
                            $city = $streetAddress;
                            $zip = null;
                        }
                    }

                    $tel    = isset($i[7]) ? $i[7] : null;
                    $name   = isset($i[1]) ? $i[1] : null;

                    $ws = null;

                    if (isset($i[11])) {
                        if (isset($i[11][0])) {
                            $ws = $i[11][0];
                        }
                    }

                    if (fnmatch("*q=*", $ws) && fnmatch("*u0026*", $ws)) {
                        $ws = cut('q=', '\u0026', $ws);
                    }

                    $box = $this->getBoundingBox($lat, $lng);

                    return [
                        'lat'                   => (double) $lat,
                        'lng'                   => (double) $lng,
                        'box'                   => $box,
                        'name'                  => (string) $name,
                        'type'                  => (string) $type,
                        'normalized_address'    => (string) $addr,
                        'country'               => (string) $country,
                        'street'                => (string) $street,
                        'number'                => (string) $number,
                        'city'                  => (string) $city,
                        'id_place'              => $place_id,
                        'id_poi'                => $id_poi,
                        'id_hex'                => $id_hex,
                        'tel'                   => $tel,
                        'site'                  => $ws,
                        'zip'                   => (string) $zip
                    ];
                }
            }

            if (strstr($json, 'https://www.google.com/local/add/choice?latlng\u003d')) {
                $id_poi = (string) cut('https://www.google.com/local/add/choice?latlng\u003d', '\u0026', $json);

                $d = $this->dwnCache("https://www.google.com/maps/api/js/ApplicationService.GetEntityDetails?pb=!1m2!1m1!4s$id_poi!2m2!1sfr!2sUS!6e1!13m1!4b1");

                $d = str_replace(["\t", "\n", "\r"], '', $d);

                list($dummy, $segTab) = explode(")]}'", $d, 2);

                try {
                    eval('$dtab = ' . $segTab . ';');
                } catch (\Exception $e) {}

                $i = $dtab[1];

                $lat    = Arrays::get($i, '0.2.0');
                $lng    = Arrays::get($i, '0.2.1');
                $addr   = Arrays::get($i, '13');
                $type   = Arrays::get($i, '12');

                $streetAddress = '';

                if (fnmatch('*, *, *', $addr)) {
                    list($streetAddress, $zipCity, $country) = explode(', ', $addr, 3);
                } else {
                    if (fnmatch('*, *', $addr)) {
                        list($zipCity, $country) = explode(', ', $addr, 3);
                    }
                }

                if (fnmatch('* * *', $streetAddress)) {
                    list($number, $street) = explode(' ', $streetAddress, 2);

                    if (!is_numeric($number)) {
                        $street = $streetAddress;
                        $number = null;
                    }
                }

                if (fnmatch('* *', $zipCity)) {
                    list($zip, $city) = explode(' ', $zipCity, 2);

                    if (!is_numeric($zip)) {
                        $city = $streetAddress;
                        $zip = null;
                    }
                }

                $tel    = isset($i[7]) ? $i[7] : null;
                $name   = isset($i[1]) ? $i[1] : null;

                $ws = null;

                if (isset($i[11])) {
                    if (isset($i[11][0])) {
                        $ws = $i[11][0];
                    }
                }

                if (fnmatch("*q=*", $ws) && fnmatch("*u0026*", $ws)) {
                    $ws = cut('q=', '\u0026', $ws);
                }

                $box = $this->getBoundingBox($lat, $lng);

                return [
                    'lat'                   => (double) $lat,
                    'lng'                   => (double) $lng,
                    'box'                   => $box,
                    'name'                  => (string) $name,
                    'type'                  => (string) $type,
                    'normalized_address'    => (string) $addr,
                    'country'               => (string) $country,
                    'street'                => (string) $street,
                    'number'                => (string) $number,
                    'city'                  => (string) $city,
                    'id_place'              => $place_id,
                    'id_poi'                => $id_poi,
                    'id_hex'                => $i[0][0],
                    'tel'                   => $tel,
                    'site'                  => $ws,
                    'zip'                   => (string) $zip
                ];
            }

            $seg = cut('/@', '/data', $json);

            $tab = explode(',', $seg);

            $lat = floatval($tab[0]);
            $lng = floatval($tab[1]);

            $addr = urldecode(cut('preview/place/', '/', $json));

            $id_hex = '0x' . cut('],"0x', '"', $code);

            if (fnmatch('*, *', $addr)) {
                list($streetAddress, $cpCity) = explode(', ', $addr, 2);

                if (fnmatch('* *', $cpCity)) {
                    list($zip, $city) = explode(' ', $cpCity, 2);

                    if (!is_numeric($zip)) {
                        $city = $streetAddress;
                        $zip = null;
                    }
                }

                if (fnmatch('* * *', $streetAddress)) {
                    list($number, $street) = explode(' ', $streetAddress, 2);

                    if (!is_numeric($number)) {
                        $street = $streetAddress;
                        $number = null;
                    }
                }
            }

            $box = $this->getBoundingBox($lat, $lng);

            return [
                'lat'                   => (double) $lat,
                'lng'                   => (double) $lng,
                'id_place'              => $place_id,
                'id_hex'                => $id_hex,
                'box'                   => $box,
                'normalized_address'    => (string) $addr,
                'street'                => (string) $street,
                'number'                => (string) $number,
                'city'                  => (string) $city,
                'zip'                   => (string) $zip
            ];
        }

        public function getBoundingBox($lat, $lng, $distance = 2, $km = true)
        {
            $lat = floatval($lat);
            $lng = floatval($lng);

            $radius = $km ? 6372.797 : 3963.1; // of earth in km or miles

            // bearings - FIX
            $due_north  = deg2rad(0);
            $due_south  = deg2rad(180);
            $due_east   = deg2rad(90);
            $due_west   = deg2rad(270);

            // convert latitude and longitude into radians
            $lat_r = deg2rad($lat);
            $lon_r = deg2rad($lng);

            $northmost  = asin(sin($lat_r) * cos($distance / $radius) + cos($lat_r) * sin($distance / $radius) * cos($due_north));
            $southmost  = asin(sin($lat_r) * cos($distance / $radius) + cos($lat_r) * sin($distance / $radius) * cos($due_south));

            $eastmost = $lon_r + atan2(sin($due_east) * sin($distance / $radius) * cos($lat_r), cos($distance / $radius) - sin($lat_r) * sin($lat_r));
            $westmost = $lon_r + atan2(sin($due_west) * sin($distance / $radius) * cos($lat_r), cos($distance / $radius) - sin($lat_r) * sin($lat_r));

            $northmost  = rad2deg($northmost);
            $southmost  = rad2deg($southmost);
            $eastmost   = rad2deg($eastmost);
            $westmost   = rad2deg($westmost);

            if ($northmost > $southmost) {
                $lat1 = $southmost;
                $lat2 = $northmost;
            } else {
                $lat1 = $northmost;
                $lat2 = $southmost;
            }

            if ($eastmost > $westmost) {
                $lon1 = $westmost;
                $lon2 = $eastmost;
            } else {
                $lon1 = $eastmost;
                $lon2 = $westmost;
            }

            return [$lat1, $lon1, $lat2, $lon2];
        }

        public function distance($lng1, $lat1, $lng2, $lat2, $kmRender = false)
        {
            $lng1 = floatval(str_replace(',', '.', $lng1));
            $lat1 = floatval(str_replace(',', '.', $lat1));
            $lng2 = floatval(str_replace(',', '.', $lng2));
            $lat2 = floatval(str_replace(',', '.', $lat2));

            $pi80 = M_PI / 180;
            $lat1 *= $pi80;
            $lng1 *= $pi80;
            $lat2 *= $pi80;
            $lng2 *= $pi80;

            $dlat           = $lat2 - $lat1;
            $dlng           = $lng2 - $lng1;
            $a              = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
            $c              = 2 * atan2(sqrt($a), sqrt(1 - $a));

            /* km */
            $earthRadius    = 6372.797;
            $km             = $earthRadius * $c;
            $km             = round($km, 2);

            /* miles */
            $earthRadius    = 3963.1;
            $miles          = $earthRadius * $c;
            $miles          = round($miles, 2);

            return $kmRender ? $km : ['km' => $km, 'miles' => $miles];
        }

        /**
         * @param $lat
         * @param $lng
         * @param string $type
         * @return array
         * @throws Exception
         * @throws \Exception
         */
        public function places($lat, $lng, $type = 'restaurant')
        {
            $url = "https://www.google.fr/search?tbm=map&fp=1&authuser=0&hl=fr&pb=!4m12!1m3!1d4073.5434512203738!2d$lng!3d$lat!2m3!1f0!2f0!3f0!3m2!1i1360!2i298!4f13.1!7i10!10b1!12m6!2m3!5m1!2b0!20e3!10b1!16b1!19m3!2m2!1i392!2i106!20m40!2m2!1i203!2i200!3m1!2i4!6m6!1m2!1i86!2i86!1m2!1i408!2i256!7m26!1m3!1e1!2b0!3e3!1m3!1e2!2b1!3e2!1m3!1e2!2b0!3e3!1m3!1e3!2b0!3e3!1m3!1e4!2b0!3e3!1m3!1e3!2b1!3e2!2b1!4b0!9b0!7e81!24m1!2b1!26m3!2m2!1i80!2i92!30m28!1m6!1m2!1i0!2i0!2m2!1i458!2i298!1m6!1m2!1i1310!2i0!2m2!1i1360!2i298!1m6!1m2!1i0!2i0!2m2!1i1360!2i20!1m6!1m2!1i0!2i278!2m2!1i1360!2i298!37m1!1e81&q=" . urlencode($type);

            $data = fmr('geo')->until('gpois.' . sha1(serialize(func_get_args()) . $type), function () use ($url) {
                return lib('geo')->dwn($url);
            }, strtotime('+6 month'));

            $data = str_replace("\n", "", $data);
            $data = str_replace("\r", "", $data);
            $data = str_replace("\t", "", $data);
            $data = str_replace(")]}'", "", $data);

            $data = json_decode($data, true);

            $tab = $data[0][1];

            array_shift($tab);

            $collection = [];

            foreach ($tab as $row) {
                $latRow     = Arrays::get($row, '14.9.2');
                $lngRow     = Arrays::get($row, '14.9.3');
                $distances  = $this->distance($lng, $lat, $lngRow, $latRow);
                $name       = Arrays::get($row, '14.11');
                $ws         = Arrays::get($row, '14.7.0');
                $tel        = Arrays::get($row, '14.3.0');
                $address    = Arrays::get($row, '14.2.0') . ', ' . Arrays::get($row, '14.2.1');
                $price      = Arrays::get($row, '14.4.2');
                $rate       = floatval(Arrays::get($row, '14.4.7'));
                $avis       = (int) Arrays::get($row, '14.42.8');
                $type       = Arrays::get($row, '14.13.0');
                $hexa       = Arrays::get($row, '14.10');
                $label      = Arrays::get($row, '14.18');
                $link       = Arrays::get($row, '14.4.3.0');

                if (strstr($link, 'ludocid')) {
                    $cid = 'g' . cut('ludocid=', '#', $link);
                } else {
                    $cid = 'g' . cut('.com/', '/', Arrays::get($row, '6.1'));
                }

                $horaires = Arrays::get($row, '14.34.1');

                $abstract = Arrays::get($row, '14.32.0.1') . '. ' . Arrays::get($row, '14.32.1.1');

                $obj = [
                    'distance'  => $distances['km'] * 1000,
                    'hexa'      => $hexa,
                    'cid'       => $cid,
                    'type'      => $type,
                    'label'     => $label,
                    'abstract'  => $abstract,
                    'name'      => $name,
                    'lat'       => $latRow,
                    'lng'       => $lngRow,
                    'website'   => $ws,
                    'phone'     => $tel,
                    'address'   => $address,
                    'rate'      => $rate,
                    'avis'      => $avis,
                    'price'     => $price,
                    'schedule'  => $this->schedule($horaires),
                    'img_in'    => 'http:' . Arrays::get($row, '14.37.0.1.6.0'),
                    'img_out'   => 'http:' . Arrays::get($row, '14.37.0.2.6.0'),
                ];

                if ($obj['img_in'] == 'http:') {
                    continue;
                } else {
                    $obj['img_in'] .= '&w=600&h=400';
                }

                if ($obj['img_out'] == 'http:') {
                    $obj['img_out'] = null;
                } else {
                    $obj['img_out'] .= '&w=600&h=400';
                }

                $collection[] = $obj;
            }

            $collection = coll($collection)->sortBy('distance')->toArray();
            $collection = array_values($collection);

            return $collection;
        }

        /**
         * @param $address
         * @param string $type
         *
         * @return array
         *
         * @throws Exception
         * @throws \Exception
         */
        public function placesByAddress($address, $type = 'restaurant')
        {
            $data = $this->getCoordsMap($address);

            return $this->places(floatval($data["lat"]), floatval($data["lng"]), $type);
        }
    }
