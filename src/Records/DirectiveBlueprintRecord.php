<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Records\AbstractRecord;

final class DirectiveBlueprintRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $class,
        public readonly string $signature,
        public readonly string $description,
    ) {}
}
