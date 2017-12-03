<?php
namespace Octo;

class Jober implements FastQueueInterface
{
    /**
     * @var FastCacheInterface
     */
    private $engine;

    /**
     * @param FastCacheInterface $engine
     */
    public function __construct(FastCacheInterface $engine)
    {
        $this->engine = $engine;
    }

    /**
     * @param string $name
     * @param callable $callable
     * @param array $args
     * @param int $when
     */
    public function set(string $name, callable $callable, $args = [], $when = 0)
    {
        $closure_id = lib('closures')->store($name, $callable)->id;

        $this->engine->hset('jobber.when', $closure_id, $when);
        $this->engine->hset('jobber.args', $closure_id, $args);
    }

    /**
     * @return bool
     */
    public function listen()
    {
        set_time_limit(false);

        $tasks = $this->engine->hgetall('jobber.when');

        foreach ($tasks as $callback_id => $task) {
            $check = $this->engine->has("jobber.instance.$callback_id");

            if ($check === false) {
                $this->engine->set("jobber.instance.$callback_id", time());

                $res = lib('closures')->fireStore(
                    (int) $callback_id,
                    (array) $this->engine->hget('jobber.args', $callback_id)
                );

                $args = array_merge([$res], (array) $this->engine->hget('jobber.args', $callback_id));

                lib('closures')->fireStore(
                    (int) $callback_id,
                    (array) $args
                );

                $this->engine->hdel('jobber.when', $callback_id);
                $this->engine->hdel('jobber.args', $callback_id);
                $this->engine->del("jobber.instance.$callback_id");
            }
        }

        return true;
    }

    /**
     * @param string $name
     * @param callable $closure
     * @param callable $callback
     * @param array $args
     * @param array $callbackArgs
     */
    public function async(string $name, callable $closure, callable $callback, $args = [], $callbackArgs = [])
    {
        $task           = $this->set($name, $closure, $args);
        $callbackTask   = $this->set($name . '_cb', $callback, $callbackArgs, strtotime('+1 YEAR'));

        $task->setCallbackId($callbackTask->id)->save();

        $this->background();
    }

    public function background()
    {
        $file = path('base') . '/queue.php';

        if (File::exists($file)) {
            $cmd = 'php ' . $file;
            backgroundTask($cmd);
        }
    }

    public function shutdown()
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

    /**
     * @return FastCacheInterface
     */
    public function getEngine(): FastCacheInterface
    {
        return $this->engine;
    }

    /**
     * @param FastCacheInterface $engine
     */
    public function setEngine(FastCacheInterface $engine)
    {
        $this->engine = $engine;
    }
}
