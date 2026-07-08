<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestBeforeAfterDirective extends AbstractDirective
{
    private static string $log = '';

    public function getSignature(): string
    {
        return 'test-before-after';
    }

    public function getDescription(): string
    {
        return 'Test before and after hooks';
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
        self::$log .= 'before-';
        $this->info('Before hook executed');
    }

    protected function execute(): ExitCode
    {
        self::$log .= 'execute-';
        $this->info('Execute hook executed');

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        self::$log .= 'after-'.$exitCode->value;
        $this->info('After hook executed');
    }
}
