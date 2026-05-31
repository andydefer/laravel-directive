<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record containing the result of creating a directive file.
 */
final class CreateDirectiveFileRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $success,
        public readonly string $path,
        public readonly ?string $error = null,
    ) {}
}
