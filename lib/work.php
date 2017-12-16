<?php
namespace Octo;

class Work
{
    /**
     * @var FastNow
     */
    private $store;

    /**
     * @var array
     */
    private $payload = [];

    public function __construct($store)
    {
        $this->store = $store;
    }

    /**
     * @param string $className
     * @param array $args
     *
     * @return Work
     */
    public function new(string $className, array $args = [])
    {
        return $this->payload('job', $className)->payload('args', $args);
    }

    /**
     * @return Work
     */
    public function now()
    {
        return $this->payload('when', 0)->save();
    }

    /**
     * @param int $minutes
     *
     * @return Work
     */
    public function in(int $minutes = 5)
    {
        $when = time() + ($minutes * 60);

        return $this->payload('when', $when)->save();
    }

    /**
     * @param int $timestamp
     *
     * @return Work
     */
    public function at(int $timestamp)
    {
        return $this->payload('when', $timestamp)->save();
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    private function payload(string $key, $value)
    {
        $this->payload[$key] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    private function save()
    {
        if ($this->ready()) {
            $row = [
                'job'       => $this->payload['job'],
                'when'      => $this->payload['when'],
                'args'      => $this->payload['args']
            ];

            return $this->store($row);
        }

        return $this;
    }

    /**
     * @return int
     */
    public function process()
    {
        if ($this->hasNext()) {
            $all        = $this->store->get('queue.whens', []);
            $now        = time();
            $computed   = 0;

            $jobs   = $this->store->get('queue.jobs', []);
            $args   = $this->store->get('queue.args', []);
            $failed = $this->store->get('queue.failed', []);

            foreach ($all as $row) {
                if ((int) $row['when'] <= $now) {
                    $computed++;
                    array_shift($all);

                    $id = $row['id'];
                    $job = isAke($jobs, $id, false);

                    if ($job) {
                        $params = isAke($args, $id, []);

                        $instance = instanciator()->make($job, $params, false);

                        try {
                            instanciator()->call($instance, 'process');
                            unset($jobs[$id]);
                            unset($args[$id]);

                            $this->success($instance);
                        } catch (\Exception $e) {
                            $failed[$id] = time();

                            $this->failed($instance);
                        }
                    }
                }
            }

            if (0 < $computed) {
                $this->store->set('queue.jobs', $jobs);
                $this->store->set('queue.args', $args);
                $this->store->set('queue.failed', $failed);

                if (is_null($all)) {
                    $all = [];
                }

                $this->store->set('queue.whens', $all);
            }
        }

        return $computed;
    }

    /**
     * @return array
     */
    public function schedule()
    {
        $schedule = [];

        $all    = $this->store->get('queue.whens', []);
        $jobs   = $this->store->get('queue.jobs', []);

        foreach ($all as $item) {
            $job = isAke($jobs, $item['id'], false);

            if ($job) {
                $schedule[] = [$job => date('d/m/Y H:i:s', (int) $item['when'])];
            }
        }

        return $schedule;
    }

    /**
     * @return array
     */
    public function fails()
    {
        $fails = [];

        $all    = $this->store->get('queue.failed', []);
        $jobs   = $this->store->get('queue.jobs', []);

        foreach ($all as $id => $timestamp) {
            $job = isAke($jobs, $id, false);

            if ($job) {
                $fails[] = [$job => date('d/m/Y H:i:s', (int) $timestamp)];
            }
        }

        return $fails;
    }

    /**
     * @param array $job
     *
     * @return $this
     */
    private function store(array $job)
    {
        $jobs   = $this->store->get('queue.jobs', []);
        $args   = $this->store->get('queue.args', []);
        $whens  = $this->store->get('queue.whens', []);

        $id = token();

        $jobs[$id]  = $job['job'];
        $args[$id]  = $job['args'];
        $whens[]    = ['id' => $id, 'when' => (int) $job['when']];

        $whens = array_values(coll($whens)->sortBy('when')->toArray());

        $this->store->set('queue.jobs', $jobs);
        $this->store->set('queue.args', $args);
        $this->store->set('queue.whens', $whens);

        return $this;
    }

    private function failed($instance)
    {
        if (method_exists($instance, 'onFail')) {
            instanciator()->call($instance, 'onFail');
        }
    }

    private function success($instance)
    {
        if (method_exists($instance, 'onSuccess')) {
            instanciator()->call($instance, 'onSuccess');
        }
    }

    /**
     * @return bool
     */
    private function ready()
    {
        return isset($this->payload['job']) && isset($this->payload['when']) && isset($this->payload['args']);
    }

    /**
     * @return bool
     */
    private function hasNext()
    {
        return !empty($this->store->get('queue.whens', []));
    }
}