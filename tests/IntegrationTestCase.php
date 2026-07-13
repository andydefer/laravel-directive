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

    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]+m/', '', $text);
    }

    /**
     * Assert that a string does not start with a given prefix.
     *
     * @param  string  $prefix  The prefix that should not be at the start
     * @param  string  $string  The string to check
     * @param  string  $message  Optional custom message
     */
    public static function assertStringNotStartsWith(string $prefix, string $string, string $message = ''): void
    {
        if ($message === '') {
            $message = sprintf(
                'String "%s" should not start with "%s"',
                $string,
                $prefix
            );
        }

        parent::assertFalse(
            str_starts_with($string, $prefix),
            $message
        );
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
        $migrationPath = __DIR__.'/Fixtures/database/migrations';

        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--force' => true,
        ])->run();
    }
}
