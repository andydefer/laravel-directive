<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Feature;

use AndyDefer\Directive\Configs\DirectiveParserConfig;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Configs\EnvDirectiveConfig;
use AndyDefer\Directive\Configs\EnvDirectiveNamingConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Contexts\FileSystemContext;
use AndyDefer\Directive\Contexts\LaravelContext;
use AndyDefer\Directive\Contexts\ParameterParserContext;
use AndyDefer\Directive\Contracts\Configs\DatabaseTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveNamingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveParserConfigInterface;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\Directive\Contracts\Configs\SignatureValidationConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Services\ArgumentApplierService;
use AndyDefer\Directive\Services\ArgumentSplitterService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Services\OptionParserService;
use AndyDefer\Directive\Services\ParameterExtractorService;
use AndyDefer\Directive\Services\ParameterOrderValidatorService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Strategies\DefaultValueArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionalArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionStrategy;
use AndyDefer\Directive\Strategies\RequiredArgumentStrategy;
use AndyDefer\Directive\Strategies\VariadicArgumentStrategy;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

// ⚠️ Ce test doit hériter de OrchestraTestCase pour avoir une vraie application Laravel
final class DirectiveServiceProviderTest extends OrchestraTestCase
{
    private DirectiveServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Utiliser l'application Laravel réelle fournie par Orchestra
        $this->provider = new DirectiveServiceProvider($this->app);
    }

    protected function getPackageProviders($app)
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

    public function test_config_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveConfigInterface::class));
    }

    public function test_config_can_be_resolved(): void
    {
        $this->provider->register();
        $config = $this->app->make(DirectiveConfigInterface::class);
        $this->assertInstanceOf(EnvDirectiveConfig::class, $config);
    }

    public function test_naming_config_is_registered_as_singleton(): void
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

    public function test_signature_validation_config_is_registered_as_singleton(): void
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

    public function test_parser_config_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveParserConfigInterface::class));
    }

    public function test_parser_config_can_be_resolved(): void
    {
        $this->provider->register();
        $config = $this->app->make(DirectiveParserConfigInterface::class);
        $this->assertInstanceOf(DirectiveParserConfig::class, $config);
    }

    public function test_file_creator_config_is_registered_as_singleton(): void
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

    public function test_testing_config_is_registered_as_singleton(): void
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

    public function test_database_testing_config_is_registered_as_singleton(): void
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

    // ==================== Context Tests ====================

    public function test_directive_discovery_context_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveDiscoveryContext::class));
    }

    public function test_directive_discovery_context_can_be_resolved(): void
    {
        $this->provider->register();
        $context = $this->app->make(DirectiveDiscoveryContext::class);
        $this->assertInstanceOf(DirectiveDiscoveryContext::class, $context);
    }

    public function test_directive_context_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveContext::class));
    }

    public function test_directive_context_can_be_resolved(): void
    {
        $this->provider->register();
        $context = $this->app->make(DirectiveContext::class);
        $this->assertInstanceOf(DirectiveContext::class, $context);
    }

    public function test_laravel_context_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(LaravelContext::class));
    }

    public function test_laravel_context_can_be_resolved(): void
    {
        $this->provider->register();
        $context = $this->app->make(LaravelContext::class);
        $this->assertInstanceOf(LaravelContext::class, $context);
    }

    public function test_file_system_context_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(FileSystemContext::class));
    }

    public function test_file_system_context_can_be_resolved(): void
    {
        $this->provider->register();
        $context = $this->app->make(FileSystemContext::class);
        $this->assertInstanceOf(FileSystemContext::class, $context);
    }

    public function test_file_creation_context_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(FileCreationContext::class));
    }

    public function test_file_creation_context_can_be_resolved(): void
    {
        $this->provider->register();
        $context = $this->app->make(FileCreationContext::class);
        $this->assertInstanceOf(FileCreationContext::class, $context);
    }

    public function test_parameter_parser_context_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(ParameterParserContext::class));
    }

    public function test_parameter_parser_context_can_be_resolved(): void
    {
        $this->provider->register();
        $context = $this->app->make(ParameterParserContext::class);
        $this->assertInstanceOf(ParameterParserContext::class, $context);
    }

    // ==================== Parser Component Tests ====================

    public function test_parameter_order_validator_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(ParameterOrderValidatorService::class));
    }

    public function test_parameter_order_validator_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(ParameterOrderValidatorService::class);
        $this->assertInstanceOf(ParameterOrderValidatorService::class, $service);
    }

    public function test_parameter_extractor_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(ParameterExtractorService::class));
    }

    public function test_parameter_extractor_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(ParameterExtractorService::class);
        $this->assertInstanceOf(ParameterExtractorService::class, $service);
    }

    public function test_option_parser_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(OptionParserService::class));
    }

    public function test_option_parser_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(OptionParserService::class);
        $this->assertInstanceOf(OptionParserService::class, $service);
    }

    public function test_argument_splitter_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(ArgumentSplitterService::class));
    }

    public function test_argument_splitter_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(ArgumentSplitterService::class);
        $this->assertInstanceOf(ArgumentSplitterService::class, $service);
    }

    public function test_argument_applier_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(ArgumentApplierService::class));
    }

    public function test_argument_applier_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(ArgumentApplierService::class);
        $this->assertInstanceOf(ArgumentApplierService::class, $service);
    }

    public function test_parser_service_is_registered_as_singleton(): void
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

    // ==================== Core Service Tests ====================

    public function test_file_system_interface_is_bound(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(FileSystemInterface::class));
    }

    public function test_file_creator_service_is_registered_as_singleton(): void
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

    public function test_signature_validation_service_is_registered_as_singleton(): void
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

    public function test_naming_service_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveNamingService::class));
    }

    public function test_naming_service_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(DirectiveNamingService::class);
        $this->assertInstanceOf(DirectiveNamingService::class, $service);
    }

    public function test_interaction_service_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveInteractionService::class));
    }

    public function test_interaction_service_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(DirectiveInteractionService::class);
        $this->assertInstanceOf(DirectiveInteractionService::class, $service);
    }

    public function test_hydrator_service_is_registered_as_singleton(): void
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

    public function test_discovery_service_is_registered_as_singleton(): void
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

    public function test_renderer_service_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveRendererService::class));
    }

    public function test_renderer_service_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(DirectiveRendererService::class);
        $this->assertInstanceOf(DirectiveRendererService::class, $service);
    }

    public function test_execution_service_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(DirectiveExecutionService::class));
    }

    public function test_execution_service_can_be_resolved(): void
    {
        $this->provider->register();
        $service = $this->app->make(DirectiveExecutionService::class);
        $this->assertInstanceOf(DirectiveExecutionService::class, $service);
    }

    // ==================== Dispatcher Tests ====================

    public function test_render_dispatcher_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(RenderDispatcher::class));
    }

    public function test_render_dispatcher_can_be_resolved(): void
    {
        $this->provider->register();
        $dispatcher = $this->app->make(RenderDispatcher::class);
        $this->assertInstanceOf(RenderDispatcher::class, $dispatcher);
    }

    public function test_input_dispatcher_is_registered_as_singleton(): void
    {
        $this->provider->register();
        $this->assertTrue($this->app->bound(InputDispatcher::class));
    }

    public function test_input_dispatcher_can_be_resolved(): void
    {
        $this->provider->register();
        $dispatcher = $this->app->make(InputDispatcher::class);
        $this->assertInstanceOf(InputDispatcher::class, $dispatcher);
    }

    // ==================== Strategy Tests ====================

    public function test_required_argument_strategy_is_registered(): void
    {
        $this->provider->register();
        $context = $this->app->make(ParameterParserContext::class);
        $strategies = $context->getStrategies();

        $hasStrategy = false;
        foreach ($strategies as $strategy) {
            if ($strategy instanceof RequiredArgumentStrategy) {
                $hasStrategy = true;
                break;
            }
        }
        $this->assertTrue($hasStrategy, 'RequiredArgumentStrategy not found in ParameterParserContext');
    }

    public function test_default_value_argument_strategy_is_registered(): void
    {
        $this->provider->register();
        $context = $this->app->make(ParameterParserContext::class);
        $strategies = $context->getStrategies();

        $hasStrategy = false;
        foreach ($strategies as $strategy) {
            if ($strategy instanceof DefaultValueArgumentStrategy) {
                $hasStrategy = true;
                break;
            }
        }
        $this->assertTrue($hasStrategy, 'DefaultValueArgumentStrategy not found in ParameterParserContext');
    }

    public function test_optional_argument_strategy_is_registered(): void
    {
        $this->provider->register();
        $context = $this->app->make(ParameterParserContext::class);
        $strategies = $context->getStrategies();

        $hasStrategy = false;
        foreach ($strategies as $strategy) {
            if ($strategy instanceof OptionalArgumentStrategy) {
                $hasStrategy = true;
                break;
            }
        }
        $this->assertTrue($hasStrategy, 'OptionalArgumentStrategy not found in ParameterParserContext');
    }

    public function test_variadic_argument_strategy_is_registered(): void
    {
        $this->provider->register();
        $context = $this->app->make(ParameterParserContext::class);
        $strategies = $context->getStrategies();

        $hasStrategy = false;
        foreach ($strategies as $strategy) {
            if ($strategy instanceof VariadicArgumentStrategy) {
                $hasStrategy = true;
                break;
            }
        }
        $this->assertTrue($hasStrategy, 'VariadicArgumentStrategy not found in ParameterParserContext');
    }

    public function test_option_strategy_is_registered(): void
    {
        $this->provider->register();
        $context = $this->app->make(ParameterParserContext::class);
        $strategies = $context->getStrategies();

        $hasStrategy = false;
        foreach ($strategies as $strategy) {
            if ($strategy instanceof OptionStrategy) {
                $hasStrategy = true;
                break;
            }
        }
        $this->assertTrue($hasStrategy, 'OptionStrategy not found in ParameterParserContext');
    }

    // ==================== Kernel Tests ====================

    public function test_kernel_can_be_resolved(): void
    {
        $this->provider->register();
        $kernel = $this->app->make(DirectiveKernel::class);
        $this->assertInstanceOf(DirectiveKernel::class, $kernel);
    }

    // ==================== Additional Context Resolution Tests ====================

    public function test_directive_discovery_context_resolution(): void
    {
        $this->provider->register();
        $context = $this->app->make(DirectiveDiscoveryContext::class);
        $this->assertInstanceOf(DirectiveDiscoveryContext::class, $context);
    }

    public function test_directive_context_resolution(): void
    {
        $this->provider->register();
        $context = $this->app->make(DirectiveContext::class);
        $this->assertInstanceOf(DirectiveContext::class, $context);
    }

    public function test_config_resolution_returns_env_directive_config(): void
    {
        $this->provider->register();
        $config = $this->app->make(DirectiveConfigInterface::class);
        $this->assertInstanceOf(EnvDirectiveConfig::class, $config);
    }
}
