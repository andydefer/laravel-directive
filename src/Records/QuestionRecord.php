<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class QuestionRecord extends AbstractRecord
{
    public function __construct(public readonly string $question) {}
}
