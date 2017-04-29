<?php
    namespace Octo;
    use ReflectionClass;

    class Annotation
    {
        const ANNOTATION_REGEX = '/@(\w+)(?:\s*(?:\(\s*)?(.*?)(?:\s*\))?)??\s*(?:\n|\*\/)/';
        const PARAMETER_REGEX = '/(\w+)\s*=\s*(\[[^\]]*\]|"[^"]*"|[^,)]*)\s*(?:,|$)/';

        public static function method($class, $method)
        {
            $reflection = new ReflectionClass($class);
            $method     = $reflection->getMethod($method);
            $docs       = $method->getDocComment();

            return static::parse($docs);
        }

        public static function class($class)
        {
            $reflection = new ReflectionClass($class);
            $docs       = $reflection->getDocComment();

            return static::parse($docs);
        }

        protected static function parse($docComment)
        {
            $hasAnnotations = preg_match_all(
                static::ANNOTATION_REGEX,
                $docComment,
                $matches,
                PREG_SET_ORDER
            );

            if (!$hasAnnotations) {
                return [];
            }

            $annotations = [];

            foreach ($matches AS $anno) {
                $annoName = Inflector::lower($anno[1]);
                $val = true;

                if (isset($anno[2])) {
                    $hasParams = preg_match_all(
                        self::PARAMETER_REGEX,
                        $anno[2],
                        $params,
                        PREG_SET_ORDER
                    );

                    if ($hasParams) {
                        $val = [];

                        foreach ($params AS $param) {
                            $val[$param[1]] = static::value($param[2]);
                        }
                    } else {
                        $val = trim($anno[2]);

                        if ($val == '') {
                            $val = true;
                        } else {
                            $val = static::value($val);
                        }
                    }
                }

                if (isset($annotations[$annoName])) {
                    if (!is_array($annotations[$annoName])) {
                        $annotations[$annoName] = array($annotations[$annoName]);
                    }

                    $annotations[$annoName][] = $val;
                } else {
                    $annotations[$annoName] = $val;
                }
            }

            return $annotations;
        }

        protected static function value($value)
        {
            $val = trim($value);

            if (substr($val, 0, 1) == '[' && substr($val, -1) == ']') {
                $vals = explode(',', substr($val, 1, -1));
                $val = [];

                foreach ($vals AS $v) {
                    $val[] = static::value($v);
                }

                return $val;
            } elseif (substr($val, 0, 1) == '{' && substr($val, -1) == '}') {
                    return json_decode($val);
            } elseif (substr($val, 0, 1) == '"' && substr($val, -1) == '"') {
                $val = substr($val, 1, -1);

                return static::value($val);
            } elseif (Inflector::lower($val) == 'true') {
                return true;
            } elseif (Inflector::lower($val) == 'false') {
                return false;
            } elseif (is_numeric($val)) {
                if ((float) $val == (int) $val) {
                    return (int) $val;
                } else {
                    return (float) $val;
                }
            } else {
                return $val;
            }
        }
    }
