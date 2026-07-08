<?php

// tests/Fixtures/Directives/TestVariadicDirective.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestVariadicDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test-variadic {name} {files*} {--verbose}';
    }

    public function getDescription(): string
    {
        return 'Test directive with variadic arguments';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $this->line("Name: {$name}");

        if ($this->hasVariadicArguments()) {
            $this->line('Files:');
            foreach ($this->getVariadicArguments() as $file) {
                $this->line("  - {$file}");
            }
        }

        if ($this->isFlagActive('verbose')) {
            $this->info('Verbose mode enabled');
        }

        return ExitCode::SUCCESS;
    }
}
