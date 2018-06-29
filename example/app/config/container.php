<?php

use Octo\Fastmiddlewarecsrf;
use Octo\FastSessionInterface;

$container->set(FastSessionInterface::class, function () {
    return session();
});

$container->set(Fastmiddlewarecsrf::class, function () use ($container) {
    $session = $container->get(FastSessionInterface::class);

    return new Fastmiddlewarecsrf($session);
});