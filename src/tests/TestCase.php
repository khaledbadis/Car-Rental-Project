<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Compose injects the development database. Never let RefreshDatabase touch it.
        $app['config']->set('database.connections.pgsql.database', 'rental_test');
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql.url', null);

        return $app;
    }
}
