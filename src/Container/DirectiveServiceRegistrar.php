<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Container;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\ContainerInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\LaravelJsonl\Strategies\TemporalPathStrategy;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\SignatureParser\Contracts\ParserRegistryInterface;
use AndyDefer\SignatureParser\Contracts\SignatureParserInterface;
use AndyDefer\SignatureParser\SignatureParser;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Service registrar for Directive components.
 */
final class DirectiveServiceRegistrar
{
    /**
     * @param  Container|LaravelContainerAdapter  $container  The container to register services into
     */
    public function __construct(
        private readonly object $container,
    ) {}

    public function registerAll(): void
    {
        $this->registerConfigs();
        $this->registerCoreServices();
        $this->registerParserComponents();
        $this->registerScannersAndDiscovers();
        $this->registerDiscoveryServices();
        $this->registerKernel();
    }

    private function registerConfigs(): void
    {
        // ✅ Enregistrer ArrayConfigRepository comme ConfigRepository
        $this->container->singleton(ConfigRepository::class, function ($container) {
            $config = [
                'directive' => [
                    'base_path' => $container->basePath(),
                    'debug' => false,
                    'max_depth' => 3,
                    'custom_sources' => [],
                    'log_base_path' => $container->basePath().'/.directive',
                ],
            ];

            // Charger le fichier de config s'il existe
            $configFile = $container->basePath().'/config/directive.php';
            if (file_exists($configFile)) {
                $fileConfig = require $configFile;
                if (is_array($fileConfig)) {
                    $config['directive'] = array_merge($config['directive'], $fileConfig);
                }
            }

            return new ArrayConfigRepository($config);
        });

        // ✅ Enregistrer DirectiveConfig avec le ConfigRepository
        $this->container->singleton(DirectiveConfigInterface::class, function ($container) {
            return new DirectiveConfig(
                $container->make(ConfigRepository::class)
            );
        });
    }

    private function registerCoreServices(): void
    {
        $this->container->singleton(FileSystemInterface::class, function () {
            return new FileSystemService;
        });

        $this->container->singleton(Console::class, function () {
            return new Console;
        });

        $this->container->singleton(Parser::class, function () {
            return (new ParserFactory)->createForNewestSupportedVersion();
        });
    }

    private function registerParserComponents(): void
    {
        $this->container->singleton(SignatureParser::class, function () {
            return new SignatureParser;
        });

        $this->container->singleton(DirectiveParserInterface::class, function ($container) {
            return new DirectiveParserService($container->make(SignatureParser::class));
        });

        $this->container->singleton(ParserRegistryInterface::class, DirectiveParserService::class);
        $this->container->singleton(SignatureParserInterface::class, DirectiveParserService::class);
    }

    private function registerScannersAndDiscovers(): void
    {
        $this->container->singleton(ComposerReaderService::class, function ($container) {
            return new ComposerReaderService(
                $container->make(DirectiveConfigInterface::class),
                $container->make(FileSystemInterface::class)
            );
        });

        $this->container->singleton(DependencyResolverService::class, function ($container) {
            return new DependencyResolverService(
                $container->make(ComposerReaderService::class),
                $container->make(FileSystemInterface::class)
            );
        });

        $this->container->singleton(DirectiveScannerInterface::class, function ($container) {
            return new DirectiveClassScanner(
                $container->make(FileSystemInterface::class),
                $container->make(Parser::class)
            );
        });

        $this->container->singleton(BuiltInDirectiveDiscovery::class, function () {
            return new BuiltInDirectiveDiscovery;
        });

        $this->container->singleton(WorkspaceDirectiveDiscovery::class, function ($container) {
            return new WorkspaceDirectiveDiscovery(
                $container->make(FileSystemInterface::class),
                $container->make(DirectiveScannerInterface::class)
            );
        });

        $this->container->singleton(VendorDirectiveDiscovery::class, function ($container) {
            return new VendorDirectiveDiscovery(
                $container->make(ComposerReaderService::class),
                $container->make(DependencyResolverService::class),
                $container->make(FileSystemInterface::class),
                $container->make(DirectiveScannerInterface::class)
            );
        });
    }

    private function registerDiscoveryServices(): void
    {
        $this->container->singleton(DirectiveDiscoveryService::class, function ($container) {
            return DirectiveDiscoveryService::init($container->make(ContainerInterface::class));
        });

        $this->container->singleton(ExecutionStatsLogger::class, function ($container) {
            $config = $container->make(DirectiveConfigInterface::class);
            $fileSystem = $container->make(FileSystemInterface::class);
            $console = $container->make(Console::class);

            $strategy = new TemporalPathStrategy($config->basePath());

            return new ExecutionStatsLogger(
                $config,
                $fileSystem,
                $console
            );
        });
    }

    private function registerKernel(): void
    {
        $this->container->singleton(DirectiveKernel::class, function ($container) {
            return DirectiveKernel::init($container->make(ContainerInterface::class));
        });
    }
}
