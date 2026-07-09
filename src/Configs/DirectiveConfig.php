<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Configuration manager for the Directive system.
 *
 * This class provides a typed interface to the Laravel configuration repository,
 * ensuring type safety and providing default values for all configuration options.
 */
final class DirectiveConfig implements DirectiveConfigInterface
{
    /**
     * The configuration key prefix used in the Laravel config repository.
     */
    private const CONFIG_KEY = 'directive';

    /**
     * Default directories to scan for directives.
     *
     * @var array<int, string>
     */
    private const DEFAULT_DIRECTORIES = [
        'src/Directives',
        'app/Directives',
    ];

    /**
     * Default reserved signatures that cannot be used as directive names.
     *
     * @var array<int, string>
     */
    private const DEFAULT_RESERVED_SIGNATURES = [
        '-h',
        '--help',
        '-v',
        '--version',
        '-l',
        '--list',
        'help',
        'list',
        'version',
    ];

    /**
     * @param  ConfigRepository  $config  The Laravel configuration repository
     */
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * Gets the base path of the application.
     *
     * @return string The base path, or current working directory if not configured
     *
     * @throws \RuntimeException If the current working directory cannot be determined
     */
    public function basePath(): string
    {
        $path = $this->config->get(self::CONFIG_KEY.'.base_path');

        if (is_string($path) && $path !== '') {
            return $path;
        }

        $cwd = getcwd();

        if ($cwd === false) {
            throw new \RuntimeException('Unable to determine current working directory');
        }

        return $cwd;
    }

    /**
     * Gets the directories to scan for directives.
     *
     * @return array<int, string> The list of directories
     */
    public function getDirectories(): array
    {
        $directories = $this->config->get(
            self::CONFIG_KEY.'.directories',
            self::DEFAULT_DIRECTORIES
        );

        return $this->ensureStringArray($directories, self::DEFAULT_DIRECTORIES);
    }

    /**
     * Gets the reserved signatures that cannot be used as directive names.
     *
     * @return array<int, string> The list of reserved signatures
     */
    public function getReservedSignatures(): array
    {
        $reserved = $this->config->get(
            self::CONFIG_KEY.'.reserved',
            self::DEFAULT_RESERVED_SIGNATURES
        );

        return $this->ensureStringArray($reserved, self::DEFAULT_RESERVED_SIGNATURES);
    }

    /**
     * Sets the reserved signatures.
     *
     * @param  array<int, string>  $signatures  The list of reserved signatures
     */
    public function setReservedSignatures(array $signatures): void
    {
        $this->config->set(self::CONFIG_KEY.'.reserved', $signatures);
    }

    /**
     * Gets the vendor directory path.
     *
     * @return string The vendor directory path
     */
    public function getVendorDir(): string
    {
        return $this->basePath().'/vendor';
    }

    /**
     * Gets the composer.json file path.
     *
     * @return string The composer.json path
     */
    public function getComposerPath(): string
    {
        return $this->basePath().'/composer.json';
    }

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool True if debug mode is enabled, false otherwise
     */
    public function isDebug(): bool
    {
        return (bool) $this->config->get(self::CONFIG_KEY.'.debug', false);
    }

    /**
     * Gets the maximum directory scanning depth.
     *
     * @return int The maximum depth
     */
    public function getMaxDepth(): int
    {
        return (int) $this->config->get(self::CONFIG_KEY.'.max_depth', 3);
    }

    /**
     * Gets the custom sources to scan for directives.
     *
     * @return array<int, string> The list of custom source paths
     */
    public function getCustomSources(): array
    {
        $sources = $this->config->get(self::CONFIG_KEY.'.custom_sources', []);

        return $this->ensureStringArray($sources, []);
    }

    /**
     * Gets the log base path for execution statistics.
     *
     * @return string The log base path
     */
    public function getLogBasePath(): string
    {
        $path = $this->config->get(self::CONFIG_KEY.'.log_base_path');

        if (is_string($path) && $path !== '') {
            return $path;
        }

        return $this->basePath().'/.directive';
    }

    /**
     * Ensures a value is an array of strings.
     *
     * @param  mixed  $value  The value to validate
     * @param  array<int, string>  $default  The default value if validation fails
     * @return array<int, string> The validated array
     */
    private function ensureStringArray(mixed $value, array $default = []): array
    {
        if (! is_array($value)) {
            return $default;
        }

        $filtered = array_filter($value, 'is_string');

        return array_values($filtered);
    }
}
