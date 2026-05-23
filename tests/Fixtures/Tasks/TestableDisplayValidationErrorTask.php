<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Tasks;

use AndyDefer\Directive\Tasks\DisplayValidationErrorTask;

/**
 * Testable version of DisplayValidationErrorTask that captures output instead of writing to STDERR.
 */
final class TestableDisplayValidationErrorTask extends DisplayValidationErrorTask
{
    private string $capturedOutput = '';

    public function execute(string $error): void
    {
        $stream = fopen('php://memory', 'r+');

        fwrite($stream, "\e[31m✗ Error:\e[0m " . $error . "\n");
        fwrite($stream, "\n\e[33mValid examples:\e[0m\n");
        fwrite($stream, "  • user:list\n");
        fwrite($stream, "  • cache-clear\n");
        fwrite($stream, "  • api:user-profile\n");

        rewind($stream);
        $this->capturedOutput = stream_get_contents($stream);
        fclose($stream);
    }

    public function getCapturedOutput(): string
    {
        return $this->capturedOutput;
    }

    public function resetCapturedOutput(): void
    {
        $this->capturedOutput = '';
    }
}
