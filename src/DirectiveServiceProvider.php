<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Factories\ContainerDirectiveFactory;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Tasks\AskQuestionTask;
use AndyDefer\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\Directive\Tasks\DisplayErrorTask;
use AndyDefer\Directive\Tasks\DisplayMessageTask;
use AndyDefer\Directive\Tasks\DisplayTableTask;
use AndyDefer\Logger\Contracts\LoggerInterface;
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

        // ===== Services (ordre d'enregistrement important) =====

        // 1. Parser - Pas de dépendances
        $this->app->singleton(DirectiveParserService::class, function ($app) {
            return new DirectiveParserService;
        });

        // 2. Hydrator - Dépend de la factory
        $this->app->singleton(DirectiveHydratorService::class, function ($app) {
            return new DirectiveHydratorService(
                $app->make(DirectiveFactoryInterface::class)
            );
        });

        // 3. Discovery - Dépend de Config et Hydrator
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return new DirectiveDiscoveryService(
                $app->make(DirectiveConfig::class),
                $app->make(DirectiveHydratorService::class)
            );
        });

        // 4. Renderer - Pas de dépendances
        $this->app->singleton(DirectiveRendererService::class, function ($app) {
            return new DirectiveRendererService;
        });

        // 5. Execution - Dépend de tous les autres services
        $this->app->singleton(DirectiveExecutionService::class, function ($app) {
            return new DirectiveExecutionService(
                $app->make(DirectiveDiscoveryService::class),
                $app->make(DirectiveParserService::class),
                $app->make(DirectiveHydratorService::class),
                $app->make(DirectiveRendererService::class),
                $app->make(DisplayMessageTask::class),
                $app->make(DisplayErrorTask::class),
                $app->make(LoggerInterface::class),
            );
        });

        // ===== Tasks (singletons simples) =====

        $this->app->singleton(DisplayMessageTask::class, function ($app) {
            return new DisplayMessageTask;
        });

        $this->app->singleton(AskQuestionTask::class, function ($app) {
            return new AskQuestionTask;
        });

        $this->app->singleton(ConfirmQuestionTask::class, function ($app) {
            return new ConfirmQuestionTask;
        });

        $this->app->singleton(DisplayTableTask::class, function ($app) {
            return new DisplayTableTask;
        });

        $this->app->singleton(DisplayErrorTask::class, function ($app) {
            return new DisplayErrorTask;
        });

        // ===== Kernel (point d'entrée) =====

        $this->app->singleton(DirectiveKernel::class, function ($app) {
            return new DirectiveKernel(
                $app->make(DirectiveExecutionService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/directive.php' => config_path('directive.php'),
        ], 'directive-config');
    }
}
