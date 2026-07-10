<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestBeforeFailingDirective extends AbstractDirective
{
    private static string $log = '';

    public function getSignature(): string
    {
        return 'test:before-failing';
    }

    public function getDescription(): string
    {
        return 'Test before hook that fails';
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
        throw new \RuntimeException('Before hook failed');
    }

    protected function execute(): ExitCode
    {
        self::$log .= 'execute-';

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        self::$log .= 'after-'.$exitCode->value;
    }
}
