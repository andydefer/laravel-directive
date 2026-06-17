<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests;

use AndyDefer\Directive\DirectiveServiceProvider;
use Carbon\Carbon;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->runDatabaseMigrations();
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

        $app['config']->set('cache.default', 'array');
        $app['config']->set('view.compiled', __DIR__.'/storage/framework/views');
        $app['config']->set('directive.path', __DIR__.'/Fixtures/Directives');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
    }

    protected function getPackageProviders($app)
    {
        return [
            DirectiveServiceProvider::class,
        ];
    }

    protected function runDatabaseMigrations(): void
    {
        $migrationPath = __DIR__.'/database/migrations';

        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--force' => true,
        ])->run();
    }
}
