<?php

require_once __DIR__.'/../vendor/autoload.php';

$configCache = dirname(__DIR__).'/bootstrap/cache/config.php';
if (file_exists($configCache)) {
    @unlink($configCache);
}
