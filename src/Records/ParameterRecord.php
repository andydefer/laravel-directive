<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Records\AbstractRecord;

/**
 * Represents a parameter (argument or option) with its name and value.
 *
 * For arguments, value is always a string.
 * For options, value can be a boolean (flag) or a string.
 */
final class ParameterRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly bool|string|int|null $value,
    ) {}
}
