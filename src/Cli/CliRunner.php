<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Cli;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\SignatureParser\SignatureParser;
use Illuminate\Foundation\Application;

final class CliRunner
{
    public function __construct(
        private readonly Application $application,
    ) {}

    public function run(array $argv): int
    {
        $kernel = $this->buildKernel();

        return $kernel->run($argv)->value;
    }

    private function buildKernel(): DirectiveKernel
    {
        $fileSystem = new FileSystemService;
        $scanner = new DirectiveClassScanner($fileSystem);
        $console = new Console;

        // Config
        $config = $this->application->make(DirectiveConfigInterface::class);

        // Discovers
        $builtInSource = new BuiltInDirectiveDiscovery;
        $workspaceSource = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);

        $composerReader = new ComposerReaderService($config, $fileSystem);
        $dependencyResolver = new DependencyResolverService($composerReader, $fileSystem);
        $vendorSource = new VendorDirectiveDiscovery(
            $composerReader,
            $dependencyResolver,
            $fileSystem,
            $scanner
        );

        $parser = new DirectiveParserService(new SignatureParser);

        // Discovery Service
        $discovery = new DirectiveDiscoveryService(
            $builtInSource,
            $workspaceSource,
            $vendorSource,
            $parser,
            $scanner,
            $fileSystem,
            $config,
        );

        // Kernel (plus besoin de Hydrator)
        return new DirectiveKernel(
            $this->application,
            $discovery,
        );
    }
}
