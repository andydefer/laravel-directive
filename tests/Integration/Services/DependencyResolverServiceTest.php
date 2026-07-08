<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Services;

use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class DependencyResolverServiceTest extends IntegrationTestCase
{
    private string $tempDir;

    private ComposerReaderService $composerReader;

    private FileSystemInterface $fileSystem;

    private DependencyResolverService $resolver;

    private DirectiveConfigInterface $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/dependency_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->fileSystem = new FileSystemService;

        // Créer un config mock qui pointe vers le tempDir
        $configRepository = $this->createMock(ConfigRepository::class);
        $configRepository->method('get')
            ->willReturnMap([
                ['directive.base_path', null, $this->tempDir],
            ]);

        $this->config = new DirectiveConfig($configRepository);
        $this->composerReader = new ComposerReaderService($this->config, $this->fileSystem);
        $this->resolver = new DependencyResolverService($this->composerReader, $this->fileSystem);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = glob($dir.'/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->removeDirectory($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dir);
    }

    private function createComposerJson(string $path, array $data): void
    {
        file_put_contents(
            $path.'/composer.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function createVendorPackage(string $package, array $composerData): void
    {
        $packagePath = $this->tempDir.'/vendor/'.$package;
        mkdir($packagePath, 0777, true);
        $this->createComposerJson($packagePath, $composerData);
    }

    private function createRootComposer(array $data): void
    {
        $this->createComposerJson($this->tempDir, $data);
    }

    public function test_resolve_all_returns_all_dependencies(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-a', [
            'require' => [
                'andydefer/package-b' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-b', [
            'require' => [],
        ]);

        $result = $this->resolver->resolveAll();

        $this->assertArrayHasKey('andydefer/package-a', $result);
        $this->assertArrayHasKey('andydefer/package-b', $result);
    }

    public function test_resolve_package_dependencies_returns_specific_package_deps(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-a', [
            'require' => [
                'andydefer/package-b' => '^1.0',
                'andydefer/package-c' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-b', [
            'require' => [],
        ]);

        $this->createVendorPackage('andydefer/package-c', [
            'require' => [],
        ]);

        $result = $this->resolver->resolvePackageDependencies('andydefer/package-a');

        $this->assertArrayHasKey('andydefer/package-a', $result);
        $this->assertArrayHasKey('andydefer/package-b', $result);
        $this->assertArrayHasKey('andydefer/package-c', $result);
    }

    public function test_get_dependency_tree_returns_tree_structure(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-a', [
            'require' => [
                'andydefer/package-b' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-b', [
            'require' => [],
        ]);

        $tree = $this->resolver->getDependencyTree();

        $this->assertArrayHasKey('andydefer/package-a', $tree);
        $this->assertArrayHasKey('andydefer/package-b', $tree['andydefer/package-a']);
    }

    public function test_get_flat_dependencies_returns_collection(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-a', [
            'require' => [
                'andydefer/package-b' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-b', [
            'require' => [],
        ]);

        $result = $this->resolver->getFlatDependencies();

        $this->assertTrue($result->contains('andydefer/package-a'));
        $this->assertTrue($result->contains('andydefer/package-b'));
        $this->assertCount(2, $result);
    }

    public function test_has_circular_dependency_returns_false_when_no_cycle(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-a', [
            'require' => [
                'andydefer/package-b' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-b', [
            'require' => [],
        ]);

        $this->assertFalse($this->resolver->hasCircularDependency());
    }

    public function test_has_circular_dependency_returns_true_when_cycle_exists(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-a', [
            'require' => [
                'andydefer/package-b' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-b', [
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $this->assertTrue($this->resolver->hasCircularDependency());
    }

    public function test_handles_complex_dependency_chain(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-a', [
            'require' => [
                'andydefer/package-b' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-b', [
            'require' => [
                'andydefer/package-c' => '^1.0',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-c', [
            'require' => [],
        ]);

        $result = $this->resolver->resolveAll();

        $this->assertArrayHasKey('andydefer/package-a', $result);
        $this->assertArrayHasKey('andydefer/package-b', $result);
        $this->assertArrayHasKey('andydefer/package-c', $result);
    }

    public function test_skips_php_dependencies(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
                'php' => '^8.1',
            ],
        ]);

        $this->createVendorPackage('andydefer/package-a', [
            'require' => [],
        ]);

        $result = $this->resolver->resolveAll();

        $this->assertArrayHasKey('andydefer/package-a', $result);
        $this->assertArrayNotHasKey('php', $result);
    }

    public function test_handles_missing_vendor_directory_gracefully(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $result = $this->resolver->resolveAll();

        $this->assertArrayNotHasKey('andydefer/package-a', $result);
    }

    public function test_handles_missing_composer_json_in_package(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        mkdir($this->tempDir.'/vendor/andydefer/package-a', 0777, true);

        $result = $this->resolver->resolveAll();

        $this->assertArrayNotHasKey('andydefer/package-a', $result);
    }

    public function test_handles_invalid_composer_json_in_package(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $packagePath = $this->tempDir.'/vendor/andydefer/package-a';
        mkdir($packagePath, 0777, true);
        file_put_contents($packagePath.'/composer.json', '{invalid json');

        $result = $this->resolver->resolveAll();

        $this->assertArrayNotHasKey('andydefer/package-a', $result);
    }

    public function test_resolve_all_with_no_requirements(): void
    {
        $this->createRootComposer([
            'require' => [],
        ]);

        $result = $this->resolver->resolveAll();

        $this->assertEmpty($result);
    }

    public function test_get_flat_dependencies_with_no_dependencies(): void
    {
        $this->createRootComposer([
            'require' => [],
        ]);

        $result = $this->resolver->getFlatDependencies();

        $this->assertTrue($result->isEmpty());
    }

    public function test_dependency_tree_with_no_dependencies(): void
    {
        $this->createRootComposer([
            'require' => [],
        ]);

        $tree = $this->resolver->getDependencyTree();

        $this->assertEmpty($tree);
    }

    public function test_resolve_package_dependencies_with_non_existent_package(): void
    {
        $this->createRootComposer([
            'require' => [
                'andydefer/package-a' => '^1.0',
            ],
        ]);

        $result = $this->resolver->resolvePackageDependencies('andydefer/non-existent');

        $this->assertEmpty($result);
    }
}
