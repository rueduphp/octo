<?php
namespace App\Services;

use App\Facades\Bus as BusDispatcher;
use Octo\Module as BaseModule;
use Octo\Ultimate;
use Octo\Work;

class Module extends BaseModule
{
    /**
     * @param string $name
     * @return object
     */
    public function getRepository(string $name)
    {
        return repo($name);
    }

    /**
     * @param string $name
     * @return Model|\Octo\Elegant
     */
    public function getModel(string $name)
    {
        return model($name);
    }

    /**
     * @param string $name
     * @return object
     */
    public function getObserver(string $name)
    {
        return observer($name);
    }

    /**
     * @param null|Ultimate $session
     * @return string
     * @throws \ReflectionException
     */
    public function getLocale(?Ultimate $session = null): string
    {
        return locale($session);
    }

    /**
     * @param string $locale
     * @param Ultimate|null $session
     */
    public function setLocale(string $locale, ?Ultimate $session = null)
    {
        setAppLocale($locale, $session);
    }

    /**
     * @param string $name
     * @param $callback
     * @return $this
     */
    public function addPolicy(string $name, $callback)
    {
        trust()->policy($name, $callback);

        return $this;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function allows(string $name)
    {
        return $this->addPolicy($name, function () {
            return true;
        });
    }

    /**
     * @param string $name
     * @return $this
     */
    public function denies(string $name)
    {
        return $this->addPolicy($name, function () {
            return false;
        });
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function isGranted(...$args)
    {
        return $this->can(...$args);
    }

    /**
     * @param mixed ...$args
     * @return bool|\GuzzleHttp\Psr7\Response
     * @throws \ReflectionException
     */
    public function untilIsGranted(...$args)
    {
        $status = $this->can(...$args);

        if (false === $status) {
            $action = array_shift($args);

            return abort(401, 'This action {' . $action . '} is not granted.');
        }

        return true;
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function can(...$args): bool
    {
        return trust()->can(...$args);
    }

    /**
     * @param $job
     * @param array $args
     * @return Work
     */
    protected function dispatch($job, array $args = [])
    {
        return BusDispatcher::new($job, $args);
    }

    /**
     * @param $job
     * @param array $args
     * @return Work
     */
    public function dispatchNow($job, array $args = [])
    {
        return $this->dispatch($job, $args)->now();
    }

    /**
     * @param $job
     * @param int $minutes
     * @param array $args
     * @return Work
     */
    public function dispatchIn($job, int $minutes, array $args = [])
    {
        return $this->dispatch($job, $args)->in($minutes);
    }

    /**
     * @param $job
     * @param int $timestamp
     * @param array $args
     * @return Work
     */
    public function dispatchAt($job, int $timestamp, array $args = [])
    {
        return $this->dispatch($job, $args)->at($timestamp);
    }

    /**
     * @param $middleware
     * @return array
     */
    public function middleware($middleware)
    {
        if (is_string($middleware) && class_exists($middleware)) {
            return [$middleware];
        } elseif (is_array($middleware) && !is_callable($middleware)) {
            return $middleware;
        }
    }

    /**
     * @param string $path
     * @param array $data
     * @param array $mergeData
     * @return \Illuminate\View\Factory|string
     */
    public function view(string $path, array $data = [], array $mergeData = [])
    {
        $data += $this->getVars();

        return view($path, $data, $mergeData);
    }
}
