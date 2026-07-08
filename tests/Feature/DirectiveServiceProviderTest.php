<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Feature;

use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Configs\EnvDirectiveNamingConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Contracts\Configs\DatabaseTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveNamingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\Directive\Contracts\Configs\SignatureValidationConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Services\SignatureValidationService;
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

    public function test_naming_config_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveNamingConfigInterface::class));
    }

    public function test_naming_config_can_be_resolved(): void
    {
        $this->provider->register();
        $config = $this->app->make(DirectiveNamingConfigInterface::class);
        $this->assertInstanceOf(EnvDirectiveNamingConfig::class, $config);
    }

    public function test_signature_validation_config_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(SignatureValidationConfigInterface::class));
    }

    public function test_signature_validation_config_can_be_resolved(): void
    {
        $this->provider->register();
        $config = $this->app->make(SignatureValidationConfigInterface::class);
        $this->assertInstanceOf(EnvSignatureValidationConfig::class, $config);
    }

    public function test_file_creator_config_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(FileCreatorConfigInterface::class));
    }

    public function test_file_creator_config_can_be_resolved(): void
    {
        $this->provider->register();
        $config = $this->app->make(FileCreatorConfigInterface::class);
        $this->assertInstanceOf(FileCreatorConfig::class, $config);
    }

    public function test_testing_config_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveTestingConfigInterface::class));
    }

    public function test_testing_config_can_be_resolved(): void
    {
        $this->provider->register();
        $config = $this->app->make(DirectiveTestingConfigInterface::class);
        $this->assertInstanceOf(DirectiveTestingConfig::class, $config);
    }

    public function test_database_testing_config_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DatabaseTestingConfigInterface::class));
    }

    public function test_database_testing_config_is_same_as_testing_config(): void
    {
        $this->provider->register();
        $testingConfig = $this->app->make(DirectiveTestingConfigInterface::class);
        $databaseConfig = $this->app->make(DatabaseTestingConfigInterface::class);
        $this->assertSame($testingConfig, $databaseConfig);
    }

    // ==================== Core Service Tests ====================

    public function test_file_system_interface_is_bound(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(FileSystemInterface::class));
    }

    public function test_file_creator_service_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(FileCreatorService::class));
    }

    public function test_file_creator_service_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(FileCreatorService::class);
        $this->assertInstanceOf(FileCreatorService::class, $service);
    }

    public function test_signature_validation_service_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(SignatureValidationService::class));
    }

    public function test_signature_validation_service_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(SignatureValidationService::class);
        $this->assertInstanceOf(SignatureValidationService::class, $service);
    }

    public function test_hydrator_service_is_registered(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveHydratorService::class));
    }

    public function test_hydrator_service_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(DirectiveHydratorService::class);
        $this->assertInstanceOf(DirectiveHydratorService::class, $service);
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
        $this->assertTrue($this->app->bound(DirectiveParserService::class));
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
        $this->assertTrue($this->app->bound(DirectiveClassScanner::class));
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
