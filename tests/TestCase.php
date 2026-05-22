<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests;

use AndyDefer\Directive\DirectiveServiceProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [__DIR__ . '/Fixtures/views']);
    }

    protected function getPackageProviders($app)
    {
        return [
            DirectiveServiceProvider::class,
        ];
    }
}
