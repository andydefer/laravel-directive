<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Records\AskQuestionRecord;

class ConfirmQuestionTask
{
    private $inputStream;

    public function __construct($inputStream = STDIN)
    {
        $this->inputStream = $inputStream;
    }

    public function execute(AskQuestionRecord $record): bool
    {
        echo $record->question.' (y/n) ';
        $answer = strtolower(trim(fgets($this->inputStream)));

        return in_array($answer, ['y', 'yes'], true);
    }
}
