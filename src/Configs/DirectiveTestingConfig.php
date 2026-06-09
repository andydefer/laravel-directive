<?php
// src/Configs/DirectiveTestingConfig.php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\Enums\PermissionMode;

/**
 * Configuration for directive testing.
 *
 * This implementation reads configuration from environment variables
 * with sensible defaults.
 *
 * @author Andy Defer
 */
final class DirectiveTestingConfig implements DirectiveTestingConfigInterface
{
    /**
     * {@inheritDoc}
     */
    public function tempDirectoryPrefix(): string
    {
        return getenv('DIRECTIVE_TEST_TEMP_PREFIX') ?: 'directive_test_';
    }

    /**
     * {@inheritDoc}
     */
    public function tempDirectoryPermission(): PermissionMode
    {
        $value = (int) (getenv('DIRECTIVE_TEST_TEMP_PERMISSION') ?: 0777);

        return PermissionMode::fromValue($value) ?? PermissionMode::DIRECTORY;
    }

    /**
     * {@inheritDoc}
     */
    public function defaultBootLaravel(): bool
    {
        $value = getenv('DIRECTIVE_TEST_BOOT_LARAVEL');

        if ($value === null) {
            return false;
        }

        return $value === 'true' || $value === '1';
    }

    /**
     * {@inheritDoc}
     */
    public function cleanupAfterTest(): bool
    {
        $value = getenv('DIRECTIVE_TEST_CLEANUP');

        if ($value === null) {
            return true;
        }

        return $value !== 'false' && $value !== '0';
    }

    /**
     * {@inheritDoc}
     */
    public function captureOutput(): bool
    {
        $value = getenv('DIRECTIVE_TEST_CAPTURE_OUTPUT');

        if ($value === null) {
            return true;
        }

        return $value !== 'false' && $value !== '0';
    }

    /**
     * {@inheritDoc}
     */
    public function executionTimeout(): int
    {
        return (int) (getenv('DIRECTIVE_TEST_EXECUTION_TIMEOUT') ?: 60);
    }

    /**
     * {@inheritDoc}
     */
    public function verboseLogging(): bool
    {
        $value = getenv('DIRECTIVE_TEST_VERBOSE_LOGGING');

        return $value === 'true' || $value === '1';
    }
}
