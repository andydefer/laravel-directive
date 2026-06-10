<?php

// src/Records/FileOperationRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class FileOperationRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $operation,
        public readonly string $path,
        public readonly ?int $bytes,
        public readonly DateTimeVO $timestamp,
    ) {}
}
