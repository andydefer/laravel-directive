<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Bootstrap;

/**
 * Centralized path resolution for the Directive CLI bootstrap.
 */
final class Paths
{
    public static function projectRoot(): string
    {
        return getcwd() ?: throw new \RuntimeException('Unable to determine current working directory');
    }

    public static function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function envFile(): string
    {
        return self::projectRoot().'/.env';
    }

    public static function projectAutoload(): string
    {
        return self::projectRoot().'/vendor/autoload.php';
    }

    public static function packageAutoload(): string
    {
        return self::packageRoot().'/vendor/autoload.php';
    }

    public static function laravelBootstrap(): string
    {
        return self::projectRoot().'/bootstrap/app.php';
    }

    public static function compiledProviders(): string
    {
        return self::projectRoot().'/storage/framework/providers.php';
    }

    public static function appConfig(): string
    {
        return self::projectRoot().'/config/app.php';
    }

    public static function hasEnvFile(): bool
    {
        return file_exists(self::envFile());
    }

    public static function hasProjectAutoload(): bool
    {
        return file_exists(self::projectAutoload());
    }

    public static function hasPackageAutoload(): bool
    {
        return file_exists(self::packageAutoload());
    }

    public static function hasLaravelBootstrap(): bool
    {
        return file_exists(self::laravelBootstrap());
    }

    public static function hasCompiledProviders(): bool
    {
        return file_exists(self::compiledProviders());
    }

    public static function hasAppConfig(): bool
    {
        return file_exists(self::appConfig());
    }
}
