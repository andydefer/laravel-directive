<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestCircularDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test-circular';
    }

    public function getDescription(): string
    {
        return 'Test circular call detection';
    }

    protected function execute(): ExitCode
    {
        $this->info('Circular directive started');
        $this->call('test-circular'); // S'appelle lui-même → boucle infinie
        $this->info('Circular directive finished');

        return ExitCode::SUCCESS;
    }
}
