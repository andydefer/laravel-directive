<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit;

use AndyDefer\Directive\Configs\EnvDirectiveConfig;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Tests\UnitTestCase;
use Illuminate\Container\Container;

final class DirectiveServiceProviderTest extends UnitTestCase
{
    private Container $container;

    private DirectiveServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;

        $this->container->instance('config', new class
        {
            public function get($key, $default = null)
            {
                return $default;
            }

            public function has($key)
            {
                return false;
            }
        });

        $this->provider = new DirectiveServiceProvider($this->container);
    }

    public function test_register_does_not_throw_exception(): void
    {
        $this->provider->register();
        $this->addToAssertionCount(1);
    }

    public function test_config_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(DirectiveConfigInterface::class));
    }

    public function test_laravel_bootstrapper_context_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(LaravelBootstrapperContext::class));
    }

    public function test_factory_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(DirectiveFactoryInterface::class));
    }

    public function test_parser_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(DirectiveParserService::class));
    }

    public function test_hydrator_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(DirectiveHydratorService::class));
    }

    public function test_discovery_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(DirectiveDiscoveryService::class));
    }

    public function test_renderer_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(DirectiveRendererService::class));
    }

    public function test_signature_validation_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(SignatureValidationService::class));
    }

    public function test_naming_service_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(DirectiveNamingService::class));
    }

    public function test_render_task_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(RenderDispatcher::class));
    }

    public function test_input_task_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(InputDispatcher::class));
    }

    public function test_interaction_service_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(DirectiveInteractionService::class));
    }

    public function test_execution_service_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->container->bound(DirectiveExecutionService::class));
    }

    public function test_kernel_can_be_resolved(): void
    {
        $this->provider->register();
        $kernel = $this->container->make(DirectiveKernel::class);
        $this->assertInstanceOf(DirectiveKernel::class, $kernel);
    }

    public function test_render_task_can_be_resolved(): void
    {
        $this->provider->register();
        $task = $this->container->make(RenderDispatcher::class);
        $this->assertInstanceOf(RenderDispatcher::class, $task);
    }

    public function test_input_task_can_be_resolved(): void
    {
        $this->provider->register();
        $task = $this->container->make(InputDispatcher::class);
        $this->assertInstanceOf(InputDispatcher::class, $task);
    }

    public function test_config_can_be_resolved(): void
    {
        $this->provider->register();
        $config = $this->container->make(DirectiveConfigInterface::class);
        $this->assertInstanceOf(DirectiveConfigInterface::class, $config);
    }

    public function test_laravel_bootstrapper_context_can_be_resolved(): void
    {
        $this->provider->register();
        $bootstrapperContext = $this->container->make(LaravelBootstrapperContext::class);
        $this->assertInstanceOf(LaravelBootstrapperContext::class, $bootstrapperContext);
    }
}
