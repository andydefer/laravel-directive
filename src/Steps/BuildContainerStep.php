<?php

// src/Steps/BuildContainerStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Configs\EnvDirectiveConfig;
use AndyDefer\Directive\Configs\EnvDirectiveNamingConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\StepResultStatus;
use AndyDefer\Directive\Enums\TestingStep;
use AndyDefer\Directive\Factories\ContainerDirectiveFactory;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Testing\TestDirectiveRegistry;
use Illuminate\Container\Container;

final class BuildContainerStep implements DirectiveTestingStepInterface
{
    public function supports(DirectiveTestingContext $context): bool
    {
        return ! $context->hasContainer();
    }

    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        try {
            $container = new Container;

            // Enregistrer l'application Laravel dans le container si elle existe
            $laravelApp = $context->getLaravelApp();
            if ($laravelApp !== null) {
                $container->instance('app', $laravelApp);
                // Configurer les Facades pour utiliser ce container
                \Illuminate\Support\Facades\Facade::setFacadeApplication($laravelApp);
            }

            $tempDir = $context->getTempDir();
            $bootLaravel = $context->shouldBootLaravel();

            // Register dispatchers
            $container->singleton(RenderDispatcher::class, fn() => new RenderDispatcher);
            $container->singleton(InputDispatcher::class, fn() => new InputDispatcher);

            // Register interaction service
            $container->singleton(DirectiveInteractionService::class, function ($c) {
                return new DirectiveInteractionService(
                    $c->make(RenderDispatcher::class),
                    $c->make(InputDispatcher::class),
                );
            });

            $interaction = $container->make(DirectiveInteractionService::class);
            $context->setInteraction($interaction);

            // Register validation and naming services
            $container->singleton(SignatureValidationService::class, fn() => new SignatureValidationService(new EnvSignatureValidationConfig));
            $container->singleton(DirectiveNamingService::class, fn() => new DirectiveNamingService(new EnvDirectiveNamingConfig));

            // Register Laravel bootstrapper context
            $container->singleton(LaravelBootstrapperContext::class, function () use ($bootLaravel, $tempDir) {
                $bootstrapperContext = new LaravelBootstrapperContext;

                if ($bootLaravel && $tempDir !== null) {
                    $bootstrapperContext->setCustomBootstrapPath($tempDir . '/bootstrap/app.php');
                }

                return $bootstrapperContext;
            });

            // Register directive discovery context
            $container->singleton(DirectiveDiscoveryContext::class, function () {
                return new DirectiveDiscoveryContext;
            });

            // Register config
            $directiveConfig = new EnvDirectiveConfig;
            $container->instance(DirectiveConfigInterface::class, $directiveConfig);

            // Register factory
            $factory = new ContainerDirectiveFactory($container);

            // Get contexts
            $laravelBootstrapperContext = $container->make(LaravelBootstrapperContext::class);
            $discoveryContext = $container->make(DirectiveDiscoveryContext::class);

            // Register hydrator with dependencies injected via constructor
            $hydrator = new DirectiveHydratorService($factory, $laravelBootstrapperContext);

            // Register registry
            $registry = new TestDirectiveRegistry;
            $context->setRegistry($registry);

            // Register discovery service with all dependencies injected via constructor
            $discovery = new DirectiveDiscoveryService(
                config: $directiveConfig,
                hydrator: $hydrator,
                context: $discoveryContext,
                laravelBootstrapperContext: $laravelBootstrapperContext,
                loader: null,
            );

            // Register renderer
            $renderer = new DirectiveRendererService($container->make(RenderDispatcher::class));

            // Register execution service with all dependencies injected via constructor
            $executionService = new DirectiveExecutionService(
                discovery: $discovery,
                parser: new DirectiveParserService,
                hydrator: $hydrator,
                renderer: $renderer,
                laravelBootstrapperContext: $laravelBootstrapperContext,
            );

            // Register kernel
            $kernel = new DirectiveKernel(
                $executionService,
                $container->make(SignatureValidationService::class),
                $renderer,
            );

            $context->setContainer($container);
            $context->setKernel($kernel);

            $context->addStepResult(
                step_name: TestingStep::BUILD_CONTAINER,
                status: StepResultStatus::SUCCESS,
                message: 'Container built successfully'
            );
        } catch (\Exception $e) {
            $context->addStepResult(
                step_name: TestingStep::BUILD_CONTAINER,
                status: StepResultStatus::FAILED,
                message: $e->getMessage()
            );
        }

        return $next($context);
    }
}
