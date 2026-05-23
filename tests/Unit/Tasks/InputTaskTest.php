<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Tasks;

use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Records\QuestionRecord;
use AndyDefer\Directive\Records\UserChoiceRecord;
use AndyDefer\Directive\Tasks\InputTask;
use AndyDefer\Directive\Tests\TestCase;

final class InputTaskTest extends TestCase
{
    private InputTask $task;
    private $inputStream;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inputStream = fopen('php://memory', 'r+');
        $this->task = new InputTask($this->inputStream);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        fclose($this->inputStream);
    }

    private function setUserInput(string $input): void
    {
        fwrite($this->inputStream, $input . "\n");
        rewind($this->inputStream);
    }

    private function runAndCaptureOutput(callable $callback): array
    {
        ob_start();
        $result = $callback();
        $output = ob_get_clean();

        return [
            'result' => $result,
            'output' => $output,
        ];
    }

    public function test_execute_simple_question_returns_user_input(): void
    {
        $this->setUserInput('John Doe');
        $record = new QuestionRecord('What is your name?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::SIMPLE_QUESTION);
        });

        $this->assertSame('John Doe', $response['result']);
        $this->assertStringContainsString('What is your name?', $response['output']);
    }

    public function test_execute_simple_question_trims_whitespace(): void
    {
        $this->setUserInput('  John Doe  ');
        $record = new QuestionRecord('What is your name?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::SIMPLE_QUESTION);
        });

        $this->assertSame('John Doe', $response['result']);
    }

    public function test_execute_confirmation_returns_true_for_y(): void
    {
        $this->setUserInput('y');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::CONFIRMATION);
        });

        $this->assertTrue($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_confirmation_returns_true_for_yes(): void
    {
        $this->setUserInput('yes');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::CONFIRMATION);
        });

        $this->assertTrue($response['result']);
    }

    public function test_execute_confirmation_returns_false_for_n(): void
    {
        $this->setUserInput('n');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::CONFIRMATION);
        });

        $this->assertFalse($response['result']);
    }

    public function test_execute_confirmation_returns_false_for_no(): void
    {
        $this->setUserInput('no');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::CONFIRMATION);
        });

        $this->assertFalse($response['result']);
    }

    public function test_execute_user_choice_returns_valid_choice(): void
    {
        $this->setUserInput('2');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::USER_CHOICE);
        });

        $this->assertSame(2, $response['result']);
        $this->assertStringContainsString('Which one do you want to use? [1-5]', $response['output']);
    }

    public function test_execute_user_choice_returns_null_for_invalid_input(): void
    {
        $this->setUserInput('abc');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::USER_CHOICE);
        });

        $this->assertNull($response['result']);
    }

    public function test_execute_user_choice_returns_null_for_out_of_range(): void
    {
        $this->setUserInput('6');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::USER_CHOICE);
        });

        $this->assertNull($response['result']);
    }

    public function test_execute_returns_null_for_unsupported_type(): void
    {
        $record = new QuestionRecord('Test');

        $result = $this->task->execute($record, InputType::USER_CHOICE);

        $this->assertNull($result);
    }
}
