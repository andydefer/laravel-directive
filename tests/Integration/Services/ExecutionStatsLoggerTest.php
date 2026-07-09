<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Services;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ExecutionStatsRecord;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Strategies\TemporalPathStrategy;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Config\Repository as ConfigRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class ExecutionStatsLoggerTest extends IntegrationTestCase
{
    private string $basePath;

    private DirectiveConfigInterface $config;

    private FileSystemService $fileSystem;

    private ExecutionStatsLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        ob_start();

        $this->basePath = sys_get_temp_dir().'/directive_logs_'.uniqid();

        $configRepository = new ConfigRepository([
            'directive' => [
                'base_path' => $this->basePath,
            ],
        ]);
        $this->config = new DirectiveConfig($configRepository);
        $this->fileSystem = new FileSystemService;

        $strategy = new TemporalPathStrategy($this->config->basePath());
        $jsonlService = new JsonlService(
            $strategy,
            $this->fileSystem,
            new JsonlContext
        );

        $this->logger = new ExecutionStatsLogger(
            $this->config,
            $this->fileSystem,
            $jsonlService,
            new Console
        );
    }

    protected function tearDown(): void
    {
        ob_end_clean();

        parent::tearDown();

        if (is_dir($this->basePath)) {
            $this->removeDirectory($this->basePath);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    private function createStatsRecord(ExitCode $exitCode = ExitCode::SUCCESS, ?string $error = null): ExecutionStatsRecord
    {
        return new ExecutionStatsRecord(
            command: 'test:command',
            directiveClass: 'App\\Directives\\TestDirective',
            signature: 'test:command {name}',
            exitCode: $exitCode,
            duration: 0.123,
            memoryUsage: 1024,
            peakMemoryUsage: 2048,
            callsCount: 2,
            error: $error,
        );
    }

    public function test_log_writes_to_jsonl_file(): void
    {
        $record = $this->createStatsRecord();

        $this->logger->log($record);
        $this->logger->getJsonlService()->flushBuffer();

        $today = date('Y-m-d');
        $hour = date('H');
        $filePath = $this->basePath.'/'.$today.'/'.$hour.'.jsonl';

        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertNotEmpty($content);

        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('time', $data);
        $this->assertArrayHasKey('level', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('payload', $data);

        $this->assertSame('info', $data['level']);
        $this->assertSame('directive_execution', $data['type']);
        $this->assertSame('test:command', $data['payload']['command']);
        $this->assertSame('App\\Directives\\TestDirective', $data['payload']['directive_class']);
        $this->assertSame(0, $data['payload']['exit_code']);
        $this->assertTrue($data['payload']['success']);
        $this->assertSame(0.123, $data['payload']['duration_seconds']);
        $this->assertSame(1024, $data['payload']['memory_bytes']);
        $this->assertSame('1.00 KB', $data['payload']['memory_human']);
        $this->assertSame(2048, $data['payload']['peak_memory_bytes']);
        $this->assertSame('2.00 KB', $data['payload']['peak_memory_human']);
        $this->assertSame(2, $data['payload']['calls_count']);
    }

    public function test_log_with_error_writes_error_level(): void
    {
        $record = $this->createStatsRecord(ExitCode::RUNTIME_ERROR, 'Something went wrong');

        $this->logger->log($record);
        $this->logger->getJsonlService()->flushBuffer();

        $today = date('Y-m-d');
        $hour = date('H');
        $filePath = $this->basePath.'/'.$today.'/'.$hour.'.jsonl';

        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        $this->assertSame('error', $data['level']);
        $this->assertFalse($data['payload']['success']);
        $this->assertSame('Something went wrong', $data['payload']['error']);
    }

    public function test_log_with_context_includes_context(): void
    {
        $record = $this->createStatsRecord();
        $context = MapCollection::from([
            'user_id' => 123,
            'user_name' => 'John Doe',
        ]);

        $this->logger->log($record, $context);
        $this->logger->getJsonlService()->flushBuffer();

        $today = date('Y-m-d');
        $hour = date('H');
        $filePath = $this->basePath.'/'.$today.'/'.$hour.'.jsonl';

        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        $this->assertArrayHasKey('context', $data['payload']);
        $this->assertSame(123, $data['payload']['context']['user_id']);
        $this->assertSame('John Doe', $data['payload']['context']['user_name']);
    }

    public function test_log_without_context_does_not_include_context(): void
    {
        $record = $this->createStatsRecord();

        $this->logger->log($record, null);
        $this->logger->getJsonlService()->flushBuffer();

        $today = date('Y-m-d');
        $hour = date('H');
        $filePath = $this->basePath.'/'.$today.'/'.$hour.'.jsonl';

        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        $this->assertArrayNotHasKey('context', $data['payload']);
    }

    public function test_set_base_path_changes_log_location(): void
    {
        $newBasePath = sys_get_temp_dir().'/directive_logs_new_'.uniqid();

        $this->logger->setBasePath($newBasePath);

        $record = $this->createStatsRecord();
        $this->logger->log($record);
        $this->logger->getJsonlService()->flushBuffer();

        $today = date('Y-m-d');
        $hour = date('H');
        $filePath = $newBasePath.'/'.$today.'/'.$hour.'.jsonl';

        $this->assertFileExists($filePath);

        if (is_dir($newBasePath)) {
            $this->removeDirectory($newBasePath);
        }
    }

    public function test_get_base_path_returns_current_path(): void
    {
        $basePath = $this->logger->getBasePath();
        $this->assertSame($this->basePath, $basePath);
    }

    public function test_get_summary_returns_empty_summary_when_no_logs(): void
    {
        $summary = $this->logger->getSummary();

        $this->assertEquals(0, $summary['total']);
        $this->assertEquals(0, $summary['success']);
        $this->assertEquals(0, $summary['failed']);
        $this->assertEquals(0, $summary['success_rate']);
        $this->assertEquals(0.0, $summary['avg_duration']);
        $this->assertEquals(0.0, $summary['avg_memory']);
        $this->assertEquals(0, $summary['total_calls']);
        $this->assertEquals(0.0, $summary['avg_calls']);
    }

    public function test_get_summary_returns_correct_statistics(): void
    {
        $this->logger->log($this->createStatsRecord(ExitCode::SUCCESS));
        $this->logger->log($this->createStatsRecord(ExitCode::SUCCESS));
        $this->logger->log($this->createStatsRecord(ExitCode::RUNTIME_ERROR, 'Error'));
        $this->logger->log($this->createStatsRecord(ExitCode::SUCCESS));

        $this->logger->getJsonlService()->flushBuffer();

        $summary = $this->logger->getSummary();

        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, $summary['success']);
        $this->assertSame(1, $summary['failed']);
        $this->assertEquals(75.0, $summary['success_rate']);
        $this->assertGreaterThan(0, $summary['avg_duration']);
        $this->assertGreaterThan(0, $summary['avg_memory']);
        $this->assertGreaterThan(0, $summary['total_calls']);
        $this->assertGreaterThan(0, $summary['avg_calls']);
    }

    public function test_format_memory_handles_bytes(): void
    {
        $reflection = new \ReflectionClass(ExecutionStatsLogger::class);
        $method = $reflection->getMethod('formatMemory');

        $result = $method->invoke($this->logger, 500);
        $this->assertSame('500 B', $result);

        $result = $method->invoke($this->logger, 1024);
        $this->assertSame('1.00 KB', $result);

        $result = $method->invoke($this->logger, 2048);
        $this->assertSame('2.00 KB', $result);

        $result = $method->invoke($this->logger, 1048576);
        $this->assertSame('1.00 MB', $result);

        $result = $method->invoke($this->logger, 2097152);
        $this->assertSame('2.00 MB', $result);
    }

    public function test_get_jsonl_service_returns_instance(): void
    {
        $service = $this->logger->getJsonlService();
        $this->assertInstanceOf(JsonlService::class, $service);
    }

    public function test_multiple_logs_append_to_same_file(): void
    {
        $record1 = $this->createStatsRecord(ExitCode::SUCCESS);
        $record2 = $this->createStatsRecord(ExitCode::RUNTIME_ERROR, 'Error');

        $this->logger->log($record1);
        $this->logger->log($record2);
        $this->logger->getJsonlService()->flushBuffer();

        $today = date('Y-m-d');
        $hour = date('H');
        $filePath = $this->basePath.'/'.$today.'/'.$hour.'.jsonl';

        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $lines = explode("\n", trim($content));

        $this->assertCount(2, $lines);

        $data1 = json_decode($lines[0], true);
        $data2 = json_decode($lines[1], true);

        $this->assertTrue($data1['payload']['success']);
        $this->assertFalse($data2['payload']['success']);
        $this->assertArrayNotHasKey('error', $data1['payload']);
        $this->assertSame('Error', $data2['payload']['error']);
    }

    public function test_log_handles_exceptions_gracefully(): void
    {
        $invalidBasePath = '/invalid/path/that/does/not/exist';

        $mockConfig = $this->createMock(DirectiveConfigInterface::class);
        $mockConfig->method('basePath')->willReturn($invalidBasePath);

        $strategy = new TemporalPathStrategy($invalidBasePath);
        $jsonlService = new JsonlService(
            $strategy,
            $this->fileSystem,
            new JsonlContext
        );

        $logger = new ExecutionStatsLogger(
            $mockConfig,
            $this->fileSystem,
            $jsonlService,
            new Console
        );

        $record = $this->createStatsRecord();

        // ✅ L'exception est capturée et un warning est affiché
        $logger->log($record);

        $logger->getJsonlService()->flushBuffer();

        $today = date('Y-m-d');
        $hour = date('H');
        $filePath = $invalidBasePath.'/'.$today.'/'.$hour.'.jsonl';
        $this->assertFileDoesNotExist($filePath);
    }
}
