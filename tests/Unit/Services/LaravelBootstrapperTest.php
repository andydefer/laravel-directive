<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Tests\UnitTestCase;

final class LaravelBootstrapperTest extends UnitTestCase
{
    private LaravelBootstrapper $bootstrapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapper = new LaravelBootstrapper;
    }

    protected function tearDown(): void
    {
        $this->bootstrapper->reset();
        parent::tearDown();
    }

    public function test_bootstrap_returns_false_when_not_in_laravel_project(): void
    {
        $result = $this->bootstrapper->bootstrap();

        $this->assertFalse($result);
    }

    public function test_is_bootstrapped_returns_false_initially(): void
    {
        $this->assertFalse($this->bootstrapper->isBootstrapped());
    }

    public function test_error_message_is_set_on_failure(): void
    {
        $this->bootstrapper->bootstrap();

        $error = $this->bootstrapper->getError();

        if (! $this->bootstrapper->isBootstrapped()) {
            $this->assertNotNull($error);
            $this->assertStringContainsString('bootstrap', $error);
        }
    }

    public function test_get_application_returns_null_initially(): void
    {
        $this->assertNull($this->bootstrapper->getApplication());
    }

    public function test_reset_clears_bootstrap_state(): void
    {
        $this->bootstrapper->bootstrap();
        $this->bootstrapper->reset();

        $this->assertFalse($this->bootstrapper->isBootstrapped());
        $this->assertNull($this->bootstrapper->getApplication());
        $this->assertNull($this->bootstrapper->getError());
    }

    public function test_bootstrap_only_runs_once(): void
    {
        $result1 = $this->bootstrapper->bootstrap();
        $result2 = $this->bootstrapper->bootstrap();

        $this->assertSame($result1, $result2);
    }

    public function test_bootstrap_caches_failure_state(): void
    {
        $result1 = $this->bootstrapper->bootstrap();
        $error1 = $this->bootstrapper->getError();

        $result2 = $this->bootstrapper->bootstrap();
        $error2 = $this->bootstrapper->getError();

        $this->assertSame($result1, $result2);
        $this->assertSame($error1, $error2);
    }
}
