<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Records\AbstractRecord;

final class AskQuestionRecord extends AbstractRecord
{
    public function __construct(public readonly string $question) {}
}
