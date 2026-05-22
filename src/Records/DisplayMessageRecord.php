<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Records\AbstractRecord;
use AndyDefer\Directive\Enums\MessageType;

final class DisplayMessageRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $message,
        public readonly MessageType $type,
    ) {}
}
