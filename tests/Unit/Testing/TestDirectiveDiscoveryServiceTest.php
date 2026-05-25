<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Testing;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Testing\TestDirectiveDiscoveryService;
use AndyDefer\Directive\Tests\Fixtures\Directives\AnotherTestDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestDirectiveWithArgs;
use AndyDefer\Directive\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class TestDirectiveDiscoveryServiceTest extends UnitTestCase
{
    private TestDirectiveDiscoveryService $service;
    private DirectiveConfig $config;
    private DirectiveHydratorService&MockObject $hydrator;
    private DirectiveInteractionService&MockObject $interaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->config = DirectiveConfig::default()->withDirectivesPath('/test/path');
        $this->hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->service = new TestDirectiveDiscoveryService($this->config, $this->hydrator, true);
    }

    public function test_register_directive_adds_directive_to_collection(): void
    {
        $directive = new TestConcreteDirective($this->interaction);

        $this->service->registerDirective($directive);

        $this->assertCount(1, $this->service->getRegisteredDirectives());
        $this->assertSame($directive, $this->service->getRegisteredDirectives()[0]);
    }

    public function test_register_multiple_directives_adds_all(): void
    {
        $directive1 = new TestConcreteDirective($this->interaction);
        $directive2 = new AnotherTestDirective($this->interaction);

        $this->service->registerDirectives([$directive1, $directive2]);

        $this->assertCount(2, $this->service->getRegisteredDirectives());
    }

    public function test_clear_registered_directives_empties_collection(): void
    {
        $directive = new TestConcreteDirective($this->interaction);
        $this->service->registerDirective($directive);

        $this->assertCount(1, $this->service->getRegisteredDirectives());

        $this->service->clearRegisteredDirectives();

        $this->assertCount(0, $this->service->getRegisteredDirectives());
    }

    public function test_register_directive_class_instantiates_and_registers(): void
    {
        $directive = $this->service->registerDirectiveClass(TestConcreteDirective::class, [$this->interaction]);

        $this->assertInstanceOf(TestConcreteDirective::class, $directive);
        $this->assertCount(1, $this->service->getRegisteredDirectives());
        $this->assertSame($directive, $this->service->getRegisteredDirectives()[0]);
    }

    public function test_register_directive_class_passes_constructor_args(): void
    {
        $customArg = 'custom-value';
        /** @var AndyDefer\Directive\Tests\Fixtures\Directives\TestDirectiveWithArgs $directive */
        $directive = $this->service->registerDirectiveClass(
            TestDirectiveWithArgs::class,
            [$this->interaction, $customArg]
        );

        $this->assertSame($customArg, $directive->getCustomArg());
    }

    public function test_discover_returns_registered_directives_metadata(): void
    {
        $directive = new TestConcreteDirective($this->interaction);

        $this->service->registerDirective($directive);

        $result = $this->service->discover();

        $this->assertCount(1, $result);

        $item = $result->firstItem();
        $this->assertInstanceOf(DirectiveMetadataRecord::class, $item);
        $this->assertSame('test-concrete', $item->signature);
        $this->assertSame(TestConcreteDirective::class, $item->class);
    }

    public function test_discover_returns_empty_when_no_directives(): void
    {
        $result = $this->service->discover();

        $this->assertCount(0, $result);
    }

    public function test_discover_includes_all_registered_directives(): void
    {
        $directive1 = new TestConcreteDirective($this->interaction);
        $directive2 = new AnotherTestDirective($this->interaction);

        $this->service->registerDirectives([$directive1, $directive2]);

        $result = $this->service->discover();

        $this->assertCount(2, $result);

        $signatures = [];
        foreach ($result as $item) {
            $signatures[] = $item->signature;
        }

        $this->assertContains('test-concrete', $signatures);
        $this->assertContains('another-test', $signatures);
    }

    public function test_discover_with_filesystem_enabled_calls_parent(): void
    {
        $mock = $this->getMockBuilder(TestDirectiveDiscoveryService::class)
            ->setConstructorArgs([$this->config, $this->hydrator, false])
            ->onlyMethods(['discoverFromFilesystem', 'discoverFromVendorPackagesRecursive'])
            ->getMock();

        $mock->expects($this->once())->method('discoverFromFilesystem');
        $mock->expects($this->once())->method('discoverFromVendorPackagesRecursive');

        $mock->discover();
    }
}
