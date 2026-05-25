<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class TestCalculatorDirective extends AbstractDirective
{
    public function __construct(
        DirectiveInteractionService $interaction,
    ) {
        parent::__construct($interaction);
    }

    public function getSignature(): string
    {
        return 'calculator {operation} {a} {b?}';
    }

    public function getDescription(): string
    {
        return 'Test calculator directive for testing arithmetic operations';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('calc');
        $aliases->add('math');
        return $aliases;
    }

    public function execute(): ExitCode
    {
        $operation = $this->argument('operation');
        $a = (float) $this->argument('a');
        $b = (float) ($this->argument('b') ?? 0);

        if ($operation === null) {
            $this->error('Operation is required');
            return ExitCode::INVALID_ARGUMENT;
        }

        $result = match ($operation) {
            'add' => $a + $b,
            'sub' => $a - $b,
            'mul' => $a * $b,
            'div' => $b != 0 ? $a / $b : throw new \InvalidArgumentException('Division by zero'),
            'pow' => $a ** $b,
            'mod' => $a % $b,
            default => null,
        };

        if ($result === null) {
            $this->error("Unknown operation: {$operation}");
            return ExitCode::INVALID_ARGUMENT;
        }

        $this->line((string) $result);

        if ($this->hasOption('verbose')) {
            $this->info("Operation: {$operation}, A: {$a}, B: {$b}, Result: {$result}");
        }

        return ExitCode::SUCCESS;
    }
}
