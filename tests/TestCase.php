<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $config = $app->make('config');
        $database = (string) $config->get('database.connections.'.$config->get('database.default').'.database');

        // RefreshDatabase runs migrate:fresh, so a wrong database would be wiped.
        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException("Refusing to run tests against database [{$database}]; expected a name ending in _testing.");
        }

        return $app;
    }
}
