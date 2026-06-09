<?php
// src/Records/FileCreationResultRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class FileCreationResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $success,
        public readonly string $destinationPath,
        public readonly string $message,
    ) {}
}
