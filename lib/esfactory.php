<?php

namespace Octo;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;

class Esfactory
{

    /**
     * @var array
     */
    protected $configMappings = [
        'sslVerification'    => 'setSSLVerification',
        'sniffOnStart'       => 'setSniffOnStart',
        'retries'            => 'setRetries',
        'httpHandler'        => 'setHandler',
        'connectionPool'     => 'setConnectionPool',
        'connectionSelector' => 'setSelector',
        'serializer'         => 'setSerializer',
        'connectionFactory'  => 'setConnectionFactory',
        'endpoint'           => 'setEndpoint',
    ];

    /**
     * @param array $config
     *
     * @return \Elasticsearch\Client|mixed
     */
    public function make(array $config)
    {
        return $this->buildClient($config);
    }

    /**
     * @param array $config
     * @return Client
     */
    protected function buildClient(array $config): Client
    {
        $clientBuilder = ClientBuilder::create();

        $clientBuilder->setHosts($config['hosts']);

        if (aget($config, 'logging')) {
            $logObject = aget($config, 'logObject');
            $logPath = aget($config, 'logPath');
            $logLevel = aget($config, 'logLevel');

            if ($logObject && $logObject instanceof LoggerInterface) {
                $clientBuilder->setLogger($logObject);
            } else if ($logPath && $logLevel) {
                $logObject = ClientBuilder::defaultLogger($logPath, $logLevel);
                $clientBuilder->setLogger($logObject);
            }
        }

        foreach ($this->configMappings as $key => $method) {
            $value = aget($config, $key);

            if ($value !== null) {
                call_user_func([$clientBuilder, $method], $value);
            }
        }

        return $clientBuilder->build();
    }
}