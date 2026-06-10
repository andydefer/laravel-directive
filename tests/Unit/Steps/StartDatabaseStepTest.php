<?php

// tests/Unit/Steps/StartDatabaseStepTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Steps;

use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\StepResultStatus;
use AndyDefer\Directive\Enums\TestingStep;
use AndyDefer\Directive\Steps\StartDatabaseStep;
use AndyDefer\Directive\Tests\UnitTestCase;
use PDO;

final class StartDatabaseStepTest extends UnitTestCase
{
    private DirectiveTestingContext $context;

    private StartDatabaseStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new DirectiveTestingContext(true);
        $this->step = new StartDatabaseStep;
    }

    public function test_supports_returns_true_when_laravel_enabled_and_no_connection(): void
    {
        $this->assertTrue($this->step->supports($this->context));
    }

    public function test_supports_returns_false_when_already_connected(): void
    {
        $this->context->setDatabaseConnection(new PDO('sqlite::memory:'));
        $this->assertFalse($this->step->supports($this->context));
    }

    public function test_supports_returns_false_when_laravel_disabled(): void
    {
        $context = new DirectiveTestingContext(false);
        $this->assertFalse($this->step->supports($context));
    }

    public function test_execute_creates_sqlite_memory_connection(): void
    {
        $config = new DirectiveTestingConfig;
        $this->context->setConfig($config);
        $this->context->setTempDir(sys_get_temp_dir());

        $result = $this->step->execute($this->context, fn ($c) => $c);

        $this->assertTrue($this->context->hasDatabaseConnection());
        $this->assertNotNull($this->context->getDatabaseConnection());
        $this->assertNotNull($this->context->getDatabaseConnectionRecord());
        $this->assertTrue($result->hasStepResult(TestingStep::START_DATABASE));

        $stepResult = $result->getStepResult(TestingStep::START_DATABASE);
        $this->assertSame(StepResultStatus::SUCCESS, $stepResult->status);
    }

    public function test_execute_creates_sqlite_file_connection(): void
    {
        putenv('TEST_SQLITE_DATABASE=test.db');

        $config = new DirectiveTestingConfig;
        $this->context->setConfig($config);
        $this->context->setTempDir(sys_get_temp_dir());

        $result = $this->step->execute($this->context, fn ($c) => $c);

        $this->assertTrue($this->context->hasDatabaseConnection());
        $this->assertNotNull($this->context->getDatabaseConnection());

        $record = $this->context->getDatabaseConnectionRecord();
        $this->assertStringContainsString('test.db', $record->sqlite_database);

        putenv('TEST_SQLITE_DATABASE=');
    }

    public function test_execute_returns_failed_when_temp_dir_is_null(): void
    {
        $this->context->setTempDir(null);

        $result = $this->step->execute($this->context, fn ($c) => $c);

        $this->assertFalse($this->context->hasDatabaseConnection());
        $this->assertTrue($result->hasStepResult(TestingStep::START_DATABASE));

        $stepResult = $result->getStepResult(TestingStep::START_DATABASE);
        $this->assertSame(StepResultStatus::FAILED, $stepResult->status);
        $this->assertStringContainsString('temporary directory is null', $stepResult->message);
    }

    public function test_execute_fails_with_invalid_driver(): void
    {
        putenv('TEST_DB_DRIVER=invalid');

        $config = new DirectiveTestingConfig;
        $this->context->setConfig($config);
        $this->context->setTempDir(sys_get_temp_dir());

        $result = $this->step->execute($this->context, fn ($c) => $c);

        $this->assertFalse($this->context->hasDatabaseConnection());
        $this->assertTrue($result->hasStepResult(TestingStep::START_DATABASE));

        $stepResult = $result->getStepResult(TestingStep::START_DATABASE);
        $this->assertSame(StepResultStatus::FAILED, $stepResult->status);
        $this->assertStringContainsString('Unsupported database driver', $stepResult->message);

        putenv('TEST_DB_DRIVER=');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('TEST_DB_DRIVER=');
        putenv('TEST_SQLITE_DATABASE=');
    }
}
