<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Tasks;

use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Tasks\CreateDirectiveFileTask;
use AndyDefer\Directive\Tests\TestCase;

final class CreateDirectiveFileTaskTest extends TestCase
{
    private string $tempDir;
    private CreateDirectiveFileTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/directive_test_' . uniqid();
        mkdir($this->tempDir);
        chdir($this->tempDir);

        // Create stubs directory inside temp dir
        mkdir($this->tempDir . '/stubs');
        file_put_contents(
            $this->tempDir . '/stubs/directive.stub',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class {{class}} extends AbstractDirective
{
    public function getSignature(): string
    {
        return '{{signature}}';
    }

    public function getDescription(): string
    {
        return '{{description}}';
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
PHP
        );

        // Pass stub path directly to constructor
        $this->task = new CreateDirectiveFileTask(
            new DirectiveNamingService(),
            $this->tempDir . '/stubs/directive.stub'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temp directory files
        $files = glob($this->tempDir . '/app/Directives/*.php');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        if (is_dir($this->tempDir . '/app/Directives')) {
            rmdir($this->tempDir . '/app/Directives');
        }
        if (is_dir($this->tempDir . '/app')) {
            rmdir($this->tempDir . '/app');
        }
        if (file_exists($this->tempDir . '/stubs/directive.stub')) {
            unlink($this->tempDir . '/stubs/directive.stub');
        }
        if (is_dir($this->tempDir . '/stubs')) {
            rmdir($this->tempDir . '/stubs');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function test_execute_creates_directory_and_file_successfully(): void
    {
        $className = 'UserCreateDirective';
        $signature = 'user-create';

        $result = $this->task->execute($className, $signature);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('/app/Directives/UserCreateDirective.php', $result->path);
        $this->assertNull($result->error);
        $this->assertFileExists($result->path);
    }

    public function test_execute_returns_error_when_file_already_exists(): void
    {
        $className = 'ExistingDirective';
        $signature = 'existing-cmd';

        $this->task->execute($className, $signature);
        $result = $this->task->execute($className, $signature);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Directive already exists', $result->error);
    }

    public function test_execute_creates_file_with_correct_content(): void
    {
        $className = 'TestDirective';
        $signature = 'test-cmd';

        $result = $this->task->execute($className, $signature);

        $this->assertTrue($result->success);

        $content = file_get_contents($result->path);
        $this->assertStringContainsString('final class TestDirective extends AbstractDirective', $content);
        $this->assertStringContainsString("return 'test-cmd {--option}';", $content);
        $this->assertStringContainsString("return 'Generated directive for test-cmd';", $content);
    }

    public function test_execute_creates_nested_directories_if_not_exists(): void
    {
        $className = 'NestedDirective';
        $signature = 'nested-cmd';

        $result = $this->task->execute($className, $signature);

        $this->assertTrue($result->success);
        $this->assertDirectoryExists($this->tempDir . '/app/Directives');
    }
}
