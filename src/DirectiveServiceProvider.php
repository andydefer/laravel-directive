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
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contexts\LaravelContext;
use AndyDefer\Directive\Contexts\ParameterParserContext;
use AndyDefer\Directive\Contracts\Configs\DatabaseTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveNamingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveParserConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\Directive\Contracts\Configs\SignatureValidationConfigInterface;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\ArgumentApplierService;
use AndyDefer\Directive\Services\ArgumentSplitterService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
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
use AndyDefer\Directive\Steps\BootstrapLaravelStep;
use AndyDefer\Directive\Steps\BuildContainerStep;
use AndyDefer\Directive\Steps\ChangeToTempDirectoryStep;
use AndyDefer\Directive\Steps\CreateLaravelStructureStep;
use AndyDefer\Directive\Steps\CreateTempDirectoryStep;
use AndyDefer\Directive\Steps\StartDatabaseStep;
use AndyDefer\Directive\Strategies\DefaultValueArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionalArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionStrategy;
use AndyDefer\Directive\Strategies\RequiredArgumentStrategy;
use AndyDefer\Directive\Strategies\VariadicArgumentStrategy;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\EnumService;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Support\ServiceProvider;

final class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Configurations
        $this->registerConfigs();

        // Contexts
        $this->registerContexts();

        // Parser components
        $this->registerParserComponents();

        // Core services
        $this->registerCoreServices();

        // Dispatchers
        $this->registerDispatchers();

        // Strategies
        $this->registerStrategies();

        // Steps for testing
        $this->registerTestingSteps();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/directive.php' => config_path('directive.php'),
        ], 'directive-config');
    }

    // ==================== Configurations ====================

    private function registerConfigs(): void
    {
        // Directive config
        $this->app->singleton(DirectiveConfigInterface::class, function ($app) {
            return new EnvDirectiveConfig;
        });

        // Directive naming config
        $this->app->singleton(DirectiveNamingConfigInterface::class, function ($app) {
            return new EnvDirectiveNamingConfig;
        });

        // Signature validation config
        $this->app->singleton(SignatureValidationConfigInterface::class, function ($app) {
            return new EnvSignatureValidationConfig;
        });

        // Directive parser config
        $this->app->singleton(DirectiveParserConfigInterface::class, function ($app) {
            return new DirectiveParserConfig;
        });

        // File creator config
        $this->app->singleton(FileCreatorConfigInterface::class, function ($app) {
            return new FileCreatorConfig(new EnumService);
        });

        // Directive testing config (implements both interfaces)
        $this->app->singleton(DirectiveTestingConfigInterface::class, function ($app) {
            return new DirectiveTestingConfig;
        });

        // Database testing config (same instance as DirectiveTestingConfig)
        $this->app->singleton(DatabaseTestingConfigInterface::class, function ($app) {
            return $app->make(DirectiveTestingConfigInterface::class);
        });
    }

    // ==================== Contexts ====================

    private function registerContexts(): void
    {
        // LaravelBootstrapperContext - pour le bootstrap de Laravel
        $this->app->singleton(LaravelBootstrapperContext::class, function ($app) {
            return new LaravelBootstrapperContext;
        });

        // DirectiveDiscoveryContext - pour la découverte des directives
        $this->app->singleton(DirectiveDiscoveryContext::class, function ($app) {
            return new DirectiveDiscoveryContext;
        });

        // DirectiveContext - pour le contexte d'exécution d'une directive
        $this->app->singleton(DirectiveContext::class, function ($app) {
            return new DirectiveContext(
                laravelBootstrapper: $app->make(LaravelBootstrapperContext::class),
                blueprint: new DirectiveBlueprintRecord('', '', ''),
                aliases: new StringTypedCollection,
                shouldBootLaravel: false,
            );
        });

        // LaravelContext - pour l'état de Laravel
        $this->app->singleton(LaravelContext::class, function ($app) {
            return new LaravelContext;
        });

        // FileSystemContext - pour les opérations système
        $this->app->singleton(FileSystemContext::class, function ($app) {
            return new FileSystemContext;
        });

        // FileCreationContext - pour la création de fichiers
        $this->app->singleton(FileCreationContext::class, function ($app) {
            return new FileCreationContext;
        });
    }

    // ==================== Parser Components ====================

    private function registerParserComponents(): void
    {
        // ParameterParserContext avec ses stratégies
        $this->app->singleton(ParameterParserContext::class, function ($app) {
            $context = new ParameterParserContext;

            $context->addStrategy(new RequiredArgumentStrategy);
            $context->addStrategy(new DefaultValueArgumentStrategy);
            $context->addStrategy(new OptionalArgumentStrategy);
            $context->addStrategy(new VariadicArgumentStrategy);
            $context->addStrategy(new OptionStrategy);

            return $context;
        });

        // ParameterOrderValidatorService
        $this->app->singleton(ParameterOrderValidatorService::class, function ($app) {
            return new ParameterOrderValidatorService(
                $app->make(ParameterParserContext::class)
            );
        });

        // ParameterExtractorService
        $this->app->singleton(ParameterExtractorService::class, function ($app) {
            return new ParameterExtractorService(
                $app->make(ParameterParserContext::class)
            );
        });

        // OptionParserService
        $this->app->singleton(OptionParserService::class, function ($app) {
            return new OptionParserService($app->make(DirectiveParserConfigInterface::class));
        });

        // ArgumentSplitterService
        $this->app->singleton(ArgumentSplitterService::class, function ($app) {
            return new ArgumentSplitterService($app->make(DirectiveParserConfigInterface::class));
        });

        // ArgumentApplierService
        $this->app->singleton(ArgumentApplierService::class, function ($app) {
            return new ArgumentApplierService;
        });

        // DirectiveParserService
        $this->app->singleton(DirectiveParserService::class, function ($app) {
            return new DirectiveParserService($app->make(DirectiveParserConfigInterface::class));
        });
    }

    // ==================== Core Services ====================

    private function registerCoreServices(): void
    {
        // FileSystemInterface
        $this->app->bind(FileSystemInterface::class, function ($app) {
            return new FileSystemService;
        });

        // FileCreatorService
        $this->app->singleton(FileCreatorService::class, function ($app) {
            return new FileCreatorService(
                config: $app->make(FileCreatorConfigInterface::class),
                filesystem: $app->make(FileSystemInterface::class),
                pathParser: $app->make(PathSegmentsParserService::class),
                pathBuilder: $app->make(PathBuilderService::class),
                caseConverter: $app->make(StringCaseConverterService::class),
            );
        });

        // Enregistrement des nouvelles dépendances si nécessaire
        $this->app->singleton(StringCaseConverterService::class);
        $this->app->singleton(PathSegmentsParserService::class);
        $this->app->singleton(PathBuilderService::class);
        // SignatureValidationService
        $this->app->singleton(SignatureValidationService::class, function ($app) {
            return new SignatureValidationService($app->make(SignatureValidationConfigInterface::class));
        });

        // DirectiveNamingService
        $this->app->singleton(DirectiveNamingService::class, function ($app) {
            return new DirectiveNamingService($app->make(DirectiveNamingConfigInterface::class));
        });

        // DirectiveInteractionService
        $this->app->singleton(DirectiveInteractionService::class, function ($app) {
            return new DirectiveInteractionService(
                renderDispatcher: $app->make(RenderDispatcher::class),
                inputDispatcher: $app->make(InputDispatcher::class),
            );
        });

        // DirectiveHydratorService
        $this->app->singleton(DirectiveHydratorService::class, function ($app) {
            return new DirectiveHydratorService(
                laravelBootstrapperContext: $app->make(LaravelBootstrapperContext::class),
                interaction: $app->make(DirectiveInteractionService::class),
            );
        });

        // DirectiveDiscoveryService
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return new DirectiveDiscoveryService(
                config: $app->make(DirectiveConfigInterface::class),
                hydrator: $app->make(DirectiveHydratorService::class),
                context: $app->make(DirectiveDiscoveryContext::class),
                laravelBootstrapperContext: $app->make(LaravelBootstrapperContext::class),
                loader: null,
            );
        });

        // DirectiveRendererService
        $this->app->singleton(DirectiveRendererService::class, function ($app) {
            return new DirectiveRendererService(
                renderDispatcher: $app->make(RenderDispatcher::class),
            );
        });

        // DirectiveExecutionService
        $this->app->singleton(DirectiveExecutionService::class, function ($app) {
            return new DirectiveExecutionService(
                discovery: $app->make(DirectiveDiscoveryService::class),
                parser: $app->make(DirectiveParserService::class),
                hydrator: $app->make(DirectiveHydratorService::class),
                renderer: $app->make(DirectiveRendererService::class),
                laravelBootstrapperContext: $app->make(LaravelBootstrapperContext::class),
            );
        });

        // DirectiveKernel
        $this->app->singleton(DirectiveKernel::class, function ($app) {
            return new DirectiveKernel(
                service: $app->make(DirectiveExecutionService::class),
                signatureValidator: $app->make(SignatureValidationService::class),
                renderer: $app->make(DirectiveRendererService::class),
            );
        });
    }

    // ==================== Dispatchers ====================

    private function registerDispatchers(): void
    {
        $this->app->singleton(RenderDispatcher::class, function ($app) {
            return new RenderDispatcher;
        });

        $this->app->singleton(InputDispatcher::class, function ($app) {
            return new InputDispatcher;
        });
    }

    // ==================== Strategies ====================

    private function registerStrategies(): void
    {
        // Les stratégies sont enregistrées directement dans ParameterParserContext
        // Pas besoin de les enregistrer séparément car elles sont instanciées dans le constructeur
    }

    // ==================== Testing Steps ====================

    private function registerTestingSteps(): void
    {
        $this->app->singleton(CreateTempDirectoryStep::class, function ($app) {
            return new CreateTempDirectoryStep;
        });

        $this->app->singleton(ChangeToTempDirectoryStep::class, function ($app) {
            return new ChangeToTempDirectoryStep;
        });

        $this->app->singleton(CreateLaravelStructureStep::class, function ($app) {
            return new CreateLaravelStructureStep;
        });

        $this->app->singleton(BootstrapLaravelStep::class, function ($app) {
            return new BootstrapLaravelStep;
        });

        $this->app->singleton(BuildContainerStep::class, function ($app) {
            return new BuildContainerStep;
        });

        $this->app->singleton(StartDatabaseStep::class, function ($app) {
            return new StartDatabaseStep($app->make(DatabaseTestingConfigInterface::class));
        });
    }
}
