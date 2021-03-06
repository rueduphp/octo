<?php
    namespace Octo;

    class Later implements FastQueueInterface
    {
        /**
         * @param $name
         * @param $closure
         * @param array $args
         * @param int $when
         *
         * @return Objet
         */
        public static function set($name, $closure, $args = [], $when = 0)
        {
            $closure_id = lib('closures')->store($name, $closure)->id;

            /** @var Octalia $db */
            $db = em('systemLatertask');

            $db->optimized = false;

            return $db->firstOrCreate([
                'closure_id'    => (int) $closure_id,
                'when'          => (int) $when,
                'args'          => (array) $args
            ]);
        }

        public static function listen($cli = true)
        {
            set_time_limit(false);

            /** @var Octalia $dbTask */
            $dbTask     = em('systemLatertask');
            /** @var Octalia $dbInstance */
            $dbInstance = em('systemLaterinstance');

            $dbTask->optimized      = false;
            $dbInstance->optimized  = false;

            $tasks = $dbTask->where('when', '<', time())->get();

            if ($tasks->count() > 0) {
                foreach ($tasks as $task) {
                    $check = $dbInstance
                        ->where('task_id', '=', (int) $task['id'])
                        ->count()
                    ;

                    $callback_id = isAke($task, 'callback_id', null);

                    if ($check == 0) {
                        /** @var Objet $instance */
                        $instance = $dbInstance->store([
                            'task_id'   => (int) $task['id'],
                            'start'     => time()
                        ]);

                        $res = lib('closures')->fireStore(
                            (int) $task['closure_id'],
                            (array) $task['args']
                        );

                        if ($callback_id) {
                            $cb = $dbTask->find((int) $callback_id);
                            $t = $cb->toArray();

                            $args = array_merge([$res], $t['args']);

                            lib('closures')->fireStore(
                                (int) $t['closure_id'],
                                (array) $args
                            );

                            $cb->delete();
                        }

                        $dt = $dbTask->find((int) $task['id']);

                        if ($dt) {
                            $dt->delete();
                        }

                        $instance->delete();

                        /** @var Octalia $dbHistory */
                        $dbHistory = em('systemLaterhistory');

                        $dbHistory->optimized = false;

                        $dbHistory->store([
                            'task'              => (array) $task,
                            'execution_time'    => time()
                        ]);
                    }
                }
            }

            return true;
        }

        public static function async($name, $closure, $callback, $args = [], $callbackArgs = [])
        {
            $task           = self::set($name, $closure, $args);
            $callbackTask   = self::set($name . '_cb', $callback, $callbackArgs, strtotime('+1 YEAR'));

            $task->setCallbackId($callbackTask->id)->save();

            self::background();
        }

        public static function background()
        {
            $file = path('base') . '/queue.php';

            if (File::exists($file)) {
                $cmd = 'php ' . $file;
                backgroundTask($cmd);
            }
        }

        public static function shutdown()
        {
            $afters = Registry::get('afters', []);

            if (!empty($afters)) {
                $key    = hash(token() . serialize($afters));
                $file   = path('cache') . DS . $key . '.after';

                File::delete($file);

                File::put($file,
                    '<?php' . ' namespace ' . __NAMESPACE__ .
                    ' {' . "\n" . '$configs = ' . var_export(Config::all(), true) .
                    ";\n\n" . 'foreach ($configs as $k => $v) Config::set($k, $v); ' .
                    "\n\n" . 'return ' . var_export($afters, true) . ';};'
                );

                $exec = realpath(__DIR__ . '/afterbin.php');

                if (File::exists($exec)) {
                    $cmd = 'php ' . $exec . ' ' . path("cache");
                    backgroundTask($cmd);
                }
            }
        }

        public function after($dir)
        {
            $now = now();

            Config::set("dir.cache", $dir);

            $finder = Finder::create();

            $afters = $finder->only($dir)->extension('after');

            $laters = [];

            foreach ($afters as $after) {
                Config::reset();

                $afterTasks = include_once $after->real_path;

                foreach ($afterTasks as $afterTask) {
                    $callback   = unserializeClosure($afterTask['callback']);
                    $params     = $afterTask['params'];
                    $when       = $afterTask['when'];

                    $diff = $now - $when;

                    if ($diff >= 0) call($callback, $params);
                    else $laters[$when] = [$callback, $params, $when, Config::all()];
                }

                File::delete($after->real_path);
            }

            if (!empty($laters)) {
                ksort($laters);
                $this->at($laters);
            }
        }

        public function at($tasks)
        {
            $now = now();
            $task = array_shift($tasks);

            list($callback, $params, $when, $config) = $task;

            $timeToExecute = $when - $now;

            if (0 >= $timeToExecute) {
                Config::reset();
                Config::fill($config);
                call($callback, $params);

                if (!empty($tasks)) {
                    return $this->at($tasks);
                }
            } else {
                waitUntil($timeToExecute, function () use ($callback, $params, $config, $tasks) {
                    Config::reset();
                    Config::fill($config);
                    call($callback, $params);

                    if (!empty($tasks)) {
                        return $this->at($tasks);
                    }
                });
            }
        }
    }
