<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Tasks;

use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Records\QuestionRecord;
use AndyDefer\Directive\Records\UserChoiceRecord;
use AndyDefer\Directive\Tests\UnitTestCase;

final class InputTaskTest extends UnitTestCase
{
    private InputDispatcher $task;

    private $inputStream;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create an in-memory stream for testing
        $this->inputStream = fopen('php://memory', 'r+');
        $this->task = new InputDispatcher($this->inputStream);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up: Close the test stream
        fclose($this->inputStream);
    }

    /**
     * Helper method to simulate user input.
     *
     * @param  string  $input  The input to simulate
     */
    private function setUserInput(string $input): void
    {
        fwrite($this->inputStream, $input."\n");
        rewind($this->inputStream);
    }

    /**
     * Helper method to capture output during execution.
     *
     * @param  callable  $callback  The function to execute
     * @return array{result: mixed, output: string}
     */
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

    // ==================== Simple Question Tests ====================

    public function test_execute_simple_question_returns_user_input(): void
    {
        // Arrange: Simulate user input
        $this->setUserInput('John Doe');
        $record = new QuestionRecord('What is your name?');

        // Act: Execute the input task
        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::SIMPLE_QUESTION);
        });

        // Assert: Verify the input was captured correctly
        $this->assertSame('John Doe', $response['result']);
        $this->assertStringContainsString('What is your name?', $response['output']);
    }

    public function test_execute_simple_question_trims_whitespace(): void
    {
        // Arrange: Simulate user input with surrounding whitespace
        $this->setUserInput('  John Doe  ');
        $record = new QuestionRecord('What is your name?');

        // Act: Execute the input task
        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::SIMPLE_QUESTION);
        });

        // Assert: Verify whitespace was trimmed
        $this->assertSame('John Doe', $response['result']);
    }

    // ==================== Confirmation Tests ====================

    public function test_execute_confirmation_returns_true_for_y(): void
    {
        // Arrange: Simulate 'y' input
        $this->setUserInput('y');
        $record = new QuestionRecord('Continue?');

        // Act: Execute confirmation task
        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::CONFIRMATION);
        });

        // Assert: Verify true is returned with confirmation prompt
        $this->assertTrue($response['result']);
        $this->assertStringContainsString('Continue? (y/n)', $response['output']);
    }

    public function test_execute_confirmation_returns_true_for_yes(): void
    {
        // Arrange: Simulate 'yes' input
        $this->setUserInput('yes');
        $record = new QuestionRecord('Continue?');

        // Act: Execute confirmation task
        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::CONFIRMATION);
        });

        // Assert: Verify true is returned
        $this->assertTrue($response['result']);
    }

    public function test_execute_confirmation_returns_false_for_n(): void
    {
        // Arrange: Simulate 'n' input
        $this->setUserInput('n');
        $record = new QuestionRecord('Continue?');

        // Act: Execute confirmation task
        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::CONFIRMATION);
        });

        // Assert: Verify false is returned
        $this->assertFalse($response['result']);
    }

    public function test_execute_confirmation_returns_false_for_no(): void
    {
        // Arrange: Simulate 'no' input
        $this->setUserInput('no');
        $record = new QuestionRecord('Continue?');

        // Act: Execute confirmation task
        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::CONFIRMATION);
        });

        // Assert: Verify false is returned
        $this->assertFalse($response['result']);
    }

    // ==================== User Choice Tests ====================

    public function test_execute_user_choice_returns_valid_choice(): void
    {
        // Arrange: Simulate choice '2' input
        $this->setUserInput('2');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        // Act: Execute user choice task
        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::USER_CHOICE);
        });

        // Assert: Verify the correct choice is returned
        $this->assertSame(2, $response['result']);
        $this->assertStringContainsString('Which one do you want to use? [1-5]', $response['output']);
    }

    public function test_execute_user_choice_returns_null_for_invalid_input(): void
    {
        // Arrange: Simulate non-numeric input
        $this->setUserInput('abc');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        // Act: Execute user choice task
        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::USER_CHOICE);
        });

        // Assert: Verify null is returned for invalid input
        $this->assertNull($response['result']);
    }

    public function test_execute_user_choice_returns_null_for_out_of_range(): void
    {
        // Arrange: Simulate out-of-range input
        $this->setUserInput('6');
        $record = new UserChoiceRecord(choice: 0, max: 5);

        // Act: Execute user choice task
        $response = $this->runAndCaptureOutput(function () use ($record) {
            return $this->task->execute($record, InputType::USER_CHOICE);
        });

        // Assert: Verify null is returned for out-of-range choice
        $this->assertNull($response['result']);
    }

    // ==================== Unsupported Type Tests ====================

    public function test_execute_returns_null_for_unsupported_type(): void
    {
        // Arrange: Create a record (type doesn't matter as strategy won't be found)
        $record = new QuestionRecord('Test');

        // Act: Execute with unsupported type
        $result = $this->task->execute($record, InputType::USER_CHOICE);

        // Assert: Verify null is returned (no strategy supports this combination)
        $this->assertNull($result);
    }
}
