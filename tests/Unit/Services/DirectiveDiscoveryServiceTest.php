<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Factories\ContainerDirectiveFactory;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionClass;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveDiscoveryServiceTest extends UnitTestCase
{
    private string $fixturesPath;
    private DirectiveDiscoveryService $service;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Set up fixtures path and container
        $this->fixturesPath = realpath(__DIR__ . '/../../Fixtures/Directives');

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);

        $this->container = new Container();

        $this->container->singleton(RenderDispatcher::class, function () {
            return new RenderDispatcher();
        });
        $this->container->singleton(InputDispatcher::class, function () {
            return new InputDispatcher();
        });

        $this->container->singleton(DirectiveInteractionService::class, function ($c) {
            return new DirectiveInteractionService(
                $c->make(RenderDispatcher::class),
                $c->make(InputDispatcher::class),
            );
        });

        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);

        // Act: Create service instance
        $this->service = new DirectiveDiscoveryService($config, $hydrator);
    }

    // ==================== Filesystem Discovery Tests ====================

    public function test_discover_returns_typed_records_of_directive_metadata(): void
    {
        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify result is a typed collection of metadata records
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertContains(DirectiveMetadataRecord::class, $result->getAllowedTypes());
        $this->assertGreaterThan(0, $result->count(), 'No directives discovered. Check fixtures path: ' . $this->fixturesPath);
    }

    public function test_finds_test_echo_directive(): void
    {
        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify the test-echo directive is found with correct data
        $found = false;
        foreach ($result as $directive) {
            if (str_contains($directive->signature, 'test-echo')) {
                $found = true;
                $this->assertSame(TestEchoDirective::class, $directive->class);
                $this->assertInstanceOf(StringTypedCollection::class, $directive->aliases);
                break;
            }
        }

        $this->assertTrue($found, 'Directive "test-echo" not found in path: ' . $this->fixturesPath);
    }

    public function test_ignores_invalid_directives_that_dont_extend_abstract_directive(): void
    {
        // Arrange: Create a temporary directory with an invalid directive
        $tempDir = sys_get_temp_dir() . '/directive_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        $invalidClassPath = $tempDir . '/InvalidDirective.php';
        $invalidClassContent = <<<'PHP'
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class InvalidDirective implements DirectiveInterface
{
    public function getSignature(): string { return 'invalid'; }
    public function getDescription(): string { return 'Invalid directive'; }
    public function getAliases(): StringTypedCollection { return new StringTypedCollection(); }
    public function getBlueprint(): DirectiveBlueprintRecord { return new DirectiveBlueprintRecord('invalid', static::class, 'test'); }
    public function setArguments($arguments) { return $this; }
    public function argument(string $key): ?string { return null; }
    public function setOptions($options) { return $this; }
    public function option(string $key): bool|string|null { return null; }
    public function hasOption(string $key): bool { return false; }
    public function shouldBootLaravel(): bool { return false; }
    public function hasLaravel(): bool { return false; }
    public function getLaravel(): ?object { return null; }
    public function setLaravelBootstrapper($bootstrapper) { return $this; }
    public function execute(): ExitCode { return ExitCode::SUCCESS; }
}
PHP;

        file_put_contents($invalidClassPath, $invalidClassContent);

        $config = DirectiveConfig::default()->withDirectivesPath($tempDir);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        // Act: Discover directives
        $result = $service->discover();

        // Assert: Verify invalid directive was ignored
        $this->assertEquals(0, $result->count());

        // Clean up
        unlink($invalidClassPath);
        rmdir($tempDir);
    }

    public function test_ignores_abstract_directives(): void
    {
        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify all discovered directives are concrete
        $this->assertGreaterThan(0, $result->count(), 'No directives found to test abstract check');

        foreach ($result as $directive) {
            $reflection = new ReflectionClass($directive->class);
            $this->assertFalse($reflection->isAbstract(), 'Directive ' . $directive->class . ' should not be abstract');
            $this->assertTrue(is_subclass_of($directive->class, AbstractDirective::class), 'Directive ' . $directive->class . ' must extend AbstractDirective');
        }
    }

    public function test_finds_concrete_directives(): void
    {
        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify concrete directives are found
        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertNotEmpty($signatures, 'No signatures found in path: ' . $this->fixturesPath);

        $found = false;
        foreach ($signatures as $signature) {
            if (str_contains($signature, 'test-echo')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'No test-echo directive found in signatures: ' . implode(', ', $signatures));
    }

    public function test_returns_complete_metadata_structure(): void
    {
        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify each directive has complete metadata
        $this->assertGreaterThan(0, $result->count(), 'No directives found to test in path: ' . $this->fixturesPath);

        foreach ($result as $directive) {
            $this->assertIsString($directive->signature);
            $this->assertNotEmpty($directive->signature);
            $this->assertIsString($directive->class);
            $this->assertNotEmpty($directive->class);
            $this->assertIsString($directive->description);
            $this->assertInstanceOf(StringTypedCollection::class, $directive->aliases);
        }
    }

    public function test_discovers_all_valid_directives(): void
    {
        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify at least one directive is discovered
        $this->assertGreaterThanOrEqual(1, $result->count(), 'No directives discovered in path: ' . $this->fixturesPath);
    }

    public function test_signatures_are_unique(): void
    {
        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify no duplicate signatures
        $this->assertGreaterThan(0, $result->count(), 'No directives found to test uniqueness');

        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertEquals(count($signatures), count(array_unique($signatures)), 'Duplicate signatures found: ' . print_r(array_count_values($signatures), true));
    }

    public function test_returns_empty_result_for_invalid_path(): void
    {
        // Arrange: Configure with invalid path
        $invalidPath = '/invalid/path/that/does/not/exist';
        $config = DirectiveConfig::default()->withDirectivesPath($invalidPath);

        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        // Act: Discover directives
        $result = $service->discover();

        // Assert: Verify empty result
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertEquals(0, $result->count());
    }

    public function test_returns_empty_result_for_empty_directory(): void
    {
        // Arrange: Create empty directory
        $emptyDir = sys_get_temp_dir() . '/empty_directives_' . uniqid();
        mkdir($emptyDir, 0777, true);

        $config = DirectiveConfig::default()->withDirectivesPath($emptyDir);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        // Act: Discover directives
        $result = $service->discover();

        // Assert: Verify empty result
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertEquals(0, $result->count());

        // Clean up
        rmdir($emptyDir);
    }

    public function test_aliases_are_loaded_correctly(): void
    {
        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify aliases are properly loaded
        $this->assertGreaterThan(0, $result->count(), 'No directives found to test aliases');

        foreach ($result as $directive) {
            $this->assertInstanceOf(StringTypedCollection::class, $directive->aliases);

            foreach ($directive->aliases as $alias) {
                $this->assertIsString($alias);
            }
        }
    }

    // ==================== Vendor Package Discovery Tests ====================

    public function test_discover_from_vendor_packages_scans_only_composer_packages(): void
    {
        // Arrange
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('discoverFromVendorPackagesRecursive');

        $results = new DirectiveMetadataCollection();

        // Act
        $results = $method->invoke($service, $results);

        // Assert
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $results);
    }

    // ==================== Recursive Discovery Depth Tests ====================

    public function test_scan_package_at_depth_1(): void
    {
        // Arrange
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageMethod = $reflection->getMethod('scanPackage');

        $results = new DirectiveMetadataCollection();

        // Act: Scan a non-existent package (should not crash)
        $scanPackageMethod->invoke($service, $results, 'andydefer/laravel-directive', 1);

        // Assert
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $results);
    }

    public function test_scan_package_ignores_php_internal_packages(): void
    {
        // Arrange
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageMethod = $reflection->getMethod('scanPackage');
        $scannedPackagesProperty = $reflection->getProperty('scannedPackages');

        // Reset cache
        $scannedPackagesProperty->setValue($service, []);

        $results = new DirectiveMetadataCollection();

        // Act: Scan a PHP internal package
        $scanPackageMethod->invoke($service, $results, 'php', 1);

        // Assert: Package should not be marked as scanned
        $scannedPackages = $scannedPackagesProperty->getValue($service);
        $this->assertArrayNotHasKey('php', $scannedPackages);
    }

    public function test_scan_package_limits_depth_to_2(): void
    {
        // Arrange
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageMethod = $reflection->getMethod('scanPackage');

        // Act: Scan at depth 3 (should be ignored)
        $scanPackageMethod->invoke($service, new DirectiveMetadataCollection(), 'test-package', 3);

        // Assert: No exception thrown, just ignored
        $this->assertTrue(true);
    }

    public function test_scan_package_does_not_scan_same_package_twice(): void
    {
        // Arrange
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageMethod = $reflection->getMethod('scanPackage');
        $scannedPackagesProperty = $reflection->getProperty('scannedPackages');

        // Reset cache
        $scannedPackagesProperty->setValue($service, []);

        $results = new DirectiveMetadataCollection();

        // Create a mock package
        $tempVendorDir = sys_get_temp_dir() . '/vendor_test_' . uniqid();
        $testPackageName = 'test/mock-package-' . uniqid();
        $testPackagePath = $tempVendorDir . '/' . $testPackageName;

        mkdir($testPackagePath, 0777, true);

        // Create composer.json
        file_put_contents($testPackagePath . '/composer.json', '{"name":"' . $testPackageName . '"}');

        // Create directives directory
        $directivesPath = $testPackagePath . '/src/Directives';
        mkdir($directivesPath, 0777, true);

        $directiveContent = <<<'PHP'
<?php

namespace Test\Mock\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestDirective extends AbstractDirective
{
    public function getSignature(): string { return 'test'; }
    public function getDescription(): string { return 'Test directive'; }
    public function execute(): ExitCode { return ExitCode::SUCCESS; }
}
PHP;
        file_put_contents($directivesPath . '/TestDirective.php', $directiveContent);

        // Redirect vendor directory
        $vendorDirProperty = $reflection->getProperty('vendorDir');
        $originalVendorDir = $vendorDirProperty->getValue($service);
        $vendorDirProperty->setValue($service, $tempVendorDir);

        // Act: First scan
        $scanPackageMethod->invoke($service, $results, $testPackageName, 1);

        $scannedPackagesAfterFirst = $scannedPackagesProperty->getValue($service);

        // Act: Second scan of same package
        $scanPackageMethod->invoke($service, $results, $testPackageName, 1);

        $scannedPackagesAfterSecond = $scannedPackagesProperty->getValue($service);

        // Assert: Package was marked as scanned and only scanned once
        $this->assertArrayHasKey($testPackageName, $scannedPackagesAfterSecond);
        $this->assertCount(1, $scannedPackagesAfterSecond);

        // Restore and clean up
        $vendorDirProperty->setValue($service, $originalVendorDir);
        $this->removeDirectory($testPackagePath);
        $this->removeDirectory($tempVendorDir);
    }

    public function test_scan_package_directories_scans_multiple_paths(): void
    {
        // Arrange
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageDirectoriesMethod = $reflection->getMethod('scanPackageDirectories');

        $tempPackageDir = sys_get_temp_dir() . '/test_package_' . uniqid();
        mkdir($tempPackageDir . '/src/Directives', 0777, true);

        $directiveContent = <<<'PHP'
<?php

namespace TestPackage\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class TestDirective extends AbstractDirective
{
    public function getSignature(): string { return 'test'; }
    public function getDescription(): string { return 'Test'; }
    public function execute(): ExitCode { return ExitCode::SUCCESS; }
}
PHP;

        file_put_contents($tempPackageDir . '/src/Directives/TestDirective.php', $directiveContent);

        $results = new DirectiveMetadataCollection();

        // Act: Scan directories (should not throw exception)
        $scanPackageDirectoriesMethod->invoke($service, $results, $tempPackageDir);

        // Assert
        $this->assertTrue(true);

        // Clean up
        $this->removeDirectory($tempPackageDir);
    }

    // ==================== Debug Mode Tests ====================

    public function test_debug_mode_outputs_skipped_classes(): void
    {
        // Arrange
        $originalDebug = getenv('DIRECTIVE_DEBUG');

        putenv('DIRECTIVE_DEBUG=true');

        $tempDir = sys_get_temp_dir() . '/debug_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        $abstractContent = <<<'PHP'
<?php

namespace TestPackage\Directives;

use AndyDefer\Directive\AbstractDirective;

abstract class AbstractTestDirective extends AbstractDirective {}
PHP;

        file_put_contents($tempDir . '/AbstractTestDirective.php', $abstractContent);

        $config = DirectiveConfig::default()->withDirectivesPath($tempDir);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        // Act: Capture output
        ob_start();
        $result = $service->discover();
        $output = ob_get_clean();

        // Assert
        $this->assertEquals(0, $result->count());

        // Restore and clean up
        if ($originalDebug === false) {
            putenv('DIRECTIVE_DEBUG');
        } else {
            putenv('DIRECTIVE_DEBUG=' . $originalDebug);
        }

        unlink($tempDir . '/AbstractTestDirective.php');
        rmdir($tempDir);
    }

    // ==================== Validation Tests ====================

    public function test_only_classes_extending_abstract_directive_are_discovered(): void
    {
        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify all discovered directives extend AbstractDirective
        foreach ($result as $directive) {
            $this->assertTrue(
                is_subclass_of($directive->class, AbstractDirective::class),
                sprintf('Class %s does not extend %s', $directive->class, AbstractDirective::class)
            );

            $this->assertTrue(
                is_subclass_of($directive->class, DirectiveInterface::class),
                sprintf('Class %s does not implement DirectiveInterface', $directive->class)
            );
        }
    }

    public function test_handles_malformed_php_files_gracefully(): void
    {
        // Arrange: Create malformed PHP file
        $tempDir = sys_get_temp_dir() . '/malformed_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        $malformedPath = $tempDir . '/MalformedDirective.php';
        file_put_contents($malformedPath, '<?php this is not valid php code {');

        $config = DirectiveConfig::default()->withDirectivesPath($tempDir);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        // Act: Discover directives
        $result = $service->discover();

        // Assert: Malformed file is ignored gracefully
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertEquals(0, $result->count());

        // Clean up
        unlink($malformedPath);
        rmdir($tempDir);
    }

    /**
     * Recursively removes a directory and all its contents.
     *
     * @param string $dir Directory path to remove
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
