<?php
    namespace Octo;

    class Cli
    {
        private $styles = array(
            'ERROR'     => array(
                'bg'    => 'red',
                'fg'    => 'white',
                'bold'  => true
            ),
            'INFO'      => array(
                'fg'    => 'green',
                'bold'  => true
            ),
            'SUCCESS'   => array(
                'fg'    => 'white',
                'bg'    => 'green',
                'bold'  => true
            ),
            'COMMENT'   => array(
                'fg'    => 'yellow'
            ),
            'QUESTION'  => array(
                'bg'    => 'cyan',
                'fg'    => 'black',
                'bold'  => false
            ),
        ),
        $options        = array(
            'bold'      => 1,
            'underscore'=> 4,
            'blink'     => 5,
            'reverse'   => 7,
            'conceal'   => 8
        ),
        $foreground     = array(
            'black'     => 30,
            'red'       => 31,
            'green'     => 32,
            'yellow'    => 33,
            'blue'      => 34,
            'magenta'   => 35,
            'cyan'      => 36,
            'white'     => 37
        ),
        $background = array(
            'black'     => 40,
            'red'       => 41,
            'green'     => 42,
            'yellow'    => 43,
            'blue'      => 44,
            'magenta'   => 45,
            'cyan'      => 46,
            'white'     => 47
        );

        public function __construct($args)
        {
            $method = Arrays::first($args);
            $argv   = array_slice($args, 1);

            if (method_exists($this, $method)) {
                call_user_func_array(array($this, $method), $argv);
            } else {
                $this->render(Arrays::first($args) . ' is not a valid method', 'ERROR');
            }
        }

        public function boot()
        {
            return $this;
        }

        public function render($msg, $type = 'INFO')
        {
            echo "\n";
            echo($this->format($msg, $type));
            echo "\n";
            echo "\n";
        }

        public static function show($msg, $type = 'INFO')
        {
            $cli = new self(['boot']);
            $cli->render($msg, $type);
        }

        private function format($text = '', $parameters = array())
        {
            if (!is_array($parameters) && 'NONE' == $parameters) {
                return $text;
            }

            if (!is_array($parameters) && isset($this->styles[$parameters])) {
                $parameters = $this->styles[$parameters];
            }

            $codes = [];

            $fg = isAke($parameters, 'fg', null);
            $bg = isAke($parameters, 'bg', null);

            if (!empty($fg)) {
                $codes[] = $this->foreground[$fg];
            }

            if (!empty($bg)) {
                $codes[] = $this->background[$bg];
            }

            foreach ($this->options as $option => $value) {
                $paramOpt = isAke($parameters, $option, null);

                if (!empty($paramOpt)) {
                    $codes[] = $value;
                }
            }

            return "\033[" . implode(';', $codes) . 'm' . $text . "\033[0m";
        }

        public static function args($args)
        {
            $collection = [];

            if (!empty($args)) {
                foreach ($args as $arg) {
                    if (strstr($arg, '=')) {
                        list($key, $value) = explode('=', $arg, 2);
                        $collection[substr($key, 2)] = $value;
                    } elseif ($arg[0] == ':') {
                        $collection['task'] = substr($arg, 1);
                    }
                }
            }

            return $collection;
        }

        public static function tasks($dir = null)
        {
            $dir = is_null($dir) ? path('tasks') : $dir;
            $tasks = glob($dir . DS . '*.php');
            $collection = [];

            if (count($tasks)) {
                foreach ($tasks as $task) {
                    $task = str_replace([
                            $dir . DS,
                            '.php'
                        ],
                        '',
                        $task
                    );

                    array_push($collection, $task);
                }
            }

            return $collection;
        }
    }
