<?php
    namespace Octo;

    class Async
    {
        private $callable;
        private $args;

        public static function add($class, array $args = [], $queue = 'default', $time = 0)
        {
            if ($time != 0) {
                $time = $time > (365 * 84600) ? $time : $time + time();
            }

            $class = !is_string($class) ? get_class($class) : $class;

            return em('systemQueue')->store([
                'status'    => 1,
                'queue'     => $queue,
                'class'     => $class,
                'args'      => $args,
                'time'      => $time
            ]);
        }

        public static function listen($queue = 'default')
        {
            $jobs = em('systemQueue')
            ->where('queue', $queue)
            ->where('status', 1)
            ->where('time', '<', time())
            ->get();

            Cli::show($jobs->count() . ' jobs', 'SUCCESS');

            foreach ($jobs as $job) {
                try {
                    $instance = maker($job['class'], $job['args'], false);

                    $instance->handle();

                    Cli::show($job->status, 'SUCCESS');

                    $job->status = 2;
                    $job->save();

                    Cli::show($job['class'] . " has been successfully played.", 'SUCCESS');
                } catch (\Exception $e) {
                    Cli::show($job->status, 'ERROR');

                    $job->status = 3;
                    $job->save();

                    Cli::show($e->getMessage(), 'ERROR');
                    Cli::show($job['class'] . " has failed.", 'ERROR');
                }
            }
        }
    }
