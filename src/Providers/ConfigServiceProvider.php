<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Providers;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Bootstraps the application configuration repository.
 *
 * This provider ensures a {@see ConfigRepository} instance is always available
 * in the Laravel service container. When Laravel's native configuration loader
 * has already registered the `config` binding, this provider simply exposes
 * the existing instance under the contract alias. Otherwise, it scans the
 * conventional configuration directories and aggregates their contents into
 * a fresh repository.
 */
final class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Maximum allowed recursion depth when scanning configuration directories.
     *
     * Acts as a safety net against pathological filesystem structures
     * (symlink loops, extremely deep trees) that could otherwise cause
     * unbounded traversal.
     */
    private const MAX_RECURSION_DEPTH = 20;

    /**
     * Conventional directories scanned for configuration files, relative to
     * the application base path.
     *
     * @var list<string>
     */
    private const CONFIG_DIRECTORIES = [
        '/config',
        '/configs',
        '/src/config',
        '/src/configs',
        '/resources/config',
    ];

    /**
     * Current recursion depth during a directory scan.
     */
    private static int $recursionDepth = 0;

    /**
     * Canonical paths already visited during the current scan, used to detect
     * cycles (symlinks, overlapping roots) and avoid loading a directory twice.
     *
     * @var array<string, true>
     */
    private static array $visitedPaths = [];

    /**
     * Register the configuration repository binding.
     *
     * If Laravel has already bootstrapped a `config` binding, this method only
     * aliases it to the {@see ConfigRepository} contract. Otherwise, a new
     * repository is built from the configuration files discovered on disk.
     */
    public function register(): void
    {
        if ($this->app->bound('config')) {
            $this->app->alias('config', ConfigRepository::class);

            return;
        }

        $this->app->singleton(ConfigRepository::class, function ($app): Repository {
            $config = [];

            foreach (self::CONFIG_DIRECTORIES as $directory) {
                $path = $app->basePath().$directory;

                if (! is_dir($path)) {
                    continue;
                }

                $this->loadConfigFiles($path, $config);
            }

            return new Repository($config);
        });

        $this->app->alias(ConfigRepository::class, 'config');
    }

    /**
     * Recursively load every PHP configuration file from the given directory
     * into the configuration array.
     *
     * Files are keyed by their basename (without extension). When a key is
     * already present and both values are arrays, the new content is merged
     * into the existing one; otherwise the new value replaces the old one.
     *
     * Directory traversal is guarded against infinite recursion through a
     * maximum depth and a canonical-path cycle detector.
     *
     * @param  string  $directory  Absolute path to the directory being scanned.
     * @param  array<string, mixed>  $config  Configuration accumulator (by reference).
     */
    private function loadConfigFiles(string $directory, array &$config): void
    {
        if (! $this->enterDirectory($directory)) {
            return;
        }

        try {
            $files = scandir($directory);

            if ($files === false) {
                return;
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $path = $directory.DIRECTORY_SEPARATOR.$file;

                if (is_dir($path)) {
                    $this->loadConfigFiles($path, $config);

                    continue;
                }

                $this->loadConfigFile($path, $config);
            }
        } finally {
            self::$recursionDepth--;
        }
    }

    /**
     * Attempt to enter a directory, enforcing recursion and cycle guards.
     *
     * @param  string  $directory  Directory about to be scanned.
     * @return bool True when the scan may proceed, false when it must be skipped.
     */
    private function enterDirectory(string $directory): bool
    {
        if (self::$recursionDepth >= self::MAX_RECURSION_DEPTH) {
            return false;
        }

        $realPath = realpath($directory);

        if ($realPath === false || isset(self::$visitedPaths[$realPath])) {
            return false;
        }

        self::$visitedPaths[$realPath] = true;
        self::$recursionDepth++;

        return true;
    }

    /**
     * Load a single PHP configuration file and merge its contents into the
     * configuration array.
     *
     * Non-PHP files, non-array returns, and files that throw during inclusion
     * are silently ignored so that a single faulty file cannot break the
     * entire bootstrap process.
     *
     * @param  string  $path  Absolute path to the file.
     * @param  array<string, mixed>  $config  Configuration accumulator (by reference).
     */
    private function loadConfigFile(string $path, array &$config): void
    {
        if (! is_file($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
            return;
        }

        try {
            $content = require $path;
        } catch (Throwable) {
            return;
        }

        if (! is_array($content)) {
            return;
        }

        $key = pathinfo($path, PATHINFO_FILENAME);

        if (array_key_exists($key, $config) && is_array($config[$key])) {
            $config[$key] = array_merge($config[$key], $content);

            return;
        }

        $config[$key] = $content;
    }
}
