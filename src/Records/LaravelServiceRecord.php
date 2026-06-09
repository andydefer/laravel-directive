<?php
// src/Records/LaravelServiceRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class LaravelServiceRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $service_name,
        public readonly string $alias,
        public readonly DateTimeVO $registered_at,
    ) {}
}
