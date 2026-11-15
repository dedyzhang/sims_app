<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.connections.sqlite.database', database_path('testing.sqlite'));

        return $app;
    }
}
