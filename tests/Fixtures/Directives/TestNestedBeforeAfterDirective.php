<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestNestedBeforeAfterDirective extends AbstractDirective
{
    private static string $log = '';

    public function getSignature(): string
    {
        return 'test:nested-before-after';
    }

    public function getDescription(): string
    {
        return 'Test nested before and after hooks';
    }

    public static function getLog(): string
    {
        return self::$log;
    }

    public static function resetLog(): void
    {
        self::$log = '';
    }

    protected function beforeExecute(): void
    {
        self::$log .= 'parent-before-';
        $this->info('Parent before hook executed');
    }

    protected function execute(): ExitCode
    {
        self::$log .= 'parent-execute-';
        $this->info('Parent execute hook executed');

        $this->call('test:before-after');

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        self::$log .= 'parent-after-'.$exitCode->value;
        $this->info('Parent after hook executed');
    }
}
