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

        $interaction = $this->createMock(DirectiveInteractionService::class);

        $this->tempDir = sys_get_temp_dir().'/file_creator_test_'.uniqid();
        mkdir($this->tempDir, 0755, true);

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
        $result = $this->directive->exposeToPascalCase('user-profile');
        $this->assertEquals('UserProfile', $result);
    }

    public function test_to_pascal_case_from_snake(): void
    {
        $result = $this->directive->exposeToPascalCase('user_profile');
        $this->assertEquals('UserProfile', $result);
    }

    public function test_to_pascal_case_with_multiple_words(): void
    {
        $result = $this->directive->exposeToPascalCase('send-welcome-email-task');
        $this->assertEquals('SendWelcomeEmailTask', $result);
    }

    public function test_to_kebab_case(): void
    {
        $result = $this->directive->exposeToKebabCase('SendWelcomeEmailTask');
        $this->assertEquals('send-welcome-email-task', $result);
    }

    public function test_to_kebab_case_with_single_word(): void
    {
        $result = $this->directive->exposeToKebabCase('User');
        $this->assertEquals('user', $result);
    }

    // ==================== Tests d'extraction de chemins ====================

    public function test_extract_path_segments_simple_name(): void
    {
        $result = $this->directive->exposeExtractPathSegments('UserRepository');

        $this->assertEquals([], $result['segments']);
        $this->assertEquals('UserRepository', $result['className']);
        $this->assertEquals('', $result['subPath']);
        $this->assertEquals('UserRepository', $result['fullPath']);
    }

    public function test_extract_path_segments_with_subdirectory(): void
    {
        $result = $this->directive->exposeExtractPathSegments('admin/user/UserRepository');

        $this->assertEquals('UserRepository', $result['className']);
        $this->assertEquals('Admin/User', $result['subPath']);
        $this->assertEquals('Admin/User/UserRepository', $result['fullPath']);
    }

    public function test_extract_path_segments_uppercases_subdirectories(): void
    {
        $result = $this->directive->exposeExtractPathSegments('admin/user/profile');

        $this->assertEquals('profile', $result['className']);
        $this->assertEquals('Admin/User', $result['subPath']);
        $this->assertEquals('Admin/User/profile', $result['fullPath']);
    }

    // ==================== Tests de construction de namespace ====================

    public function test_build_namespace_without_subpath(): void
    {
        $result = $this->directive->exposeBuildNamespace('App\\Tasks', '');
        $this->assertEquals('App\\Tasks', $result);
    }

    public function test_build_namespace_with_subpath(): void
    {
        $result = $this->directive->exposeBuildNamespace('App\\Tasks', 'Admin/User');
        $this->assertEquals('App\\Tasks\\Admin\\User', $result);
    }

    // ==================== Tests de chemin absolu ====================

    public function test_get_app_path_without_subpath(): void
    {
        $result = $this->directive->exposeGetAppPath('/app/Tasks/', 'UserTask');
        $this->assertStringEndsWith('/app/Tasks/UserTask.php', $result);
    }

    public function test_get_app_path_with_subpath(): void
    {
        $result = $this->directive->exposeGetAppPath('/app/Tasks/', 'UserTask', 'Admin');
        $this->assertStringEndsWith('/app/Tasks/Admin/UserTask.php', $result);
    }

    // ==================== Tests de création de fichier ====================

    public function test_create_file_success(): void
    {
        $stubPath = $this->tempDir.'/test.stub';
        file_put_contents($stubPath, 'Hello {{ name }}!');

        $destination = $this->tempDir.'/output.php';

        $result = $this->directive->exposeCreateFile($stubPath, $destination, ['{{ name }}' => 'World']);

        $this->assertTrue($result);
        $this->assertFileExists($destination);
        $this->assertEquals('Hello World!', file_get_contents($destination));
    }

    public function test_create_file_creates_directory(): void
    {
        $stubPath = $this->tempDir.'/test.stub';
        file_put_contents($stubPath, 'Content');

        $destination = $this->tempDir.'/sub/dir/output.php';

        $result = $this->directive->exposeCreateFile($stubPath, $destination, []);

        $this->assertTrue($result);
        $this->assertFileExists($destination);
        $this->assertDirectoryExists($this->tempDir.'/sub/dir');
    }

    public function test_create_file_returns_false_when_file_exists_without_force(): void
    {
        $stubPath = $this->tempDir.'/test.stub';
        file_put_contents($stubPath, 'Content');

        $destination = $this->tempDir.'/exists.php';
        file_put_contents($destination, 'old content');

        $result = $this->directive->exposeCreateFile($stubPath, $destination, []);

        $this->assertFalse($result);
        $this->assertStringContainsString('File already exists', $this->directive->getLastError() ?? '');
        $this->assertEquals('old content', file_get_contents($destination));
    }

    public function test_create_file_overwrites_when_force_true(): void
    {
        $stubPath = $this->tempDir.'/test.stub';
        file_put_contents($stubPath, 'New content');

        $destination = $this->tempDir.'/exists.php';
        file_put_contents($destination, 'old content');

        $result = $this->directive->exposeCreateFile($stubPath, $destination, [], true);

        $this->assertTrue($result);
        $this->assertEquals('New content', file_get_contents($destination));
    }

    public function test_create_file_returns_false_when_stub_not_found(): void
    {
        $stubPath = $this->tempDir.'/not-exist.stub';
        $destination = $this->tempDir.'/output.php';

        $result = $this->directive->exposeCreateFile($stubPath, $destination, []);

        $this->assertFalse($result);
        $this->assertStringContainsString('Stub template not found at', $this->directive->getLastError() ?? '');
        $this->assertFileDoesNotExist($destination);
    }

    public function test_create_file_replaces_multiple_variables(): void
    {
        $stubPath = $this->tempDir.'/test.stub';
        file_put_contents($stubPath, 'Class {{ class }} extends {{ parent }}');

        $destination = $this->tempDir.'/output.php';

        $result = $this->directive->exposeCreateFile($stubPath, $destination, [
            '{{ class }}' => 'UserTask',
            '{{ parent }}' => 'AbstractTask',
        ]);

        $this->assertTrue($result);
        $this->assertEquals('Class UserTask extends AbstractTask', file_get_contents($destination));
    }

    public function test_create_file_with_empty_replacement(): void
    {
        $stubPath = $this->tempDir.'/test.stub';
        file_put_contents($stubPath, 'Value: {{ value }}');

        $destination = $this->tempDir.'/output.php';

        $result = $this->directive->exposeCreateFile($stubPath, $destination, ['{{ value }}' => '']);

        $this->assertTrue($result);
        $this->assertEquals('Value: ', file_get_contents($destination));
    }

    // ==================== Méthodes privées ====================

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
