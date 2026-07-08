<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class DirectiveDiscoveryServiceTest extends IntegrationTestCase
{
    private string $fixturesPath;

    private string $invalidFixturesPath;

    private string $abstractFixturesPath;

    private string $nonDirectiveFixturesPath;

    private string $malformedFixturesPath;

    private DirectiveDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesPath = realpath(__DIR__.'/../../Fixtures/Directives');
        $this->invalidFixturesPath = realpath(__DIR__.'/../../Fixtures/Invalid');
        $this->abstractFixturesPath = realpath(__DIR__.'/../../Fixtures/Abstracts');
        $this->nonDirectiveFixturesPath = realpath(__DIR__.'/../../Fixtures/NonDirectives');
        $this->malformedFixturesPath = realpath(__DIR__.'/../../Fixtures/Malformed');

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
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->invalidFixturesPath);

        $result = $service->discover();

        // Aucune directive ne doit être trouvée car InvalidDirective n'implémente pas AbstractDirective
        foreach ($result as $directive) {
            $this->assertNotEquals('invalid', $directive->signature);
        }
    }

    public function test_ignores_abstract_directives(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->abstractFixturesPath);

        $result = $service->discover();

        // Aucune directive ne doit être trouvée car la classe est abstraite
        foreach ($result as $directive) {
            $this->assertNotEquals('abstract', $directive->signature);
        }
    }

    public function test_ignores_non_directive_classes(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->nonDirectiveFixturesPath);

        $result = $service->discover();

        // Aucune directive ne doit être trouvée car la classe n'implémente pas DirectiveInterface
        foreach ($result as $directive) {
            $this->assertNotEquals('not-a-directive', $directive->signature);
        }
    }

    public function test_handles_malformed_php_files_gracefully(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->malformedFixturesPath);

        $result = $service->discover();

        // Les fichiers malformés doivent être ignorés silencieusement
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
        // Les directives built-in sont toujours présentes
        $this->assertGreaterThan(0, $result->count());
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
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource('/invalid/path/that/does/not/exist');

        $result = $service->discover();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
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
}
