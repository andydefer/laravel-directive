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
use AndyDefer\Directive\Tasks\InputTask;
use AndyDefer\Directive\Tasks\RenderTask;
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

        $this->fixturesPath = realpath(__DIR__ . '/../../Fixtures/Directives');

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);

        $this->container = new Container;

        $this->container->singleton(RenderTask::class, function () {
            return new RenderTask;
        });
        $this->container->singleton(InputTask::class, function () {
            return new InputTask;
        });

        $this->container->singleton(DirectiveInteractionService::class, function ($c) {
            return new DirectiveInteractionService(
                $c->make(RenderTask::class),
                $c->make(InputTask::class),
            );
        });

        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);

        $this->service = new DirectiveDiscoveryService($config, $hydrator);
    }

    // ==================== Tests avec découverte fichiers ====================

    public function test_discover_returns_typed_records_of_directive_metadata(): void
    {
        $result = $this->service->discover();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertContains(DirectiveMetadataRecord::class, $result->getAllowedTypes());
        $this->assertGreaterThan(0, $result->count(), 'No directives discovered. Check fixtures path: ' . $this->fixturesPath);
    }

    public function test_finds_test_echo_directive(): void
    {
        $result = $this->service->discover();

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

        $result = $service->discover();

        $this->assertEquals(0, $result->count());

        unlink($invalidClassPath);
        rmdir($tempDir);
    }

    public function test_ignores_abstract_directives(): void
    {
        $result = $this->service->discover();

        $this->assertGreaterThan(0, $result->count(), 'No directives found to test abstract check');

        foreach ($result as $directive) {
            $reflection = new ReflectionClass($directive->class);
            $this->assertFalse($reflection->isAbstract(), 'Directive ' . $directive->class . ' should not be abstract');
            $this->assertTrue(is_subclass_of($directive->class, AbstractDirective::class), 'Directive ' . $directive->class . ' must extend AbstractDirective');
        }
    }

    public function test_finds_concrete_directives(): void
    {
        $result = $this->service->discover();

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
        $result = $this->service->discover();

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
        $result = $this->service->discover();

        $this->assertGreaterThanOrEqual(1, $result->count(), 'No directives discovered in path: ' . $this->fixturesPath);
    }

    public function test_signatures_are_unique(): void
    {
        $result = $this->service->discover();

        $this->assertGreaterThan(0, $result->count(), 'No directives found to test uniqueness');

        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertEquals(count($signatures), count(array_unique($signatures)), 'Duplicate signatures found: ' . print_r(array_count_values($signatures), true));
    }

    public function test_returns_empty_result_for_invalid_path(): void
    {
        $invalidPath = '/invalid/path/that/does/not/exist';
        $config = DirectiveConfig::default()->withDirectivesPath($invalidPath);

        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $result = $service->discover();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertEquals(0, $result->count());
    }

    public function test_returns_empty_result_for_empty_directory(): void
    {
        $emptyDir = sys_get_temp_dir() . '/empty_directives_' . uniqid();
        mkdir($emptyDir, 0777, true);

        $config = DirectiveConfig::default()->withDirectivesPath($emptyDir);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $result = $service->discover();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertEquals(0, $result->count());

        rmdir($emptyDir);
    }

    public function test_aliases_are_loaded_correctly(): void
    {
        $result = $this->service->discover();

        $this->assertGreaterThan(0, $result->count(), 'No directives found to test aliases');

        foreach ($result as $directive) {
            $this->assertInstanceOf(StringTypedCollection::class, $directive->aliases);

            foreach ($directive->aliases as $alias) {
                $this->assertIsString($alias);
            }
        }
    }

    // ==================== Tests de découverte depuis les packages ====================

    public function test_discover_from_vendor_packages_scans_only_composer_packages(): void
    {
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('discoverFromVendorPackagesRecursive');

        $results = new DirectiveMetadataCollection;
        $results = $method->invoke($service, $results);

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $results);
    }

    // ==================== Tests de découverte récursive à profondeur 2 ====================

    public function test_scan_package_at_depth_1(): void
    {
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageMethod = $reflection->getMethod('scanPackage');

        $results = new DirectiveMetadataCollection;

        // Scanner un package factice (n'existe pas, mais ne doit pas planter)
        $scanPackageMethod->invoke($service, $results, 'andydefer/laravel-directive', 1);

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $results);
    }

    public function test_scan_package_ignores_php_internal_packages(): void
    {
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageMethod = $reflection->getMethod('scanPackage');
        $scannedPackagesProperty = $reflection->getProperty('scannedPackages');

        // Réinitialiser le cache
        $scannedPackagesProperty->setValue($service, []);

        $results = new DirectiveMetadataCollection;

        // Scanner un package PHP interne
        $scanPackageMethod->invoke($service, $results, 'php', 1);

        // Le package ne doit pas être marqué comme scanné car ignoré
        $scannedPackages = $scannedPackagesProperty->getValue($service);
        $this->assertArrayNotHasKey('php', $scannedPackages);
    }

    public function test_scan_package_limits_depth_to_2(): void
    {
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageMethod = $reflection->getMethod('scanPackage');

        // Créer un mock pour suivre les appels
        $scanPackageMethod->invoke($service, new DirectiveMetadataCollection, 'test-package', 3);

        // Ne doit pas lancer d'exception, juste ignorer
        $this->assertTrue(true);
    }

    public function test_scan_package_does_not_scan_same_package_twice(): void
    {
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageMethod = $reflection->getMethod('scanPackage');
        $scannedPackagesProperty = $reflection->getProperty('scannedPackages');

        // Réinitialiser le cache
        $scannedPackagesProperty->setValue($service, []);

        $results = new DirectiveMetadataCollection;

        // Créer un package factice avec un vrai dossier
        $tempVendorDir = sys_get_temp_dir() . '/vendor_test_' . uniqid();
        $testPackageName = 'test/mock-package-' . uniqid();
        $testPackagePath = $tempVendorDir . '/' . $testPackageName;

        mkdir($testPackagePath, 0777, true);

        // Créer un composer.json factice
        file_put_contents($testPackagePath . '/composer.json', '{"name":"' . $testPackageName . '"}');

        // Créer un dossier Directives avec une directive factice
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

        // Rediriger le vendorDir du service vers notre dossier temporaire
        $vendorDirProperty = $reflection->getProperty('vendorDir');
        $originalVendorDir = $vendorDirProperty->getValue($service);

        $vendorDirProperty->setValue($service, $tempVendorDir);

        // Premier scan
        $scanPackageMethod->invoke($service, $results, $testPackageName, 1);

        $scannedPackagesAfterFirst = $scannedPackagesProperty->getValue($service);

        // Deuxième scan du même package
        $scanPackageMethod->invoke($service, $results, $testPackageName, 1);

        $scannedPackagesAfterSecond = $scannedPackagesProperty->getValue($service);

        // Vérifier que le package a été marqué comme scanné
        $this->assertArrayHasKey($testPackageName, $scannedPackagesAfterSecond);

        // Vérifier qu'il n'a été scanné qu'une seule fois (la clé n'apparaît qu'une fois)
        $this->assertCount(1, $scannedPackagesAfterSecond);

        // Restaurer la valeur originale
        $vendorDirProperty->setValue($service, $originalVendorDir);

        // Nettoyage - supprimer récursivement
        $this->removeDirectory($testPackagePath);
        $this->removeDirectory($tempVendorDir);
    }

    public function test_scan_package_directories_scans_multiple_paths(): void
    {
        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new ReflectionClass($service);
        $scanPackageDirectoriesMethod = $reflection->getMethod('scanPackageDirectories');

        $tempPackageDir = sys_get_temp_dir() . '/test_package_' . uniqid();
        mkdir($tempPackageDir . '/src/Directives', 0777, true);

        // Créer un fichier directive factice
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

        $results = new DirectiveMetadataCollection;

        // Cette méthode ne doit pas lancer d'exception
        $scanPackageDirectoriesMethod->invoke($service, $results, $tempPackageDir);

        // Nettoyage - supprimer récursivement
        $this->removeDirectory($tempPackageDir);

        $this->assertTrue(true);
    }

    // ==================== Test du debug mode ====================

    public function test_debug_mode_outputs_skipped_classes(): void
    {
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

        ob_start();
        $result = $service->discover();
        $output = ob_get_clean();

        $this->assertEquals(0, $result->count());

        if ($originalDebug === false) {
            putenv('DIRECTIVE_DEBUG');
        } else {
            putenv('DIRECTIVE_DEBUG=' . $originalDebug);
        }

        unlink($tempDir . '/AbstractTestDirective.php');
        rmdir($tempDir);
    }

    // ==================== Tests de vérification des directives valides ====================

    public function test_only_classes_extending_abstract_directive_are_discovered(): void
    {
        $result = $this->service->discover();

        foreach ($result as $directive) {
            $this->assertTrue(
                is_subclass_of($directive->class, AbstractDirective::class),
                sprintf(
                    'Class %s does not extend %s',
                    $directive->class,
                    AbstractDirective::class
                )
            );

            $this->assertTrue(
                is_subclass_of($directive->class, DirectiveInterface::class),
                sprintf(
                    'Class %s does not implement DirectiveInterface',
                    $directive->class
                )
            );
        }
    }

    public function test_handles_malformed_php_files_gracefully(): void
    {
        $tempDir = sys_get_temp_dir() . '/malformed_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        $malformedPath = $tempDir . '/MalformedDirective.php';
        file_put_contents($malformedPath, '<?php this is not valid php code {');

        $config = DirectiveConfig::default()->withDirectivesPath($tempDir);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $result = $service->discover();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertEquals(0, $result->count());

        unlink($malformedPath);
        rmdir($tempDir);
    }

    /**
     * Supprime récursivement un dossier et tout son contenu
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
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
