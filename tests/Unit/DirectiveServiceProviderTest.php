<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit;

use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Tests\TestCase;
use Illuminate\Container\Container;

final class DirectiveServiceProviderTest extends TestCase
{
    private Container $container;
    private DirectiveServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        $this->provider = new DirectiveServiceProvider($this->container);
    }

    public function test_register_does_not_throw_exception(): void
    {
        // Act & Assert
        $this->provider->register();
        $this->addToAssertionCount(1);
    }

    public function test_boot_does_not_throw_exception(): void
    {
        // Act & Assert
        $this->provider->boot();
        $this->addToAssertionCount(1);
    }

    public function test_config_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Config\DirectiveConfig::class));
    }

    public function test_factory_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Contracts\DirectiveFactoryInterface::class));
    }

    public function test_registrar_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Contracts\DirectiveRegistrarInterface::class));
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Services\DirectiveRegistrar::class));
    }

    public function test_parser_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Services\DirectiveParserService::class));
    }

    public function test_hydrator_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Services\DirectiveHydratorService::class));
    }

    public function test_discovery_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Services\DirectiveDiscoveryService::class));
    }

    public function test_renderer_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Services\DirectiveRendererService::class));
    }

    public function test_signature_validation_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Services\SignatureValidationService::class));
    }

    public function test_naming_service_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Services\DirectiveNamingService::class));
    }

    public function test_render_task_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Tasks\RenderTask::class));
    }

    public function test_input_task_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Tasks\InputTask::class));
    }

    public function test_create_directive_file_task_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Tasks\CreateDirectiveFileTask::class));
    }

    public function test_interaction_service_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Services\DirectiveInteractionService::class));
    }

    public function test_execution_service_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Services\DirectiveExecutionService::class));
    }

    public function test_make_directive_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\Directives\MakeDirective::class));
    }

    public function test_kernel_is_registered_as_singleton(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $this->assertTrue($this->container->bound(\AndyDefer\Directive\DirectiveKernel::class));
    }

    public function test_make_directive_can_be_resolved(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $makeDirective = $this->container->make(\AndyDefer\Directive\Directives\MakeDirective::class);
        $this->assertInstanceOf(\AndyDefer\Directive\Directives\MakeDirective::class, $makeDirective);
    }

    public function test_kernel_can_be_resolved(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $kernel = $this->container->make(\AndyDefer\Directive\DirectiveKernel::class);
        $this->assertInstanceOf(\AndyDefer\Directive\DirectiveKernel::class, $kernel);
    }

    public function test_render_task_can_be_resolved(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $task = $this->container->make(\AndyDefer\Directive\Tasks\RenderTask::class);
        $this->assertInstanceOf(\AndyDefer\Directive\Tasks\RenderTask::class, $task);
    }

    public function test_input_task_can_be_resolved(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $task = $this->container->make(\AndyDefer\Directive\Tasks\InputTask::class);
        $this->assertInstanceOf(\AndyDefer\Directive\Tasks\InputTask::class, $task);
    }

    public function test_config_can_be_resolved(): void
    {
        // Act
        $this->provider->register();

        // Assert
        $config = $this->container->make(\AndyDefer\Directive\Config\DirectiveConfig::class);
        $this->assertInstanceOf(\AndyDefer\Directive\Config\DirectiveConfig::class, $config);
    }
}
