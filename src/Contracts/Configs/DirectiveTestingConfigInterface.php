<?php
// src/Contracts/Configs/DirectiveTestingConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Configs;

use AndyDefer\Directive\Enums\PermissionMode;

/**
 * Interface for directive testing configuration.
 *
 * Defines the contract for configuration values used by the directive
 * testing service. Implementations can use environment variables,
 * configuration files, or any other source.
 *
 * @author Andy Defer
 */
interface DirectiveTestingConfigInterface
{
    /**
     * Get the prefix for temporary directory names.
     *
     * @return string Prefix for temp directories (e.g., "directive_test_")
     */
    public function tempDirectoryPrefix(): string;

    /**
     * Get the permission mode for the temporary directory.
     *
     * @return PermissionMode Permission mode (e.g., 0755 for directories)
     */
    public function tempDirectoryPermission(): PermissionMode;

    /**
     * Get the default boot Laravel setting.
     *
     * @return bool True if Laravel should be bootstrapped by default
     */
    public function defaultBootLaravel(): bool;

    /**
     * Determine if temporary files should be cleaned up after tests.
     *
     * @return bool True to clean up, false to keep temp files for debugging
     */
    public function cleanupAfterTest(): bool;

    /**
     * Determine if output should be captured during tests.
     *
     * @return bool True to capture output, false to allow direct output
     */
    public function captureOutput(): bool;

    /**
     * Get the timeout in seconds for directive execution.
     *
     * @return int Timeout in seconds, 0 means no timeout
     */
    public function executionTimeout(): int;

    /**
     * Determine if detailed logging should be enabled during tests.
     *
     * @return bool True to enable verbose logging
     */
    public function verboseLogging(): bool;
}
