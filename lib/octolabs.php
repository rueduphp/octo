<?php
    namespace Octo;

    class Octolabs
    {
        public function __construct($request)
        {
            array_shift($request);

            list($controller, $action) = explode(':', $method = array_shift($request), 2);

            $class = Strings::camelize($controller . '_command');

            $command = maker($class);

            $actions = get_class_methods($command);

            $args = [];

            foreach ($request as $arg) {
                if (fnmatch('--*=*', $arg)) {
                    list($k, $v) = explode('=', str_replace_first('--', '', $arg), 2);
                    $args[$k] = $v;
                } else {
                    $args[] = $arg;
                }
            }

            if (in_array($action, $actions)) {
                if (in_array('starting', $actions)) {
                    callMethod($command, 'starting');
                }

                callMethod($command, $action, $args);

                if (in_array('stopping', $actions)) {
                    callMethod($command, 'stopping', $method);
                }
            } else {
                if ('tasks' == $controller) {
                    callMethod($command, 'any', $action, $args);
                } else {
                    Cli::show("Invalid method $method", 'ERROR');
                }
            }
        }
    }

    class DbCommand
    {
        public function starting()
        {
            Timer::start();
        }

        public function stopping($task)
        {
            Timer::stop();

            Cli::show("Execution time of task '$task' ==> " . Timer::get() . " s.");
        }

        public function seeds()
        {
            $seeders = path('app') . '/config/seeders.php';

            if (File::exists($seeders)) {
                $seeders = include $seeders;

                foreach ($seeders as $entityClass => $seederClass) {
                    $entityManager  = maker($entityClass);
                    $seeder         = maker($seederClass);
                    $count          = $seeder->run($entityManager, faker());

                    Cli::show("$entityClass $count seeds OK", 'SUCCESS');
                }
            } else {
                Cli::show("$seeders does not exist", 'ERROR');
            }
        }
    }

    class QueueCommand
    {
        public function starting()
        {
            Timer::start();
        }

        public function stopping($task)
        {
            Timer::stop();

            Cli::show("Execution time of task '$task' ==> " . Timer::get() . " s.");
        }

        public function listen()
        {
            Async::listen();
        }
    }

    class MakeCommand
    {
        public function starting()
        {
            Timer::start();
        }

        public function stopping($task)
        {
            Timer::stop();

            Cli::show("Execution time of task '$task' ==> " . Timer::get() . " s.");
        }

        public function request($request)
        {
            $name           = current($request);
            $namespace      = isAke($request, 'namespace', 'App\\requests');

            $newRequest  = path('app') . '/requests/' . ucfirst(Strings::lower($name)) . '.php';

            if (File::exists($newRequest)) {
                Cli::show("$newRequest ever exists", 'ERROR');
            } else {
                $code = "<?php\n\tnamespace $namespace;\n\n\tuse CustomRequest;\n\n\tclass $name extends CustomRequest\n\t{\n\t\tpublic function boot()\n\t\t{\n\t\t}\n\n\t\tpublic function authorize()\n\t\t{\n\t\t}\n\t}";

                if (!is_dir(path('app') . '/requests')) {
                    Dir::mkdir(path('app') . '/requests');
                }

                File::put($newRequest, $code);

                Cli::show("$name request has been successfully created", 'SUCCESS');
            }
        }

        public function controller($request)
        {
            $name           = current($request);
            $namespace      = isAke($request, 'namespace', 'App');
            $newController  = path('app') . '/controllers/' . Strings::lower($name) . '.php';

            $nameController = Strings::camelize('app_' . $name . '_controller');

            if (File::exists($newController)) {
                Cli::show("$nameController ever exists", 'ERROR');
            } else {
                $code = "<?php\n\tnamespace $namespace;\n\n\tuse ControllerBase;\n\n\tclass $nameController extends ControllerBase\n\t{\n\t\tpublic function boot()\n\t\t{\n\t\t}\n\t}";
                File::put($newController, $code);

                Cli::show("$nameController has been successfully created", 'SUCCESS');
            }
        }

        public function entity($request)
        {
            $name       = current($request);
            $namespace  = isAke($request, 'namespace', 'Octo');
            $newEntity  = path('app') . '/entities/' . $name . '.php';

            $nameEntity = $name . 'Entity';

            if (File::exists($newEntity)) {
                Cli::show("$nameEntity ever exists", 'ERROR');
            } else {
                $code = "<?php\n\tnamespace $namespace;\n\n\tuse Octo\\Octal as EntityManager;\n\n\tclass $nameEntity extends EntityManager\n\t{\n\t}";
                File::put($newEntity, $code);

                Cli::show("$nameEntity has been successfully created", 'SUCCESS');
            }
        }
    }

    class TasksCommand
    {
        private $tasks = [];

        public function __construct()
        {
            $this->tasks = Cli::tasks(path('app') . '/tasks');

            $config = path('app') . '/config/tasks.php';

            if (File::exists($config)) {
                $aliases = include $config;
                $this->tasks += array_values($aliases);
            }
        }

        private function starting($class, $methods)
        {
            if (in_array('init', $methods)) {
                call_user_func_array([$class, 'init'], []);
            }

            $start = Timer::start();
        }

        private function stopping($class, $methods, $task)
        {
            if (in_array('exit', $methods)) {
                call_user_func_array([$class, 'exit'], []);
            }

            Timer::stop();

            Cli::show("Execution time of task '$task' ==> " . Timer::get() . " s.");
        }

        public function any($task, $args)
        {
            $class = '\\OctoTask\\' . Strings::camelize($task);

            if (!class_exists($class)) {
                $config = path('app') . '/config/tasks.php';

                if (File::exists($config)) {
                    $aliases = include $config;

                    $class = isAke($aliases, $task, null);
                }
            }

            $methods = get_class_methods($class);

            if (!empty($methods) && in_array('run', $methods)) {
                $this->starting($class, $methods);

                $instance = maker($class);

                $res = call_user_func_array([$instance, 'run'], $args);

                $this->stopping($class, $methods, $task);
            } else {
                Cli::show("Invalid task $task", 'ERROR');
            }
        }

        public function tests($request)
        {
            $class = '\\OctoTask\\Tests';

            if (!class_exists($class)) {
                $config = path('app') . '/config/tasks.php';

                if (File::exists($config)) {
                    $aliases = include $config;

                    $class = isAke($aliases, 'tests', null);
                }
            }

            $methods = get_class_methods($class);

            $this->starting($class, $methods);

            $instance = maker($class);

            $res = call_user_func_array([$instance, 'run'], []);

            $this->stopping($class, $methods, 'tests');
        }

        public function listing()
        {
            Cli::show("Tasks list", 'COMMENT');

            foreach ($this->tasks as $task) {
                Cli::show($task, 'QUESTION');

                $class = '\\OctoTask\\' . $task;
                $methods = get_class_methods($class);

                Cli::show(implode("\n", $methods));
            }
        }
    }

    class TestsCommand
    {
        public function run($request)
        {
            (new TasksCommand)->tests($request);
        }
    }
