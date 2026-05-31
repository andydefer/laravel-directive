<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies\Input;

use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Records\QuestionRecord;
use AndyDefer\Directive\Strategies\Input\ConfirmationStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Utils\EmptyRecord;

final class ConfirmationStrategyTest extends UnitTestCase
{
    private ConfirmationStrategy $strategy;

    private $inputStream;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inputStream = fopen('php://memory', 'r+');
        $this->strategy = new ConfirmationStrategy($this->inputStream);
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

    public function test_supports_confirmation_type(): void
    {
        $this->assertTrue($this->strategy->supports(InputType::CONFIRMATION));
        $this->assertFalse($this->strategy->supports(InputType::SIMPLE_QUESTION));
        $this->assertFalse($this->strategy->supports(InputType::USER_CHOICE));
    }

    public function test_execute_returns_true_for_y(): void
    {
        $this->setUserInput('y');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::CONFIRMATION);
        });

        $this->assertTrue($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_returns_true_for_yes(): void
    {
        $this->setUserInput('yes');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::CONFIRMATION);
        });

        $this->assertTrue($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_returns_true_for_y_uppercase(): void
    {
        $this->setUserInput('Y');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::CONFIRMATION);
        });

        $this->assertTrue($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_returns_true_for_ye_s_uppercase(): void
    {
        $this->setUserInput('YES');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::CONFIRMATION);
        });

        $this->assertTrue($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_returns_false_for_n(): void
    {
        $this->setUserInput('n');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::CONFIRMATION);
        });

        $this->assertFalse($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_returns_false_for_no(): void
    {
        $this->setUserInput('no');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::CONFIRMATION);
        });

        $this->assertFalse($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_returns_false_for_invalid_input(): void
    {
        $this->setUserInput('maybe');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::CONFIRMATION);
        });

        $this->assertFalse($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_returns_false_for_empty_input(): void
    {
        $this->setUserInput('');
        $record = new QuestionRecord('Continue?');

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::CONFIRMATION);
        });

        $this->assertFalse($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_returns_false_for_invalid_record(): void
    {
        $this->setUserInput('y');
        $record = new EmptyRecord;

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::CONFIRMATION);
        });

        $this->assertFalse($response['result']);
        $this->assertEmpty($response['output']);
    }
}
