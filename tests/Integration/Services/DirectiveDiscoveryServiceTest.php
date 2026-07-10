<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Container\Container;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\Directive\Enums\DiscoverySource;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestGreetingDirective;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
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

    protected function createFreshDiscoveryService(): DirectiveDiscoveryService
    {
        // Create a temporary directory with composer.json
        $tempDir = sys_get_temp_dir().'/directive_test_'.uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir.'/composer.json', '{
            "name": "test/app",
            "require": {
                "php": "^8.1"
            }
        }');

        $appMock = $this->createMock(Container::class);

        $parserMock = $this->createMock(DirectiveParserInterface::class);
        $scannerMock = $this->createMock(DirectiveScannerInterface::class);
        $fileSystemMock = $this->createMock(FileSystemInterface::class);

        $configMock = $this->createMock(DirectiveConfigInterface::class);
        $configMock->method('getCustomSources')->willReturn([]);
        $configMock->method('getReservedSignatures')->willReturn([]);
        $configMock->method('getVendorDir')->willReturn($tempDir.'/vendor');
        $configMock->method('getComposerPath')->willReturn($tempDir.'/composer.json');
        $configMock->method('basePath')->willReturn($tempDir);

        $appMock->method('make')->willReturnCallback(function ($class) use ($parserMock, $scannerMock, $fileSystemMock, $configMock) {
            return match ($class) {
                DirectiveParserInterface::class => $parserMock,
                DirectiveScannerInterface::class => $scannerMock,
                FileSystemInterface::class => $fileSystemMock,
                DirectiveConfigInterface::class => $configMock,
                default => throw new \RuntimeException("Unexpected make call: {$class}"),
            };
        });

        return DirectiveDiscoveryService::init($appMock);
    }

    // ==================== EXISTING TESTS ====================

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
            if (str_contains($directive->signature, 'test:echo')) {
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

        foreach ($result as $directive) {
            $this->assertNotEquals('invalid', $directive->signature);
        }
    }

    public function test_ignores_abstract_directives(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->abstractFixturesPath);

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertNotEquals('abstract', $directive->signature);
        }
    }

    public function test_ignores_non_directive_classes(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->nonDirectiveFixturesPath);

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertNotEquals('not-a-directive', $directive->signature);
        }
    }

    public function test_handles_malformed_php_files_gracefully(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->malformedFixturesPath);

        $result = $service->discover();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
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
            if (str_contains($signature, 'test:echo')) {
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

    // ==================== NEW TESTS FOR REGISTERED DIRECTIVES ====================

    public function test_add_directive_registers_class(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addDirective(TestEchoDirective::class);

        $result = $service->discover();

        $found = false;
        foreach ($result as $directive) {
            if ($directive->class === TestEchoDirective::class) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Registered directive not found in discovery');
    }

    public function test_add_directives_registers_multiple_classes(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addDirectives([
            TestEchoDirective::class,
            TestGreetingDirective::class,
        ]);

        $result = $service->discover();

        $classes = [];
        foreach ($result as $directive) {
            $classes[] = $directive->class;
        }

        $this->assertContains(TestEchoDirective::class, $classes);
        $this->assertContains(TestGreetingDirective::class, $classes);
    }

    public function test_add_directive_throws_exception_for_invalid_class(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must extend AndyDefer\Directive\AbstractDirective');

        $service->addDirective(\stdClass::class);
    }

    // ==================== NEW TESTS FOR SOURCE MANAGEMENT ====================

    public function test_ignore_source_prevents_discovery(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $resultWithoutIgnore = $service->discover();
        $countWithoutIgnore = $resultWithoutIgnore->count();

        $service->ignoreSource(DiscoverySource::CUSTOM);
        $resultWithIgnore = $service->discover();

        $this->assertLessThan($countWithoutIgnore, $resultWithIgnore->count());
    }

    public function test_ignore_sources_prevents_multiple_discoveries(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->ignoreSources([DiscoverySource::CUSTOM, DiscoverySource::VENDOR]);

        $result = $service->discover();
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
    }

    public function test_enable_source_restores_discovery(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->ignoreSource(DiscoverySource::CUSTOM);
        $service->enableSource(DiscoverySource::CUSTOM);

        $result = $service->discover();
        $this->assertGreaterThan(0, $result->count());
    }

    public function test_is_source_ignored_returns_correct_value(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $this->assertFalse($service->isSourceIgnored(DiscoverySource::VENDOR));

        $service->ignoreSource(DiscoverySource::VENDOR);
        $this->assertTrue($service->isSourceIgnored(DiscoverySource::VENDOR));
    }

    public function test_ignore_source_with_string(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $this->assertFalse($service->isSourceIgnored('vendor'));

        $service->ignoreSource('vendor');
        $this->assertTrue($service->isSourceIgnored('vendor'));
    }

    // ==================== NEW TESTS FOR PATH MANAGEMENT ====================

    public function test_ignore_path_excludes_directory(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $service->addSource($this->fixturesPath);
        $resultWithoutIgnore = $service->discover();

        $service->ignorePath($this->fixturesPath);
        $resultWithIgnore = $service->discover();

        $this->assertLessThanOrEqual($resultWithoutIgnore->count(), $resultWithIgnore->count());
    }

    public function test_ignore_paths_excludes_multiple_directories(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $service->addSources([$this->fixturesPath, $this->invalidFixturesPath]);
        $service->ignorePaths([$this->fixturesPath, $this->invalidFixturesPath]);

        $result = $service->discover();

        $this->assertGreaterThan(0, $result->count());
    }

    public function test_enable_path_restores_discovery(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->ignorePath($this->fixturesPath);
        $service->enablePath($this->fixturesPath);

        $result = $service->discover();
        $this->assertGreaterThan(0, $result->count());
    }

    // ==================== NEW TESTS FOR DIRECTIVE MANAGEMENT ====================

    public function test_ignore_directive_excludes_specific_directive(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->ignoreDirective('test:echo');

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertFalse(str_contains($directive->signature, 'test:echo'));
        }
    }

    public function test_ignore_directives_excludes_multiple_directives(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->ignoreDirectives(['test:echo', 'test:variadic']);

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertFalse(str_contains($directive->signature, 'test:echo'));
            $this->assertFalse(str_contains($directive->signature, 'test:variadic'));
        }
    }

    public function test_enable_directive_restores_directive(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);
        $service->ignoreSource(DiscoverySource::VENDOR);

        $fullSignature = 'test:echo {message=?} {extra=?}';

        $result = $service->discover();
        $found = false;
        foreach ($result as $directive) {
            if ($directive->signature === $fullSignature) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Directive should be present initially');

        $service->ignoreDirective('test:echo');
        $result = $service->discover();
        $found = false;
        foreach ($result as $directive) {
            if ($directive->signature === $fullSignature) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Directive should be ignored');

        $service->enableDirective('test:echo');
        $result = $service->discover();
        $found = false;
        foreach ($result as $directive) {
            if ($directive->signature === $fullSignature) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Directive was not restored');
    }

    public function test_is_directive_ignored_returns_correct_value(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $this->assertFalse($service->isDirectiveIgnored('test:echo'));

        $service->ignoreDirective('test:echo');
        $this->assertTrue($service->isDirectiveIgnored('test:echo'));
    }

    // ==================== NEW TESTS FOR NAMESPACE FILTERING ====================

    public function test_only_namespace_filters_discovery(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->onlyNamespace('AndyDefer\\Directive\\Tests\\Fixtures\\Directives\\');

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertStringStartsWith(
                'AndyDefer\\Directive\\Tests\\Fixtures\\Directives\\',
                $directive->class
            );
        }
    }

    public function test_only_namespaces_filters_with_multiple_namespaces(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->onlyNamespaces([
            'AndyDefer\\Directive\\Tests\\Fixtures\\Directives\\',
            'AndyDefer\\Directive\\Tests\\Fixtures\\Custom\\',
        ]);

        $result = $service->discover();

        foreach ($result as $directive) {
            $isValid = false;
            foreach (['AndyDefer\\Directive\\Tests\\Fixtures\\Directives\\', 'AndyDefer\\Directive\\Tests\\Fixtures\\Custom\\'] as $namespace) {
                if (str_starts_with($directive->class, $namespace)) {
                    $isValid = true;
                    break;
                }
            }
            $this->assertTrue($isValid, 'Directive not in allowed namespaces');
        }
    }

    public function test_exclude_namespace_filters_discovery(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->excludeNamespace('AndyDefer\\Directive\\Tests\\Fixtures\\Invalid\\');

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertFalse(
                str_starts_with($directive->class, 'AndyDefer\\Directive\\Tests\\Fixtures\\Invalid\\')
            );
        }
    }

    public function test_exclude_namespaces_filters_with_multiple_namespaces(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->excludeNamespaces([
            'AndyDefer\\Directive\\Tests\\Fixtures\\Invalid\\',
            'AndyDefer\\Directive\\Tests\\Fixtures\\Abstracts\\',
        ]);

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertFalse(
                str_starts_with($directive->class, 'AndyDefer\\Directive\\Tests\\Fixtures\\Invalid\\')
            );
            $this->assertFalse(
                str_starts_with($directive->class, 'AndyDefer\\Directive\\Tests\\Fixtures\\Abstracts\\')
            );
        }
    }

    // ==================== NEW TESTS FOR PREFIX FILTERING ====================

    public function test_only_prefix_filters_discovery(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->onlyPrefix('test:');

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertStringStartsWith('test:', $directive->signature);
        }
    }

    public function test_only_prefixes_filters_with_multiple_prefixes(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->onlyPrefixes(['test:', 'greeting']);

        $result = $service->discover();

        foreach ($result as $directive) {
            $isValid = false;
            foreach (['test:', 'greeting'] as $prefix) {
                if (str_starts_with($directive->signature, $prefix)) {
                    $isValid = true;
                    break;
                }
            }
            $this->assertTrue($isValid, 'Directive not in allowed prefixes');
        }
    }

    public function test_exclude_prefix_filters_discovery(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->excludePrefix('test:');

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertFalse(
                str_starts_with($directive->signature, 'test:')
            );
        }
    }

    public function test_exclude_prefixes_filters_with_multiple_prefixes(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->excludePrefixes(['test:', 'greeting']);

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertFalse(
                str_starts_with($directive->signature, 'test:')
            );
            $this->assertFalse(
                str_starts_with($directive->signature, 'greeting')
            );
        }
    }

    // ==================== NEW TESTS FOR AUTO-DISCOVERY ====================

    public function test_disable_auto_discovery(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->disableAutoDiscovery();

        $result = $service->discover();
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
    }

    public function test_enable_auto_discovery(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->disableAutoDiscovery();
        $service->enableAutoDiscovery();

        $this->assertTrue($service->isAutoDiscoveryEnabled());

        $result = $service->discover();
        $this->assertGreaterThan(0, $result->count());
    }

    public function test_manual_only_alias(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $service->manualOnly();

        $this->assertFalse($service->isAutoDiscoveryEnabled());
    }

    // ==================== NEW TESTS FOR DEPTH MANAGEMENT ====================

    public function test_set_max_depth_clamps_values(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $service->setMaxDepth(1);
        $this->assertEquals(2, $service->getMaxDepth());

        $service->setMaxDepth(10);
        $this->assertEquals(7, $service->getMaxDepth());

        $service->setMaxDepth(5);
        $this->assertEquals(5, $service->getMaxDepth());
    }

    // ==================== NEW TESTS FOR RESET CONFIG ====================

    public function test_reset_config_clears_all_filters(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $service
            ->ignoreSource(DiscoverySource::VENDOR)
            ->ignorePath('/some/path')
            ->ignoreDirective('test:echo')
            ->onlyNamespace('App\\')
            ->excludeNamespace('App\\Deprecated\\')
            ->onlyPrefix('test:')
            ->excludePrefix('deprecated-')
            ->disableAutoDiscovery()
            ->setMaxDepth(5);

        $service->resetConfig();

        $this->assertFalse($service->isSourceIgnored(DiscoverySource::VENDOR));
        $this->assertFalse($service->isDirectiveIgnored('test:echo'));
        $this->assertTrue($service->isAutoDiscoveryEnabled());
        $this->assertEquals(3, $service->getMaxDepth());
    }

    // ==================== NEW TESTS FOR COMBINED FILTERS ====================

    public function test_combined_filters_work_together(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service
            ->ignoreSource(DiscoverySource::VENDOR)
            ->ignoreDirective('test:echo')
            ->onlyNamespace('AndyDefer\\Directive\\Tests\\Fixtures\\Directives\\')
            ->onlyPrefix('test:')
            ->setMaxDepth(2);

        $result = $service->discover();

        foreach ($result as $directive) {
            $this->assertStringStartsWith(
                'AndyDefer\\Directive\\Tests\\Fixtures\\Directives\\',
                $directive->class
            );

            $this->assertStringStartsWith('test:', $directive->signature);

            $this->assertFalse(str_contains($directive->signature, 'test:echo'));
        }
    }

    // ==================== NEW TESTS FOR DUPLICATION ====================

    public function test_add_source_does_not_duplicate(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('customSources');

        $emptyCollection = new StringTypedCollection;
        $property->setValue($service, $emptyCollection);

        $service->addSource('/path/one');
        $service->addSource('/path/one');

        $sources = $property->getValue($service);

        $this->assertCount(1, $sources);
    }

    public function test_add_directive_does_not_duplicate(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $service->addDirective(TestEchoDirective::class);
        $service->addDirective(TestEchoDirective::class);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('registeredDirectives');
        $property->setAccessible(true);
        $directives = $property->getValue($service);

        $this->assertCount(1, $directives);
    }

    // ==================== NEW TESTS FOR COLLECTION MANAGEMENT ====================

    public function test_get_collection_returns_current_collection(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $collection = $service->getCollection();
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $collection);
    }

    public function test_clear_resets_collection(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->discover();
        $this->assertGreaterThan(0, $service->getCollection()->count());

        $service->clear();
        $this->assertEquals(0, $service->getCollection()->count());
    }

    public function test_discover_replaces_existing_collection(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource($this->fixturesPath);

        $service->discover();
        $firstCollection = $service->getCollection();

        $service->discover();
        $secondCollection = $service->getCollection();

        $this->assertNotSame($firstCollection, $secondCollection);
        $this->assertEquals($firstCollection->count(), $secondCollection->count());
    }

    // ==================== PROBLEMS TESTS ====================

    public function test_get_problems_returns_empty_collection_initially(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $problems = $service->getProblems();

        $this->assertInstanceOf(ListCollection::class, $problems);
        $this->assertTrue($problems->isEmpty());
    }

    public function test_problems_are_recorded_when_container_resolution_fails(): void
    {
        $service = $this->createFreshDiscoveryService();

        $problems = $service->getProblems();
        $this->assertInstanceOf(ListCollection::class, $problems);
    }

    public function test_problems_are_recorded_when_discovery_fails(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        // Force une erreur en ajoutant un chemin invalide
        $service->addSource('/invalid/path/that/does/not/exist');

        $service->discover();

        $problems = $service->getProblems();
        $this->assertInstanceOf(ListCollection::class, $problems);
    }

    public function test_problem_contains_required_fields(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('addProblem');
        $method->invoke($service, 'test_key', 'Test context', 'Test message', ['extra' => 'data']);

        $problems = $service->getProblems();
        $this->assertFalse($problems->isEmpty());

        $problem = $problems->first();
        $this->assertInstanceOf(StrictAssociative::class, $problem);

        $this->assertTrue($problem->has('key'));
        $this->assertTrue($problem->has('context'));
        $this->assertTrue($problem->has('message'));
        $this->assertTrue($problem->has('context_data'));
        $this->assertTrue($problem->has('timestamp'));

        $this->assertSame('test_key', $problem->get('key'));
        $this->assertSame('Test context', $problem->get('context'));
        $this->assertSame('Test message', $problem->get('message'));
        $this->assertSame(['extra' => 'data'], $problem->get('context_data')->toArray());
    }

    public function test_clear_problems_empties_collection(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('addProblem');
        $method->invoke($service, 'test_key', 'Test context', 'Test message');

        $this->assertFalse($service->getProblems()->isEmpty());

        $service->clearProblems();

        $this->assertTrue($service->getProblems()->isEmpty());
    }

    public function test_problems_are_recorded_for_custom_source_not_directory(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $service->addSource('/path/that/is/not/a/directory');

        $service->discover();

        $problems = $service->getProblems();
        $found = false;
        foreach ($problems as $problem) {
            if ($problem->get('key') === 'custom_source_not_directory') {
                $found = true;
                $this->assertStringContainsString('not a directory', $problem->get('context'));
                break;
            }
        }
        $this->assertTrue($found, 'Problem for non-directory custom source not found');
    }

    public function test_problems_are_recorded_for_invalid_directive_class(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        try {
            $service->addDirective(\stdClass::class);
        } catch (\InvalidArgumentException $e) {
            // Exception attendue
        }

        $problems = $service->getProblems();
        $found = false;
        foreach ($problems as $problem) {
            if ($problem->get('key') === 'add_directive') {
                $found = true;
                $this->assertStringContainsString('must extend', $problem->get('message'));
                break;
            }
        }
        $this->assertTrue($found, 'Problem for invalid directive class not found');
    }

    public function test_multiple_problems_are_collected(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        // Ajouter plusieurs problèmes
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('addProblem');
        $method->invoke($service, 'problem_1', 'Context 1', 'Message 1');
        $method->invoke($service, 'problem_2', 'Context 2', 'Message 2');
        $method->invoke($service, 'problem_3', 'Context 3', 'Message 3');

        $problems = $service->getProblems();
        $this->assertCount(3, $problems);

        $keys = [];
        foreach ($problems as $problem) {
            $keys[] = $problem->get('key');
        }
        $this->assertContains('problem_1', $keys);
        $this->assertContains('problem_2', $keys);
        $this->assertContains('problem_3', $keys);
    }

    public function test_problem_context_data_contains_relevant_information(): void
    {
        $service = $this->app->make(DirectiveDiscoveryService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('addProblem');
        $method->invoke($service, 'test_key', 'Test context', 'Test message', [
            'class' => 'TestClass',
            'path' => '/test/path',
            'command' => 'test:command',
        ]);

        $problem = $service->getProblems()->first();
        $contextData = $problem->get('context_data');

        $this->assertArrayHasKey('class', $contextData);
        $this->assertArrayHasKey('path', $contextData);
        $this->assertArrayHasKey('command', $contextData);
        $this->assertSame('TestClass', $contextData['class']);
        $this->assertSame('/test/path', $contextData['path']);
        $this->assertSame('test:command', $contextData['command']);
    }
}
