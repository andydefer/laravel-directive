<?php

// src/Configs/DatabaseTestingConfig.php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\DatabaseTestingConfigInterface;

final class DatabaseTestingConfig implements DatabaseTestingConfigInterface
{
    public function getDriver(): string
    {
        return getenv('TEST_DB_DRIVER') ?: 'sqlite';
    }

    public function getSqliteDatabase(): string
    {
        return getenv('TEST_SQLITE_DATABASE') ?: ':memory:';
    }

    public function getMysqlHost(): string
    {
        return getenv('TEST_MYSQL_HOST') ?: '127.0.0.1';
    }

    public function getMysqlPort(): int
    {
        return (int) (getenv('TEST_MYSQL_PORT') ?: 3306);
    }

    public function getMysqlDatabase(): string
    {
        return getenv('TEST_MYSQL_DATABASE') ?: 'directive_test';
    }

    public function getMysqlUsername(): string
    {
        return getenv('TEST_MYSQL_USERNAME') ?: 'root';
    }

    public function getMysqlPassword(): string
    {
        return getenv('TEST_MYSQL_PASSWORD') ?: '';
    }

    public function getMysqlCharset(): string
    {
        return getenv('TEST_MYSQL_CHARSET') ?: 'utf8mb4';
    }

    public function getConnectionTimeout(): int
    {
        return (int) (getenv('TEST_DB_CONNECTION_TIMEOUT') ?: 5);
    }

    public function getMaxRetries(): int
    {
        return (int) (getenv('TEST_DB_MAX_RETRIES') ?: 3);
    }

    public function getRetryDelayMs(): int
    {
        return (int) (getenv('TEST_DB_RETRY_DELAY_MS') ?: 100);
    }
}
