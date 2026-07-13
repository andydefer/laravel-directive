<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\ConsoleWriter\Console\Contracts\ConsoleInterface;
use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Container\LaravelContainerAdapter;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\ContainerInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Contracts\Services\ComposerReaderInterface;
use AndyDefer\Directive\Contracts\Services\DependencyResolverInterface;
use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\SignatureParser\Contracts\ParserRegistryInterface;
use AndyDefer\SignatureParser\Contracts\SignatureParserInterface;
use AndyDefer\SignatureParser\SignatureParser;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ Enregistrer l'adaptateur Laravel
        $this->registerContainerAdapter();

        // ✅ Enregistrer tous les services
        $this->registerConfigs();
        $this->registerCoreServices();
        $this->registerParserComponents();
        $this->registerScannersAndDiscovers();
        $this->registerDiscoveryServices();
        $this->registerKernel();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/directive.php' => config_path('directive.php'),
        ], 'directive-config');
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

        $this->app->singleton(ConsoleInterface::class, function () {
            return new Console;
        });
    }

    /**
     * Register configuration services.
     */
    private function registerConfigs(): void
    {
        // ✅ ConfigRepository avec configuration par défaut
        $this->app->singleton(ConfigRepository::class, function ($app) {
            $basePath = $app->basePath();

            $config = [
                'directive' => [
                    'base_path' => $basePath,
                    'debug' => false,
                    'max_depth' => 3,
                    'custom_sources' => [],
                    'log_base_path' => $basePath.'/.directive',
                ],
            ];

            // Charger le fichier de config s'il existe
            $configFile = $basePath.'/config/directive.php';
            if (file_exists($configFile)) {
                $fileConfig = require $configFile;
                if (is_array($fileConfig)) {
                    $config['directive'] = array_merge($config['directive'], $fileConfig);
                }
            }

            return new ConfigRepository($config);
        });

        // ✅ DirectiveConfig
        $this->app->singleton(DirectiveConfigInterface::class, function ($app) {
            return new DirectiveConfig(
                $app->make(ConfigRepository::class)
            );
        });
    }

    /**
     * Register core services.
     */
    private function registerCoreServices(): void
    {
        $this->app->singleton(FileSystemInterface::class, function () {
            return new FileSystemService;
        });

        $this->app->singleton(Console::class, function () {
            return new Console;
        });

        $this->app->singleton(Parser::class, function () {
            return (new ParserFactory)->createForNewestSupportedVersion();
        });
    }

    /**
     * Register parser components.
     */
    private function registerParserComponents(): void
    {
        $this->app->singleton(SignatureParser::class, function () {
            return new SignatureParser;
        });

        $this->app->singleton(DirectiveParserInterface::class, function ($app) {
            return new DirectiveParserService($app->make(SignatureParser::class));
        });

        $this->app->singleton(ParserRegistryInterface::class, function ($app) {
            return $app->make(DirectiveParserInterface::class);
        });

        $this->app->singleton(SignatureParserInterface::class, function ($app) {
            return $app->make(DirectiveParserInterface::class);
        });
    }

    /**
     * Register scanners and discovries.
     */
    private function registerScannersAndDiscovers(): void
    {
        // ComposerReader
        $this->app->singleton(ComposerReaderService::class, function ($app) {
            return new ComposerReaderService(
                $app->make(DirectiveConfigInterface::class),
                $app->make(FileSystemInterface::class)
            );
        });
        $this->app->alias(ComposerReaderService::class, ComposerReaderInterface::class);

        // DependencyResolver
        $this->app->singleton(DependencyResolverService::class, function ($app) {
            return new DependencyResolverService(
                $app->make(ComposerReaderInterface::class),
                $app->make(FileSystemInterface::class)
            );
        });
        $this->app->alias(DependencyResolverService::class, DependencyResolverInterface::class);

        // DirectiveScanner
        $this->app->singleton(DirectiveScannerInterface::class, function ($app) {
            return new DirectiveClassScanner(
                $app->make(FileSystemInterface::class),
                $app->make(Parser::class)
            );
        });

        // Discovers
        $this->app->singleton(BuiltInDirectiveDiscovery::class, function () {
            return new BuiltInDirectiveDiscovery;
        });

        $this->app->singleton(WorkspaceDirectiveDiscovery::class, function ($app) {
            return new WorkspaceDirectiveDiscovery(
                $app->make(FileSystemInterface::class),
                $app->make(DirectiveScannerInterface::class),
                $app->make(DirectiveConfigInterface::class)
            );
        });

        $this->app->singleton(VendorDirectiveDiscovery::class, function ($app) {
            return new VendorDirectiveDiscovery(
                $app->make(ComposerReaderInterface::class),
                $app->make(DependencyResolverInterface::class),
                $app->make(FileSystemInterface::class),
                $app->make(DirectiveScannerInterface::class)
            );
        });
    }

    /**
     * Register discovery services.
     */
    private function registerDiscoveryServices(): void
    {
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return DirectiveDiscoveryService::init($app);
        });

        $this->app->singleton(ExecutionStatsLogger::class, function ($app) {
            $config = $app->make(DirectiveConfigInterface::class);
            $fileSystem = $app->make(FileSystemInterface::class);
            $console = $app->make(Console::class);

            return new ExecutionStatsLogger(
                $config,
                $fileSystem,
                $console
            );
        });
    }

    /**
     * Register the Directive Kernel.
     */
    private function registerKernel(): void
    {
        $this->app->singleton(DirectiveKernel::class, function ($app) {
            return DirectiveKernel::init($app);
        });
    }
}
