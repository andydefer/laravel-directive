<?php
// src/Steps/BuildContainerStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\DirectiveKernel;
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
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Testing\TestDirectiveRegistry;
use Illuminate\Container\Container;

/**
 * Step that builds the service container for testing.
 *
 * @author Andy Defer
 */
final class BuildContainerStep implements DirectiveTestingStepInterface
{
    public function supports(DirectiveTestingContext $context): bool
    {
        return !$context->hasContainer();
    }

    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        $container = new Container();
        $tempDir = $context->getTempDir();
        $bootLaravel = $context->shouldBootLaravel();

        // Register dispatchers
        $container->singleton(RenderDispatcher::class, fn() => new RenderDispatcher());
        $container->singleton(InputDispatcher::class, fn() => new InputDispatcher());

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
        $container->singleton(SignatureValidationService::class, fn() => new SignatureValidationService());
        $container->singleton(DirectiveNamingService::class, fn() => new DirectiveNamingService());

        // Register Laravel bootstrapper
        $container->singleton(LaravelBootstrapper::class, function () use ($bootLaravel, $tempDir) {
            $bootstrapper = new LaravelBootstrapper();

            if ($bootLaravel) {
                $bootstrapper->setCustomBootstrapPath($tempDir . '/bootstrap/app.php');
            }

            return $bootstrapper;
        });

        // Register config
        $directiveConfig = DirectiveConfig::default()->withDirectivesPath($tempDir . '/app/Directives');
        $container->instance(DirectiveConfig::class, $directiveConfig);

        // Register factory and hydrator
        $factory = new ContainerDirectiveFactory($container);
        $hydrator = new DirectiveHydratorService($factory);
        $laravelBootstrapper = $container->make(LaravelBootstrapper::class);
        $hydrator->setLaravelBootstrapper($laravelBootstrapper);

        // Register registry
        $registry = new TestDirectiveRegistry();
        $context->setRegistry($registry);

        // Register discovery service
        $discovery = new DirectiveDiscoveryService($directiveConfig, $hydrator, $registry);
        $discovery->setLaravelBootstrapper($laravelBootstrapper);

        // Register renderer
        $renderer = new DirectiveRendererService($container->make(RenderDispatcher::class));

        // Register execution service
        $executionService = new DirectiveExecutionService(
            discovery: $discovery,
            parser: new DirectiveParserService(),
            hydrator: $hydrator,
            renderer: $renderer,
        );
        $executionService->setLaravelBootstrapper($laravelBootstrapper);

        // Register kernel
        $kernel = new DirectiveKernel(
            $executionService,
            $container->make(SignatureValidationService::class),
            $renderer,
        );

        $context->setContainer($container);
        $context->setKernel($kernel);
        $context->addStepResult('build_container', 'Container built successfully');

        return $next($context);
    }
}
