<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Enums\ParameterType;
use AndyDefer\Records\AbstractRecord;

/**
 * Represents a parsed parameter help information.
 *
 * This record contains metadata about a directive parameter including its
 * name, type (argument or option), whether it's required, and its default value.
 */
final class ParsedParameterRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly ParameterType $type,
        public readonly bool $required,
        public readonly mixed $default,
    ) {}
}
