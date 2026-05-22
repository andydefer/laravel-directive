<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Tasks\AskQuestionTask;
use AndyDefer\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\Directive\Tasks\DisplayMessageTask;
use AndyDefer\Directive\Tasks\DisplayTableTask;

final class TestConcreteDirective extends AbstractDirective
{
    public function __construct(
        DisplayMessageTask $displayMessage,
        AskQuestionTask $askQuestion,
        ConfirmQuestionTask $confirmQuestion,
        DisplayTableTask $displayTable,
    ) {
        parent::__construct($displayMessage, $askQuestion, $confirmQuestion, $displayTable);
    }

    public function getSignature(): string
    {
        return 'test:concrete';
    }

    public function getDescription(): string
    {
        return 'Test concrete directive for AbstractDirective tests';
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
