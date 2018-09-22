<?php

use Octo\Fastmiddlewarecsrf;
use Octo\FastSessionInterface;

$container = new \App\Services\Container;

$container->set(FastSessionInterface::class, function () {
    return \App\Services\Auth::getInstance()->session();
});

$container->set(Fastmiddlewarecsrf::class, function () use ($container) {
    /** @var \Octo\Ultimate $session */
    $session = $container->get(FastSessionInterface::class);

    return new Fastmiddlewarecsrf($session);
});
