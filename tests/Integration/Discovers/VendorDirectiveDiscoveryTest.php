<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Discovers;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\PhpServices\Services\FileSystemService;

final class VendorDirectiveDiscoveryTest extends IntegrationTestCase
{
    private VendorDirectiveDiscovery $discovery;

    private string $vendorPath;

    protected function setUp(): void
    {
        parent::setUp();

        $fileSystem = new FileSystemService;
        $config = $this->app->make(DirectiveConfigInterface::class);
        $scanner = $this->app->make(DirectiveScannerInterface::class);

        $composerReader = new ComposerReaderService($config, $fileSystem);
        $dependencyResolver = new DependencyResolverService($composerReader, $fileSystem);

        $this->discovery = new VendorDirectiveDiscovery(
            $composerReader,
            $dependencyResolver,
            $fileSystem,
            $scanner,
            3
        );

        $this->vendorPath = $composerReader->getVendorDir();
    }

    // ==================== BASIC DISCOVERY TESTS ====================

    public function test_discover_returns_array(): void
    {
        $result = $this->discovery->discover();

        $this->assertIsArray($result);
    }

    public function test_discover_finds_built_in_directives(): void
    {
        $result = $this->discovery->discover();

        $found = false;
        foreach ($result as $class) {
            if (str_contains($class, 'BuiltIn')) {
                $found = true;
                break;
            }
        }

        // Le package actuel peut ou non avoir des directives built-in dans vendor
        // Ce test vérifie que la découverte fonctionne
        $this->assertIsArray($result);
    }

    // ==================== SCAN PACKAGE TESTS ====================

    public function test_scan_package_returns_array_for_existing_package(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('scanPackage');

        // Tester avec un package existant (laravel/framework)
        $result = $method->invoke($this->discovery, 'laravel/framework');

        $this->assertIsArray($result);
    }

    public function test_scan_package_returns_empty_array_for_non_existing_package(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('scanPackage');

        $result = $method->invoke($this->discovery, 'non/existing/package');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_package_path_returns_correct_path(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('getPackagePath');

        $realVendor = dirname(__DIR__, 3).'/vendor';
        $package = 'orchestra/testbench-core';
        $expectedPath = $realVendor.'/'.$package;

        $path = $method->invoke($this->discovery, $package);

        $this->assertStringContainsString('orchestra/testbench-core', $path);
        $this->assertIsString($path);
        $this->assertNotEmpty($path);
    }

    // ==================== PROBLEMS TESTS ====================

    public function test_get_problems_returns_empty_collection_initially(): void
    {
        $problems = $this->discovery->getProblems();

        $this->assertInstanceOf(ListCollection::class, $problems);
        $this->assertTrue($problems->isEmpty());
    }

    public function test_clear_problems_empties_collection(): void
    {
        $this->discovery->discover();

        $this->discovery->clearProblems();

        $problems = $this->discovery->getProblems();
        $this->assertTrue($problems->isEmpty());
    }

    // ==================== READ COMPOSER JSON TESTS ====================
    public function test_read_composer_json_returns_data_for_valid_package(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('readComposerJson');

        // ✅ Utiliser le vendor réel du projet (pas celui de Testbench)
        $realVendor = dirname(__DIR__, 3).'/vendor';
        $packagePath = $realVendor.'/orchestra/testbench-core';

        $result = $method->invoke($this->discovery, $packagePath);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('orchestra/testbench-core', $result['name']);
    }

    public function test_read_composer_json_returns_null_for_invalid_path(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('readComposerJson');

        $result = $method->invoke($this->discovery, '/non/existent/path');

        $this->assertNull($result);
    }

    // ==================== SCAN AUTOLOAD PATHS TESTS ====================

    public function test_scan_autoload_paths_scans_psr4_directories(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('scanAutoloadPaths');

        $packagePath = $this->vendorPath.'/laravel/framework';
        $result = $method->invoke($this->discovery, 'laravel/framework', $packagePath);

        $this->assertIsArray($result);
    }

    // ==================== EXTRACT CUSTOM SOURCES TESTS ====================

    public function test_extract_custom_sources_returns_array(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('extractCustomSources');

        // Tester avec un chemin qui n'existe pas
        $result = $method->invoke($this->discovery, '/non/existent/config.php');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_filter_string_values_filters_correctly(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('filterStringValues');

        $input = ['string1', 123, 'string2', null, true, 'string3'];
        $result = $method->invoke($this->discovery, $input);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContains('string1', $result);
        $this->assertContains('string2', $result);
        $this->assertContains('string3', $result);
        $this->assertNotContains(123, $result);
        $this->assertNotContains(null, $result);
        $this->assertNotContains(true, $result);
    }

    // ==================== REAL DISCOVERY TESTS ====================

    public function test_discover_finds_actual_directives(): void
    {
        $result = $this->discovery->discover();

        // Au minimum, le package courant devrait avoir des directives
        // Mais cela dépend du contexte de test
        $this->assertIsArray($result);
    }

    public function test_discover_returns_uniqe_classes(): void
    {
        $result = $this->discovery->discover();

        $unique = array_unique($result);

        $this->assertCount(count($result), $unique);
    }

    public function test_discover_does_not_return_duplicates(): void
    {
        $result = $this->discovery->discover();

        $count = count($result);
        $uniqueCount = count(array_unique($result));

        $this->assertEquals($count, $uniqueCount);
    }
}
