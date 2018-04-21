<?php
    namespace Octo;

    class Inflector
    {
        protected static $_transliteration = array(
            '/à|á|å|â/'     => 'a',
            '/è|é|ê|ẽ|ë/'   => 'e',
            '/ì|í|î/'       => 'i',
            '/ò|ó|ô|ø/'     => 'o',
            '/ù|ú|ů|û/'     => 'u',
            '/ç/'           => 'c',
            '/ñ/'           => 'n',
            '/ä|æ/'         => 'ae',
            '/ö/'           => 'oe',
            '/ü/'           => 'ue',
            '/Ä/'           => 'Ae',
            '/Ü/'           => 'Ue',
            '/Ö/'           => 'Oe',
            '/ß/'           => 'ss'
       );

        protected static $_uninflected = array(
            'Amoyese', 'bison', 'Borghese', 'bream', 'breeches', 'britches', 'buffalo', 'cantus',
            'carp', 'chassis', 'clippers', 'cod', 'coitus', 'Congoese', 'contretemps', 'corps',
            'debris', 'diabetes', 'djinn', 'eland', 'elk', 'equipment', 'Faroese', 'flounder',
            'Foochowese', 'gallows', 'Genevese', 'Genoese', 'Gilbertese', 'graffiti',
            'headquarters', 'herpes', 'hijinks', 'Hottentotese', 'information', 'innings',
            'jackanapes', 'Kiplingese', 'Kongoese', 'Lucchese', 'mackerel', 'Maltese', 'media',
            'mews', 'moose', 'mumps', 'Nankingese', 'news', 'nexus', 'Niasese', 'People',
            'Pekingese', 'Piedmontese', 'pincers', 'Pistoiese', 'pliers', 'Portuguese',
            'proceedings', 'rabies', 'rice', 'rhinoceros', 'salmon', 'Sarawakese', 'scissors',
            'sea[- ]bass', 'series', 'Shavese', 'shears', 'siemens', 'species', 'swine', 'testes',
            'trousers', 'trout','tuna', 'Vermontese', 'Wenchowese', 'whiting', 'wildebeest',
            'Yengeese'
       );

        protected static $_singular = array(
            'rules' => array(
                '/(s)tatuses$/i'                                                            => '\1\2tatus',
                '/^(.*)(menu)s$/i'                                                          => '\1\2',
                '/(quiz)zes$/i'                                                             => '\\1',
                '/(matr)ices$/i'                                                            => '\1ix',
                '/(vert|ind)ices$/i'                                                        => '\1ex',
                '/^(ox)en/i'                                                                => '\1',
                '/(alias)(es)*$/i'                                                          => '\1',
                '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|viri?)i$/i'   => '\1us',
                '/(cris|ax|test)es$/i'                                                      => '\1is',
                '/(shoe)s$/i'                                                               => '\1',
                '/(o)es$/i'                                                                 => '\1',
                '/ouses$/'                                                                  => 'ouse',
                '/uses$/'                                                                   => 'us',
                '/([m|l])ice$/i'                                                            => '\1ouse',
                '/(x|ch|ss|sh)es$/i'                                                        => '\1',
                '/(m)ovies$/i'                                                              => '\1\2ovie',
                '/(s)eries$/i'                                                              => '\1\2eries',
                '/([^aeiouy]|qu)ies$/i'                                                     => '\1y',
                '/([lr])ves$/i'                                                             => '\1f',
                '/(tive)s$/i'                                                               => '\1',
                '/(hive)s$/i'                                                               => '\1',
                '/(drive)s$/i'                                                              => '\1',
                '/([^fo])ves$/i'                                                            => '\1fe',
                '/(^analy)ses$/i'                                                           => '\1sis',
                '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i'          => '\1\2sis',
                '/([ti])a$/i'                                                               => '\1um',
                '/(p)eople$/i'                                                              => '\1\2erson',
                '/(m)en$/i'                                                                 => '\1an',
                '/(c)hildren$/i'                                                            => '\1\2hild',
                '/(n)ews$/i'                                                                => '\1\2ews',
                '/^(.*us)$/'                                                                => '\\1',
                '/s$/i'                                                                     => ''
            ),
            'irregular' => array(),
            'uninflected' => array(
                '.*[nrlm]ese',
                '.*deer',
                '.*fish', '
                .*measles',
                '.*ois',
                '.*pox',
                '.*sheep',
                '.*ss'
            )
       );

        protected static $_singularized = array();

        protected static $_plural = array(
            'rules' => array(
                '/(s)tatus$/i' => '\1\2tatuses',
                '/(quiz)$/i' => '\1zes',
                '/^(ox)$/i' => '\1\2en',
                '/([m|l])ouse$/i' => '\1ice',
                '/(matr|vert|ind)(ix|ex)$/i'  => '\1ices',
                '/(x|ch|ss|sh)$/i' => '\1es',
                '/([^aeiouy]|qu)y$/i' => '\1ies',
                '/(hive)$/i' => '\1s',
                '/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
                '/sis$/i' => 'ses',
                '/([ti])um$/i' => '\1a',
                '/(p)erson$/i' => '\1eople',
                '/(m)an$/i' => '\1en',
                '/(c)hild$/i' => '\1hildren',
                '/(buffal|tomat)o$/i' => '\1\2oes',
                '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|vir)us$/i' => '\1i',
                '/us$/' => 'uses',
                '/(alias)$/i' => '\1es',
                '/(ax|cri|test)is$/i' => '\1es',
                '/s$/' => 's',
                '/^$/' => '',
                '/$/' => 's'
            ),
            'irregular' => array(
                'atlas' => 'atlases', 'beef' => 'beefs', 'brother' => 'brothers',
                'child' => 'children', 'corpus' => 'corpuses', 'cow' => 'cows',
                'ganglion' => 'ganglions', 'genie' => 'genies', 'genus' => 'genera',
                'graffito' => 'graffiti', 'hoof' => 'hoofs', 'loaf' => 'loaves', 'man' => 'men',
                'leaf' => 'leaves', 'money' => 'monies', 'mongoose' => 'mongooses', 'move' => 'moves',
                'mythos' => 'mythoi', 'numen' => 'numina', 'occiput' => 'occiputs',
                'octopus' => 'octopuses', 'opus' => 'opuses', 'ox' => 'oxen', 'penis' => 'penises',
                'person' => 'people', 'sex' => 'sexes', 'soliloquy' => 'soliloquies',
                'testis' => 'testes', 'trilby' => 'trilbys', 'turf' => 'turfs'
            ),
            'uninflected' => array(
                '.*[nrlm]ese', '.*deer', '.*fish', '.*measles', '.*ois', '.*pox', '.*sheep'
            )
       );

        protected static $_pluralized = array();

        protected static $_camelized = array();

        protected static $_underscored = array();

        protected static $_humanized = array();

        public static function contains($haystack, $needles)
        {
            foreach ((array) $needles as $needle) {
                if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                    return true;
                }
            }

            return false;
        }

        public static function utf8($str)
        {
            if (false === isUtf8($str)) {
                $str = utf8_encode($str);
            }

            return $str;
        }

        public static function rules($type, $config = array())
        {
            $var = '_' . $type;

            if (!isset(static::${$var})) {
                return null;
            }

            if (empty($config)) {
                return static::${$var};
            }

            switch ($type) {
                case 'transliteration':
                    $_config = array();

                    foreach ($config as $key => $val) {
                        if ($key[0] != '/') {
                            $key = '/' . join('|', array_filter(preg_split('//u', $key))) . '/';
                        }

                        $_config[$key] = $val;
                    }
                    static::$_transliteration = array_merge(
                        $_config,
                        static::$_transliteration,
                        $_config
                    );
                break;
                case 'uninflected':
                    static::$_uninflected                   = array_merge(static::$_uninflected, (array) $config);
                    static::$_plural['regexUninflected']    = null;
                    static::$_singular['regexUninflected']  = null;

                    foreach ((array) $config as $word) {
                        unset(static::$_singularized[$word], static::$_pluralized[$word]);
                    }
                break;
                case 'singular':
                case 'plural':
                    if (isset(static::${$var}[key($config)])) {
                        foreach ($config as $rType => $set) {
                            static::${$var}[$rType] = array_merge($set, static::${$var}[$rType], $set);

                            if ($rType == 'irregular') {
                                $swap                       = ($type == 'singular' ? '_plural' : '_singular');
                                static::${$swap}[$rType]    = array_flip(static::${$var}[$rType]);
                            }
                        }
                    } else {
                        static::${$var}['rules'] = array_merge(
                            $config, static::${$var}['rules'], $config
                        );
                    }
                break;
            }
        }

        public static function pluralize($word)
        {
            if (isset(static::$_pluralized[$word])) {
                return static::$_pluralized[$word];
            }

            extract(static::$_plural);

            if (!isset($regexUninflected) || !isset($regexIrregular)) {
                $regexUninflected = static::_enclose(join('|', $uninflected + static::$_uninflected));
                $regexIrregular = static::_enclose(join('|', array_keys($irregular)));

                static::$_plural += compact('regexUninflected', 'regexIrregular');
            }

            if (preg_match('/(' . $regexUninflected . ')$/i', $word, $regs)) {
                return static::$_pluralized[$word] = $word;
            }

            if (preg_match('/(.*)\\b(' . $regexIrregular . ')$/i', $word, $regs)) {
                $plural = substr($word, 0, 1) . substr($irregular[static::lower($regs[2])], 1);

                return static::$_pluralized[$word] = $regs[1] . $plural;
            }

            foreach ($rules as $rule => $replacement) {
                if (preg_match($rule, $word)) {
                    return static::$_pluralized[$word] = preg_replace($rule, $replacement, $word);
                }
            }

            return static::$_pluralized[$word] = $word;
        }

        public static function replace($a, $b, $s)
        {
            return str_replace($a, $b, $s);
        }

        public static function singularize($word)
        {
            if (isset(static::$_singularized[$word])) {
                return static::$_singularized[$word];
            }

            if (empty(static::$_singular['irregular'])) {
                static::$_singular['irregular'] = array_flip(static::$_plural['irregular']);
            }

            extract(static::$_singular);

            if (!isset($regexUninflected) || !isset($regexIrregular)) {
                $regexUninflected = static::_enclose(join('|', $uninflected + static::$_uninflected));
                $regexIrregular = static::_enclose(join('|', array_keys($irregular)));
                static::$_singular += compact('regexUninflected', 'regexIrregular');
            }

            if (preg_match("/(.*)\\b({$regexIrregular})\$/i", $word, $regs)) {
                $singular = substr($word, 0, 1) . substr($irregular[strtolower($regs[2])], 1);
                return static::$_singularized[$word] = $regs[1] . $singular;
            }

            if (preg_match('/^(' . $regexUninflected . ')$/i', $word, $regs)) {
                return static::$_singularized[$word] = $word;
            }

            foreach ($rules as $rule => $replacement) {
                if (preg_match($rule, $word)) {
                    return static::$_singularized[$word] = preg_replace($rule, $replacement, $word);
                }
            }

            return static::$_singularized[$word] = $word;
        }

        public static function reset()
        {
            static::$_singularized = static::$_pluralized = array();
            static::$_camelized = static::$_underscored = array();
            static::$_humanized = array();

            static::$_plural['regexUninflected'] = static::$_singular['regexUninflected'] = null;
            static::$_plural['regexIrregular'] = static::$_singular['regexIrregular'] = null;
            static::$_transliteration = array(
                '/à|á|å|â/'     => 'a',
                '/è|é|ê|ẽ|ë/'   => 'e',
                '/ì|í|î/'       => 'i',
                '/ò|ó|ô|ø/'     => 'o',
                '/ù|ú|ů|û/'     => 'u',
                '/ç/'           => 'c',
                '/ñ/'           => 'n',
                '/ä|æ/'         => 'ae',
                '/ö/'           => 'oe',
                '/ü/'           => 'ue',
                '/Ä/'           => 'Ae',
                '/Ü/'           => 'Ue',
                '/Ö/'           => 'Oe',
                '/ß/'           => 'ss'
            );
        }

        public static function camelize($string, $spacify = true, $lazy = false)
        {
            return implode('', explode(' ', ucwords(implode(' ', explode('_', $string)))));
        }

        public static function uncamelize($string, $splitter = "_")
        {
            $string = preg_replace('/(?!^)[[:upper:]][[:lower:]]/', '$0', preg_replace('/(?!^)[[:upper:]]+/', $splitter . '$0', $string));

            return static::lower($string);
        }

        public static function underscore($word)
        {
            if (isset(static::$_underscored[$word])) {
                return static::$_underscored[$word];
            }

            return static::$_underscored[$word] = static::lower(static::slug($word, '_'));
        }

        public static function slug($string, $replacement = '-')
        {
            $map = static::$_transliteration + [
                '/[^\w\s]/'             => ' ',
                '/\\s+/'                => $replacement,
                '/(?<=[a-z])([A-Z])/'   => $replacement . '\\1',
                str_replace(
                    ':rep',
                    preg_quote(
                        $replacement,
                        '/'
                    ),
                    '/^[:rep]+|[:rep]+$/'
                ) => ''
            ];

            return preg_replace(
                array_keys($map),
                array_values($map),
                $string
            );
        }

        public static function humanize($word, $separator = '_')
        {
            if (isset(static::$_humanized[$key = $word . ':' . $separator])) {
                return static::$_humanized[$key];
            }

            return static::$_humanized[$key] = ucwords(str_replace($separator, " ", $word));
        }

        public static function tableize($className)
        {
            return static::pluralize(static::underscore($className));
        }

        public static function reclassify($tableName)
        {
            return static::camelize(static::singularize($tableName));
        }

        protected static function _enclose($string)
        {
            return '(?:' . $string . ')';
        }

        public static function length($value)
        {
            return (MB_STRING) ? mb_strlen($value, static::encoding()) : strlen($value);
        }

        public static function lower($value)
        {
            return (MB_STRING) ? mb_strtolower($value, static::encoding()) : strtolower($value);
        }

        public static function upper($value)
        {
            return (MB_STRING) ? mb_strtoupper($value, static::encoding()) : strtoupper($value);
        }

        public static function substr($str, $start, $length = false, $encoding = 'utf-8')
        {
            if (is_array($str)) {
                return false;
            }

            if (function_exists('mb_substr')) {
                return mb_substr($str, (int)$start, ($length === false ? static::length($str) : (int)$length), $encoding);
            }

            return substr($str, $start, ($length === false ? static::length($str) : (int)$length));
        }

        public static function ucfirst($str)
        {
            return static::upper(static::substr($str, 0, 1)) . static::substr($str, 1);
        }

        public static function isEmpty($value)
        {
            return ($value === '' || $value === null);
        }

        public static function encoding()
        {
            return 'utf-8';
        }

        public static function urlize($text, $separator = '-')
        {
            $text = str_replace(
                array(
                    "\n",
                    "\r",
                    "\t",
                ),
                '',
                $text
            );

            $text = static::lower(static::unaccent($text));

            $text = preg_replace('/\W/', ' ', $text);

            $text = static::lower(
                preg_replace(
                    '/[^A-Z^a-z^0-9^\/]+/',
                    $separator,
                    preg_replace(
                        '/([a-z\d])([A-Z])/',
                        '\1_\2',
                        preg_replace(
                            '/([A-Z]+)([A-Z][a-z])/',
                            '\1_\2',
                            preg_replace(
                                '/::/',
                                '/',
                                $text
                            )
                        )
                    )
                )
            );

            return trim($text, $separator);
        }

        public static function makeIndexes($str)
        {
            $words = static::urlize($str, ' ');

            $words = explode(' ', $words);

            $collection = array();

            if (!empty($words)) {
                foreach ($words as $word) {
                    if (strlen($word) > 1 || !is_numeric($word)) {
                        array_push($collection, $word);
                    }
                }
            }

            return $collection;
        }

        public static function unaccent($string)
        {
            if (!preg_match('/[\x80-\xff]/', $string)) {
                return $string;
            }

            if (isUtf8($string)) {
                $chars = array(
                    chr(195).chr(128) => 'A',
                    chr(195).chr(129) => 'A',
                    chr(195).chr(130) => 'A',
                    chr(195).chr(131) => 'A',
                    chr(195).chr(132) => 'A',
                    chr(195).chr(133) => 'A',
                    chr(195).chr(135) => 'C',
                    chr(195).chr(136) => 'E',
                    chr(195).chr(137) => 'E',
                    chr(195).chr(138) => 'E',
                    chr(195).chr(139) => 'E',
                    chr(195).chr(140) => 'I',
                    chr(195).chr(141) => 'I',
                    chr(195).chr(142) => 'I',
                    chr(195).chr(143) => 'I',
                    chr(195).chr(145) => 'N',
                    chr(195).chr(146) => 'O',
                    chr(195).chr(147) => 'O',
                    chr(195).chr(148) => 'O',
                    chr(195).chr(149) => 'O',
                    chr(195).chr(150) => 'O',
                    chr(195).chr(153) => 'U',
                    chr(195).chr(154) => 'U',
                    chr(195).chr(155) => 'U',
                    chr(195).chr(156) => 'U',
                    chr(195).chr(157) => 'Y',
                    chr(195).chr(159) => 's',
                    chr(195).chr(160) => 'a',
                    chr(195).chr(161) => 'a',
                    chr(195).chr(162) => 'a',
                    chr(195).chr(163) => 'a',
                    chr(195).chr(164) => 'a',
                    chr(195).chr(165) => 'a',
                    chr(195).chr(167) => 'c',
                    chr(195).chr(168) => 'e',
                    chr(195).chr(169) => 'e',
                    chr(195).chr(170) => 'e',
                    chr(195).chr(171) => 'e',
                    chr(195).chr(172) => 'i',
                    chr(195).chr(173) => 'i',
                    chr(195).chr(174) => 'i',
                    chr(195).chr(175) => 'i',
                    chr(195).chr(177) => 'n',
                    chr(195).chr(178) => 'o',
                    chr(195).chr(179) => 'o',
                    chr(195).chr(180) => 'o',
                    chr(195).chr(181) => 'o',
                    chr(195).chr(182) => 'o',
                    chr(195).chr(182) => 'o',
                    chr(195).chr(185) => 'u',
                    chr(195).chr(186) => 'u',
                    chr(195).chr(187) => 'u',
                    chr(195).chr(188) => 'u',
                    chr(195).chr(189) => 'y',
                    chr(195).chr(191) => 'y',
                    chr(196).chr(128) => 'A',
                    chr(196).chr(129) => 'a',
                    chr(196).chr(130) => 'A',
                    chr(196).chr(131) => 'a',
                    chr(196).chr(132) => 'A',
                    chr(196).chr(133) => 'a',
                    chr(196).chr(134) => 'C',
                    chr(196).chr(135) => 'c',
                    chr(196).chr(136) => 'C',
                    chr(196).chr(137) => 'c',
                    chr(196).chr(138) => 'C',
                    chr(196).chr(139) => 'c',
                    chr(196).chr(140) => 'C',
                    chr(196).chr(141) => 'c',
                    chr(196).chr(142) => 'D',
                    chr(196).chr(143) => 'd',
                    chr(196).chr(144) => 'D',
                    chr(196).chr(145) => 'd',
                    chr(196).chr(146) => 'E',
                    chr(196).chr(147) => 'e',
                    chr(196).chr(148) => 'E',
                    chr(196).chr(149) => 'e',
                    chr(196).chr(150) => 'E',
                    chr(196).chr(151) => 'e',
                    chr(196).chr(152) => 'E',
                    chr(196).chr(153) => 'e',
                    chr(196).chr(154) => 'E',
                    chr(196).chr(155) => 'e',
                    chr(196).chr(156) => 'G',
                    chr(196).chr(157) => 'g',
                    chr(196).chr(158) => 'G',
                    chr(196).chr(159) => 'g',
                    chr(196).chr(160) => 'G',
                    chr(196).chr(161) => 'g',
                    chr(196).chr(162) => 'G',
                    chr(196).chr(163) => 'g',
                    chr(196).chr(164) => 'H',
                    chr(196).chr(165) => 'h',
                    chr(196).chr(166) => 'H',
                    chr(196).chr(167) => 'h',
                    chr(196).chr(168) => 'I',
                    chr(196).chr(169) => 'i',
                    chr(196).chr(170) => 'I',
                    chr(196).chr(171) => 'i',
                    chr(196).chr(172) => 'I',
                    chr(196).chr(173) => 'i',
                    chr(196).chr(174) => 'I',
                    chr(196).chr(175) => 'i',
                    chr(196).chr(176) => 'I',
                    chr(196).chr(177) => 'i',
                    chr(196).chr(178) => 'IJ',
                    chr(196).chr(179) => 'ij',
                    chr(196).chr(180) => 'J',
                    chr(196).chr(181) => 'j',
                    chr(196).chr(182) => 'K',
                    chr(196).chr(183) => 'k',
                    chr(196).chr(184) => 'k',
                    chr(196).chr(185) => 'L',
                    chr(196).chr(186) => 'l',
                    chr(196).chr(187) => 'L',
                    chr(196).chr(188) => 'l',
                    chr(196).chr(189) => 'L',
                    chr(196).chr(190) => 'l',
                    chr(196).chr(191) => 'L',
                    chr(197).chr(128) => 'l',
                    chr(197).chr(129) => 'L',
                    chr(197).chr(130) => 'l',
                    chr(197).chr(131) => 'N',
                    chr(197).chr(132) => 'n',
                    chr(197).chr(133) => 'N',
                    chr(197).chr(134) => 'n',
                    chr(197).chr(135) => 'N',
                    chr(197).chr(136) => 'n',
                    chr(197).chr(137) => 'N',
                    chr(197).chr(138) => 'n',
                    chr(197).chr(139) => 'N',
                    chr(197).chr(140) => 'O',
                    chr(197).chr(141) => 'o',
                    chr(197).chr(142) => 'O',
                    chr(197).chr(143) => 'o',
                    chr(197).chr(144) => 'O',
                    chr(197).chr(145) => 'o',
                    chr(197).chr(146) => 'OE',
                    chr(197).chr(147) => 'oe',
                    chr(197).chr(148) => 'R',
                    chr(197).chr(149) => 'r',
                    chr(197).chr(150) => 'R',
                    chr(197).chr(151) => 'r',
                    chr(197).chr(152) => 'R',
                    chr(197).chr(153) => 'r',
                    chr(197).chr(154) => 'S',
                    chr(197).chr(155) => 's',
                    chr(197).chr(156) => 'S',
                    chr(197).chr(157) => 's',
                    chr(197).chr(158) => 'S',
                    chr(197).chr(159) => 's',
                    chr(197).chr(160) => 'S',
                    chr(197).chr(161) => 's',
                    chr(197).chr(162) => 'T',
                    chr(197).chr(163) => 't',
                    chr(197).chr(164) => 'T',
                    chr(197).chr(165) => 't',
                    chr(197).chr(166) => 'T',
                    chr(197).chr(167) => 't',
                    chr(197).chr(168) => 'U',
                    chr(197).chr(169) => 'u',
                    chr(197).chr(170) => 'U',
                    chr(197).chr(171) => 'u',
                    chr(197).chr(172) => 'U',
                    chr(197).chr(173) => 'u',
                    chr(197).chr(174) => 'U',
                    chr(197).chr(175) => 'u',
                    chr(197).chr(176) => 'U',
                    chr(197).chr(177) => 'u',
                    chr(197).chr(178) => 'U',
                    chr(197).chr(179) => 'u',
                    chr(197).chr(180) => 'W',
                    chr(197).chr(181) => 'w',
                    chr(197).chr(182) => 'Y',
                    chr(197).chr(183) => 'y',
                    chr(197).chr(184) => 'Y',
                    chr(197).chr(185) => 'Z',
                    chr(197).chr(186) => 'z',
                    chr(197).chr(187) => 'Z',
                    chr(197).chr(188) => 'z',
                    chr(197).chr(189) => 'Z',
                    chr(197).chr(190) => 'z',
                    chr(197).chr(191) => 's',
                    // Euro Sign
                    chr(226).chr(130).chr(172) => 'E',
                    // GBP (Pound) Sign
                    chr(194).chr(163) => '',
                    'Ã„' => 'Ae', 'Ã¤' => 'ae', 'Ãœ' => 'Ue', 'Ã¼' => 'ue',
                    'Ã–' => 'Oe', 'Ã¶' => 'oe', 'ÃŸ' => 'ss'
                );

                $string = strtr($string, $chars);
            } else {
                $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158)
                    . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194)
                    . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202)
                    . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210)
                    . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218)
                    . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227)
                    . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235)
                    . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243)
                    . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251)
                    . chr(252) . chr(253) . chr(255);

                $chars['out']       = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";
                $string             = strtr($string, $chars['in'], $chars['out']);

                $doubleChars['in']  = array(
                    chr(140),
                    chr(156),
                    chr(198),
                    chr(208),
                    chr(222),
                    chr(223),
                    chr(230),
                    chr(240),
                    chr(254)
                );

                $doubleChars['out'] = array(
                    'OE',
                    'oe',
                    'AE',
                    'DH',
                    'TH',
                    'ss',
                    'ae',
                    'dh',
                    'th'
                );

                $string             = str_replace(
                    $doubleChars['in'],
                    $doubleChars['out'],
                    $string
                );
            }

            return $string;
        }

        public static function stripAccents($str)
        {
            return strtr(
                utf8_decode($str),
                utf8_decode(
                    '’àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'
                ),
                '\'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'
           );
        }

        public static function urlSafeB64Encode($data)
        {
            $b64 = base64_encode($data);

            $b64 = str_replace(
                array(
                    '+',
                    '/',
                    '\r',
                    '\n',
                    '='
                ), array(
                    '-',
                    '_'
                ),
                $b64
            );

            return $b64;
        }

        public static function urlSafeB64Decode($b64)
        {
            $b64 = str_replace(
                array(
                    '-',
                    '_'
                ),
                array(
                    '+',
                    '/'
                ),
                $b64
            );

            return base64_decode($b64);
        }

        public static function deNamespace($className)
        {
            $className = trim($className, '\\');

            if ($lastSeparator = strrpos($className, '\\')) {
                $className = substr($className, $lastSeparator + 1);
            }

            return $className;
        }

        public static function getNamespace($className)
        {
            $className = trim($className, '\\');

            if ($lastSeparator = strrpos($className, '\\')) {
                return substr($className, 0, $lastSeparator + 1);
            }

            return '';
        }

        public static function getFileExtension($filename, $lower = true)
        {
            if (true === $lower) {
                $filename = static::lower($filename);
            }

            $tab = explode('.', $filename);

            return end($tab);
        }

        public static function strReplaceFirst($search, $replace, $subject)
        {
            return implode($replace, explode($search, $subject, 2));
        }

        public static function limit($value, $limit = 100, $end = '...')
        {
            if (static::length($value) <= $limit) {
                return $value;
            }

            return mb_substr($value, 0, $limit, 'utf8') . $end;
        }

        public static function limitExact($value, $limit = 100, $end = '...')
        {
            if (static::length($value) <= $limit) {
                return $value;
            }

            $limit -= static::length($end);

            return static::limit($value, $limit, $end);
        }

        public static function title($value)
        {
            return mb_convert_case($value, MB_CASE_TITLE, 'utf8');
        }

        public static function words($value, $words = 100, $end = '...')
        {
            if (trim($value) == '') {
                return '';
            }

            preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

            if (static::length($value) == static::length($matches[0])) {
                $end = '';
            }

            return rtrim($matches[0]) . $end;
        }

        public static function slugify($title, $separator = '-')
        {
            $title = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $title);

            $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', static::lower($title));

            $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

            return trim($title, $separator);
        }

        public static function classify($value)
        {
            $search = array('_', '-', '.');

            return str_replace(' ', '_', static::title(str_replace($search, ' ', $value)));
        }

        public static function segments($value)
        {
            return array_diff(explode('/', trim($value, '/')), array(''));
        }

        public static function random($length, $type = 'alnum')
        {
            return substr(str_shuffle(str_repeat(static::pool($type), 5)), 0, $length);
        }

        public static function is($pattern, $value)
        {
            $patterns = is_array($pattern) ? $pattern : (array) $pattern;

            if (empty($patterns)) {
                return false;
            }

            foreach ($patterns as $pattern) {
                if ($pattern == $value) {
                    return true;
                }

                $pattern = preg_quote($pattern, '#');
                $pattern = str_replace('\*', '.*', $pattern);

                if (preg_match('#^'.$pattern.'\z#u', $value) === 1) {
                    return true;
                }
            }

            return false;
        }

        protected static function pool($type)
        {
            switch ($type) {
                case 'alpha':
                    return 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                case 'alnum':
                    return '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                default:
                    throw new Exception("Invalid random string type [$type].");
            }
        }

        public static function random2($length = 16)
        {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $bytes = openssl_random_pseudo_bytes($length * 2);

                if ($bytes === false) {
                    throw new Exception('Unable to generate random string.');
                }

                return substr(str_replace(array('/', '+', '='), '', base64_encode($bytes)), 0, $length);
            }

            return static::quickRandom($length);
        }

        public static function quickRandom($length = 16)
        {
            $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

            return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
        }

        public static function quickRandomNum($length = 8)
        {
            $pool = '123456789';

            return (int) substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
        }

        /**
         * @param $value
         * @return bool|mixed|null|string|string[]
         * @throws \ReflectionException
         */
        public static function kebab($value)
        {
            return static::snake($value, '-');
        }

        /**
         * @param $value
         * @param string $delimiter
         * @return bool|mixed|null|string|string[]
         * @throws \ReflectionException
         */
        public static function snake($value, $delimiter = '_')
        {
            $key = $value;

            if ($cached = static::cache($key, 'snake')) {
                return $cached;
            }

            if (!ctype_lower($value)) {
                $value = preg_replace('/\s+/u', '', ucwords($value));

                $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1'.$delimiter, $value));
            }

            static::cache($key, 'snake', $value);

            return $value;
        }

        /**
         * @param $key
         * @param $type
         * @param string $fill
         *
         * @return bool|mixed|null
         *
         * @throws \ReflectionException
         */
        private static function cache($key, $type, $fill = 'octodummy')
        {
            if ('octodummy' === $fill) {
                $value = Registry::get("inflector.$type.$key", 'octodummy');

                return 'octodummy' === $value ? false : $value;
            } else {
                Registry::set("inflector.$type.$key", $fill);
            }
        }

        public static function studly($value)
        {
            $value = ucwords(str_replace(array('-', '_'), ' ', $value));

            return str_replace(' ', '', $value);
        }

        public static function removeXss($str)
        {
            $attr   = array('style','on[a-z]+');
            $elem   = array('script','iframe','embed','object');
            $str    = preg_replace('#<!--.*?-->?#', '', $str);
            $str    = preg_replace('#<!--#', '', $str);
            $str    = preg_replace('#(<[a-z]+(\s+[a-z][a-z\-]+\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*)\s+href\s*=\s*(\'javascript:[^\']*\'|"javascript:[^"]*"|javascript:[^\s>]*)((\s+[a-z][a-z\-]*\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*\s*>)#is', '$1$5', $str);

            foreach ($attr as $a) {
                $regex  = '(<[a-z]+(\s+[a-z][a-z\-]+\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*)\s+' . $a . '\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*)((\s+[a-z][a-z\-]*\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*\s*>)';
                $str    = preg_replace('#' . $regex . '#is', '$1$5', $str);
            }

            foreach ($elem as $e) {
                $regex  = '<' . $e . '(\s+[a-z][a-z\-]*\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*\s*>.*?<\/' . $e . '\s*>';
                $str    = preg_replace('#' . $regex . '#is', '', $str);
            }

            return $str;
        }

        public static function html($string, $keepHtml = true)
        {
            if (true === $keepHtml) {
                return stripslashes(
                    implode(
                        '',
                        preg_replace(
                            '/^([^<].+[^>])$/e',
                            "htmlentities('\\1', ENT_COMPAT, 'utf-8')",
                            preg_split(
                                '/(<.+?>)/',
                                $string,
                                -1,
                                PREG_SPLIT_DELIM_CAPTURE
                            )
                        )
                    )
                );
            } else {
                return htmlentities($string, ENT_COMPAT, 'utf-8');
            }
        }

        public static function isSerialized($data, $strict = true)
        {
            if (!is_string($data)) return false;

            $data = trim($data);

            if ('N;' == $data) return true;

            $length = strlen($data);

            if ($length < 4) return false;

            if (':' !== $data[1]) return false;

            if ($strict) {
                $lastc = $data[$length - 1];

                if (';' !== $lastc && '}' !== $lastc) return false;
            } else {
                $semicolon = strpos($data, ';');
                $brace     = strpos($data, '}');

                if (false === $semicolon && false === $brace) return false;

                if (false !== $semicolon && $semicolon < 3) return false;

                if (false !== $brace && $brace < 4) return false;
            }
            $token = $data[0];

            switch ($token) {
                case 's' :
                    if ($strict) {
                        if ('"' !== $data[$length - 2]) return false;
                    } elseif (false === strpos($data, '"')) {
                        return false;
                    }
                case 'a' :
                case 'O' :
                    return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
                case 'b' :
                case 'i' :
                case 'd' :
                    $end = $strict ? '$' : '';

                    return (bool) preg_match("/^{$token}:[0-9.E-]+;$end/", $data);
            }

            return false;
        }

        public static function extractUrls($content)
        {
            preg_match_all(
                "#((?:[\w-]+://?|[\w\d]+[.])[^\s()<>]+[.](?:\([\w\d]+\)|(?:[^`!()\[\]{};:'\".,<>?«»“”‘’\s]|(?:[:]\d+)?/?)+))#",
                $content,
                $links
            );

            $links = array_unique(array_map('html_entity_decode', current($links)));

            return array_values($links);
        }

        public static function isMd5($str)
        {
            return (bool) preg_match('/^[0-9a-f]{32}$/i', $str);
        }

        public static function isSha1($str)
        {
            return (bool) preg_match('/^[0-9a-f]{40}$/i', $str);
        }

        public static function password($len = 8)
        {
            $password = "";

            $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

            mt_srand((double) microtime() * 1000000);

            while(strlen($password) < $len) $password .= $chars[mt_rand(0, strlen($chars) - 1)];

            return $password;
        }

        public static function br2nl($str)
        {
            return preg_replace("/\<br\s*\/?\>/i", "\n", $str);
        }

        /**
         * @param string $subject
         * @param string $search
         *
         * @return string
         */
        public static function after(string $subject, string $search): string
        {
            return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
        }
    }
