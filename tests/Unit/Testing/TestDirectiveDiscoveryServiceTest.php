<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Testing;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
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
        // Arrange: Create a test directive
        $directive = new TestConcreteDirective($this->interaction);

        // Act: Register the directive
        $this->service->registerDirective($directive);

        // Assert: Verify directive was added
        $this->assertCount(1, $this->service->getRegisteredDirectives());
        $this->assertSame($directive, $this->service->getRegisteredDirectives()[0]);
    }

    public function test_register_multiple_directives_adds_all(): void
    {
        // Arrange: Create two test directives
        $firstDirective = new TestConcreteDirective($this->interaction);
        $secondDirective = new AnotherTestDirective($this->interaction);

        // Act: Register both directives
        $this->service->registerDirectives([$firstDirective, $secondDirective]);

        // Assert: Verify both were added
        $this->assertCount(2, $this->service->getRegisteredDirectives());
    }

    public function test_clear_registered_directives_empties_collection(): void
    {
        // Arrange: Register a directive
        $directive = new TestConcreteDirective($this->interaction);
        $this->service->registerDirective($directive);
        $this->assertCount(1, $this->service->getRegisteredDirectives());

        // Act: Clear registered directives
        $this->service->clearRegisteredDirectives();

        // Assert: Collection is empty
        $this->assertCount(0, $this->service->getRegisteredDirectives());
    }

    public function test_register_directive_class_instantiates_and_registers(): void
    {
        // Act: Register directive by class name
        $directive = $this->service->registerDirectiveClass(TestConcreteDirective::class, [$this->interaction]);

        // Assert: Verify instantiation and registration
        $this->assertInstanceOf(TestConcreteDirective::class, $directive);
        $this->assertCount(1, $this->service->getRegisteredDirectives());
        $this->assertSame($directive, $this->service->getRegisteredDirectives()[0]);
    }

    public function test_register_directive_class_passes_constructor_args(): void
    {
        // Arrange: Custom constructor argument
        $customArgument = 'custom-value';

        // Act: Register directive with custom args
        /** @var TestDirectiveWithArgs $directive */
        $directive = $this->service->registerDirectiveClass(
            TestDirectiveWithArgs::class,
            [$this->interaction, $customArgument]
        );

        // Assert: Verify constructor argument was passed
        $this->assertSame($customArgument, $directive->getCustomArg());
    }

    public function test_discover_returns_registered_directives_metadata(): void
    {
        // Arrange: Register a directive
        $directive = new TestConcreteDirective($this->interaction);
        $this->service->registerDirective($directive);

        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify metadata is correct
        $this->assertCount(1, $result);
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);

        $firstItem = $result->firstItem();
        $this->assertInstanceOf(DirectiveMetadataRecord::class, $firstItem);
        $this->assertSame('test-concrete', $firstItem->signature);
        $this->assertSame(TestConcreteDirective::class, $firstItem->class);
    }

    public function test_discover_returns_empty_when_no_directives(): void
    {
        // Act: Discover with no registered directives
        $result = $this->service->discover();

        // Assert: Verify empty result
        $this->assertCount(0, $result);
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $result);
    }

    public function test_discover_includes_all_registered_directives(): void
    {
        // Arrange: Register multiple directives
        $firstDirective = new TestConcreteDirective($this->interaction);
        $secondDirective = new AnotherTestDirective($this->interaction);
        $this->service->registerDirectives([$firstDirective, $secondDirective]);

        // Act: Discover directives
        $result = $this->service->discover();

        // Assert: Verify both signatures are present
        $this->assertCount(2, $result);

        $signatures = [];
        foreach ($result as $item) {
            $signatures[] = $item->signature;
        }

        $this->assertContains('test-concrete', $signatures);
        $this->assertContains('another-test', $signatures);
    }

    public function test_discover_with_filesystem_enabled_calls_parent_methods(): void
    {
        // Arrange: Create service with filesystem discovery enabled
        $mockService = $this->getMockBuilder(TestDirectiveDiscoveryService::class)
            ->setConstructorArgs([$this->config, $this->hydrator, false])
            ->onlyMethods(['discoverFromFilesystem', 'discoverFromVendorPackagesRecursive'])
            ->getMock();

        // Assert: Expect parent methods to be called
        $mockService->expects($this->once())
            ->method('discoverFromFilesystem')
            ->willReturn(new DirectiveMetadataCollection());

        $mockService->expects($this->once())
            ->method('discoverFromVendorPackagesRecursive')
            ->willReturn(new DirectiveMetadataCollection());

        // Act: Discover directives
        $mockService->discover();
    }
}
