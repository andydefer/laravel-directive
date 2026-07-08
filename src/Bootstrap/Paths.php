<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Bootstrap;

/**
 * Centralized path resolution for the Directive CLI bootstrap.
 *
 * This class provides a single source of truth for all file system paths
 * used during the bootstrap process. All paths are resolved relative to
 * the project root directory.
 */
final class Paths
{
    /**
     * The default file name for environment configuration.
     */
    private const ENV_FILE = '.env';

    /**
     * The default directory name for Composer dependencies.
     */
    private const VENDOR_DIR = 'vendor';

    /**
     * The default bootstrap file name for Laravel applications.
     */
    private const LARAVEL_BOOTSTRAP_FILE = 'bootstrap/app.php';

    /**
     * The default path for compiled service providers.
     */
    private const COMPILED_PROVIDERS_FILE = 'storage/framework/providers.php';

    /**
     * The default application configuration file.
     */
    private const APP_CONFIG_FILE = 'config/app.php';

    /**
     * The Composer autoloader file name.
     */
    private const AUTOLOAD_FILE = 'autoload.php';

    /**
     * Gets the absolute path to the project root directory.
     *
     * @return string The project root path
     *
     * @throws \RuntimeException If the current working directory cannot be determined
     */
    public static function projectRoot(): string
    {
        $cwd = getcwd();

        if ($cwd === false) {
            throw new \RuntimeException('Unable to determine current working directory');
        }

        return $cwd;
    }

    /**
     * Gets the absolute path to the package root directory.
     *
     * @return string The package root path
     */
    public static function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Gets the absolute path to the environment file.
     *
     * @return string The .env file path
     */
    public static function envFile(): string
    {
        return self::projectRoot().'/'.self::ENV_FILE;
    }

    /**
     * Gets the absolute path to the project's Composer autoloader.
     *
     * @return string The project autoloader path
     */
    public static function projectAutoload(): string
    {
        return self::joinPaths(self::projectRoot(), self::VENDOR_DIR, self::AUTOLOAD_FILE);
    }

    /**
     * Gets the absolute path to the package's Composer autoloader.
     *
     * @return string The package autoloader path
     */
    public static function packageAutoload(): string
    {
        return self::joinPaths(self::packageRoot(), self::VENDOR_DIR, self::AUTOLOAD_FILE);
    }

    /**
     * Gets the absolute path to the Laravel bootstrap file.
     *
     * @return string The bootstrap file path
     */
    public static function laravelBootstrap(): string
    {
        return self::projectRoot().'/'.self::LARAVEL_BOOTSTRAP_FILE;
    }

    /**
     * Gets the absolute path to the compiled service providers file.
     *
     * @return string The compiled providers file path
     */
    public static function compiledProviders(): string
    {
        return self::projectRoot().'/'.self::COMPILED_PROVIDERS_FILE;
    }

    /**
     * Gets the absolute path to the application configuration file.
     *
     * @return string The app configuration file path
     */
    public static function appConfig(): string
    {
        return self::projectRoot().'/'.self::APP_CONFIG_FILE;
    }

    /**
     * Checks if the environment file exists.
     *
     * @return bool True if the .env file exists, false otherwise
     */
    public static function hasEnvFile(): bool
    {
        return file_exists(self::envFile());
    }

    /**
     * Checks if the project's Composer autoloader exists.
     *
     * @return bool True if the autoloader exists, false otherwise
     */
    public static function hasProjectAutoload(): bool
    {
        return file_exists(self::projectAutoload());
    }

    /**
     * Checks if the package's Composer autoloader exists.
     *
     * @return bool True if the autoloader exists, false otherwise
     */
    public static function hasPackageAutoload(): bool
    {
        return file_exists(self::packageAutoload());
    }

    /**
     * Checks if the Laravel bootstrap file exists.
     *
     * @return bool True if the bootstrap file exists, false otherwise
     */
    public static function hasLaravelBootstrap(): bool
    {
        return file_exists(self::laravelBootstrap());
    }

    /**
     * Checks if the compiled service providers file exists.
     *
     * @return bool True if the compiled providers file exists, false otherwise
     */
    public static function hasCompiledProviders(): bool
    {
        return file_exists(self::compiledProviders());
    }

    /**
     * Checks if the application configuration file exists.
     *
     * @return bool True if the app config file exists, false otherwise
     */
    public static function hasAppConfig(): bool
    {
        return file_exists(self::appConfig());
    }

    /**
     * Joins path segments using the appropriate directory separator.
     *
     * @param  string  ...$segments  The path segments to join
     * @return string The joined path
     */
    private static function joinPaths(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
