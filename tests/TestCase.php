<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.connections.sqlite.database', database_path('testing.sqlite'));

        return $app;
    }
}
