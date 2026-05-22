<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Records\AskQuestionRecord;

class AskQuestionTask
{
    private $inputStream;

    public function __construct($inputStream = STDIN)
    {
        $this->inputStream = $inputStream;
    }

    public function execute(AskQuestionRecord $record): string
    {
        echo $record->question . ' ';

        return trim(fgets($this->inputStream));
    }
}
