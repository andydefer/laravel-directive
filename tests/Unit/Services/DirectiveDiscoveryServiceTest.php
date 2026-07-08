<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use ReflectionClass;

final class DirectiveDiscoveryServiceTest extends IntegrationTestCase
{
    private string $fixturesPath;

    private DirectiveDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesPath = realpath(__DIR__.'/../../Fixtures/Directives');

        // Ajouter le chemin des fixtures comme source personnalisée
        $this->service = $this->app->make(DirectiveDiscoveryService::class);
        $this->service->addSource($this->fixturesPath);
    }

    public function test_discover_returns_typed_records_of_directive_metadata(): void
    {
        $result = $this->service->discover();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertContains(DirectiveMetadataRecord::class, $result->getAllowedTypes());
        $this->assertGreaterThan(0, $result->count(), 'No directives discovered. Check fixtures path: '.$this->fixturesPath);
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

        $this->assertTrue($found, 'Directive "test-echo" not found in path: '.$this->fixturesPath);
    }

    public function test_ignores_invalid_directives_that_dont_extend_abstract_directive(): void
    {
        $tempDir = sys_get_temp_dir().'/directive_test_'.uniqid();
        mkdir($tempDir, 0777, true);

        $invalidClassPath = $tempDir.'/InvalidDirective.php';
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
    public function hasLaravel(): bool { return false; }
    public function getLaravel(): ?object { return null; }
    public function setLaravelBootstrapper($bootstrapper) { return $this; }
    public function execute(): ExitCode { return ExitCode::SUCCESS; }
}
PHP;

        file_put_contents($invalidClassPath, $invalidClassContent);

        // Créer un nouveau service avec ce dossier
        $tempService = $this->app->make(DirectiveDiscoveryService::class);
        $tempService->addSource($tempDir);

        $result = $tempService->discover();

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
            $this->assertFalse($reflection->isAbstract(), 'Directive '.$directive->class.' should not be abstract');
            $this->assertTrue(is_subclass_of($directive->class, AbstractDirective::class), 'Directive '.$directive->class.' must extend AbstractDirective');
        }
    }

    public function test_finds_concrete_directives(): void
    {
        $result = $this->service->discover();

        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertNotEmpty($signatures, 'No signatures found in path: '.$this->fixturesPath);

        $found = false;
        foreach ($signatures as $signature) {
            if (str_contains($signature, 'test-echo')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'No test-echo directive found in signatures: '.implode(', ', $signatures));
    }

    public function test_returns_complete_metadata_structure(): void
    {
        $result = $this->service->discover();

        $this->assertGreaterThan(0, $result->count(), 'No directives found to test in path: '.$this->fixturesPath);

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

        $this->assertGreaterThanOrEqual(1, $result->count(), 'No directives discovered in path: '.$this->fixturesPath);
    }

    public function test_signatures_are_unique(): void
    {
        $result = $this->service->discover();

        $this->assertGreaterThan(0, $result->count(), 'No directives found to test uniqueness');

        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertEquals(count($signatures), count(array_unique($signatures)), 'Duplicate signatures found');
    }

    public function test_returns_empty_result_for_invalid_path(): void
    {
        $tempService = $this->app->make(DirectiveDiscoveryService::class);
        // Ajouter un chemin invalide ne devrait pas lever d'exception
        $tempService->addSource('/invalid/path/that/does/not/exist');

        $result = $tempService->discover();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
    }

    public function test_returns_empty_result_for_empty_directory(): void
    {
        $emptyDir = sys_get_temp_dir().'/empty_directives_'.uniqid();
        mkdir($emptyDir, 0777, true);

        $tempService = $this->app->make(DirectiveDiscoveryService::class);
        $tempService->addSource($emptyDir);

        $result = $tempService->discover();

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

    public function test_only_classes_extending_abstract_directive_are_discovered(): void
    {
        $result = $this->service->discover();

        $this->assertGreaterThan(0, $result->count(), 'No directives found to validate');

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
        $tempDir = sys_get_temp_dir().'/malformed_test_'.uniqid();
        mkdir($tempDir, 0777, true);

        $malformedPath = $tempDir.'/MalformedDirective.php';
        file_put_contents($malformedPath, '<?php this is not valid php code {');

        $tempService = $this->app->make(DirectiveDiscoveryService::class);
        $tempService->addSource($tempDir);

        $result = $tempService->discover();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        $this->assertEquals(0, $result->count());

        unlink($malformedPath);
        rmdir($tempDir);
    }
}
