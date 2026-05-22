<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Feature;

use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Factories\ContainerDirectiveFactory;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveRegistrar;
use AndyDefer\Directive\Tasks\AskQuestionTask;
use AndyDefer\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\Directive\Tasks\DisplayErrorTask;
use AndyDefer\Directive\Tasks\DisplayMessageTask;
use AndyDefer\Directive\Tasks\DisplayTableTask;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestPackageDirective;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use Illuminate\Container\Container;

final class DirectiveDiscoveryServiceIntegrationTest extends TestCase
{
    private string $fixturesPath;
    private DirectiveDiscoveryService $service;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Chemin vers les fixtures réelles
        $this->fixturesPath = __DIR__ . '/../Fixtures/Directives';

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);

        // Créer un vrai container
        $this->container = new Container();

        // Enregistrer les tasks dans le container
        $this->container->singleton(DisplayMessageTask::class);
        $this->container->singleton(AskQuestionTask::class);
        $this->container->singleton(ConfirmQuestionTask::class);
        $this->container->singleton(DisplayTableTask::class);
        $this->container->singleton(DisplayErrorTask::class);

        // Créer la factory avec le container
        $factory = new ContainerDirectiveFactory($this->container);

        // Créer l'hydrator avec la factory
        $hydrator = new DirectiveHydratorService($factory);

        // Créer le service de découverte sans registrar d'abord
        $this->service = new DirectiveDiscoveryService($config, $hydrator);
    }

    // ==================== Tests avec découverte fichiers ====================

    public function test_discover_returns_typed_records_of_directive_metadata(): void
    {
        // Arrange & Act
        $result = $this->service->discover();

        // Assert
        $this->assertInstanceOf(TypedCollection::class, $result);
        $this->assertContains(DirectiveMetadataRecord::class, $result->getAllowedTypes());
        $this->assertGreaterThan(0, $result->count());
    }

    public function test_finds_test_echo_directive(): void
    {
        // Arrange & Act
        $result = $this->service->discover();

        // Assert
        $found = false;
        foreach ($result as $directive) {
            if ($directive->signature === 'test:echo') {
                $found = true;
                $this->assertSame('Test echo directive', $directive->description);
                $this->assertSame(TestEchoDirective::class, $directive->class);
                $this->assertInstanceOf(TypedCollection::class, $directive->aliases);
                break;
            }
        }

        $this->assertTrue($found, 'Directive "test:echo" not found in discovered directives');
    }

    public function test_finds_concrete_directives(): void
    {
        // Arrange & Act
        $result = $this->service->discover();

        // Assert
        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertContains('test:echo', $signatures);
    }

    public function test_returns_complete_metadata_structure(): void
    {
        // Arrange & Act
        $result = $this->service->discover();

        // Assert
        foreach ($result as $directive) {
            $this->assertIsString($directive->signature);
            $this->assertNotEmpty($directive->signature);
            $this->assertIsString($directive->class);
            $this->assertNotEmpty($directive->class);
            $this->assertIsString($directive->description);
            $this->assertInstanceOf(TypedCollection::class, $directive->aliases);
        }
    }

    public function test_discovers_all_valid_directives(): void
    {
        // Arrange & Act
        $result = $this->service->discover();

        // Assert
        $files = glob($this->fixturesPath . '/*.php');
        $expectedCount = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'implements DirectiveInterface')) {
                $expectedCount++;
            }
        }

        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    public function test_signatures_are_unique(): void
    {
        // Arrange & Act
        $result = $this->service->discover();

        // Assert
        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertEquals(count($signatures), count(array_unique($signatures)));
    }

    public function test_returns_empty_result_for_invalid_path(): void
    {
        // Arrange
        $invalidPath = '/invalid/path/that/does/not/exist';
        $config = DirectiveConfig::default()->withDirectivesPath($invalidPath);

        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        // Act
        $result = $service->discover();

        // Assert
        $this->assertInstanceOf(TypedCollection::class, $result);
        $this->assertEquals(0, $result->count());
    }

    public function test_ignores_abstract_directives(): void
    {
        // Arrange & Act
        $result = $this->service->discover();

        // Assert
        foreach ($result as $directive) {
            $reflection = new \ReflectionClass($directive->class);
            $this->assertFalse($reflection->isAbstract());
        }
    }

    public function test_aliases_are_loaded_correctly(): void
    {
        // Arrange & Act
        $result = $this->service->discover();

        // Assert
        foreach ($result as $directive) {
            $this->assertInstanceOf(TypedCollection::class, $directive->aliases);

            foreach ($directive->aliases as $alias) {
                $this->assertIsString($alias);
            }
        }
    }

    // ==================== Tests avec Registrar (directives enregistrées) ====================

    public function test_discover_includes_registered_directives_from_registrar(): void
    {
        // Arrange
        $registrar = new DirectiveRegistrar();
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class);
        $registrar->register($classes);

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator, $registrar);

        // Act
        $result = $service->discover();

        // Assert
        $found = false;
        foreach ($result as $directive) {
            if ($directive->signature === 'test:package') {
                $found = true;
                $this->assertSame('Test directive from external package', $directive->description);
                $this->assertSame(TestPackageDirective::class, $directive->class);
                break;
            }
        }

        $this->assertTrue($found, 'Registered directive "test:package" not found');
    }

    public function test_discover_combines_filesystem_and_registered_directives(): void
    {
        // Arrange
        $registrar = new DirectiveRegistrar();
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class);
        $registrar->register($classes);

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator, $registrar);

        // Act
        $result = $service->discover();

        // Assert
        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertContains('test:echo', $signatures, 'Filesystem directive missing');
        $this->assertContains('test:package', $signatures, 'Registered directive missing');
    }

    public function test_discover_ignores_invalid_registered_classes(): void
    {
        // Arrange
        $registrar = new DirectiveRegistrar();
        $classes = new StringTypedCollection();
        $classes->add('InvalidNonExistentClass');
        $classes->add(\stdClass::class);
        $registrar->register($classes);

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator, $registrar);

        // Act
        $result = $service->discover();

        // Assert - Only filesystem directives should be found
        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertContains('test:echo', $signatures);
        $this->assertNotContains('test:package', $signatures);
    }

    public function test_discover_handles_empty_registrar(): void
    {
        // Arrange
        $registrar = new DirectiveRegistrar();

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator, $registrar);

        // Act
        $result = $service->discover();

        // Assert - Only filesystem directives should be found
        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertContains('test:echo', $signatures);
        $this->assertNotContains('test:package', $signatures);
    }

    public function test_discover_with_null_registrar_only_uses_filesystem(): void
    {
        // Arrange
        $registrar = null;

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator, $registrar);

        // Act
        $result = $service->discover();

        // Assert
        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertContains('test:echo', $signatures);
        $this->assertNotContains('test:package', $signatures);
    }

    public function test_registered_directive_metadata_structure_is_correct(): void
    {
        // Arrange
        $registrar = new DirectiveRegistrar();
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class);
        $registrar->register($classes);

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator, $registrar);

        // Act
        $result = $service->discover();

        // Assert
        $found = false;
        foreach ($result as $directive) {
            if ($directive->signature === 'test:package') {
                $found = true;
                $this->assertIsString($directive->signature);
                $this->assertNotEmpty($directive->signature);
                $this->assertIsString($directive->class);
                $this->assertNotEmpty($directive->class);
                $this->assertIsString($directive->description);
                $this->assertNotEmpty($directive->description);
                $this->assertInstanceOf(TypedCollection::class, $directive->aliases);
                $this->assertTrue($directive->aliases->contains('tpkg'));
                break;
            }
        }

        $this->assertTrue($found);
    }

    public function test_registered_directives_do_not_duplicate_signatures(): void
    {
        // Arrange
        $registrar = new DirectiveRegistrar();
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class);
        $registrar->register($classes);

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator, $registrar);

        // Act
        $result = $service->discover();

        // Assert
        $signatureCount = [];
        foreach ($result as $directive) {
            $sig = $directive->signature;
            $signatureCount[$sig] = ($signatureCount[$sig] ?? 0) + 1;
        }

        foreach ($signatureCount as $sig => $count) {
            $this->assertEquals(1, $count, "Signature '{$sig}' appears {$count} times");
        }
    }

    public function test_multiple_registrars_can_be_used(): void
    {
        // Arrange
        $registrar = new DirectiveRegistrar();

        // First registration
        $classes1 = new StringTypedCollection();
        $classes1->add(TestPackageDirective::class);
        $registrar->register($classes1);

        // Second registration (simulate another package)
        $classes2 = new StringTypedCollection();
        $classes2->add(TestPackageDirective::class); // Duplicate
        $registrar->register($classes2);

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator, $registrar);

        // Act
        $result = $service->discover();

        // Assert - Duplicates should be ignored
        $count = 0;
        foreach ($result as $directive) {
            if ($directive->signature === 'test:package') {
                $count++;
            }
        }

        $this->assertEquals(1, $count, 'Duplicate registered directives should be ignored');
    }
}
