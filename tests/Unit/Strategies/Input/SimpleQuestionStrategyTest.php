<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies\Input;

use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Records\QuestionRecord;
use AndyDefer\Directive\Strategies\Input\SimpleQuestionStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\EmptyRecord;

final class SimpleQuestionStrategyTest extends UnitTestCase
{
    private SimpleQuestionStrategy $strategy;

    private $inputStream;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inputStream = fopen('php://memory', 'r+');
        $this->strategy = new SimpleQuestionStrategy($this->inputStream);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        fclose($this->inputStream);
    }

    private function setUserInput(string $input): void
    {
        fwrite($this->inputStream, $input."\n");
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

    public function test_supports_simple_question_type(): void
    {
        $this->assertTrue($this->strategy->supports(InputType::SIMPLE_QUESTION));
        $this->assertFalse($this->strategy->supports(InputType::CONFIRMATION));
        $this->assertFalse($this->strategy->supports(InputType::USER_CHOICE));
    }

    public function test_execute_returns_user_input(): void
    {
        $this->setUserInput('John Doe');
        $record = new QuestionRecord('What is your name?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::SIMPLE_QUESTION);
        });

        $this->assertSame('John Doe', $response['result']);
        $this->assertStringContainsString('What is your name?', $response['output']);
    }

    public function test_execute_trims_whitespace(): void
    {
        $this->setUserInput('  John Doe  ');
        $record = new QuestionRecord('What is your name?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::SIMPLE_QUESTION);
        });

        $this->assertSame('John Doe', $response['result']);
        $this->assertStringContainsString('What is your name?', $response['output']);
    }

    public function test_execute_returns_empty_string_for_empty_input(): void
    {
        $this->setUserInput('');
        $record = new QuestionRecord('Enter name:');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::SIMPLE_QUESTION);
        });

        $this->assertSame('', $response['result']);
        $this->assertStringContainsString('Enter name:', $response['output']);
    }

    public function test_execute_returns_empty_string_for_invalid_record(): void
    {
        $this->setUserInput('Test');
        $record = new EmptyRecord;

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::SIMPLE_QUESTION);
        });

        $this->assertSame('', $response['result']);
        $this->assertEmpty($response['output']);
    }
}
