<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Container;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
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
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\SignatureParser\Contracts\ParserRegistryInterface;
use AndyDefer\SignatureParser\Contracts\SignatureParserInterface;
use AndyDefer\SignatureParser\SignatureParser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Complete Directive container with all services pre-registered.
 *
 * Use this for standalone applications or when you want a
 * ready-to-use container without Laravel.
 */
final class DirectiveContainer extends Container
{
    public function __construct(string $basePath = __DIR__)
    {
        parent::__construct($basePath);

        $this->registerConfigs();
        $this->registerCoreServices();
        $this->registerParserComponents();
        $this->registerScannersAndDiscovers();
        $this->registerDiscoveryServices();
        $this->registerKernel();
    }

    /**
     * Register core services.
     */
    private function registerCoreServices(): void
    {
        $this->singleton(Console::class, new Console);
        $this->singleton(FileSystemInterface::class, new FileSystemService);
        $this->singleton(Parser::class, function () {
            return (new ParserFactory)->createForNewestSupportedVersion();
        });
    }

    /**
     * Register configuration services.
     */
    private function registerConfigs(): void
    {
        $this->singleton(DirectiveConfigInterface::class, DirectiveConfig::class);
    }

    /**
     * Register parser components.
     */
    private function registerParserComponents(): void
    {
        $this->singleton(SignatureParser::class, new SignatureParser);
        $this->singleton(DirectiveParserInterface::class, function (Container $c) {
            return new DirectiveParserService($c->make(SignatureParser::class));
        });
        $this->singleton(ParserRegistryInterface::class, DirectiveParserService::class);
        $this->singleton(SignatureParserInterface::class, DirectiveParserService::class);
    }

    /**
     * Register scanners and discovers.
     */
    private function registerScannersAndDiscovers(): void
    {
        $this->singleton(DirectiveScannerInterface::class, function (Container $c) {
            return new DirectiveClassScanner(
                $c->make(FileSystemInterface::class),
                $c->make(Parser::class)
            );
        });

        $this->singleton(DirectiveClassScanner::class, function (Container $c) {
            return new DirectiveClassScanner(
                $c->make(FileSystemInterface::class),
                $c->make(Parser::class)
            );
        });

        $this->bind(ComposerReaderService::class, function (Container $c) {
            return new ComposerReaderService(
                $c->make(DirectiveConfigInterface::class),
                $c->make(FileSystemInterface::class)
            );
        });

        $this->bind(DependencyResolverService::class, function (Container $c) {
            return new DependencyResolverService(
                $c->make(ComposerReaderService::class),
                $c->make(FileSystemInterface::class)
            );
        });

        $this->singleton(BuiltInDirectiveDiscovery::class, new BuiltInDirectiveDiscovery);

        $this->singleton(WorkspaceDirectiveDiscovery::class, function (Container $c) {
            return new WorkspaceDirectiveDiscovery(
                $c->make(FileSystemInterface::class),
                $c->make(DirectiveScannerInterface::class)
            );
        });

        $this->singleton(VendorDirectiveDiscovery::class, function (Container $c) {
            return new VendorDirectiveDiscovery(
                $c->make(ComposerReaderService::class),
                $c->make(DependencyResolverService::class),
                $c->make(FileSystemInterface::class),
                $c->make(DirectiveScannerInterface::class)
            );
        });
    }

    /**
     * Register discovery services.
     */
    private function registerDiscoveryServices(): void
    {
        $this->singleton(DirectiveDiscoveryService::class, function (Container $c) {
            return DirectiveDiscoveryService::init(
                container: $c
            );
        });
    }

    /**
     * Register the kernel.
     */
    private function registerKernel(): void
    {
        $this->singleton(DirectiveKernel::class, function (Container $c) {
            return DirectiveKernel::init(
                container: $c
            );
        });
    }

    /**
     * Create a new DirectiveContainer.
     */
    public static function create(string $basePath = __DIR__): self
    {
        return new self($basePath);
    }
}
