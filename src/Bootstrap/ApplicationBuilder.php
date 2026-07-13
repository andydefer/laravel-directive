<?php

namespace AndyDefer\Directive\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ApplicationBuilder
{
    private array $providers = [];

    private array $config = [];

    /**
     * Add a service provider
     *
     * @param  class-string<ServiceProvider>  $provider
     */
    public function withProvider(string $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Add multiple service providers
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public function withProviders(array $providers): self
    {
        $this->providers = array_merge($this->providers, $providers);

        return $this;
    }

    /**
     * Set configuration
     */
    public function withConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Set a single configuration value
     */
    public function withConfigValue(string $key, mixed $value): self
    {
        $this->config[$key] = $value;

        return $this;
    }

    /**
     * Build the application
     */
    public function build(): Application
    {
        $app = $this->createBaseApplication();

        // Apply configuration
        $this->applyConfig($app);

        // Register all providers
        $this->registerProviders($app);

        // Boot all providers
        $this->bootProviders($app);

        return $app;
    }

    /**
     * Create base application
     */
    private function createBaseApplication(): Application
    {
        if (EnvironmentDetector::isPackage()) {
            return InternalApplicationFactory::create();
        }

        return ExternalApplicationFactory::create();
    }

    /**
     * Apply configuration to application
     */
    private function applyConfig(Application $app): void
    {
        foreach ($this->config as $key => $value) {
            if (method_exists($app, 'config')) {
                $app->config->set($key, $value);
            }
        }
    }

    /**
     * Register all providers
     */
    private function registerProviders(Application $app): void
    {
        foreach ($this->providers as $providerClass) {
            if (! is_subclass_of($providerClass, ServiceProvider::class)) {
                throw new \InvalidArgumentException(
                    "Class {$providerClass} must extend ".ServiceProvider::class
                );
            }

            /** @var ServiceProvider $provider */
            $provider = new $providerClass($app);
            $provider->register();
        }
    }

    /**
     * Boot all providers
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
