<?php

// src/Records/ParsedResultRecord.php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Represents the complete parsed result of a directive.
 *
 * Contains typed collections of parameters (arguments, options, and variadic arguments).
 *
 * @author Andy Defer
 */
final class ParsedResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly ParsedArgumentCollection $arguments,
        public readonly ParsedOptionCollection $options,
        public readonly StringTypedCollection $variadic_arguments,
    ) {}
}
