<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Bootstrap;

use AndyDefer\Directive\Enums\ApplicationType;
use AndyDefer\Directive\Helpers\Paths;

/**
 * Detects the execution environment and application type.
 *
 * This utility determines whether the code is running inside a Laravel web
 * application, a standalone package/library, or an unknown context. It uses
 * multiple detection strategies including file system checks, Composer
 * configuration analysis, and environment variables.
 *
 * The detection results are used by the bootstrap process to select the
 * appropriate application factory and configuration strategy.
 */
final class EnvironmentDetector
{
    /**
     * Detects if the current execution is inside a package/library context.
     *
     * Uses multiple detection strategies:
     * - Presence of vendor directory at project root
     * - Composer package type (library or package)
     * - Composer package name containing a slash
     * - Current directory being inside vendor
     *
     * @return bool True if running in a package/library context
     */
    public static function isPackage(): bool
    {
        $rootPath = Paths::projectRoot();

        // Check for vendor directory at project root
        if (! is_dir($rootPath.'/vendor')) {
            return false;
        }

        // Check Composer configuration for package indicators
        if (self::isComposerPackage($rootPath)) {
            return true;
        }

        // Check if we are inside the vendor directory
        if (str_contains(__DIR__, '/vendor/')) {
            return true;
        }

        return false;
    }

    /**
     * Detects if the current execution is inside a Laravel web application.
     *
     * Uses multiple detection strategies:
     * - Presence of Laravel-specific files (config/app.php, bootstrap/app.php, public/)
     * - Composer project type
     * - Laravel framework dependency in Composer
     * - Presence of .env file
     *
     * @return bool True if running in a Laravel web application
     */
    public static function isWebApplication(): bool
    {
        $rootPath = Paths::projectRoot();

        // Check for Laravel application structure
        if (self::hasLaravelStructure($rootPath)) {
            return true;
        }

        // Check Composer configuration
        if (self::isLaravelComposerProject($rootPath)) {
            return true;
        }

        // Check for .env file (common in Laravel applications)
        if (file_exists($rootPath.'/.env')) {
            return true;
        }

        return false;
    }

    /**
     * Alias for isPackage().
     *
     * @return bool True if running in a library context
     */
    public static function isLibrary(): bool
    {
        return self::isPackage();
    }

    /**
     * Returns the application type as a string.
     *
     * @return string One of: 'web_application', 'package', 'unknown'
     */
    public static function getApplicationType(): string
    {
        if (self::isWebApplication()) {
            return 'web_application';
        }

        if (self::isPackage()) {
            return 'package';
        }

        return 'unknown';
    }

    /**
     * Returns the application type as an enum.
     *
     * @return ApplicationType The detected application type
     */
    public static function getApplicationTypeEnum(): ApplicationType
    {
        if (self::isWebApplication()) {
            return ApplicationType::WEB_APPLICATION;
        }

        if (self::isPackage()) {
            return ApplicationType::PACKAGE;
        }

        return ApplicationType::UNKNOWN;
    }

    /**
     * Checks if the current execution is in a test environment.
     *
     * Detects test environment by:
     * - PHPUnit being installed
     * - PHPUNIT_RUNNING environment variable
     * - APP_ENV environment variable set to 'testing'
     *
     * @return bool True if running in a test environment
     */
    public static function isTestEnvironment(): bool
    {
        return defined('PHPUNIT_COMPOSER_INSTALL')
            || getenv('PHPUNIT_RUNNING') === 'true'
            || getenv('APP_ENV') === 'testing';
    }

    /**
     * Checks if the current execution is in a development environment.
     *
     * Detects development environment by:
     * - APP_ENV set to 'local' or 'development'
     * - APP_DEBUG set to 'true'
     *
     * @return bool True if running in a development environment
     */
    public static function isDevelopmentEnvironment(): bool
    {
        $env = getenv('APP_ENV');

        return $env === 'local'
            || $env === 'development'
            || getenv('APP_DEBUG') === 'true';
    }

    /**
     * Checks if the Composer configuration indicates a package.
     *
     * @param  string  $rootPath  The project root path
     * @return bool True if Composer indicates a package
     */
    private static function isComposerPackage(string $rootPath): bool
    {
        $composerPath = $rootPath.'/composer.json';

        if (! file_exists($composerPath)) {
            return false;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        if (! is_array($composer)) {
            return false;
        }

        // Check if type is library or package
        $type = $composer['type'] ?? null;
        if ($type === 'library' || $type === 'package') {
            return true;
        }

        // Check if package name contains a slash (indicates a package)
        if (isset($composer['name']) && str_contains($composer['name'], '/')) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the directory has Laravel application structure.
     *
     * @param  string  $rootPath  The project root path
     * @return bool True if Laravel structure is present
     */
    private static function hasLaravelStructure(string $rootPath): bool
    {
        $hasConfig = file_exists($rootPath.'/config/app.php');
        $hasBootstrap = file_exists($rootPath.'/bootstrap/app.php');
        $hasPublic = is_dir($rootPath.'/public');

        return $hasConfig && $hasBootstrap && $hasPublic;
    }

    /**
     * Checks if the Composer configuration indicates a Laravel project.
     *
     * @param  string  $rootPath  The project root path
     * @return bool True if Composer indicates a Laravel project
     */
    private static function isLaravelComposerProject(string $rootPath): bool
    {
        $composerPath = $rootPath.'/composer.json';

        if (! file_exists($composerPath)) {
            return false;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        if (! is_array($composer)) {
            return false;
        }

        // Check if type is project
        if (($composer['type'] ?? null) === 'project') {
            return true;
        }

        // Check for Laravel framework dependency
        $requires = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        return isset($requires['laravel/framework']);
    }
}
