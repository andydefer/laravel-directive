<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Container\DirectiveServiceRegistrar;
use AndyDefer\Directive\Container\LaravelContainerAdapter;
use AndyDefer\Directive\Contracts\ContainerInterface;
use Illuminate\Contracts\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;

final class DirectiveServiceProvider extends ServiceProvider
{
    private DirectiveServiceRegistrar $registrar;

    public function register(): void
    {
        // Enregistrer l'adaptateur Laravel
        $this->registerContainerAdapter();

        // Créer le registrar et enregistrer tous les services
        $this->registrar = new DirectiveServiceRegistrar($this->app);
        $this->registrar->registerAll();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/directive.php' => config_path('directive.php'),
        ], 'directive-config');
    }

    /**
     * Register the Laravel container adapter.
     */
    private function registerContainerAdapter(): void
    {
        $this->app->singleton(ContainerInterface::class, function ($app) {
            /** @var LaravelApplication $laravel */
            $laravel = $app;

            return new LaravelContainerAdapter($laravel);
        });
    }
}
