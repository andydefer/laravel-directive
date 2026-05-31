<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies\Input;

use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Records\UserChoiceRecord;
use AndyDefer\Directive\Strategies\Input\UserChoiceStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Utils\EmptyRecord;

final class UserChoiceStrategyTest extends UnitTestCase
{
    private UserChoiceStrategy $strategy;

    private $inputStream;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inputStream = fopen('php://memory', 'r+');
        $this->strategy = new UserChoiceStrategy($this->inputStream);
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

    public function test_supports_user_choice_type(): void
    {
        $this->assertTrue($this->strategy->supports(InputType::USER_CHOICE));
        $this->assertFalse($this->strategy->supports(InputType::SIMPLE_QUESTION));
        $this->assertFalse($this->strategy->supports(InputType::CONFIRMATION));
    }

    public function test_execute_returns_valid_choice(): void
    {
        $this->setUserInput('2');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::USER_CHOICE);
        });

        $this->assertSame(2, $response['result']);
        $this->assertStringContainsString('Which one do you want to use? [1-5]', $response['output']);
    }

    public function test_execute_returns_null_for_non_numeric_input(): void
    {
        $this->setUserInput('abc');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::USER_CHOICE);
        });

        $this->assertNull($response['result']);
        $this->assertStringContainsString('Which one do you want to use? [1-5]', $response['output']);
    }

    public function test_execute_returns_null_for_choice_below_min(): void
    {
        $this->setUserInput('0');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::USER_CHOICE);
        });

        $this->assertNull($response['result']);
        $this->assertStringContainsString('Which one do you want to use? [1-5]', $response['output']);
    }

    public function test_execute_returns_null_for_choice_above_max(): void
    {
        $this->setUserInput('6');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::USER_CHOICE);
        });

        $this->assertNull($response['result']);
        $this->assertStringContainsString('Which one do you want to use? [1-5]', $response['output']);
    }

    public function test_execute_accepts_min_boundary(): void
    {
        $this->setUserInput('1');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::USER_CHOICE);
        });

        $this->assertSame(1, $response['result']);
        $this->assertStringContainsString('Which one do you want to use? [1-5]', $response['output']);
    }

    public function test_execute_accepts_max_boundary(): void
    {
        $this->setUserInput('5');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::USER_CHOICE);
        });

        $this->assertSame(5, $response['result']);
        $this->assertStringContainsString('Which one do you want to use? [1-5]', $response['output']);
    }

    public function test_execute_returns_null_for_invalid_record(): void
    {
        $this->setUserInput('2');
        $record = new EmptyRecord;

        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->strategy->execute($record, InputType::USER_CHOICE);
        });

        $this->assertNull($response['result']);
        // No output because the strategy returns early
        $this->assertEmpty($response['output']);
    }
}
