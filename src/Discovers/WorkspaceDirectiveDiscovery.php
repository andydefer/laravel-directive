<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Discovers;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\DiscoverySourceInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;

/**
 * Discovery source for workspace directives.
 *
 * Scans the application's own directories for directive classes.
 * By default, it looks in `src/Directives` and `app/Directives`,
 * but can be configured to scan custom paths.
 */
final class WorkspaceDirectiveDiscovery implements DiscoverySourceInterface
{
    /**
     * Default directories to scan for directives within the application.
     *
     * @var array<int, string>
     */
    private const DEFAULT_PATHS = [
        'src/Directives',
        'app/Directives',
    ];

    /**
     * @var array<int, string>|null
     */
    private ?array $cache = null;

    /**
     * Custom paths to scan for directives.
     *
     * @var array<int, string>
     */
    private array $customPaths = [];

    /**
     * @param  FileSystemInterface  $fileSystem  The filesystem service
     * @param  DirectiveScannerInterface  $scanner  The directive scanner
     * @param  DirectiveConfigInterface|null  $config  The directive configuration
     * @param  int  $maxDepth  Maximum directory scanning depth
     */
    public function __construct(
        private readonly FileSystemInterface $fileSystem,
        private readonly DirectiveScannerInterface $scanner,
        private readonly ?DirectiveConfigInterface $config = null,
        private readonly int $maxDepth = 3,
    ) {}

    /**
     * Discovers directives from the application's workspace.
     *
     * @return array<int, string> List of fully qualified class names
     */
    public function discover(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = $this->doDiscover();

        return $this->cache;
    }

    /**
     * Adds a custom path to scan for directives.
     *
     * @param  string  $path  The path relative to the project root
     */
    public function addPath(string $path): self
    {
        if (! in_array($path, $this->customPaths, true)) {
            $this->customPaths[] = $path;
            $this->cache = null;
        }

        return $this;
    }

    /**
     * Adds multiple custom paths to scan for directives.
     *
     * @param  array<int, string>  $paths  The paths relative to the project root
     */
    public function addPaths(array $paths): self
    {
        foreach ($paths as $path) {
            $this->addPath($path);
        }

        return $this;
    }

    /**
     * Performs the actual discovery of directives.
     *
     * @return array<int, string> List of fully qualified class names
     */
    private function doDiscover(): array
    {
        $directives = [];
        $projectRoot = $this->getProjectRoot();

        // 1. Scanner les chemins par défaut et configurés
        $paths = $this->getScanPaths();

        foreach ($paths as $path) {
            $fullPath = $projectRoot.'/'.$path;

            if (! $this->fileSystem->isDirectory($fullPath)) {
                continue; // ✅ Pas d'erreur, on continue
            }

            $directives = array_merge(
                $directives,
                $this->scanner->scan($fullPath, $this->maxDepth)
            );
        }

        // 2. Scanner les custom_sources du projet
        $customDirectives = $this->scanWorkspaceCustomSources($projectRoot);
        $directives = array_merge($directives, $customDirectives);

        return $directives;
    }

    /**
     * Gets the paths to scan for directives.
     *
     * @return array<int, string> The paths relative to the project root
     */
    private function getScanPaths(): array
    {
        $paths = self::DEFAULT_PATHS;

        if ($this->config !== null) {
            $configPaths = $this->config->getDirectories();
            if (! empty($configPaths)) {
                $paths = $configPaths;
            }
        }

        return array_merge($paths, $this->customPaths);
    }

    /**
     * Scans custom sources from the workspace configuration.
     *
     * @param  string  $projectRoot  The project root directory
     * @return array<int, string> List of fully qualified class names
     */
    private function scanWorkspaceCustomSources(string $projectRoot): array
    {
        $directives = [];

        if ($this->config === null) {
            return $directives;
        }

        $customSources = $this->config->getCustomSources();

        foreach ($customSources as $source) {
            $fullPath = $projectRoot.'/'.ltrim($source, '/');

            if (! $this->fileSystem->isDirectory($fullPath)) {
                continue; // ✅ Pas d'erreur, on continue
            }

            $directives = array_merge(
                $directives,
                $this->scanner->scan($fullPath, $this->maxDepth)
            );
        }

        return $directives;
    }

    /**
     * Gets the project root directory.
     *
     * @return string The project root path
     *
     * @throws \RuntimeException If the current working directory cannot be determined
     */
    private function getProjectRoot(): string
    {
        $cwd = getcwd();

        if ($cwd === false) {
            throw new \RuntimeException('Unable to determine current working directory');
        }

        return $cwd;
    }
}
