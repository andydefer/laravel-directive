<?php

// src/Cli/CliRunner.php

declare(strict_types=1);

namespace AndyDefer\Directive\Cli;

use AndyDefer\Directive\Configs\EnvDirectiveConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Services\ContainerService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveMetadataExtractorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Sources\CompositeDiscoverySource;
use AndyDefer\Directive\Sources\ProjectDiscoverySource;
use AndyDefer\Directive\Sources\VendorDiscoverySource;
use AndyDefer\Directive\Strategies\DirectiveExecutionStrategy;
use AndyDefer\Directive\Strategies\HelpExecutionStrategy;
use AndyDefer\Directive\Strategies\ListExecutionStrategy;
use AndyDefer\Directive\Strategies\VersionExecutionStrategy;
use AndyDefer\DomainStructures\Services\HydrationService;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;

final class CliRunner
{
    private Container $container;

    private string $directivesPath;

    private ?Application $application;

    public function __construct(?Application $application = null)
    {
        $this->container = new Container;
        $this->application = $application;
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
            getcwd().'/app/Directives',
            getcwd().'/directives',
            getcwd().'/src/Directives',
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
        $this->container->singleton(RenderDispatcher::class, fn () => new RenderDispatcher);
        $this->container->singleton(InputDispatcher::class, fn () => new InputDispatcher);
        $this->container->singleton(DirectiveDiscoveryContext::class, fn () => new DirectiveDiscoveryContext);
        $this->container->singleton(
            SignatureValidationService::class,
            fn () => new SignatureValidationService(new EnvSignatureValidationConfig)
        );
        $this->container->singleton(
            DirectiveInteractionService::class,
            fn ($c) => new DirectiveInteractionService(
                $c->make(RenderDispatcher::class),
                $c->make(InputDispatcher::class),
            )
        );
    }

    private function buildKernel(): DirectiveKernel
    {
        $config = new EnvDirectiveConfig;
        $parser = new DirectiveParserService;
        $discoveryContext = $this->container->make(DirectiveDiscoveryContext::class);
        $interaction = $this->container->make(DirectiveInteractionService::class);
        $renderDispatcher = $this->container->make(RenderDispatcher::class);

        // Hydrator
        $hydrator = new DirectiveHydratorService(
            application: $this->application,
            interaction: $interaction,
        );

        // Extractor
        $extractor = new DirectiveMetadataExtractorService($hydrator);

        // Discovery Sources
        $projectSource = new ProjectDiscoverySource($config, $extractor);
        $vendorSource = new VendorDiscoverySource(getcwd(), $extractor);

        $compositeSource = new CompositeDiscoverySource;
        $compositeSource->addSource($projectSource);
        $compositeSource->addSource($vendorSource);

        // Discovery Service
        $discovery = new DirectiveDiscoveryService($compositeSource);

        // Renderer
        $renderer = new DirectiveRendererService($renderDispatcher);

        // Container Service pour les stratégies
        $containerService = new ContainerService;

        // Ajouter les stratégies
        $containerService->add(HelpExecutionStrategy::class, new HelpExecutionStrategy($renderer));
        $containerService->add(ListExecutionStrategy::class, new ListExecutionStrategy($discovery, $renderer));
        $containerService->add(VersionExecutionStrategy::class, new VersionExecutionStrategy($renderer));
        $containerService->add(DirectiveExecutionStrategy::class, new DirectiveExecutionStrategy(
            $discovery,
            $parser,
            $hydrator,
            $renderer
        ));

        // Execution Service avec le container
        $execution = new DirectiveExecutionService(
            discovery: $discovery,
            parser: $parser,
            hydrator: $hydrator,
            renderer: $renderer,
            container: $containerService,
        );

        // Validator
        $validator = $this->container->make(SignatureValidationService::class);

        // Hydration
        $hydration = new HydrationService;

        return new DirectiveKernel($execution, $validator, $renderer, $hydration);
    }
}
