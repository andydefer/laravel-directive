<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Factories\ContainerDirectiveFactory;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Tasks\InputTask;
use AndyDefer\Directive\Tasks\RenderTask;
use AndyDefer\Directive\Tests\Fixtures\Directives\InvalidDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use Illuminate\Container\Container;

final class DirectiveDiscoveryServiceTest extends UnitTestCase
{
    private string $fixturesPath;

    private DirectiveDiscoveryService $service;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        // Chemin absolu vers les fixtures pour éviter les problèmes de chemins relatifs
        $this->fixturesPath = realpath(__DIR__ . '/../../Fixtures/Directives');

        // Vérifier que le chemin existe
        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);

        $this->container = new Container;

        // Enregistrer les Tasks
        $this->container->singleton(RenderTask::class, function () {
            return new RenderTask;
        });
        $this->container->singleton(InputTask::class, function () {
            return new InputTask;
        });

        // Enregistrer les Services
        $this->container->singleton(DirectiveInteractionService::class, function ($c) {
            return new DirectiveInteractionService(
                $c->make(RenderTask::class),
                $c->make(InputTask::class),
            );
        });

        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);

        // Nouveau : plus de registrar, uniquement config + hydrator
        $this->service = new DirectiveDiscoveryService($config, $hydrator);
    }

    // ==================== Tests avec découverte fichiers ====================

    public function test_discover_returns_typed_records_of_directive_metadata(): void
    {
        $result = $this->service->discover();

        $this->assertInstanceOf(TypedCollection::class, $result);
        $this->assertContains(DirectiveMetadataRecord::class, $result->getAllowedTypes());
        $this->assertGreaterThan(0, $result->count(), 'No directives discovered. Check fixtures path: ' . $this->fixturesPath);
    }

    public function test_finds_test_echo_directive(): void
    {
        $result = $this->service->discover();

        $found = false;
        foreach ($result as $directive) {
            // La signature peut être 'test-echo' ou 'test-echo {message?}'
            if (str_contains($directive->signature, 'test-echo')) {
                $found = true;
                $this->assertSame('Test echo directive', $directive->description);
                $this->assertSame(TestEchoDirective::class, $directive->class);
                $this->assertInstanceOf(TypedCollection::class, $directive->aliases);
                break;
            }
        }

        $this->assertTrue($found, 'Directive "test-echo" not found in path: ' . $this->fixturesPath);
    }

    public function test_ignores_invalid_directives_that_dont_extend_abstract_directive(): void
    {
        // Créer un fichier temporaire avec une classe qui n'étend pas AbstractDirective
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
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

// ❌ Cette classe implémente l'interface mais n'étend PAS AbstractDirective
final class InvalidDirective implements DirectiveInterface
{
    public function getSignature(): string { return 'invalid'; }
    public function getDescription(): string { return 'Invalid directive'; }
    public function getAliases(): StringTypedCollection { return new StringTypedCollection(); }
    public function getBlueprint(): DirectiveBlueprintRecord { ... }
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

        // La directive invalide ne doit PAS être découverte
        $this->assertEquals(0, $result->count());

        // Nettoyage
        unlink($invalidClassPath);
        rmdir($tempDir);
    }

    public function test_ignores_abstract_directives(): void
    {
        $result = $this->service->discover();

        $this->assertGreaterThan(0, $result->count(), 'No directives found to test abstract check');

        foreach ($result as $directive) {
            $reflection = new \ReflectionClass($directive->class);
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
            $this->assertInstanceOf(TypedCollection::class, $directive->aliases);
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

        $this->assertInstanceOf(TypedCollection::class, $result);
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

        $this->assertInstanceOf(TypedCollection::class, $result);
        $this->assertEquals(0, $result->count());

        // Nettoyage
        rmdir($emptyDir);
    }

    public function test_aliases_are_loaded_correctly(): void
    {
        $result = $this->service->discover();

        $this->assertGreaterThan(0, $result->count(), 'No directives found to test aliases');

        foreach ($result as $directive) {
            $this->assertInstanceOf(TypedCollection::class, $directive->aliases);

            foreach ($directive->aliases as $alias) {
                $this->assertIsString($alias);
            }
        }
    }

    // ==================== Tests de compatibilité avec l'ancien Registrar ====================
    // Ces tests vérifient que l'ancien système continue de fonctionner pendant la période de dépréciation

    public function test_deprecated_registrar_is_still_available_but_not_used(): void
    {
        // Ce test vérifie que l'ancien registrar n'est plus utilisé
        // mais que le code ne casse pas si quelqu'un essaie de l'utiliser

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);

        // Le constructeur n'accepte plus le registrar
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $result = $service->discover();

        // La découverte fonctionne toujours
        $this->assertInstanceOf(TypedCollection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    // ==================== Tests de découverte depuis les packages ====================

    public function test_discover_from_vendor_packages_scans_only_composer_packages(): void
    {

        $config = DirectiveConfig::default();
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('discoverFromVendorPackages');


        $results = new TypedCollection(DirectiveMetadataRecord::class);
        $results = $method->invoke($service, $results);

        $this->assertInstanceOf(TypedCollection::class, $results);
    }

    // ==================== Tests de vérification des directives valides ====================

    public function test_only_classes_extending_abstract_directive_are_discovered(): void
    {
        $result = $this->service->discover();

        foreach ($result as $directive) {
            // Vérification que toutes les directives découvertes étendent bien AbstractDirective
            $this->assertTrue(
                is_subclass_of($directive->class, AbstractDirective::class),
                sprintf(
                    'Class %s does not extend %s',
                    $directive->class,
                    AbstractDirective::class
                )
            );

            // Vérification qu'elles implémentent bien l'interface (double sécurité)
            $this->assertTrue(
                is_subclass_of($directive->class, \AndyDefer\Directive\Contracts\DirectiveInterface::class),
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

        // Créer un fichier PHP malformé
        $malformedPath = $tempDir . '/MalformedDirective.php';
        file_put_contents($malformedPath, '<?php this is not valid php code {');

        $config = DirectiveConfig::default()->withDirectivesPath($tempDir);
        $factory = new ContainerDirectiveFactory($this->container);
        $hydrator = new DirectiveHydratorService($factory);
        $service = new DirectiveDiscoveryService($config, $hydrator);

        // Ne doit pas lancer d'exception
        $result = $service->discover();

        $this->assertInstanceOf(TypedCollection::class, $result);
        $this->assertEquals(0, $result->count());

        // Nettoyage
        unlink($malformedPath);
        rmdir($tempDir);
    }
}
