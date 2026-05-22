<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit\Tasks;

use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Directive\Records\AskQuestionRecord;
use AndyDefer\Directive\Tasks\AskQuestionTask;

final class AskQuestionTaskTest extends TestCase
{
    public function test_execute_returns_user_input(): void
    {
        $record = new AskQuestionRecord('What is your name?');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "John Doe\n");
        rewind($inputStream);

        $task = new AskQuestionTask($inputStream);

        $this->expectOutputString('What is your name? ');

        $result = $task->execute($record);

        $this->assertSame('John Doe', $result);

        fclose($inputStream);
    }

    public function test_execute_trims_whitespace_from_input(): void
    {
        $record = new AskQuestionRecord('Enter value:');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "  John Doe  \n");
        rewind($inputStream);

        $task = new AskQuestionTask($inputStream);

        $this->expectOutputString('Enter value: ');

        $result = $task->execute($record);

        $this->assertSame('John Doe', $result);

        fclose($inputStream);
    }

    public function test_execute_handles_empty_input(): void
    {
        $record = new AskQuestionRecord('Enter name:');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "\n");
        rewind($inputStream);

        $task = new AskQuestionTask($inputStream);

        $this->expectOutputString('Enter name: ');

        $result = $task->execute($record);

        $this->assertSame('', $result);

        fclose($inputStream);
    }

    public function test_execute_handles_multiline_input(): void
    {
        $record = new AskQuestionRecord('Enter description:');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "First line\nSecond line\n");
        rewind($inputStream);

        $task = new AskQuestionTask($inputStream);

        $this->expectOutputString('Enter description: ');

        $result = $task->execute($record);

        $this->assertSame('First line', $result);

        fclose($inputStream);
    }
}
