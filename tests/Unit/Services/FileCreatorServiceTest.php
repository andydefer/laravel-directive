<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Enums\FileCreationStep;
use AndyDefer\Directive\Enums\PermissionMode;
use AndyDefer\Directive\Records\PathSegmentsRecord;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\EnumService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileCreatorServiceTest extends TestCase
{
    private FileCreatorService $service;
    private FileCreatorConfig $config;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/file_creator_service_test_' . uniqid();
        mkdir($this->tempDir, PermissionMode::DIRECTORY->value(), true);

        $this->config = new FileCreatorConfig(new EnumService);
        $this->service = new FileCreatorService($this->config);

        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    // ==================== String Conversion Tests ====================

    public function test_to_pascal_case_from_kebab_returns_pascal_case(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $input = 'user-profile';
        $expected = 'UserProfile';

        // Act
        $result = $this->service->toPascalCase($input, $context);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_to_pascal_case_from_snake_returns_pascal_case(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $input = 'user_profile';
        $expected = 'UserProfile';

        // Act
        $result = $this->service->toPascalCase($input, $context);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_to_pascal_case_with_multiple_words_returns_pascal_case(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $input = 'send-welcome-email-task';
        $expected = 'SendWelcomeEmailTask';

        // Act
        $result = $this->service->toPascalCase($input, $context);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_to_kebab_case_returns_kebab_case(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $input = 'SendWelcomeEmailTask';
        $expected = 'send-welcome-email-task';

        // Act
        $result = $this->service->toKebabCase($input, $context);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_to_kebab_case_with_single_word_returns_lowercase(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $input = 'User';
        $expected = 'user';

        // Act
        $result = $this->service->toKebabCase($input, $context);

        // Assert
        $this->assertEquals($expected, $result);
    }

    // ==================== Path Extraction Tests ====================

    public function test_extract_path_segments_simple_name_returns_correct_segments(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $input = 'UserRepository';

        // Act
        $result = $this->service->extractPathSegments($input, $context);

        // Assert
        $this->assertInstanceOf(PathSegmentsRecord::class, $result);
        $this->assertEquals(0, $result->segments->count());
        $this->assertEquals('UserRepository', $result->className);
        $this->assertEquals('', $result->subPath);
        $this->assertEquals('UserRepository', $result->fullPath);
    }

    public function test_extract_path_segments_with_subdirectory_returns_correct_segments(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $input = 'admin/user/UserRepository';
        $expectedSegments = ['admin', 'user'];

        // Act
        $result = $this->service->extractPathSegments($input, $context);

        // Assert
        $this->assertEquals($expectedSegments, $result->segments->toArray());
        $this->assertEquals('UserRepository', $result->className);
        $this->assertEquals('Admin/User', $result->subPath);
        $this->assertEquals('Admin/User/UserRepository', $result->fullPath);
    }

    public function test_extract_path_segments_uppercases_subdirectories(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $input = 'admin/user/profile';
        $expectedSegments = ['admin', 'user'];

        // Act
        $result = $this->service->extractPathSegments($input, $context);

        // Assert
        $this->assertEquals($expectedSegments, $result->segments->toArray());
        $this->assertEquals('profile', $result->className);
        $this->assertEquals('Admin/User', $result->subPath);
        $this->assertEquals('Admin/User/profile', $result->fullPath);
    }

    // ==================== Namespace Building Tests ====================

    public function test_build_namespace_without_subpath_returns_base_namespace(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $segments = new PathSegmentsRecord(
            segments: new StringTypedCollection(),
            pascalSegments: new StringTypedCollection(),
            className: 'UserTask',
            subPath: '',
            fullPath: 'UserTask',
        );
        $baseNamespace = 'App\\Tasks';
        $expected = 'App\\Tasks';

        // Act
        $result = $this->service->buildNamespace($baseNamespace, $segments, $context);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_build_namespace_with_subpath_returns_full_namespace(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $segments = new PathSegmentsRecord(
            segments: new StringTypedCollection(['admin', 'user']),
            pascalSegments: new StringTypedCollection(['Admin', 'User']),
            className: 'UserTask',
            subPath: 'Admin/User',
            fullPath: 'Admin/User/UserTask',
        );
        $baseNamespace = 'App\\Tasks';
        $expected = 'App\\Tasks\\Admin\\User';

        // Act
        $result = $this->service->buildNamespace($baseNamespace, $segments, $context);

        // Assert
        $this->assertEquals($expected, $result);
    }

    // ==================== Path Building Tests ====================

    public function test_get_app_path_without_subpath_returns_correct_path(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $segments = new PathSegmentsRecord(
            segments: new StringTypedCollection(),
            pascalSegments: new StringTypedCollection(),
            className: 'UserTask',
            subPath: '',
            fullPath: 'UserTask',
        );
        $baseDirectory = '/app/Tasks/';

        // Act
        $result = $this->service->getAppPath($baseDirectory, $segments, $context);

        // Assert
        $this->assertStringContainsString('/app/Tasks/UserTask.php', $result);
        $this->assertStringEndsWith('UserTask.php', $result);
    }

    public function test_get_app_path_with_subpath_returns_correct_path(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $segments = new PathSegmentsRecord(
            segments: new StringTypedCollection(['admin']),
            pascalSegments: new StringTypedCollection(['Admin']),
            className: 'UserTask',
            subPath: 'Admin',
            fullPath: 'Admin/UserTask',
        );
        $baseDirectory = '/app/Tasks/';

        // Act
        $result = $this->service->getAppPath($baseDirectory, $segments, $context);

        // Assert
        $this->assertStringContainsString('/app/Tasks/Admin/UserTask.php', $result);
        $this->assertStringEndsWith('UserTask.php', $result);
    }

    // ==================== File Creation Tests ====================

    public function test_create_file_success_creates_file_with_replaced_content(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Hello {{ name }}!');
        $destination = $this->tempDir . '/output.php';

        $replacements = new ReplacementCollection();
        $replacements->addReplacement('{{ name }}', 'World');

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
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/sub/dir/output.php';
        $replacements = new ReplacementCollection();

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
        $replacements = new ReplacementCollection();

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
        $replacements = new ReplacementCollection();

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($result->success);
        $this->assertEquals('New content', file_get_contents($destination));
    }

    public function test_create_file_returns_false_when_stub_not_found(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/not-exist.stub';
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection();

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
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Class {{ class }} extends {{ parent }}');
        $destination = $this->tempDir . '/output.php';

        $replacements = new ReplacementCollection();
        $replacements->addReplacement('{{ class }}', 'UserTask');
        $replacements->addReplacement('{{ parent }}', 'AbstractTask');

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($result->success);
        $this->assertEquals('Class UserTask extends AbstractTask', file_get_contents($destination));
    }

    public function test_create_file_with_empty_replacement(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Value: {{ value }}');
        $destination = $this->tempDir . '/output.php';

        $replacements = new ReplacementCollection();
        $replacements->addReplacement('{{ value }}', '');

        // Act
        $result = $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertTrue($result->success);
        $this->assertEquals('Value: ', file_get_contents($destination));
    }

    public function test_create_file_tracks_transformation_steps_in_context(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Hello {{ name }}!');
        $destination = $this->tempDir . '/output.php';

        $replacements = new ReplacementCollection();
        $replacements->addReplacement('{{ name }}', 'World');

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
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content for {{ name }}');
        $baseDirectory = '/app/Tasks/';

        $replacements = new ReplacementCollection();
        $replacements->addReplacement('{{ name }}', 'UserTask');

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

    // ==================== Context Tracking Tests ====================

    public function test_context_tracks_current_step(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection();

        // Act
        $this->service->createFile($stubPath, $destination, $replacements, $context);

        // Assert
        $this->assertEquals(FileCreationStep::COMPLETED, $context->getCurrentStep());
    }

    public function test_context_tracks_created_directories_and_files(): void
    {
        // Arrange
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/sub/dir/output.php';
        $replacements = new ReplacementCollection();

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
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection();

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
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/not-exist.stub';
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection();

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
        $context = new FileCreationContext();
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/output.php';
        $replacements = new ReplacementCollection();

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
        if (!is_dir($dir)) {
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
