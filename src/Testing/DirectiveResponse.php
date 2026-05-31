<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\Enums\ExitCode;
use PHPUnit\Framework\Assert;

final class DirectiveResponse
{
    public function __construct(
        private readonly ExitCode $exitCode,
        private readonly string $output,
        private readonly array $arguments,
    ) {}

    public function getExitCode(): ExitCode
    {
        return $this->exitCode;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function isSuccess(): bool
    {
        return $this->exitCode->isSuccess();
    }

    public function isFailure(): bool
    {
        return ! $this->exitCode->isSuccess();
    }

    public function getExitCodeValue(): int
    {
        return $this->exitCode->value;
    }

    public function assertSuccess(): self
    {
        Assert::assertTrue(
            $this->isSuccess(),
            "Directive failed with exit code {$this->exitCode->value}. Output: {$this->output}"
        );

        return $this;
    }

    public function assertFailure(?int $expectedExitCode = null): self
    {
        if ($expectedExitCode !== null) {
            Assert::assertSame(
                $expectedExitCode,
                $this->exitCode->value,
                "Expected exit code {$expectedExitCode}, got {$this->exitCode->value}. Output: {$this->output}"
            );
        } else {
            Assert::assertFalse(
                $this->isSuccess(),
                "Expected failure but directive succeeded. Output: {$this->output}"
            );
        }

        return $this;
    }

    public function assertOutputContains(string $expected): self
    {
        Assert::assertStringContainsString($expected, $this->output);

        return $this;
    }

    public function assertOutputNotContains(string $expected): self
    {
        Assert::assertStringNotContainsString($expected, $this->output);

        return $this;
    }

    public function assertOutputMatches(string $pattern): self
    {
        Assert::assertMatchesRegularExpression($pattern, $this->output);

        return $this;
    }

    public function assertOutputEquals(string $expected): self
    {
        Assert::assertSame($expected, $this->output);

        return $this;
    }

    public function assertOutputEmpty(): self
    {
        Assert::assertEmpty($this->output);

        return $this;
    }
}
