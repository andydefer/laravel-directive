<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Configs\DirectiveParserConfig;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Configs\EnvDirectiveConfig;
use AndyDefer\Directive\Configs\EnvDirectiveNamingConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Contexts\FileSystemContext;
use AndyDefer\Directive\Contexts\LaravelContext;
use AndyDefer\Directive\Contexts\ParameterParserContext;
use AndyDefer\Directive\Contracts\Configs\DatabaseTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveNamingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveParserConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\Directive\Contracts\Configs\SignatureValidationConfigInterface;
use AndyDefer\Directive\Contracts\ContainerInterface;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\ArgumentApplierService;
use AndyDefer\Directive\Services\ArgumentSplitterService;
use AndyDefer\Directive\Services\ContainerService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveMetadataExtractorService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Services\OptionParserService;
use AndyDefer\Directive\Services\ParameterExtractorService;
use AndyDefer\Directive\Services\ParameterOrderValidatorService;
use AndyDefer\Directive\Services\PathBuilderService;
use AndyDefer\Directive\Services\PathSegmentsParserService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Services\StringCaseConverterService;
use AndyDefer\Directive\Sources\CompositeDiscoverySource;
use AndyDefer\Directive\Sources\ProjectDiscoverySource;
use AndyDefer\Directive\Sources\VendorDiscoverySource;
use AndyDefer\Directive\Steps\BootstrapLaravelStep;
use AndyDefer\Directive\Steps\ChangeToTempDirectoryStep;
use AndyDefer\Directive\Steps\CreateLaravelStructureStep;
use AndyDefer\Directive\Steps\CreateTempDirectoryStep;
use AndyDefer\Directive\Strategies\DefaultValueArgumentStrategy;
use AndyDefer\Directive\Strategies\DirectiveExecutionStrategy;
use AndyDefer\Directive\Strategies\HelpExecutionStrategy;
use AndyDefer\Directive\Strategies\ListExecutionStrategy;
use AndyDefer\Directive\Strategies\OptionalArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionStrategy;
use AndyDefer\Directive\Strategies\RequiredArgumentStrategy;
use AndyDefer\Directive\Strategies\VariadicArgumentStrategy;
use AndyDefer\Directive\Strategies\VersionExecutionStrategy;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\EnumService;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Support\ServiceProvider;

final class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfigs();
        $this->registerContexts();
        $this->registerParserComponents();
        $this->registerDiscoveryServices();
        $this->registerExecutionServices();
        $this->registerCoreServices();
        $this->registerDispatchers();
        $this->registerTestingSteps();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/directive.php' => config_path('directive.php'),
        ], 'directive-config');
    }

    private function registerConfigs(): void
    {
        $this->app->singleton(DirectiveConfigInterface::class, fn () => new EnvDirectiveConfig);
        $this->app->singleton(DirectiveNamingConfigInterface::class, fn () => new EnvDirectiveNamingConfig);
        $this->app->singleton(SignatureValidationConfigInterface::class, fn () => new EnvSignatureValidationConfig);
        $this->app->singleton(DirectiveParserConfigInterface::class, fn () => new DirectiveParserConfig);
        $this->app->singleton(FileCreatorConfigInterface::class, fn ($app) => new FileCreatorConfig($app->make(EnumService::class)));
        $this->app->singleton(DirectiveTestingConfigInterface::class, fn () => new DirectiveTestingConfig);
        $this->app->singleton(DatabaseTestingConfigInterface::class, fn ($app) => $app->make(DirectiveTestingConfigInterface::class));
    }

    private function registerContexts(): void
    {
        $this->app->singleton(DirectiveDiscoveryContext::class, fn () => new DirectiveDiscoveryContext);
        $this->app->singleton(DirectiveContext::class, fn () => new DirectiveContext(
            blueprint: new DirectiveBlueprintRecord('', '', ''),
            aliases: new StringTypedCollection,
            laravelApplication: null,
        ));
        $this->app->singleton(LaravelContext::class, fn () => new LaravelContext);
        $this->app->singleton(FileSystemContext::class, fn () => new FileSystemContext);
        $this->app->singleton(FileCreationContext::class, fn () => new FileCreationContext);
    }

    private function registerParserComponents(): void
    {
        $this->app->singleton(ParameterParserContext::class, function () {
            $context = new ParameterParserContext;
            $context->addStrategy(new RequiredArgumentStrategy);
            $context->addStrategy(new DefaultValueArgumentStrategy);
            $context->addStrategy(new OptionalArgumentStrategy);
            $context->addStrategy(new VariadicArgumentStrategy);
            $context->addStrategy(new OptionStrategy);

            return $context;
        });

        $this->app->singleton(ParameterOrderValidatorService::class, fn ($app) => new ParameterOrderValidatorService($app->make(ParameterParserContext::class)));
        $this->app->singleton(ParameterExtractorService::class, fn ($app) => new ParameterExtractorService($app->make(ParameterParserContext::class)));
        $this->app->singleton(OptionParserService::class, fn ($app) => new OptionParserService($app->make(DirectiveParserConfigInterface::class)));
        $this->app->singleton(ArgumentSplitterService::class, fn ($app) => new ArgumentSplitterService($app->make(DirectiveParserConfigInterface::class)));
        $this->app->singleton(ArgumentApplierService::class, fn () => new ArgumentApplierService);
        $this->app->singleton(DirectiveParserService::class, fn ($app) => new DirectiveParserService($app->make(DirectiveParserConfigInterface::class)));
    }

    private function registerDiscoveryServices(): void
    {
        // 1. Metadata Extractor
        $this->app->singleton(DirectiveMetadataExtractorService::class, function ($app) {
            return new DirectiveMetadataExtractorService(
                $app->make(DirectiveHydratorService::class),
            );
        });

        // 2. Project Source
        $this->app->singleton(ProjectDiscoverySource::class, function ($app) {
            return new ProjectDiscoverySource(
                $app->make(DirectiveConfigInterface::class),
                $app->make(DirectiveMetadataExtractorService::class),
            );
        });

        // 3. Vendor Source
        $this->app->singleton(VendorDiscoverySource::class, function ($app) {
            return new VendorDiscoverySource(
                getcwd(),
                $app->make(DirectiveMetadataExtractorService::class),
            );
        });

        // 4. Composite Source
        $this->app->singleton(CompositeDiscoverySource::class, function ($app) {
            $composite = new CompositeDiscoverySource;
            $composite->addSource($app->make(ProjectDiscoverySource::class));
            $composite->addSource($app->make(VendorDiscoverySource::class));

            return $composite;
        });

        // 5. Discovery Service
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return new DirectiveDiscoveryService(
                $app->make(CompositeDiscoverySource::class),
            );
        });
    }

    private function registerExecutionServices(): void
    {
        // 1. Container Service pour les stratégies
        $this->app->singleton(ContainerInterface::class, function ($app) {
            return new ContainerService;
        });

        // 2. Stratégies d'exécution
        $this->app->singleton(HelpExecutionStrategy::class, function ($app) {
            return new HelpExecutionStrategy(
                $app->make(DirectiveRendererService::class),
            );
        });

        $this->app->singleton(ListExecutionStrategy::class, function ($app) {
            return new ListExecutionStrategy(
                $app->make(DirectiveDiscoveryService::class),
                $app->make(DirectiveRendererService::class),
            );
        });

        $this->app->singleton(VersionExecutionStrategy::class, function ($app) {
            return new VersionExecutionStrategy(
                $app->make(DirectiveRendererService::class),
            );
        });

        $this->app->singleton(DirectiveExecutionStrategy::class, function ($app) {
            return new DirectiveExecutionStrategy(
                $app->make(DirectiveDiscoveryService::class),
                $app->make(DirectiveParserService::class),
                $app->make(DirectiveHydratorService::class),
                $app->make(DirectiveRendererService::class),
            );
        });

        // 3. Execution Service avec injection du ContainerService
        $this->app->singleton(DirectiveExecutionService::class, function ($app) {
            $container = $app->make(ContainerInterface::class);

            $container->add(HelpExecutionStrategy::class, $app->make(HelpExecutionStrategy::class));
            $container->add(ListExecutionStrategy::class, $app->make(ListExecutionStrategy::class));
            $container->add(VersionExecutionStrategy::class, $app->make(VersionExecutionStrategy::class));
            $container->add(DirectiveExecutionStrategy::class, $app->make(DirectiveExecutionStrategy::class));

            return new DirectiveExecutionService(
                discovery: $app->make(DirectiveDiscoveryService::class),
                parser: $app->make(DirectiveParserService::class),
                hydrator: $app->make(DirectiveHydratorService::class),
                renderer: $app->make(DirectiveRendererService::class),
                container: $container,
            );
        });
    }

    private function registerCoreServices(): void
    {
        $this->app->bind(FileSystemInterface::class, fn () => new FileSystemService);

        $this->app->singleton(FileCreatorService::class, function ($app) {
            return new FileCreatorService(
                config: $app->make(FileCreatorConfigInterface::class),
                filesystem: $app->make(FileSystemInterface::class),
                pathParser: $app->make(PathSegmentsParserService::class),
                pathBuilder: $app->make(PathBuilderService::class),
                caseConverter: $app->make(StringCaseConverterService::class),
            );
        });

        $this->app->singleton(StringCaseConverterService::class);
        $this->app->singleton(PathSegmentsParserService::class);
        $this->app->singleton(PathBuilderService::class);
        $this->app->singleton(SignatureValidationService::class, fn ($app) => new SignatureValidationService($app->make(SignatureValidationConfigInterface::class)));
        $this->app->singleton(DirectiveNamingService::class, fn ($app) => new DirectiveNamingService($app->make(DirectiveNamingConfigInterface::class)));
        $this->app->singleton(DirectiveInteractionService::class, fn ($app) => new DirectiveInteractionService(
            renderDispatcher: $app->make(RenderDispatcher::class),
            inputDispatcher: $app->make(InputDispatcher::class),
        ));

        $this->app->singleton(DirectiveHydratorService::class, function ($app) {
            return new DirectiveHydratorService(
                application: $app,
                interaction: $app->make(DirectiveInteractionService::class),
            );
        });

        $this->app->singleton(DirectiveRendererService::class, fn ($app) => new DirectiveRendererService($app->make(RenderDispatcher::class)));

        $this->app->singleton(DirectiveKernel::class, fn ($app) => new DirectiveKernel(
            service: $app->make(DirectiveExecutionService::class),
            signatureValidator: $app->make(SignatureValidationService::class),
            renderer: $app->make(DirectiveRendererService::class),
            hydration: $app->make(HydrationService::class)
        ));
    }

    private function registerDispatchers(): void
    {
        $this->app->singleton(RenderDispatcher::class, fn () => new RenderDispatcher);
        $this->app->singleton(InputDispatcher::class, fn () => new InputDispatcher);
    }

    private function registerTestingSteps(): void
    {
        $this->app->singleton(CreateTempDirectoryStep::class, fn () => new CreateTempDirectoryStep);
        $this->app->singleton(ChangeToTempDirectoryStep::class, fn () => new ChangeToTempDirectoryStep);
        $this->app->singleton(CreateLaravelStructureStep::class, fn () => new CreateLaravelStructureStep);
        $this->app->singleton(BootstrapLaravelStep::class, fn () => new BootstrapLaravelStep);
    }
}
