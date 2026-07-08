<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Feature;

use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

final class DirectiveServiceProviderTest extends OrchestraTestCase
{
    private DirectiveServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new DirectiveServiceProvider($this->app);
    }

    protected function getPackageProviders($app): array
    {
        return [
            DirectiveServiceProvider::class,
        ];
    }

    public function test_register_does_not_throw_exception(): void
    {
        $this->provider->register();
        $this->addToAssertionCount(1);
    }

    // ==================== Configuration Tests ====================

    public function test_config_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveConfigInterface::class));
    }

    public function test_config_can_be_resolved(): void
    {
        $this->provider->register();
        $config = $this->app->make(DirectiveConfigInterface::class);
        $this->assertInstanceOf(DirectiveConfig::class, $config);
    }

    // ==================== Core Service Tests ====================

    public function test_file_system_interface_is_bound(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(FileSystemInterface::class));
    }

    public function test_discovery_service_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveDiscoveryService::class));
    }

    public function test_discovery_service_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(DirectiveDiscoveryService::class);
        $this->assertInstanceOf(DirectiveDiscoveryService::class, $service);
    }

    public function test_parser_service_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveParserInterface::class));
    }

    public function test_parser_service_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(DirectiveParserService::class);
        $this->assertInstanceOf(DirectiveParserService::class, $service);
    }

    // ==================== Discover Tests ====================

    public function test_built_in_discovery_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(BuiltInDirectiveDiscovery::class));
    }

    public function test_workspace_discovery_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(WorkspaceDirectiveDiscovery::class));
    }

    public function test_vendor_discovery_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(VendorDirectiveDiscovery::class));
    }

    public function test_scanner_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveScannerInterface::class));
    }

    public function test_composer_reader_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(ComposerReaderService::class));
    }

    public function test_dependency_resolver_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DependencyResolverService::class));
    }

    // ==================== Kernel Tests ====================

    public function test_kernel_can_be_resolved(): void
    {
        $this->provider->register();
        $kernel = $this->app->make(DirectiveKernel::class);
        $this->assertInstanceOf(DirectiveKernel::class, $kernel);
    }

    // ==================== Singleton Tests ====================

    public function test_discovery_service_is_singleton(): void
    {
        $this->provider->register();
        $service1 = $this->app->make(DirectiveDiscoveryService::class);
        $service2 = $this->app->make(DirectiveDiscoveryService::class);
        $this->assertSame($service1, $service2);
    }

    public function test_kernel_is_singleton(): void
    {
        $this->provider->register();
        $kernel1 = $this->app->make(DirectiveKernel::class);
        $kernel2 = $this->app->make(DirectiveKernel::class);
        $this->assertSame($kernel1, $kernel2);
    }
}
