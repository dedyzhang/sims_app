<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! getenv('OPENSSL_CONF') && file_exists('C:/xampp/apache/conf/openssl.cnf')) {
            putenv('OPENSSL_CONF=C:/xampp/apache/conf/openssl.cnf');
        }
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.connections.sqlite.database', database_path('testing.sqlite'));
        $app['config']->set('cache.default', 'array');

        return $app;
    }
}
