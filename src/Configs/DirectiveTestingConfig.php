<?php

// src/Configs/DirectiveTestingConfig.php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\DatabaseTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\PhpServices\Enums\PermissionMode;

/**
 * Configuration for directive testing.
 *
 * This implementation reads configuration from environment variables
 * with sensible defaults.
 *
 * @author Andy Defer
 */
final class DirectiveTestingConfig implements DatabaseTestingConfigInterface, DirectiveTestingConfigInterface
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

    // ========== Database Testing Configuration Methods ==========

    /**
     * {@inheritDoc}
     */
    public function getDriver(): string
    {
        return getenv('TEST_DB_DRIVER') ?: 'sqlite';
    }

    /**
     * {@inheritDoc}
     */
    public function getSqliteDatabase(): string
    {
        return getenv('TEST_SQLITE_DATABASE') ?: ':memory:';
    }

    /**
     * {@inheritDoc}
     */
    public function getMysqlHost(): string
    {
        return getenv('TEST_MYSQL_HOST') ?: '127.0.0.1';
    }

    /**
     * {@inheritDoc}
     */
    public function getMysqlPort(): int
    {
        return (int) (getenv('TEST_MYSQL_PORT') ?: 3306);
    }

    /**
     * {@inheritDoc}
     */
    public function getMysqlDatabase(): string
    {
        return getenv('TEST_MYSQL_DATABASE') ?: 'directive_test';
    }

    /**
     * {@inheritDoc}
     */
    public function getMysqlUsername(): string
    {
        return getenv('TEST_MYSQL_USERNAME') ?: 'root';
    }

    /**
     * {@inheritDoc}
     */
    public function getMysqlPassword(): string
    {
        return getenv('TEST_MYSQL_PASSWORD') ?: '';
    }

    /**
     * {@inheritDoc}
     */
    public function getMysqlCharset(): string
    {
        return getenv('TEST_MYSQL_CHARSET') ?: 'utf8mb4';
    }

    /**
     * {@inheritDoc}
     */
    public function getConnectionTimeout(): int
    {
        return (int) (getenv('TEST_DB_CONNECTION_TIMEOUT') ?: 5);
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxRetries(): int
    {
        return (int) (getenv('TEST_DB_MAX_RETRIES') ?: 3);
    }

    /**
     * {@inheritDoc}
     */
    public function getRetryDelayMs(): int
    {
        return (int) (getenv('TEST_DB_RETRY_DELAY_MS') ?: 100);
    }
}
