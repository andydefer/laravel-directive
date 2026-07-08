<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Configs\EnvDirectiveNamingConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Contexts\FileSystemContext;
use AndyDefer\Directive\Contexts\LaravelContext;
use AndyDefer\Directive\Contracts\Configs\DatabaseTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveNamingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\Directive\Contracts\Configs\SignatureValidationConfigInterface;
use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Services\PathBuilderService;
use AndyDefer\Directive\Services\PathSegmentsParserService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Services\StringCaseConverterService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\EnumService;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\SignatureParser\Contracts\ParserRegistryInterface;
use AndyDefer\SignatureParser\Contracts\SignatureParserInterface;
use AndyDefer\SignatureParser\SignatureParser;
use Illuminate\Support\ServiceProvider;

final class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfigs();
        $this->registerContexts();
        $this->registerParserComponents();
        $this->registerScannersAndDiscovers();
        $this->registerDiscoveryServices();
        $this->registerCoreServices();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/directive.php' => config_path('directive.php'),
        ], 'directive-config');
    }

    private function registerConfigs(): void
    {
        $this->app->singleton(DirectiveConfigInterface::class, DirectiveConfig::class);

        $this->app->singleton(DirectiveNamingConfigInterface::class, fn () => new EnvDirectiveNamingConfig);
        $this->app->singleton(SignatureValidationConfigInterface::class, fn () => new EnvSignatureValidationConfig);
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
        $this->app->singleton(SignatureParser::class);
        $this->app->singleton(DirectiveParserInterface::class, DirectiveParserService::class);
        $this->app->singleton(ParserRegistryInterface::class, DirectiveParserService::class);
        $this->app->singleton(SignatureParserInterface::class, DirectiveParserService::class);
    }

    // Dans le ServiceProvider
    private function registerScannersAndDiscovers(): void
    {
        // Bind ComposerReaderService avec ses dépendances
        $this->app->bind(ComposerReaderService::class, function ($app) {
            return new ComposerReaderService(
                config: $app->make(DirectiveConfigInterface::class),
                fileSystem: $app->make(FileSystemInterface::class),
            );
        });

        // Bind DependencyResolverService
        $this->app->bind(DependencyResolverService::class, function ($app) {
            return new DependencyResolverService(
                composerReader: $app->make(ComposerReaderService::class),
                fileSystem: $app->make(FileSystemInterface::class),
            );
        });

        // Scanner
        $this->app->singleton(DirectiveClassScanner::class, function ($app) {
            return new DirectiveClassScanner(
                $app->make(FileSystemInterface::class),
            );
        });

        // Discovers
        $this->app->singleton(BuiltInDirectiveDiscovery::class);
        $this->app->singleton(WorkspaceDirectiveDiscovery::class, function ($app) {
            return new WorkspaceDirectiveDiscovery(
                $app->make(FileSystemInterface::class),
                $app->make(DirectiveClassScanner::class),
            );
        });

        $this->app->singleton(VendorDirectiveDiscovery::class, function ($app) {
            return new VendorDirectiveDiscovery(
                $app->make(ComposerReaderService::class),
                $app->make(DependencyResolverService::class),
                $app->make(FileSystemInterface::class),
                $app->make(DirectiveClassScanner::class),
            );
        });
    }

    // Dans le ServiceProvider
    private function registerDiscoveryServices(): void
    {
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return new DirectiveDiscoveryService(
                builtInSource: $app->make(BuiltInDirectiveDiscovery::class),
                workspaceSource: $app->make(WorkspaceDirectiveDiscovery::class),
                vendorSource: $app->make(VendorDirectiveDiscovery::class),
                parser: $app->make(DirectiveParserInterface::class),
                scanner: $app->make(DirectiveClassScanner::class),
                fileSystem: $app->make(FileSystemInterface::class),
                config: $app->make(DirectiveConfigInterface::class),
                maxDepth: $app->make(DirectiveConfigInterface::class)->getMaxDepth(),
            );
        });
    }

    private function registerCoreServices(): void
    {
        $this->app->bind(FileSystemInterface::class, fn () => new FileSystemService);

        $this->app->singleton(Console::class, fn () => new Console);

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
        $this->app->singleton(DirectiveHydratorService::class, function ($app) {
            return new DirectiveHydratorService(
                app: $app,
            );
        });

        $this->app->singleton(DirectiveKernel::class, fn ($app) => new DirectiveKernel(
            $app->make(DirectiveDiscoveryService::class),
            $app->make(DirectiveHydratorService::class),
        ));
    }
}
