<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Enums\PathType;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class CreatedPathRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $path,
        public readonly PathType $type,
        public readonly DateTimeVO $created_at,
    ) {}
}
