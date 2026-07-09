<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Container\LaravelContainerAdapter;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\ContainerInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\SignatureParser\Contracts\ParserRegistryInterface;
use AndyDefer\SignatureParser\Contracts\SignatureParserInterface;
use AndyDefer\SignatureParser\SignatureParser;
use Illuminate\Contracts\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfigs();
        $this->registerCoreServices();
        $this->registerParserComponents();
        $this->registerScannersAndDiscovers();
        $this->registerDiscoveryServices();
        $this->registerContainerAdapter();
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
    }

    private function registerParserComponents(): void
    {
        $this->app->singleton(SignatureParser::class);
        $this->app->singleton(DirectiveParserInterface::class, DirectiveParserService::class);
        $this->app->singleton(ParserRegistryInterface::class, DirectiveParserService::class);
        $this->app->singleton(SignatureParserInterface::class, DirectiveParserService::class);
    }

    private function registerScannersAndDiscovers(): void
    {
        $this->app->bind(ComposerReaderService::class, function ($app) {
            return new ComposerReaderService(
                config: $app->make(DirectiveConfigInterface::class),
                fileSystem: $app->make(FileSystemInterface::class),
            );
        });

        $this->app->bind(DependencyResolverService::class, function ($app) {
            return new DependencyResolverService(
                composerReader: $app->make(ComposerReaderService::class),
                fileSystem: $app->make(FileSystemInterface::class),
            );
        });

        $this->app->singleton(Parser::class, function () {
            return (new ParserFactory)->createForNewestSupportedVersion();
        });

        $this->app->singleton(DirectiveScannerInterface::class, function ($app) {
            $fileSystem = $app->make(FileSystemInterface::class);
            $parser = $app->make(Parser::class);

            return new DirectiveClassScanner($fileSystem, $parser);
        });

        $this->app->singleton(BuiltInDirectiveDiscovery::class);
        $this->app->singleton(WorkspaceDirectiveDiscovery::class, function ($app) {
            return new WorkspaceDirectiveDiscovery(
                $app->make(FileSystemInterface::class),
                $app->make(DirectiveScannerInterface::class),
            );
        });

        $this->app->singleton(VendorDirectiveDiscovery::class, function ($app) {
            return new VendorDirectiveDiscovery(
                $app->make(ComposerReaderService::class),
                $app->make(DependencyResolverService::class),
                $app->make(FileSystemInterface::class),
                $app->make(DirectiveScannerInterface::class),
            );
        });
    }

    private function registerDiscoveryServices(): void
    {
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return DirectiveDiscoveryService::init(
                container: $app->make(ContainerInterface::class)
            );
        });
    }

    private function registerCoreServices(): void
    {
        $this->app->bind(FileSystemInterface::class, fn () => new FileSystemService);

        $this->app->singleton(Console::class, fn () => new Console);
        $this->app->singleton(DirectiveKernel::class, function ($app) {
            return DirectiveKernel::init(
                container: $app->make(ContainerInterface::class)
            );
        });
    }

    /**
     * Register the Laravel container adapter.
     */
    private function registerContainerAdapter(): void
    {
        $this->app->singleton(ContainerInterface::class, function ($app) {
            /** @var LaravelApplication $laravel */
            $laravel = $app;

            return new LaravelContainerAdapter($laravel);
        });
    }
}
