<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Discovers;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Throwable;

/**
 * Discovery source for workspace directives.
 *
 * Scans the application's own directories for directive classes.
 * By default, it looks in `src/Directives` and `app/Directives`,
 * but can be configured to scan custom paths.
 */
final class WorkspaceDirectiveDiscovery extends AbstractDiscovery
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
    ) {
        parent::__construct();
    }

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

        try {
            $this->cache = $this->doDiscover();
        } catch (Throwable $e) {
            $this->addProblem(
                'workspace_discovery',
                'Failed to discover workspace directives',
                $e->getMessage(),
                []
            );
            $this->cache = [];
        }

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

        try {
            $projectRoot = Paths::projectRoot();

            // 1. Scanner les chemins par défaut et configurés
            $paths = $this->getScanPaths();

            foreach ($paths as $path) {
                $fullPath = $projectRoot.'/'.$path;

                if (! $this->fileSystem->isDirectory($fullPath)) {
                    continue;
                }

                try {
                    $directives = array_merge(
                        $directives,
                        $this->scanner->scan($fullPath, $this->maxDepth)
                    );
                } catch (Throwable $e) {
                    $this->addProblem(
                        'scan_workspace_path',
                        'Failed to scan workspace path: '.$fullPath,
                        $e->getMessage(),
                        ['path' => $path, 'full_path' => $fullPath]
                    );
                }
            }

            // 2. Scanner les custom_sources du projet
            try {
                $customDirectives = $this->scanWorkspaceCustomSources($projectRoot);
                $directives = array_merge($directives, $customDirectives);
            } catch (Throwable $e) {
                $this->addProblem(
                    'scan_workspace_custom_sources',
                    'Failed to scan workspace custom sources',
                    $e->getMessage(),
                    []
                );
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'workspace_do_discover',
                'Failed to perform workspace discovery',
                $e->getMessage(),
                []
            );
        }

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
            try {
                $configPaths = $this->config->getDirectories();
                if (! empty($configPaths)) {
                    $paths = $configPaths;
                }
            } catch (Throwable $e) {
                $this->addProblem(
                    'get_config_paths',
                    'Failed to get directories from config',
                    $e->getMessage(),
                    []
                );
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

        try {
            $customSources = $this->config->getCustomSources();

            foreach ($customSources as $source) {
                $fullPath = $projectRoot.'/'.ltrim($source, '/');

                if (! $this->fileSystem->isDirectory($fullPath)) {
                    $this->addProblem(
                        'workspace_custom_source_not_directory',
                        'Workspace custom source path is not a directory: '.$fullPath,
                        'Path does not exist or is not a directory',
                        ['source' => $source, 'full_path' => $fullPath]
                    );

                    continue;
                }

                try {
                    $directives = array_merge(
                        $directives,
                        $this->scanner->scan($fullPath, $this->maxDepth)
                    );
                } catch (Throwable $e) {
                    $this->addProblem(
                        'scan_workspace_custom_source',
                        'Failed to scan workspace custom source: '.$fullPath,
                        $e->getMessage(),
                        ['source' => $source, 'full_path' => $fullPath]
                    );
                }
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'get_workspace_custom_sources',
                'Failed to get custom sources from config',
                $e->getMessage(),
                []
            );
        }

        return $directives;
    }
}
