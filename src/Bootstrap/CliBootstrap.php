<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Bootstrap;

use AndyDefer\Directive\Cli\CliRunner;
use AndyDefer\Directive\Exceptions\BootstrapException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;

/**
 * Bootstrap script for the Directive CLI runner.
 */
final readonly class CliBootstrap
{
    public function __construct(
        private Application $app,
    ) {}

    /**
     * @param  array<int, string>  $arguments
     */
    public function run(array $arguments): int
    {
        $runner = $this->app->make(CliRunner::class);

        return $runner->run($arguments);
    }

    public static function create(): self
    {
        self::loadEnvironment();
        self::loadAutoloader();

        $app = self::createApplication();
        self::registerProviders($app);
        self::bootApplication($app);

        return new self($app);
    }

    private static function loadEnvironment(): void
    {
        if (! Paths::hasEnvFile()) {
            return;
        }

        $lines = file(Paths::envFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                putenv($line);
            }
        }
    }

    private static function loadAutoloader(): void
    {
        if (! Paths::hasProjectAutoload()) {
            throw new BootstrapException(
                'Autoloader not found at ['.Paths::projectAutoload()."]. Run 'composer install' first."
            );
        }

        require_once Paths::projectAutoload();

        if (Paths::hasPackageAutoload() && Paths::packageAutoload() !== Paths::projectAutoload()) {
            require_once Paths::packageAutoload();
        }
    }

    private static function createApplication(): Application
    {
        if (! Paths::hasLaravelBootstrap()) {
            throw new BootstrapException(
                'Laravel bootstrap file not found at ['.Paths::laravelBootstrap().'].'
            );
        }

        $app = require Paths::laravelBootstrap();

        if (! $app instanceof Application) {
            throw new BootstrapException(
                'Bootstrap file must return an instance of '.Application::class
            );
        }

        return $app;
    }

    private static function registerProviders(Application $app): void
    {
        $providers = array_merge(
            self::resolveProvidersFromStorage(),
            self::resolveProvidersFromConfig()
        );

        foreach ($providers as $provider) {
            if (! is_string($provider) || ! class_exists($provider)) {
                continue;
            }

            $app->register($provider);
        }
    }

    /**
     * @return list<class-string>
     */
    private static function resolveProvidersFromStorage(): array
    {
        if (! Paths::hasCompiledProviders()) {
            return [];
        }

        /** @var array<string, mixed> $providersData */
        $providersData = require Paths::compiledProviders();

        $providers = $providersData['providers'] ?? [];

        $packageKey = 'andydefer/laravel-task';
        if (isset($providersData[$packageKey]['providers']) && is_array($providersData[$packageKey]['providers'])) {
            $providers = array_merge($providers, $providersData[$packageKey]['providers']);
        }

        return array_values(array_filter($providers, 'is_string'));
    }

    /**
     * @return list<class-string>
     */
    private static function resolveProvidersFromConfig(): array
    {
        if (! Paths::hasAppConfig()) {
            return [];
        }

        /** @var array<string, mixed> $config */
        $config = require Paths::appConfig();

        $providers = $config['providers'] ?? [];

        return is_array($providers) ? array_values(array_filter($providers, 'is_string')) : [];
    }

    private static function bootApplication(Application $app): void
    {
        $app->make(Kernel::class)->bootstrap();
    }
}
