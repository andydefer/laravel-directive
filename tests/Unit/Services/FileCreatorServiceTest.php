<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Enums\FileCreationStep;
use AndyDefer\Directive\Records\PathSegmentsRecord;
use AndyDefer\Directive\Records\ReplacementRecord;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Services\PathBuilderService;
use AndyDefer\Directive\Services\PathSegmentsParserService;
use AndyDefer\Directive\Services\StringCaseConverterService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\EnumService;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Metadata\AllowMockObjectsWithoutExpectations;

final class FileCreatorServiceTest extends TestCase
{
    private FileCreatorService $service;
    private FileCreatorConfig $config;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/file_creator_service_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, PermissionMode::DIRECTORY->value(), true);

        $this->config = new FileCreatorConfig(new EnumService());

        $filesystem = new FileSystemService();
        $caseConverter = new StringCaseConverterService();
        $pathParser = new PathSegmentsParserService($caseConverter);
        $pathBuilder = new PathBuilderService($this->config);

        $this->service = new FileCreatorService(
            $this->config,
            $filesystem,
            $pathParser,
            $pathBuilder,
            $caseConverter
        );

        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    // ==================== File Creation Tests ====================

    public function test_create_file_success_creates_file_with_replaced_content(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Hello {{ name }}!');
        $destination = $this->tempDir . '/output.php';

        $replacements = new ReplacementCollection;
        $replacements->add(new ReplacementRecord('{{ name }}', 'World'));

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($result->success);
        $this->assertFileExists($destination);
        $this->assertEquals('Hello World!', file_get_contents($destination));
        $this->assertTrue($context->isCompleted());
        $this->assertEquals(FileCreationStep::COMPLETED, $context->getCurrentStep());
    }

    public function test_create_file_creates_directory_if_not_exists(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/sub/dir/output.php';
        $replacements = new ReplacementCollection;

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($result->success);
        $this->assertFileExists($destination);
        $this->assertDirectoryExists($this->tempDir . '/sub/dir');
        $this->assertTrue($context->hasCreatedDirectories());
        $this->assertEquals(1, $context->getCreatedDirectoriesCount());
    }

    public function test_create_file_returns_false_when_file_exists_without_force(): void
    {
        // Arrange
        $context = new FileCreationContext(false);
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/exists.php';
        file_put_contents($destination, 'old content');
        $replacements = new ReplacementCollection;

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertFalse($result->success);
        $this->assertEquals('old content', file_get_contents($destination));
        $this->assertTrue($context->isFailed());
        $this->assertEquals(FileCreationStep::FAILED, $context->getCurrentStep());
        $this->assertNotNull($context->getErrorMessage());
    }

    public function test_create_file_overwrites_when_force_true(): void
    {
        // Arrange
        $context = new FileCreationContext(true);
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'New content');
        $destination = $this->tempDir . '/exists.php';
        file_put_contents($destination, 'old content');
        $replacements = new ReplacementCollection;

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($result->success);
        $this->assertEquals('New content', file_get_contents($destination));
    }

    public function test_create_file_returns_false_when_stub_not_found(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/not-exist.stub';
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection;

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertFalse($result->success);
        $this->assertFileDoesNotExist($destination);
        $this->assertTrue($context->isFailed());
        $this->assertStringContainsString('not found', $context->getErrorMessage());
    }

    public function test_create_file_replaces_multiple_variables(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Class {{ class }} extends {{ parent }}');
        $destination = $this->tempDir . '/output.php';

        $replacements = new ReplacementCollection;
        $replacements->add(new ReplacementRecord('{{ class }}', 'UserTask'));
        $replacements->add(new ReplacementRecord('{{ parent }}', 'AbstractTask'));

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($result->success);
        $this->assertEquals('Class UserTask extends AbstractTask', file_get_contents($destination));
    }

    public function test_create_file_with_empty_replacement(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Value: {{ value }}');
        $destination = $this->tempDir . '/output.php';

        $replacements = new ReplacementCollection;
        $replacements->add(new ReplacementRecord('{{ value }}', ''));

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($result->success);
        $this->assertEquals('Value: ', file_get_contents($destination));
    }

    public function test_create_file_tracks_transformation_steps_in_context(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Hello {{ name }}!');
        $destination = $this->tempDir . '/output.php';

        $replacements = new ReplacementCollection;
        $replacements->add(new ReplacementRecord('{{ name }}', 'World'));

        // Act
        $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertGreaterThan(0, $context->getTransformationLogs()->count());
        $this->assertEquals(FileCreationStep::COMPLETED, $context->getCurrentStep());
        $this->assertTrue($context->hasCreatedFiles());
        $this->assertEquals(1, $context->getCreatedFilesCount());
    }

    // ==================== createFileFromName Tests ====================

    public function test_create_file_from_name_creates_file_with_constructed_path(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content for {{ name }}');
        $baseDirectory = '/app/Tasks/';

        $replacements = new ReplacementCollection;
        $replacements->add(new ReplacementRecord('{{ name }}', 'UserTask'));

        // Act
        $result = $this->service->createFileFromName(
            stubPath: $stubPath,
            name: 'Admin/UserTask',
            baseDirectory: $baseDirectory,
            replacements: $replacements,
            context: $context,
        );

        // Assert
        $this->assertTrue($result->success);
        $this->assertStringContainsString('UserTask', $result->destinationPath);
        $this->assertEquals('Content for UserTask', file_get_contents($result->destinationPath));
    }

    public function test_create_file_from_name_adds_automatic_replacements(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Class: {{className}}, Kebab: {{kebabClassName}}');
        $baseDirectory = '/app/Models/';

        $replacements = new ReplacementCollection;

        // Act
        $result = $this->service->createFileFromName(
            stubPath: $stubPath,
            name: 'Admin/ProductModel',
            baseDirectory: $baseDirectory,
            replacements: $replacements,
            context: $context,
        );

        // Assert
        $this->assertTrue($result->success);
        $content = file_get_contents($result->destinationPath);
        $this->assertStringContainsString('Class: ProductModel', $content);
        $this->assertStringContainsString('Kebab: product-model', $content);
    }
    // ==================== Service Accessor Tests ====================

    public function test_get_case_converter_returns_service(): void
    {
        $result = $this->service->getCaseConverter();
        $this->assertInstanceOf(StringCaseConverterService::class, $result);
    }

    public function test_get_path_parser_returns_service(): void
    {
        $result = $this->service->getPathParser();
        $this->assertInstanceOf(PathSegmentsParserService::class, $result);
    }

    public function test_get_path_builder_returns_service(): void
    {
        $result = $this->service->getPathBuilder();
        $this->assertInstanceOf(PathBuilderService::class, $result);
    }

    // ==================== Context Tracking Tests ====================

    public function test_context_tracks_current_step(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection;

        // Act
        $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertEquals(FileCreationStep::COMPLETED, $context->getCurrentStep());
    }

    public function test_context_tracks_created_directories_and_files(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/sub/dir/output.php';
        $replacements = new ReplacementCollection;

        // Act
        $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($context->hasCreatedDirectories());
        $this->assertTrue($context->hasCreatedFiles());
        $this->assertEquals(1, $context->getCreatedDirectoriesCount());
        $this->assertEquals(1, $context->getCreatedFilesCount());
    }

    public function test_context_can_be_reset(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection;

        $this->service->createFile($stubPath, $destination, $replacements, $context);
        $this->assertTrue($context->isCompleted());

        // Act
        $context->reset();

        // Assert
        $this->assertFalse($context->isCompleted());
        $this->assertFalse($context->hasError());
        $this->assertEquals(0, $context->getCreatedDirectoriesCount());
        $this->assertEquals(0, $context->getCreatedFilesCount());
        $this->assertEquals(FileCreationStep::START, $context->getCurrentStep());
    }

    // ==================== Question Methods Tests ====================

    public function test_context_has_error_returns_true_when_error_exists(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/not-exist.stub';
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection;

        // Act
        $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($context->hasError());
        $this->assertTrue($context->isFailed());
        $this->assertFalse($context->isCompleted());
        $this->assertFalse($context->isInProgress());
    }

    public function test_context_has_error_returns_false_on_success(): void
    {
        // Arrange
        $context = new FileCreationContext;
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection;

        // Act
        $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertFalse($context->hasError());
        $this->assertTrue($context->isCompleted());
        $this->assertFalse($context->isFailed());
        $this->assertFalse($context->isInProgress());
    }

    // ==================== Helper Methods ====================

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
