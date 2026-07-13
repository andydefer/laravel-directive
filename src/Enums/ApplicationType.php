<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

use AndyDefer\Directive\Factories\ExternalApplicationFactory;
use AndyDefer\Directive\Factories\InternalApplicationFactory;

/**
 * Application types supported by the Directive bootstrap process.
 *
 * These types determine which application factory is used to create
 * the Laravel container when bootstrapping the Directive kernel.
 */
enum ApplicationType: string
{
    /**
     * Internal Laravel application (full Laravel framework).
     *
     * Used when running within a Laravel project with full framework
     * capabilities, service providers, and configuration.
     */
    case INTERNAL = 'internal';

    /**
     * External standalone application (minimal Laravel container).
     *
     * Used when running as a standalone package without a full Laravel
     * installation. Provides a lightweight container with essential services.
     */
    case EXTERNAL = 'external';

    /**
     * Laravel web application with full HTTP kernel.
     *
     * Detected when the environment has typical Laravel web structure
     * (config/app.php, bootstrap/app.php, public/ directory).
     */
    case WEB_APPLICATION = 'web_application';

    /**
     * Package/library context without Laravel framework.
     *
     * Detected when running as a Composer package/library without
     * a Laravel application.
     */
    case PACKAGE = 'package';

    /**
     * Unknown or undetected application type.
     *
     * Fallback when neither web application nor package context
     * can be reliably detected.
     */
    case UNKNOWN = 'unknown';

    /**
     * Check if this type is a Laravel-based application.
     */
    public function isLaravel(): bool
    {
        return $this === self::INTERNAL || $this === self::WEB_APPLICATION;
    }

    /**
     * Check if this type is a standalone/package context.
     */
    public function isStandalone(): bool
    {
        return $this === self::EXTERNAL || $this === self::PACKAGE;
    }

    /**
     * Get the display name for this application type.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::INTERNAL => 'Internal Laravel Application',
            self::EXTERNAL => 'External Standalone Application',
            self::WEB_APPLICATION => 'Web Application (Laravel)',
            self::PACKAGE => 'Package / Library Context',
            self::UNKNOWN => 'Unknown',
        };
    }

    /**
     * Get the factory class name for this application type.
     *
     * @return class-string
     */
    public function getFactoryClass(): string
    {
        return match ($this) {
            self::INTERNAL, self::WEB_APPLICATION => InternalApplicationFactory::class,
            self::EXTERNAL, self::PACKAGE => ExternalApplicationFactory::class,
            self::UNKNOWN => ExternalApplicationFactory::class,
        };
    }
}
