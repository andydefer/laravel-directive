<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Enums\MessageType;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class DisplayMessageRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $message,
        public readonly MessageType $type,
    ) {}
}
