<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies\Input;

use AndyDefer\Directive\Contracts\InputStrategyInterface;
use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Records\QuestionRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SimpleQuestionStrategy implements InputStrategyInterface
{
    private $inputStream;

    public function __construct($inputStream = STDIN)
    {
        $this->inputStream = $inputStream;
    }

    public function supports(InputType $type): bool
    {
        return $type === InputType::SIMPLE_QUESTION;
    }

    public function execute(AbstractRecord $record, InputType $type): mixed
    {
        if (! $record instanceof QuestionRecord) {
            return '';
        }

        echo $record->question.$type->getPromptSuffix();

        return trim(fgets($this->inputStream));
    }
}
