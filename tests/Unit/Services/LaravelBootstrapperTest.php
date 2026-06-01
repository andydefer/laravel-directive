<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Tests\UnitTestCase;

/**
 * @covers \AndyDefer\Directive\Services\LaravelBootstrapper
 */
final class LaravelBootstrapperTest extends UnitTestCase
{
    private LaravelBootstrapper $bootstrapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapper = new LaravelBootstrapper();
    }

    protected function tearDown(): void
    {
        $this->bootstrapper->reset();
        parent::tearDown();
    }

    public function test_bootstrap_returns_false_when_not_in_laravel_project(): void
    {
        // Act: Attempt bootstrap outside Laravel project
        $result = $this->bootstrapper->bootstrap();

        // Assert: Should fail
        $this->assertFalse($result);
    }

    public function test_is_bootstrapped_returns_false_initially(): void
    {
        // Assert: Initially not bootstrapped
        $this->assertFalse($this->bootstrapper->isBootstrapped());
    }

    public function test_error_is_set_on_bootstrap_failure(): void
    {
        // Act: Attempt bootstrap (will fail outside Laravel)
        $this->bootstrapper->bootstrap();

        // Assert: Error should be set
        $error = $this->bootstrapper->getError();

        if (!$this->bootstrapper->isBootstrapped()) {
            $this->assertNotNull($error);
            $this->assertStringContainsString('bootstrap', $error);
        }
    }

    public function test_get_application_returns_null_initially(): void
    {
        // Assert: No application before bootstrap
        $this->assertNull($this->bootstrapper->getApplication());
    }

    public function test_reset_clears_bootstrap_state(): void
    {
        // Arrange: Attempt bootstrap
        $this->bootstrapper->bootstrap();

        // Act: Reset state
        $this->bootstrapper->reset();

        // Assert: All state is cleared
        $this->assertFalse($this->bootstrapper->isBootstrapped());
        $this->assertNull($this->bootstrapper->getApplication());
        $this->assertNull($this->bootstrapper->getError());
    }

    public function test_bootstrap_only_runs_once(): void
    {
        // Arrange: First bootstrap attempt
        $firstResult = $this->bootstrapper->bootstrap();

        // Act: Second bootstrap attempt
        $secondResult = $this->bootstrapper->bootstrap();

        // Assert: Both attempts return same result
        $this->assertSame($firstResult, $secondResult);
    }

    public function test_bootstrap_caches_failure_state(): void
    {
        // Arrange: First bootstrap attempt
        $firstResult = $this->bootstrapper->bootstrap();
        $firstError = $this->bootstrapper->getError();

        // Act: Second bootstrap attempt
        $secondResult = $this->bootstrapper->bootstrap();
        $secondError = $this->bootstrapper->getError();

        // Assert: Result and error are cached
        $this->assertSame($firstResult, $secondResult);
        $this->assertSame($firstError, $secondError);
    }

    public function test_set_custom_bootstrap_path_returns_self(): void
    {
        // Act: Set custom path
        $result = $this->bootstrapper->setCustomBootstrapPath('/custom/path');

        // Assert: Returns self for chaining
        $this->assertSame($this->bootstrapper, $result);
    }

    public function test_custom_bootstrap_path_is_used(): void
    {
        // Arrange: Set non-existent custom path
        $customPath = '/tmp/non-existent-bootstrap/app.php';
        $this->bootstrapper->setCustomBootstrapPath($customPath);

        // Act: Attempt bootstrap
        $result = $this->bootstrapper->bootstrap();

        // Assert: Should fail with custom path in error
        $this->assertFalse($result);
        $error = $this->bootstrapper->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString($customPath, $error);
    }

    public function test_multiple_resets_work_correctly(): void
    {
        // Arrange: Bootstrap and reset multiple times
        $this->bootstrapper->bootstrap();
        $this->bootstrapper->reset();
        $this->bootstrapper->bootstrap();
        $this->bootstrapper->reset();

        // Act: Check final state
        $isBootstrapped = $this->bootstrapper->isBootstrapped();
        $application = $this->bootstrapper->getApplication();
        $error = $this->bootstrapper->getError();

        // Assert: All state is cleared
        $this->assertFalse($isBootstrapped);
        $this->assertNull($application);
        $this->assertNull($error);
    }

    public function test_bootstrap_after_reset_attempts_again(): void
    {
        // Arrange: First attempt (fails)
        $firstResult = $this->bootstrapper->bootstrap();
        $firstError = $this->bootstrapper->getError();

        // Act: Reset and try again
        $this->bootstrapper->reset();
        $secondResult = $this->bootstrapper->bootstrap();
        $secondError = $this->bootstrapper->getError();

        // Assert: Both attempts behave the same
        $this->assertSame($firstResult, $secondResult);
        $this->assertSame($firstError, $secondError);
    }
}
