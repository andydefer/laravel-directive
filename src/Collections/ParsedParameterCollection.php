<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\ParsedParameterRecord;

/**
 * Collection for ParsedParameterRecord instances.
 *
 * @extends AbstractItemCollection<ParsedParameterRecord>
 */
final class ParsedParameterCollection extends AbstractItemCollection
{
    public function __construct()
    {
        parent::__construct(ParsedParameterRecord::class);
    }
}
