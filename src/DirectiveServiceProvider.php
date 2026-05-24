<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\Contracts\DirectiveRegistrarInterface;
use AndyDefer\Directive\Directives\MakeDirective;
use AndyDefer\Directive\Factories\ContainerDirectiveFactory;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRegistrar;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Tasks\CreateDirectiveFileTask;
use AndyDefer\Directive\Tasks\InputTask;
use AndyDefer\Directive\Tasks\RenderTask;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel service provider for the Directive package.
 */
final class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->registerLaravelBootstrapper();
        $this->registerFactory();
        $this->registerRegistrar();
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
        $this->registerMakeDirective();
        $this->registerBuiltInDirectives();
        $this->registerKernel();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/directive.php' => config_path('directive.php'),
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

    private function registerLaravelBootstrapper(): void
    {
        $this->app->singleton(LaravelBootstrapper::class, function ($app) {
            return new LaravelBootstrapper;
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

    private function registerRegistrar(): void
    {
        $this->app->singleton(DirectiveRegistrarInterface::class, function ($app) {
            return new DirectiveRegistrar;
        });

        $this->app->singleton(DirectiveRegistrar::class, function ($app) {
            return $app->make(DirectiveRegistrarInterface::class);
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

            // Injecter le bootstrapper si disponible
            if ($app->bound(LaravelBootstrapper::class)) {
                $hydrator->setLaravelBootstrapper($app->make(LaravelBootstrapper::class));
            }

            return $hydrator;
        });
    }

    private function registerDiscovery(): void
    {
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return new DirectiveDiscoveryService(
                config: $app->make(DirectiveConfig::class),
                hydrator: $app->make(DirectiveHydratorService::class),
                registrar: $app->make(DirectiveRegistrarInterface::class),
            );
        });
    }

    private function registerRenderer(): void
    {
        $this->app->singleton(DirectiveRendererService::class, function ($app) {
            return new DirectiveRendererService(
                renderTask: $app->make(RenderTask::class),
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
            RenderTask::class,
            InputTask::class,
            CreateDirectiveFileTask::class,
        ];

        foreach ($tasks as $task) {
            $this->app->singleton($task, function ($app) use ($task) {
                if ($task === CreateDirectiveFileTask::class) {
                    return new CreateDirectiveFileTask(
                        namingService: $app->make(DirectiveNamingService::class),
                    );
                }

                return new $task;
            });
        }
    }

    private function registerInputTask(): void
    {
        $this->app->singleton(InputTask::class, function ($app) {
            return new InputTask;
        });
    }

    private function registerInteractionService(): void
    {
        $this->app->singleton(DirectiveInteractionService::class, function ($app) {
            return new DirectiveInteractionService(
                renderTask: $app->make(RenderTask::class),
                inputTask: $app->make(InputTask::class),
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

            // Injecter le bootstrapper si disponible
            if ($app->bound(LaravelBootstrapper::class)) {
                $executionService->setLaravelBootstrapper($app->make(LaravelBootstrapper::class));
            }

            return $executionService;
        });
    }

    private function registerMakeDirective(): void
    {
        $this->app->singleton(MakeDirective::class, function ($app) {
            return new MakeDirective(
                interaction: $app->make(DirectiveInteractionService::class),
                signatureValidator: $app->make(SignatureValidationService::class),
                namingService: $app->make(DirectiveNamingService::class),
                fileTask: $app->make(CreateDirectiveFileTask::class),
            );
        });
    }

    private function registerBuiltInDirectives(): void
    {
        $this->app->afterResolving(DirectiveRegistrarInterface::class, function ($registrar) {
            $classes = new StringTypedCollection;
            $classes->add(MakeDirective::class);

            $registrar->register($classes);
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
