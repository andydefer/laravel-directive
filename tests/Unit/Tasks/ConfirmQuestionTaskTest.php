<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit\Tasks;

use AndyDefer\Directive\Records\AskQuestionRecord;
use AndyDefer\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\Directive\Tests\TestCase;

final class ConfirmQuestionTaskTest extends TestCase
{
    public function test_execute_returns_true_for_y(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "y\n");
        rewind($inputStream);

        $task = new ConfirmQuestionTask($inputStream);

        ob_start();
        $result = $task->execute($record);
        ob_end_clean();

        $this->assertTrue($result);

        fclose($inputStream);
    }

    public function test_execute_returns_true_for_yes(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "yes\n");
        rewind($inputStream);

        $task = new ConfirmQuestionTask($inputStream);

        ob_start();
        $result = $task->execute($record);
        ob_end_clean();

        $this->assertTrue($result);

        fclose($inputStream);
    }

    public function test_execute_returns_false_for_n(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "n\n");
        rewind($inputStream);

        $task = new ConfirmQuestionTask($inputStream);

        ob_start();
        $result = $task->execute($record);
        ob_end_clean();

        $this->assertFalse($result);

        fclose($inputStream);
    }

    public function test_execute_returns_false_for_no(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "no\n");
        rewind($inputStream);

        $task = new ConfirmQuestionTask($inputStream);

        ob_start();
        $result = $task->execute($record);
        ob_end_clean();

        $this->assertFalse($result);

        fclose($inputStream);
    }

    public function test_execute_returns_false_for_invalid_input(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "maybe\n");
        rewind($inputStream);

        $task = new ConfirmQuestionTask($inputStream);

        ob_start();
        $result = $task->execute($record);
        ob_end_clean();

        $this->assertFalse($result);

        fclose($inputStream);
    }

    public function test_execute_returns_true_for_y_case_insensitive(): void
    {
        $record = new AskQuestionRecord('Continue?');

        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, "Y\n");
        rewind($inputStream);

        $task = new ConfirmQuestionTask($inputStream);

        ob_start();
        $result = $task->execute($record);
        ob_end_clean();

        $this->assertTrue($result);

        fclose($inputStream);
    }
}
