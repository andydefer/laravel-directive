<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Cli;

use AndyDefer\Directive\Configs\EnvDirectiveConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\SignatureValidationService;
use Illuminate\Container\Container;

final class CliRunner
{
    private Container $container;
    private string $directivesPath;

    public function __construct()
    {
        $this->container = new Container();
        $this->directivesPath = $this->resolveDirectivesPath();
        putenv("DIRECTIVE_PATH={$this->directivesPath}");
    }

    public function run(array $argv): int
    {
        $this->registerBaseServices();
        $kernel = $this->buildKernel();

        return $kernel->run($argv)->value;
    }

    private function resolveDirectivesPath(): string
    {
        $candidates = [
            getcwd() . '/app/Directives',
            getcwd() . '/directives',
            getcwd() . '/src/Directives',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private function registerBaseServices(): void
    {
        // Dispatchers
        $this->container->singleton(RenderDispatcher::class, fn() => new RenderDispatcher);
        $this->container->singleton(InputDispatcher::class, fn() => new InputDispatcher);

        // Contexts
        $this->container->singleton(LaravelBootstrapperContext::class, fn() => new LaravelBootstrapperContext);
        $this->container->singleton(DirectiveDiscoveryContext::class, fn() => new DirectiveDiscoveryContext);

        // Validation
        $this->container->singleton(
            SignatureValidationService::class,
            fn() => new SignatureValidationService(new EnvSignatureValidationConfig)
        );

        // Interaction
        $this->container->singleton(
            DirectiveInteractionService::class,
            fn($c) => new DirectiveInteractionService(
                $c->make(RenderDispatcher::class),
                $c->make(InputDispatcher::class),
            )
        );
    }

    private function buildKernel(): DirectiveKernel
    {
        $config = new EnvDirectiveConfig();
        $parser = new DirectiveParserService();

        $laravelContext = $this->container->make(LaravelBootstrapperContext::class);
        $discoveryContext = $this->container->make(DirectiveDiscoveryContext::class);
        $interaction = $this->container->make(DirectiveInteractionService::class);

        // Hydrator sans factory
        $hydrator = new DirectiveHydratorService(
            laravelBootstrapperContext: $laravelContext,
            interaction: $interaction,
        );

        $discovery = new DirectiveDiscoveryService(
            config: $config,
            hydrator: $hydrator,
            context: $discoveryContext,
            laravelBootstrapperContext: $laravelContext,
            loader: null,
        );

        $renderer = new DirectiveRendererService($this->container->make(RenderDispatcher::class));
        $validator = $this->container->make(SignatureValidationService::class);

        $execution = new DirectiveExecutionService(
            discovery: $discovery,
            parser: $parser,
            hydrator: $hydrator,
            renderer: $renderer,
            laravelBootstrapperContext: $laravelContext,
        );

        return new DirectiveKernel($execution, $validator, $renderer);
    }
}
