<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Traits;

use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Tests\Fixtures\Directives\FileCreatorTestDirective;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class FileCreatorTest extends TestCase
{
    private FileCreatorTestDirective $directive;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/file_creator_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $interaction = $this->createMock(DirectiveInteractionService::class);
        $this->directive = new FileCreatorTestDirective($interaction);

        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    // ==================== Tests de conversion de chaînes ====================

    public function test_to_pascal_case_from_kebab(): void
    {
        // Arrange
        $input = 'user-profile';
        $expected = 'UserProfile';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'toPascalCase', [$input]);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_to_pascal_case_from_snake(): void
    {
        // Arrange
        $input = 'user_profile';
        $expected = 'UserProfile';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'toPascalCase', [$input]);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_to_pascal_case_with_multiple_words(): void
    {
        // Arrange
        $input = 'send-welcome-email-task';
        $expected = 'SendWelcomeEmailTask';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'toPascalCase', [$input]);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_to_kebab_case(): void
    {
        // Arrange
        $input = 'SendWelcomeEmailTask';
        $expected = 'send-welcome-email-task';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'toKebabCase', [$input]);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_to_kebab_case_with_single_word(): void
    {
        // Arrange
        $input = 'User';
        $expected = 'user';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'toKebabCase', [$input]);

        // Assert
        $this->assertEquals($expected, $result);
    }

    // ==================== Tests d'extraction de chemins ====================

    public function test_extract_path_segments_simple_name(): void
    {
        // Arrange
        $input = 'UserRepository';
        $expected = [
            'segments' => [],
            'className' => 'UserRepository',
            'subPath' => '',
            'fullPath' => 'UserRepository'
        ];

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'extractPathSegments', [$input]);

        // Assert
        $this->assertEquals($expected['segments'], $result['segments']);
        $this->assertEquals($expected['className'], $result['className']);
        $this->assertEquals($expected['subPath'], $result['subPath']);
        $this->assertEquals($expected['fullPath'], $result['fullPath']);
    }

    public function test_extract_path_segments_with_subdirectory(): void
    {
        // Arrange
        $input = 'admin/user/UserRepository';
        $expected = [
            'segments' => ['admin', 'user'],
            'className' => 'UserRepository',
            'subPath' => 'Admin/User',
            'fullPath' => 'Admin/User/UserRepository'
        ];

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'extractPathSegments', [$input]);

        // Assert
        $this->assertEquals($expected['segments'], $result['segments']);
        $this->assertEquals($expected['className'], $result['className']);
        $this->assertEquals($expected['subPath'], $result['subPath']);
        $this->assertEquals($expected['fullPath'], $result['fullPath']);
    }

    public function test_extract_path_segments_uppercases_subdirectories(): void
    {
        // Arrange
        $input = 'admin/user/profile';
        $expected = [
            'segments' => ['admin', 'user'],
            'className' => 'profile',
            'subPath' => 'Admin/User',
            'fullPath' => 'Admin/User/profile'
        ];

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'extractPathSegments', [$input]);

        // Assert
        $this->assertEquals($expected['segments'], $result['segments']);
        $this->assertEquals($expected['className'], $result['className']);
        $this->assertEquals($expected['subPath'], $result['subPath']);
        $this->assertEquals($expected['fullPath'], $result['fullPath']);
    }

    // ==================== Tests de construction de namespace ====================

    public function test_build_namespace_without_subpath(): void
    {
        // Arrange
        $baseNamespace = 'App\\Tasks';
        $subPath = '';
        $expected = 'App\\Tasks';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'buildNamespace', [$baseNamespace, $subPath]);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function test_build_namespace_with_subpath(): void
    {
        // Arrange
        $baseNamespace = 'App\\Tasks';
        $subPath = 'Admin/User';
        $expected = 'App\\Tasks\\Admin\\User';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'buildNamespace', [$baseNamespace, $subPath]);

        // Assert
        $this->assertEquals($expected, $result);
    }

    // ==================== Tests de chemin absolu ====================

    public function test_get_app_path_without_subpath(): void
    {
        // Arrange
        $baseDir = '/app/Tasks/';
        $className = 'UserTask';
        $subPath = '';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'getAppPath', [$baseDir, $className, $subPath]);

        // Assert
        $this->assertStringEndsWith('/app/Tasks/UserTask.php', $result);
    }

    public function test_get_app_path_with_subpath(): void
    {
        // Arrange
        $baseDir = '/app/Tasks/';
        $className = 'UserTask';
        $subPath = 'Admin';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'getAppPath', [$baseDir, $className, $subPath]);

        // Assert
        $this->assertStringEndsWith('/app/Tasks/Admin/UserTask.php', $result);
    }

    // ==================== Tests de création de fichier ====================

    public function test_create_file_success(): void
    {
        // Arrange
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Hello {{ name }}!');
        $destination = $this->tempDir . '/output.php';
        $replacements = ['{{ name }}' => 'World'];

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'createFile', [
            $stubPath,
            $destination,
            $replacements,
            false
        ]);

        // Assert
        $this->assertTrue($result);
        $this->assertFileExists($destination);
        $this->assertEquals('Hello World!', file_get_contents($destination));
    }

    public function test_create_file_creates_directory(): void
    {
        // Arrange
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/sub/dir/output.php';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'createFile', [
            $stubPath,
            $destination,
            [],
            false
        ]);

        // Assert
        $this->assertTrue($result);
        $this->assertFileExists($destination);
        $this->assertDirectoryExists($this->tempDir . '/sub/dir');
    }

    public function test_create_file_returns_false_when_file_exists_without_force(): void
    {
        // Arrange
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Content');
        $destination = $this->tempDir . '/exists.php';
        file_put_contents($destination, 'old content');

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'createFile', [
            $stubPath,
            $destination,
            [],
            false
        ]);

        // Assert
        $this->assertFalse($result);
        $this->assertEquals('old content', file_get_contents($destination));
    }

    public function test_create_file_overwrites_when_force_true(): void
    {
        // Arrange
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'New content');
        $destination = $this->tempDir . '/exists.php';
        file_put_contents($destination, 'old content');

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'createFile', [
            $stubPath,
            $destination,
            [],
            true
        ]);

        // Assert
        $this->assertTrue($result);
        $this->assertEquals('New content', file_get_contents($destination));
    }

    public function test_create_file_returns_false_when_stub_not_found(): void
    {
        // Arrange
        $stubPath = $this->tempDir . '/not-exist.stub';
        $destination = $this->tempDir . '/output.php';

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'createFile', [
            $stubPath,
            $destination,
            [],
            false
        ]);

        // Assert
        $this->assertFalse($result);
        $this->assertFileDoesNotExist($destination);
    }

    public function test_create_file_replaces_multiple_variables(): void
    {
        // Arrange
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Class {{ class }} extends {{ parent }}');
        $destination = $this->tempDir . '/output.php';
        $replacements = [
            '{{ class }}' => 'UserTask',
            '{{ parent }}' => 'AbstractTask',
        ];

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'createFile', [
            $stubPath,
            $destination,
            $replacements,
            false
        ]);

        // Assert
        $this->assertTrue($result);
        $this->assertEquals('Class UserTask extends AbstractTask', file_get_contents($destination));
    }

    public function test_create_file_with_empty_replacement(): void
    {
        // Arrange
        $stubPath = $this->tempDir . '/test.stub';
        file_put_contents($stubPath, 'Value: {{ value }}');
        $destination = $this->tempDir . '/output.php';
        $replacements = ['{{ value }}' => ''];

        // Act
        $result = $this->invokeProtectedMethod($this->directive, 'createFile', [
            $stubPath,
            $destination,
            $replacements,
            false
        ]);

        // Assert
        $this->assertTrue($result);
        $this->assertEquals('Value: ', file_get_contents($destination));
    }

    // ==================== Méthode utilitaire ====================

    private function invokeProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        return $method->invoke($object, ...$parameters);
    }

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
