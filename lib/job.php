<?php
    namespace Octo;

    class Job
    {
        public function add($job, $when = 0)
        {
            if (is_string($job)) {
                $job = maker($job);
            }

            $methods = get_class_methods($job);

            if (!in_array('process', $methods)) {
                exception('job', 'The job class ' . get_class($job) . ' does not implement a process method.');
            }

            $callback = function ($job) {
                return $job->process();
            };

            lib('later')->set('Job.' . token(), $callback, [$job], $when);

            lib('later')->background();

            return true;
        }

        public function at($job, $ts)
        {
            return $this->add($job, $ts);
        }

        public function in($job, $minutes = 1)
        {
            $ts = time() + ($minutes * 60);

            return $this->add($job, $ts);
        }
    }
