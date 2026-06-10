<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Configs\EnvDirectiveConfig;
use AndyDefer\Directive\Configs\EnvDirectiveNamingConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Services\FileSystemInterface;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Services\FileSystemService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\DomainStructures\Services\EnumService;
use Illuminate\Support\ServiceProvider;

final class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->registerLaravelBootstrapperContext();
        $this->registerDirectiveDiscoveryContext();
        $this->registerParser();
        $this->registerHydrator();
        $this->registerDiscovery();
        $this->registerRenderer();
        $this->registerSignatureValidation();
        $this->registerNamingService();
        $this->registerTasks();
        $this->registerInputTask();
        $this->registerInteractionService();
        $this->registerExecution();
        $this->registerFileCreator();
        $this->registerKernel();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/directive.php' => config_path('directive.php'),
        ], 'directive-config');
    }

    private function registerConfig(): void
    {
        $this->app->singleton(DirectiveConfigInterface::class, function ($app) {
            return new EnvDirectiveConfig;
        });
    }

    private function registerLaravelBootstrapperContext(): void
    {
        $this->app->singleton(LaravelBootstrapperContext::class, function ($app) {
            return new LaravelBootstrapperContext;
        });
    }

    private function registerDirectiveDiscoveryContext(): void
    {
        $this->app->singleton(DirectiveDiscoveryContext::class, function ($app) {
            return new DirectiveDiscoveryContext;
        });
    }

    private function registerParser(): void
    {
        $this->app->singleton(DirectiveParserService::class, function ($app) {
            return new DirectiveParserService;
        });
    }

    private function registerHydrator(): void
    {
        $this->app->singleton(DirectiveHydratorService::class, function ($app) {
            return new DirectiveHydratorService(
                laravelBootstrapperContext: $app->make(LaravelBootstrapperContext::class),
                interaction: $app->make(DirectiveInteractionService::class),
            );
        });
    }

    private function registerDiscovery(): void
    {
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return new DirectiveDiscoveryService(
                config: $app->make(DirectiveConfigInterface::class),
                hydrator: $app->make(DirectiveHydratorService::class),
                context: $app->make(DirectiveDiscoveryContext::class),
                laravelBootstrapperContext: $app->make(LaravelBootstrapperContext::class),
                loader: null,
            );
        });
    }

    private function registerRenderer(): void
    {
        $this->app->singleton(DirectiveRendererService::class, function ($app) {
            return new DirectiveRendererService(
                renderDispatcher: $app->make(RenderDispatcher::class),
            );
        });
    }

    private function registerSignatureValidation(): void
    {
        $this->app->singleton(SignatureValidationService::class, function ($app) {
            return new SignatureValidationService(new EnvSignatureValidationConfig);
        });
    }

    private function registerNamingService(): void
    {
        $this->app->singleton(DirectiveNamingService::class, function ($app) {
            return new DirectiveNamingService(new EnvDirectiveNamingConfig);
        });
    }

    private function registerTasks(): void
    {
        $tasks = [
            RenderDispatcher::class,
            InputDispatcher::class,
        ];

        foreach ($tasks as $task) {
            $this->app->singleton($task, function ($app) use ($task) {
                return new $task;
            });
        }
    }

    private function registerInputTask(): void
    {
        $this->app->singleton(InputDispatcher::class, function ($app) {
            return new InputDispatcher;
        });
    }

    private function registerInteractionService(): void
    {
        $this->app->singleton(DirectiveInteractionService::class, function ($app) {
            return new DirectiveInteractionService(
                renderDispatcher: $app->make(RenderDispatcher::class),
                inputDispatcher: $app->make(InputDispatcher::class),
            );
        });
    }

    private function registerExecution(): void
    {
        $this->app->singleton(DirectiveExecutionService::class, function ($app) {
            return new DirectiveExecutionService(
                discovery: $app->make(DirectiveDiscoveryService::class),
                parser: $app->make(DirectiveParserService::class),
                hydrator: $app->make(DirectiveHydratorService::class),
                renderer: $app->make(DirectiveRendererService::class),
                laravelBootstrapperContext: $app->make(LaravelBootstrapperContext::class),
            );
        });
    }

    private function registerFileCreator(): void
    {
        $this->app->bind(FileSystemInterface::class, function ($app) {
            return new FileSystemService;
        });

        $this->app->singleton(FileCreatorConfig::class, function ($app) {
            return new FileCreatorConfig(new EnumService);
        });

        $this->app->singleton(FileCreatorService::class, function ($app) {
            return new FileCreatorService(
                config: $app->make(FileCreatorConfig::class),
                filesystem: $app->make(FileSystemInterface::class),
            );
        });
    }

    private function registerKernel(): void
    {
        $this->app->singleton(DirectiveKernel::class, function ($app) {
            return new DirectiveKernel(
                service: $app->make(DirectiveExecutionService::class),
                signatureValidator: $app->make(SignatureValidationService::class),
                renderer: $app->make(DirectiveRendererService::class),
            );
        });
    }

    private function isLaravelConfigAvailable(object $app): bool
    {
        return $app->has('config') && $app['config']->has('directive');
    }
}
