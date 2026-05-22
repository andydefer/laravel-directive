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
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRegistrar;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Tasks\AskQuestionTask;
use AndyDefer\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\Directive\Tasks\DisplayErrorTask;
use AndyDefer\Directive\Tasks\DisplayMessageTask;
use AndyDefer\Directive\Tasks\DisplayTableTask;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use Illuminate\Support\ServiceProvider;

class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Config
        $this->app->singleton(DirectiveConfig::class, function ($app) {
            $config = DirectiveConfig::default();

            if ($app->has('config') && $app->config->has('directive')) {
                $appConfig = $app->config->get('directive', []);

                if (isset($appConfig['path'])) {
                    $config = $config->withDirectivesPath($appConfig['path']);
                }
            }

            return $config;
        });

        // Factory
        $this->app->singleton(DirectiveFactoryInterface::class, function ($app) {
            return new ContainerDirectiveFactory($app);
        });

        // Registrar (must be registered first so packages can use it)
        $this->app->singleton(DirectiveRegistrarInterface::class, function ($app) {
            return new DirectiveRegistrar();
        });
        $this->app->singleton(DirectiveRegistrar::class, function ($app) {
            return $app->make(DirectiveRegistrarInterface::class);
        });

        // Parser
        $this->app->singleton(DirectiveParserService::class, function ($app) {
            return new DirectiveParserService();
        });

        // Hydrator
        $this->app->singleton(DirectiveHydratorService::class, function ($app) {
            return new DirectiveHydratorService(
                $app->make(DirectiveFactoryInterface::class)
            );
        });

        // Discovery
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return new DirectiveDiscoveryService(
                $app->make(DirectiveConfig::class),
                $app->make(DirectiveHydratorService::class),
                $app->make(DirectiveRegistrarInterface::class),
            );
        });

        // Renderer
        $this->app->singleton(DirectiveRendererService::class, function ($app) {
            return new DirectiveRendererService();
        });

        // Execution
        $this->app->singleton(DirectiveExecutionService::class, function ($app) {
            return new DirectiveExecutionService(
                $app->make(DirectiveDiscoveryService::class),
                $app->make(DirectiveParserService::class),
                $app->make(DirectiveHydratorService::class),
                $app->make(DirectiveRendererService::class),
                $app->make(DisplayMessageTask::class),
                $app->make(DisplayErrorTask::class),
            );
        });

        // Tasks
        $this->app->singleton(DisplayMessageTask::class, function ($app) {
            return new DisplayMessageTask();
        });

        $this->app->singleton(AskQuestionTask::class, function ($app) {
            return new AskQuestionTask();
        });

        $this->app->singleton(ConfirmQuestionTask::class, function ($app) {
            return new ConfirmQuestionTask();
        });

        $this->app->singleton(DisplayTableTask::class, function ($app) {
            return new DisplayTableTask();
        });

        $this->app->singleton(DisplayErrorTask::class, function ($app) {
            return new DisplayErrorTask();
        });

        // Make directive
        $this->app->singleton(MakeDirective::class);

        // Register built-in directives
        $this->app->afterResolving(DirectiveRegistrarInterface::class, function ($registrar) {
            $classes = new StringTypedCollection();
            $classes->add(MakeDirective::class);

            $registrar->register($classes);
        });

        // Kernel
        $this->app->singleton(DirectiveKernel::class, function ($app) {
            return new DirectiveKernel(
                $app->make(DirectiveExecutionService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/directive.php' => config_path('directive.php'),
        ], 'directive-config');
    }
}
