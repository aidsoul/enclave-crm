<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';
        if (file_exists($cachedConfig)) {
            @unlink($cachedConfig);
        }

        $app = parent::createApplication();

        $dbName = $app['config']->get('database.connections.pgsql.database');
        if ($dbName !== 'crm_testing') {
            $app['config']->set('database.connections.pgsql.database', 'crm_testing');
            DB::purge('pgsql');
        }

        $app['config']->set('cache.prefix', 'testing_cache_');

        if ($app['config']->get('database.connections.pgsql.database') !== 'crm_testing') {
            throw new \RuntimeException("CRITICAL: Tests must run against 'crm_testing' database only!");
        }

        return $app;
    }
}
