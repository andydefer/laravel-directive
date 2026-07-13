<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Bootstrap;

use AndyDefer\Directive\Enums\ApplicationType;
use AndyDefer\Directive\Factories\ExternalApplicationFactory;
use AndyDefer\Directive\Factories\InternalApplicationFactory;
use AndyDefer\Directive\Helpers\EnvironmentDetector;
use AndyDefer\Directive\Providers\ConfigServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Factory for creating and configuring the Directive application.
 *
 * This class provides both a fluent builder interface and a simple
 * factory method for creating Laravel applications with custom
 * service providers and configuration.
 *
 * @example
 * // Internal Laravel application
 * $app = ApplicationBuilder::internal([
 *     DirectiveServiceProvider::class
 * ])->build();
 *
 * // External standalone application with config
 * $app = ApplicationBuilder::external([
 *     DirectiveServiceProvider::class
 * ])->withConfig(['debug' => true])->build();
 *
 * // With automatic detection
 * $app = ApplicationBuilder::create([DirectiveServiceProvider::class]);
 */
final class ApplicationBuilder
{
    /**
     * @var array<class-string<ServiceProvider>>
     */
    private array $providers = [];

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * @var array<string, string>
     */
    private array $configPaths = [];

    private ?ApplicationType $forcedType = null;

    /**
     * Private constructor to enforce factory pattern.
     */
    private function __construct()
    {
        // ✅ ConfigServiceProvider chargé automatiquement par défaut
        $this->providers[] = ConfigServiceProvider::class;
    }

    /**
     * Create a new application builder instance.
     *
     * @param  ApplicationType|null  $type  Force a specific application type
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function init(?ApplicationType $type = null, array $providers = []): self
    {
        $builder = new self;

        if ($type !== null) {
            $builder->forceType($type);
        }

        if (! empty($providers)) {
            $builder->withProviders($providers);
        }

        return $builder;
    }

    /**
     * Create a builder for an internal (Laravel) application.
     *
     * This forces the builder to use the InternalApplicationFactory
     * regardless of environment detection.
     *
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function internal(array $providers = []): self
    {
        return self::init(ApplicationType::INTERNAL, $providers);
    }

    /**
     * Create a builder for an external (standalone) application.
     *
     * This forces the builder to use the ExternalApplicationFactory
     * regardless of environment detection.
     *
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function external(array $providers = []): self
    {
        return self::init(ApplicationType::EXTERNAL, $providers);
    }

    /**
     * Create a builder for a web application context.
     *
     * Forces the builder to use the InternalApplicationFactory
     * with web application detection.
     *
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function web(array $providers = []): self
    {
        return self::init(ApplicationType::WEB_APPLICATION, $providers);
    }

    /**
     * Create a builder for a package/library context.
     *
     * Forces the builder to use the ExternalApplicationFactory
     * with package detection.
     *
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function package(array $providers = []): self
    {
        return self::init(ApplicationType::PACKAGE, $providers);
    }

    /**
     * Create application with providers (simple approach).
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     * @param  ApplicationType|null  $type  Force a specific application type
     */
    public static function create(array $providers = [], ?ApplicationType $type = null): Application
    {
        return self::init($type, $providers)
            ->build();
    }

    /**
     * Create an internal (Laravel) application with providers.
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function createInternal(array $providers = []): Application
    {
        return self::internal($providers)
            ->build();
    }

    /**
     * Create an external (standalone) application with providers.
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function createExternal(array $providers = []): Application
    {
        return self::external($providers)
            ->build();
    }

    /**
     * Force the application to be built with a specific type.
     *
     * @param  ApplicationType  $type  The application type to force
     */
    public function forceType(ApplicationType $type): self
    {
        $this->forcedType = $type;

        return $this;
    }

    /**
     * Add a service provider.
     *
     * @param  class-string<ServiceProvider>  $provider
     */
    public function withProvider(string $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Add multiple service providers.
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public function withProviders(array $providers): self
    {
        $this->providers = array_merge($this->providers, $providers);

        return $this;
    }

    /**
     * Set configuration.
     */
    public function withConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Set a single configuration value.
     */
    public function withConfigValue(string $key, mixed $value): self
    {
        $this->config[$key] = $value;

        return $this;
    }

    /**
     * Add a configuration file path.
     *
     * The configuration file should return an array of configuration values.
     *
     * @param  string  $path  Absolute path to the configuration file
     * @param  string|null  $key  Optional key to nest the config under
     */
    public function withConfigPath(string $path, ?string $key = null): self
    {
        $this->configPaths[$path] = $key ?? pathinfo($path, PATHINFO_FILENAME);

        return $this;
    }

    /**
     * Add multiple configuration file paths.
     *
     * @param  array<string, string|null>  $paths  Array of paths with optional keys
     *
     * @example
     *   withConfigPaths([
     *       '/path/to/directive.php',              // key = 'directive'
     *       '/path/to/nemesis.php' => 'nemesis',   // explicit key
     *   ])
     */
    public function withConfigPaths(array $paths): self
    {
        foreach ($paths as $path => $key) {
            if (is_int($path)) {
                // No key provided, use filename
                $this->withConfigPath($key);
            } else {
                // Key provided
                $this->withConfigPath($path, $key);
            }
        }

        return $this;
    }

    /**
     * Build the application.
     */
    public function build(): Application
    {
        $app = $this->createBaseApplication();

        $this->loadConfigFiles($app);
        $this->applyConfig($app);
        $this->registerProviders($app);
        $this->bootProviders($app);

        return $app;
    }

    /**
     * Create base application.
     */
    private function createBaseApplication(): Application
    {
        // ✅ Use forced type if specified
        if ($this->forcedType !== null) {
            return match ($this->forcedType) {
                ApplicationType::INTERNAL => InternalApplicationFactory::create(),
                ApplicationType::EXTERNAL => ExternalApplicationFactory::create(),
                ApplicationType::WEB_APPLICATION => InternalApplicationFactory::create(),
                ApplicationType::PACKAGE => ExternalApplicationFactory::create(),
                default => $this->detectApplication(),
            };
        }

        return $this->detectApplication();
    }

    /**
     * Detect the application type from the environment.
     */
    private function detectApplication(): Application
    {
        if (EnvironmentDetector::isWebApplication()) {
            return InternalApplicationFactory::create();
        }

        if (EnvironmentDetector::isPackage()) {
            return ExternalApplicationFactory::create();
        }

        // Default to External (standalone)
        return ExternalApplicationFactory::create();
    }

    /**
     * Load configuration from files.
     */
    private function loadConfigFiles(Application $app): void
    {
        foreach ($this->configPaths as $path => $key) {
            if (! file_exists($path)) {
                throw new \InvalidArgumentException(
                    sprintf('Configuration file not found: %s', $path)
                );
            }

            $config = require $path;

            if (! is_array($config)) {
                throw new \InvalidArgumentException(
                    sprintf('Configuration file must return an array: %s', $path)
                );
            }

            // Merge with existing config
            if (method_exists($app, 'config')) {
                $current = $app->config->get($key, []);
                $app->config->set($key, array_merge($current, $config));
            }
        }
    }

    /**
     * Apply configuration to application.
     */
    private function applyConfig(Application $app): void
    {
        foreach ($this->config as $key => $value) {
            if (method_exists($app, 'config')) {
                $current = $app->config->get($key, []);

                if (is_array($current) && is_array($value)) {
                    $app->config->set($key, array_merge($current, $value));
                } else {
                    $app->config->set($key, $value);
                }
            }
        }
    }

    /**
     * Register all providers.
     */
    private function registerProviders(Application $app): void
    {
        foreach ($this->providers as $providerClass) {
            if (! is_subclass_of($providerClass, ServiceProvider::class)) {
                throw new \InvalidArgumentException(
                    sprintf('Class "%s" must extend %s', $providerClass, ServiceProvider::class)
                );
            }

            /** @var ServiceProvider $provider */
            $provider = new $providerClass($app);
            $provider->register();
        }
    }

    /**
     * Boot all providers.
     */
    private function bootProviders(Application $app): void
    {
        foreach ($this->providers as $providerClass) {
            /** @var ServiceProvider $provider */
            $provider = new $providerClass($app);

            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
    }
}
