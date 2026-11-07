<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuses to run against anything but a *_test database, so RefreshDatabase can never wipe the live data.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Tests must run against a *_test database, not [{$database}]. Run them with php8.4 artisan test.");
        }
    }
}
