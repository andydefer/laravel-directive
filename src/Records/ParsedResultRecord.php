<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Records\AbstractRecord;

/**
 * Represents the complete parsed result of a directive.
 *
 * Contains typed collections of parameters (arguments and options).
 */
final class ParsedResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly ParameterCollection $arguments,
        public readonly ParameterCollection $options,
    ) {}
}
