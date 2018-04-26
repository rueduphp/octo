<?php

namespace Octo;

use Elasticsearch\Client;

class Esmanager
{
    /**
     * @var In
     */
    protected $app;

    /**
     * @var Esfactory
     */
    protected $factory;

    /**
     * @var array
     */
    protected $connections = [];

    /**
     * @param In $app
     * @param Esfactory $factory
     */
    public function __construct(In $app, Esfactory $factory)
    {
        $this->app = $app;
        $this->factory = $factory;
    }

    /**
     * @param string|null $name
     * @return Client
     */
    public function connection(?string $name = null): Client
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $client = $this->makeConnection($name);

            $this->connections[$name] = $client;
        }

        return $this->connections[$name];
    }

    /**
     * Get the default connection.
     *
     * @return string
     */
    public function getDefaultConnection(): string
    {
        return $this->app['config']['elasticsearch.defaultConnection'];
    }

    /**
     * Set the default connection.
     *
     * @param string $connection
     */
    public function setDefaultConnection(string $connection)
    {
        $this->app['config']['elasticsearch.defaultConnection'] = $connection;
    }

    /**
     * Make a new connection.
     *
     * @param string $name
     *
     * @return \Elasticsearch\Client
     */
    protected function makeConnection(string $name): Client
    {
        $config = $this->getConfig($name);

        return $this->factory->make($config);
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    protected function getConfig(string $name)
    {
        $connections = $this->app['config']['elasticsearch.connections'];

        if (null === $config = aget($connections, $name)) {
            throw new \InvalidArgumentException("Elasticsearch connection [$name] not configured.");
        }

        return $config;
    }

    /**
     * @return array
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return call_user_func_array([$this->connection(), $method], $parameters);
    }
}