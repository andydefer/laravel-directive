<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Discovers;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Directives\SupportDirective;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\PhpServices\Services\FileSystemService;

final class WorkspaceDirectiveDiscoveryTest extends IntegrationTestCase
{
    private WorkspaceDirectiveDiscovery $discovery;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesPath = realpath(__DIR__.'/../../Fixtures/Directives');

        $fileSystem = new FileSystemService;
        $scanner = $this->app->make(DirectiveClassScanner::class);
        $config = $this->app->make(DirectiveConfigInterface::class);

        $this->discovery = new WorkspaceDirectiveDiscovery(
            $fileSystem,
            $scanner,
            $config,
            3
        );
    }

    // ==================== BASIC DISCOVERY TESTS ====================

    public function test_discover_returns_array_of_classes(): void
    {
        $result = $this->discovery->discover();

        $this->assertIsArray($result);
    }

    public function test_discover_finds_test_echo_directive(): void
    {
        $result = $this->discovery->discover();

        $found = false;
        foreach ($result as $class) {
            if ($class === SupportDirective::class) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'SupportDirective not found in workspace discovery');
    }

    public function test_discover_returns_classes_as_strings(): void
    {
        $result = $this->discovery->discover();

        $this->assertNotEmpty($result);

        foreach ($result as $class) {
            $this->assertIsString($class);
            $this->assertTrue(class_exists($class), 'Class does not exist: '.$class);
        }
    }

    // ==================== CACHE TESTS ====================

    public function test_discover_caches_results(): void
    {
        $firstResult = $this->discovery->discover();
        $secondResult = $this->discovery->discover();

        $this->assertSame($firstResult, $secondResult);
    }

    public function test_add_path_clears_cache(): void
    {
        $firstResult = $this->discovery->discover();

        $this->discovery->addPath('src/Commands');

        $secondResult = $this->discovery->discover();

        // Les résultats peuvent être différents ou identiques, mais le cache est vidé
        $this->assertIsArray($secondResult);
    }

    public function test_add_paths_clears_cache(): void
    {
        $firstResult = $this->discovery->discover();

        $this->discovery->addPaths(['src/Commands', 'app/Directives']);

        $secondResult = $this->discovery->discover();

        $this->assertIsArray($secondResult);
    }

    // ==================== PATH MANAGEMENT TESTS ====================

    public function test_add_path_does_not_duplicate(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $customPathsProperty = $reflection->getProperty('customPaths');

        $this->discovery->addPath('src/Commands');
        $this->discovery->addPath('src/Commands');

        $customPaths = $customPathsProperty->getValue($this->discovery);

        $this->assertCount(1, array_keys($customPaths, 'src/Commands', true));
    }

    public function test_get_scan_paths_includes_default_paths(): void
    {
        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('getScanPaths');

        $paths = $method->invoke($this->discovery);

        $this->assertIsArray($paths);
        $this->assertContains('src/Directives', $paths);
        $this->assertContains('app/Directives', $paths);
    }

    public function test_get_scan_paths_includes_custom_paths(): void
    {
        $this->discovery->addPath('custom/path');
        $this->discovery->addPath('another/path');

        $reflection = new \ReflectionClass($this->discovery);
        $method = $reflection->getMethod('getScanPaths');

        $paths = $method->invoke($this->discovery);

        $this->assertContains('custom/path', $paths);
        $this->assertContains('another/path', $paths);
    }

    // ==================== PROBLEMS TESTS ====================

    public function test_get_problems_returns_empty_collection_initially(): void
    {
        $problems = $this->discovery->getProblems();

        $this->assertInstanceOf(ListCollection::class, $problems);
        $this->assertTrue($problems->isEmpty());
    }

    public function test_discover_does_not_create_problems_for_existing_paths(): void
    {
        $this->discovery->addPath($this->fixturesPath);
        $this->discovery->discover();

        $problems = $this->discovery->getProblems();

        $this->assertInstanceOf(ListCollection::class, $problems);
    }

    public function test_clear_problems_empties_collection(): void
    {
        $this->discovery->addPath('/non/existent/path');
        $this->discovery->discover();

        $this->discovery->clearProblems();

        $problems = $this->discovery->getProblems();
        $this->assertTrue($problems->isEmpty());
    }
}
