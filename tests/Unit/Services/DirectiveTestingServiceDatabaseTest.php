<?php

// tests/Unit/Services/DirectiveTestingServiceDatabaseTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestLaravelDatabaseDirective;
use AndyDefer\Directive\Tests\Fixtures\Models\TestPost;
use AndyDefer\Directive\Tests\Fixtures\Models\TestUser;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\Directive\Tests\TestDirectiveConfig;

final class DirectiveTestingServiceDatabaseTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $fixturesPath = realpath(__DIR__ . '/../../Fixtures/Directives');
        $config = new TestDirectiveConfig($fixturesPath);
        $this->app->instance(DirectiveConfigInterface::class, $config);

        // Signature correcte : (application, context, config)
        $this->service = new DirectiveTestingService($this->app);
    }

    protected function tearDown(): void
    {
        try {
            TestUser::truncate();
            TestPost::truncate();
        } catch (\Exception $e) {
            // Ignorer
        }

        $this->service->destroy();
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        TestUser::truncate();
        TestPost::truncate();

        $john = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $jane = TestUser::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => bcrypt('password456'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $bob = TestUser::create([
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com',
            'password' => bcrypt('password789'),
            'is_active' => false,
            'email_verified_at' => null,
        ]);

        TestPost::create([
            'user_id' => $john->id,
            'title' => 'John\'s First Post',
            'content' => 'Content of first post',
            'is_published' => true,
        ]);

        TestPost::create([
            'user_id' => $john->id,
            'title' => 'John\'s Second Post',
            'content' => 'Content of second post',
            'is_published' => true,
        ]);

        TestPost::create([
            'user_id' => $john->id,
            'title' => 'John\'s Draft',
            'content' => 'Draft content',
            'is_published' => false,
        ]);

        TestPost::create([
            'user_id' => $jane->id,
            'title' => 'Jane\'s Published Post',
            'content' => 'Jane\'s content',
            'is_published' => true,
        ]);

        TestPost::create([
            'user_id' => $jane->id,
            'title' => 'Jane\'s Draft',
            'content' => 'Jane\'s draft',
            'is_published' => false,
        ]);
    }

    public function test_laravel_database_directive_executes_successfully(): void
    {
        $this->seedTestData();

        //  $response = $this->service->runDirective('test-laravel-db');
        $response = $this->service->run(TestLaravelDatabaseDirective::class);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Testing Laravel database integration', $response->output);
        $this->assertStringContainsString('Laravel is available', $response->output);
    }
}
