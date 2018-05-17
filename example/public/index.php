<?php
require_once __DIR__ . '/../vendor/autoload.php';

try {
    bootApp();
} catch (\Exception $e) {
    dd($e);
}
