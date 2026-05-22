<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Records\AskQuestionRecord;

class ConfirmQuestionTask
{
    public function execute(AskQuestionRecord $record): bool
    {
        echo $record->question . ' (y/n) ';
        $answer = strtolower(trim(fgets(STDIN)));

        return in_array($answer, ['y', 'yes'], true);
    }
}
