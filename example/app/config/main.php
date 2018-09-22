<?php

use Illuminate\Cache\FileStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;

$this->container['file.cache'] = new Repository(new FileStore(l('files'), \Octo\storage_path('cf')));
$this->container['throttle'] = new RateLimiter($this->container['file.cache']);
