<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Services;

use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
final class ComposerReaderServiceTest extends IntegrationTestCase
{
    private string $tempDir;

    private FileSystemService $fileSystem;

    private DirectiveConfigInterface $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/composer_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->fileSystem = new FileSystemService;

        // Créer un config mock qui pointe vers le tempDir
        $configRepository = $this->createMock(ConfigRepository::class);
        $configRepository->method('get')
            ->willReturnMap([
                ['directive.base_path', null, $this->tempDir],
            ]);

        $this->config = new DirectiveConfig($configRepository);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->tempDir)) {
            $this->fileSystem->deleteDirectory($this->tempDir);
        }
    }

    private function createComposerJson(array $data): void
    {
        $this->fileSystem->put(
            $this->tempDir.'/composer.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function test_get_require_returns_dependencies(): void
    {
        $composerData = [
            'require' => [
                'andydefer/domain-structures' => '^1.0',
                'laravel/framework' => '^10.0',
            ],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);
        $result = $reader->getRequire();

        $this->assertSame('^1.0', $result['andydefer/domain-structures']);
        $this->assertSame('^10.0', $result['laravel/framework']);
    }

    public function test_get_require_dev_returns_dev_dependencies(): void
    {
        $composerData = [
            'require-dev' => [
                'phpunit/phpunit' => '^10.0',
                'mockery/mockery' => '^1.6',
            ],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);
        $result = $reader->getRequireDev();

        $this->assertSame('^10.0', $result['phpunit/phpunit']);
        $this->assertSame('^1.6', $result['mockery/mockery']);
    }

    public function test_get_all_dependencies_merges_require_and_require_dev(): void
    {
        $composerData = [
            'require' => [
                'andydefer/domain-structures' => '^1.0',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^10.0',
            ],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);
        $result = $reader->getAllDependencies();

        $this->assertCount(2, $result);
        $this->assertSame('^1.0', $result['andydefer/domain-structures']);
        $this->assertSame('^10.0', $result['phpunit/phpunit']);
    }

    public function test_get_package_names_returns_package_names(): void
    {
        $composerData = [
            'require' => [
                'andydefer/domain-structures' => '^1.0',
                'laravel/framework' => '^10.0',
                'php' => '^8.1', // Should be ignored
            ],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);
        $result = $reader->getPackageNames();

        $this->assertContains('andydefer/domain-structures', $result);
        $this->assertContains('laravel/framework', $result);
        $this->assertNotContains('php', $result);
    }

    public function test_get_vendor_directories_returns_vendor_names(): void
    {
        $composerData = [
            'require' => [
                'andydefer/domain-structures' => '^1.0',
                'laravel/framework' => '^10.0',
            ],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);
        $result = $reader->getVendorDirectories();

        $this->assertContains('andydefer', $result);
        $this->assertContains('laravel', $result);
        $this->assertCount(2, $result);
    }

    public function test_has_package_returns_true_when_package_exists(): void
    {
        $composerData = [
            'require' => [
                'andydefer/domain-structures' => '^1.0',
            ],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);

        $this->assertTrue($reader->hasPackage('andydefer/domain-structures'));
        $this->assertFalse($reader->hasPackage('unknown/package'));
    }

    public function test_get_package_version_returns_version(): void
    {
        $composerData = [
            'require' => [
                'andydefer/domain-structures' => '^1.0',
            ],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);

        $this->assertSame('^1.0', $reader->getPackageVersion('andydefer/domain-structures'));
        $this->assertNull($reader->getPackageVersion('unknown/package'));
    }

    public function test_get_autoload_returns_autoload_config(): void
    {
        $composerData = [
            'autoload' => [
                'psr-4' => [
                    'AndyDefer\\' => 'src/',
                ],
            ],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);
        $result = $reader->getAutoload();

        $this->assertArrayHasKey('psr-4', $result);
        $this->assertSame(['AndyDefer\\' => 'src/'], $result['psr-4']);
    }

    public function test_get_autoload_dev_returns_autoload_dev_config(): void
    {
        $composerData = [
            'autoload-dev' => [
                'psr-4' => [
                    'AndyDefer\\Tests\\' => 'tests/',
                ],
            ],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);
        $result = $reader->getAutoloadDev();

        $this->assertArrayHasKey('psr-4', $result);
        $this->assertSame(['AndyDefer\\Tests\\' => 'tests/'], $result['psr-4']);
    }

    public function test_get_vendor_dir_returns_vendor_path(): void
    {
        $reader = new ComposerReaderService($this->config, $this->fileSystem);

        $this->assertSame($this->tempDir.'/vendor', $reader->getVendorDir());
    }

    public function test_throws_exception_when_composer_json_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('composer.json not found');

        // Ne pas créer composer.json
        $reader = new ComposerReaderService($this->config, $this->fileSystem);
        $reader->getRequire();
    }

    public function test_throws_exception_when_composer_json_is_invalid(): void
    {
        $this->fileSystem->put(
            $this->tempDir.'/composer.json',
            '{invalid json'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in composer.json');

        $reader = new ComposerReaderService($this->config, $this->fileSystem);
        $reader->getRequire();
    }

    public function test_returns_empty_array_when_no_require(): void
    {
        $composerData = [
            'name' => 'my/package',
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);

        $this->assertSame([], $reader->getRequire());
        $this->assertSame([], $reader->getRequireDev());
        $this->assertSame([], $reader->getAllDependencies());
        $this->assertSame([], $reader->getPackageNames());
    }

    public function test_handles_composer_json_with_only_name(): void
    {
        $composerData = [
            'name' => 'my/package',
            'require' => [],
            'require-dev' => [],
        ];

        $this->createComposerJson($composerData);

        $reader = new ComposerReaderService($this->config, $this->fileSystem);

        $this->assertSame([], $reader->getRequire());
        $this->assertSame([], $reader->getRequireDev());
        $this->assertSame([], $reader->getAllDependencies());
        $this->assertSame([], $reader->getPackageNames());
        $this->assertSame([], $reader->getVendorDirectories());
    }
}
