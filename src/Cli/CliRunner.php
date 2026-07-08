<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Cli;

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
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\SignatureParser\SignatureParser;
use Illuminate\Foundation\Application;
use PhpParser\ParserFactory;

/**
 * CLI Runner for the Directive system.
 *
 * This class is responsible for building the dependency graph and executing
 * the CLI kernel with the provided arguments.
 */
final class CliRunner
{
    /**
     * @param  Application  $application  The Laravel application instance
     */
    public function __construct(
        private readonly Application $application,
    ) {}

    /**
     * Executes the CLI runner with the given arguments.
     *
     * @param  array<int, string>  $argv  The CLI arguments
     * @return int The exit code
     */
    public function run(array $argv): int
    {
        $kernel = $this->buildKernel();

        return $kernel->run($argv)->value;
    }

    /**
     * Builds and configures the Directive kernel with all dependencies.
     *
     * @return DirectiveKernel The fully configured kernel
     */
    private function buildKernel(): DirectiveKernel
    {
        $fileSystem = $this->createFileSystem();
        $scanner = $this->createScanner($fileSystem);

        $config = $this->getConfig();
        $parser = $this->createParser();

        $discovery = $this->createDiscoveryService(
            $fileSystem,
            $scanner,
            $config,
            $parser
        );

        return new DirectiveKernel(
            $this->application,
            $discovery
        );
    }

    /**
     * Creates the filesystem service.
     *
     * @return FileSystemInterface The filesystem service
     */
    private function createFileSystem(): FileSystemInterface
    {
        return new FileSystemService;
    }

    /**
     * Creates the directive class scanner.
     *
     * @param  FileSystemInterface  $fileSystem  The filesystem service
     * @return DirectiveClassScanner The scanner instance
     */
    private function createScanner(FileSystemInterface $fileSystem): DirectiveClassScanner
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        return new DirectiveClassScanner($fileSystem, $parser);
    }

    /**
     * Retrieves the directive configuration.
     *
     * @return DirectiveConfigInterface The configuration instance
     */
    private function getConfig(): DirectiveConfigInterface
    {
        return $this->application->make(DirectiveConfigInterface::class);
    }

    /**
     * Creates the directive parser service.
     *
     * @return DirectiveParserService The parser instance
     */
    private function createParser(): DirectiveParserService
    {
        return new DirectiveParserService(new SignatureParser);
    }

    /**
     * Creates the directive discovery service with all sources.
     *
     * @param  FileSystemInterface  $fileSystem  The filesystem service
     * @param  DirectiveClassScanner  $scanner  The class scanner
     * @param  DirectiveConfigInterface  $config  The configuration
     * @param  DirectiveParserService  $parser  The parser service
     * @return DirectiveDiscoveryService The discovery service
     */
    private function createDiscoveryService(
        FileSystemInterface $fileSystem,
        DirectiveClassScanner $scanner,
        DirectiveConfigInterface $config,
        DirectiveParserService $parser
    ): DirectiveDiscoveryService {
        $sources = $this->createDiscoverySources($fileSystem, $scanner, $config);

        return new DirectiveDiscoveryService(
            $sources['builtIn'],
            $sources['workspace'],
            $sources['vendor'],
            $parser,
            $scanner,
            $fileSystem,
            $config
        );
    }

    /**
     * Creates all discovery sources.
     *
     * @param  FileSystemInterface  $fileSystem  The filesystem service
     * @param  DirectiveClassScanner  $scanner  The class scanner
     * @param  DirectiveConfigInterface  $config  The configuration
     * @return array<string, mixed> The discovery sources
     */
    private function createDiscoverySources(
        FileSystemInterface $fileSystem,
        DirectiveClassScanner $scanner,
        DirectiveConfigInterface $config
    ): array {
        $composerReader = new ComposerReaderService($config, $fileSystem);
        $dependencyResolver = new DependencyResolverService($composerReader, $fileSystem);

        return [
            'builtIn' => new BuiltInDirectiveDiscovery,
            'workspace' => new WorkspaceDirectiveDiscovery($fileSystem, $scanner),
            'vendor' => new VendorDirectiveDiscovery(
                $composerReader,
                $dependencyResolver,
                $fileSystem,
                $scanner
            ),
        ];
    }
}
