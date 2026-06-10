<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\Contracts\Services\FileSystemInterface;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Factories\ContainerDirectiveFactory;
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

/**
 * Laravel service provider for the Directive package.
 */
final class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->registerLaravelBootstrapperContext();
        $this->registerFactory();
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
        $this->app->singleton(DirectiveConfig::class, function ($app) {
            $config = DirectiveConfig::default();

            if ($this->isLaravelConfigAvailable($app)) {
                $appConfig = $app['config']->get('directive', []);

                if (isset($appConfig['path'])) {
                    $config = $config->withDirectivesPath($appConfig['path']);
                }
            }

            return $config;
        });
    }

    private function registerLaravelBootstrapperContext(): void
    {
        $this->app->singleton(LaravelBootstrapperContext::class, function ($app) {
            return new LaravelBootstrapperContext;
        });
    }

    private function registerFactory(): void
    {
        $this->app->singleton(DirectiveFactoryInterface::class, function ($app) {
            return new ContainerDirectiveFactory(
                container: $app,
            );
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
            $hydrator = new DirectiveHydratorService(
                factory: $app->make(DirectiveFactoryInterface::class),
            );

            if ($app->bound(LaravelBootstrapperContext::class)) {
                $hydrator->setLaravelBootstrapper($app->make(LaravelBootstrapperContext::class));
            }

            return $hydrator;
        });
    }

    private function registerDiscovery(): void
    {
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            $discovery = new DirectiveDiscoveryService(
                config: $app->make(DirectiveConfig::class),
                hydrator: $app->make(DirectiveHydratorService::class),
                loader: null,
            );

            if ($app->bound(LaravelBootstrapperContext::class)) {
                $discovery->setLaravelBootstrapper($app->make(LaravelBootstrapperContext::class));
            }

            return $discovery;
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
            return new SignatureValidationService;
        });
    }

    private function registerNamingService(): void
    {
        $this->app->singleton(DirectiveNamingService::class, function ($app) {
            return new DirectiveNamingService;
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
            $executionService = new DirectiveExecutionService(
                discovery: $app->make(DirectiveDiscoveryService::class),
                parser: $app->make(DirectiveParserService::class),
                hydrator: $app->make(DirectiveHydratorService::class),
                renderer: $app->make(DirectiveRendererService::class),
            );

            if ($app->bound(LaravelBootstrapperContext::class)) {
                $executionService->setLaravelBootstrapper($app->make(LaravelBootstrapperContext::class));
            }

            return $executionService;
        });
    }

    /**
     * Register the file creator service and its dependencies.
     */
    private function registerFileCreator(): void
    {
        // Register FileSystemInterface with native implementation
        $this->app->bind(FileSystemInterface::class, function ($app) {
            return new FileSystemService;
        });

        // Register FileCreatorConfig
        $this->app->singleton(FileCreatorConfig::class, function ($app) {
            return new FileCreatorConfig(new EnumService);
        });

        // Register FileCreatorService
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
