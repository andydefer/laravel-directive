<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit\Tasks;

use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Directive\Records\AskQuestionRecord;
use AndyDefer\Directive\Tasks\ConfirmQuestionTask;

final class ConfirmQuestionTaskTest extends TestCase
{
    private ConfirmQuestionTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->task = new ConfirmQuestionTask;
    }

    public function test_execute_returns_true_for_y(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $input = fopen('php://memory', 'r+');
        fwrite($input, "y\n");
        rewind($input);

        $task = new class extends ConfirmQuestionTask
        {
            public $testInput;

            public function execute(AskQuestionRecord $record): bool
            {
                echo $record->question . ' (y/n) ';
                $answer = strtolower(trim(fgets($this->testInput)));

                return in_array($answer, ['y', 'yes'], true);
            }
        };

        $task->testInput = $input;
        $result = $task->execute($record);

        $this->assertTrue($result);
    }

    public function test_execute_returns_true_for_yes(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $input = fopen('php://memory', 'r+');
        fwrite($input, "yes\n");
        rewind($input);

        $task = new class extends ConfirmQuestionTask
        {
            public $testInput;

            public function execute(AskQuestionRecord $record): bool
            {
                echo $record->question . ' (y/n) ';
                $answer = strtolower(trim(fgets($this->testInput)));

                return in_array($answer, ['y', 'yes'], true);
            }
        };

        $task->testInput = $input;
        $result = $task->execute($record);

        $this->assertTrue($result);
    }

    public function test_execute_returns_false_for_n(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $input = fopen('php://memory', 'r+');
        fwrite($input, "n\n");
        rewind($input);

        $task = new class extends ConfirmQuestionTask
        {
            public $testInput;

            public function execute(AskQuestionRecord $record): bool
            {
                echo $record->question . ' (y/n) ';
                $answer = strtolower(trim(fgets($this->testInput)));

                return in_array($answer, ['y', 'yes'], true);
            }
        };

        $task->testInput = $input;
        $result = $task->execute($record);

        $this->assertFalse($result);
    }

    public function test_execute_returns_false_for_no(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $input = fopen('php://memory', 'r+');
        fwrite($input, "no\n");
        rewind($input);

        $task = new class extends ConfirmQuestionTask
        {
            public $testInput;

            public function execute(AskQuestionRecord $record): bool
            {
                echo $record->question . ' (y/n) ';
                $answer = strtolower(trim(fgets($this->testInput)));

                return in_array($answer, ['y', 'yes'], true);
            }
        };

        $task->testInput = $input;
        $result = $task->execute($record);

        $this->assertFalse($result);
    }

    public function test_execute_returns_false_for_invalid_input(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $input = fopen('php://memory', 'r+');
        fwrite($input, "maybe\n");
        rewind($input);

        $task = new class extends ConfirmQuestionTask
        {
            public $testInput;

            public function execute(AskQuestionRecord $record): bool
            {
                echo $record->question . ' (y/n) ';
                $answer = strtolower(trim(fgets($this->testInput)));

                return in_array($answer, ['y', 'yes'], true);
            }
        };

        $task->testInput = $input;
        $result = $task->execute($record);

        $this->assertFalse($result);
    }
}
