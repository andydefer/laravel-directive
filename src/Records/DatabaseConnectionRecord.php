<?php

// src/Records/DatabaseConnectionRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class DatabaseConnectionRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $driver,
        public readonly ?string $sqlite_database,
        public readonly ?string $mysql_host,
        public readonly ?int $mysql_port,
        public readonly ?string $mysql_database,
        public readonly ?string $mysql_username,
        public readonly ?string $mysql_password,
        public readonly ?string $mysql_charset,
        public readonly int $connection_timeout,
        public readonly int $max_retries,
        public readonly int $retry_delay_ms,
        public readonly bool $is_connected,
        public readonly ?string $error_message,
    ) {}
}
