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

    /**
     * @var string
     */
    private $date_format = 'd/m/Y H:i:s';

    public function __construct($store)
    {
        $this->store = $store;
    }

    /**
     * @param string $className
     * @param array $args
     * @return Work
     */
    public function new(string $className, array $args = []): self
    {
        return $this->payload('job', $className)->payload('args', $args);
    }

    /**
     * @return Work
     */
    public function now(): self
    {
        return $this->payload('when', 0)->save();
    }

    /**
     * @param int $minutes
     * @return Work
     */
    public function in(int $minutes = 5): self
    {
        $when = time() + ($minutes * 60);

        return $this->payload('when', $when)->save();
    }

    /**
     * @param int $timestamp
     * @return Work
     */
    public function at(int $timestamp): self
    {
        return $this->payload('when', $timestamp)->save();
    }

    /**
     * @param string $key
     * @param $value
     * @return Work
     */
    private function payload(string $key, $value): self
    {
        $this->payload[$key] = $value;

        return $this;
    }

    /**
     * @return Work
     */
    private function save(): self
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
     * @throws \ReflectionException
     */
    public function process(): int
    {
        $computed = 0;

        if ($this->hasNext()) {
            $all = $this->store->get('queue.whens', []);

            $now = time();

            $jobs = $this->store->get('queue.jobs', []);

            $args = $this->store->get('queue.args', []);

            $failed = $this->store->get('queue.failed', []);

            foreach ($all as $row) {
                if ((int) $row['when'] <= $now) {
                    ++$computed;
                    array_shift($all);

                    $id     = $row['id'];
                    $job    = isAke($jobs, $id, false);

                    if ($job) {
                        $params = isAke($args, $id, []);

                        $instance = gi()->make($job, $params, false);

                        try {
                            gi()->call($instance, 'process');

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
                $this->store
                    ->set('queue.jobs', $jobs)
                    ->set('queue.args', $args)
                    ->set('queue.failed', $failed)
                ;

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
    public function schedule(): array
    {
        $schedule   = [];

        $all = $this->store->get('queue.whens', []);

        $jobs = $this->store->get('queue.jobs', []);

        foreach ($all as $item) {
            $job = isAke(
                $jobs,
                $item['id'],
                false
            );

            if ($job) {
                $schedule[] = [$job => date($this->date_format, (int) $item['when'])];
            }
        }

        return $schedule;
    }

    /**
     * @return array
     */
    public function fails(): array
    {
        $fails  = [];

        $all  = $this->store->get('queue.failed', [])
        ;

        $jobs   = $this->store->get('queue.jobs', []);

        foreach ($all as $id => $timestamp) {
            $job = isAke(
                $jobs,
                $id,
                false
            );

            if ($job) {
                $fails[] = [$job => date($this->date_format, (int) $timestamp)];
            }
        }

        return $fails;
    }

    /**
     * @return bool
     */
    public function hasNext(): bool
    {
        return !empty($this->store->get('queue.whens', []));
    }

    /**
     * @param array $job
     * @return Work
     */
    private function store(array $job): self
    {
        $jobs = $this->store->get('queue.jobs', []);

        $args = $this->store->get('queue.args', []);

        $whens = $this->store->get('queue.whens', []);

        $id = token();

        $jobs[$id]  = $job['job'];
        $args[$id]  = $job['args'];

        $whens[]  = [
            'id' => $id,
            'when' => (int) $job['when']
        ];

        $whens = array_values(coll($whens)->sortBy('when')->toArray());

        $this->store
            ->set('queue.jobs', $jobs)
            ->set('queue.args', $args)
            ->set('queue.whens', $whens)
        ;

        return $this;
    }

    /**
     * @param $instance
     * @throws \ReflectionException
     */
    private function failed($instance): void
    {
        if (method_exists($instance, 'onFail')) {
            gi()->call($instance, 'onFail');
        }
    }

    /**
     * @param $instance
     * @throws \ReflectionException
     */
    private function success($instance): void
    {
        if (method_exists($instance, 'onSuccess')) {
            gi()->call($instance, 'onSuccess');
        }
    }

    /**
     * @return bool
     */
    private function ready(): bool
    {
        return isset($this->payload['job']) && isset($this->payload['when']) && isset($this->payload['args']);
    }

    /**
     * @param string $date_format
     * @return Work
     */
    public function setDateFormat(string $date_format): self
    {
        $this->date_format = $date_format;

        return $this;
    }

    /**
     * @param $store
     * @return Work
     */
    public function setStore($store): self
    {
        $this->store = $store;

        return $this;
    }
}
