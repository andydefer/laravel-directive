<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Tests\TestCase;

final class DirectiveNamingServiceTest extends TestCase
{
    private DirectiveNamingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveNamingService();
    }

    public function test_generate_class_name_converts_simple_name(): void
    {
        $result = $this->service->generateClassName('user-create');
        $this->assertSame('UserCreateDirective', $result);
    }

    public function test_generate_class_name_converts_single_word(): void
    {
        $result = $this->service->generateClassName('list');
        $this->assertSame('ListDirective', $result);
    }

    public function test_generate_class_name_converts_multiple_hyphens(): void
    {
        $result = $this->service->generateClassName('db-migrate-fresh');
        $this->assertSame('DbMigrateFreshDirective', $result);
    }

    public function test_generate_class_name_converts_with_numbers(): void
    {
        $result = $this->service->generateClassName('api-v2');
        $this->assertSame('ApiV2Directive', $result);
    }

    public function test_generate_class_name_converts_complex_name(): void
    {
        $result = $this->service->generateClassName('user-profile-create-v2');
        $this->assertSame('UserProfileCreateV2Directive', $result);
    }

    public function test_generate_signature_with_option_adds_placeholder(): void
    {
        $result = $this->service->generateSignatureWithOption('user-create');
        $this->assertSame('user-create {--option}', $result);
    }

    public function test_replace_stub_variables(): void
    {
        $stub = 'class {{class}} extends {{signature}} {{description}} {{date}}';
        $className = 'UserCreateDirective';
        $signature = 'user-create';

        $result = $this->service->replaceStubVariables($stub, $className, $signature);

        $this->assertStringContainsString('class UserCreateDirective', $result);
        $this->assertStringContainsString('user-create {--option}', $result);
        $this->assertStringContainsString('Generated directive for user-create', $result);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result);
    }
}
