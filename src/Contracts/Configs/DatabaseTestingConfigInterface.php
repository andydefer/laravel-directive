<?php

// src/Contracts/Configs/DatabaseTestingConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Configs;

interface DatabaseTestingConfigInterface
{
    public function getDriver(): string;

    public function getSqliteDatabase(): string;

    public function getMysqlHost(): string;

    public function getMysqlPort(): int;

    public function getMysqlDatabase(): string;

    public function getMysqlUsername(): string;

    public function getMysqlPassword(): string;

    public function getMysqlCharset(): string;

    public function getConnectionTimeout(): int;

    public function getMaxRetries(): int;

    public function getRetryDelayMs(): int;
}
