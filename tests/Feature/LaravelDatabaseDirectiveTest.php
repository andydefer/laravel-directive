<?php

// tests/Feature/LaravelDatabaseDirectiveTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Feature;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\Fixtures\Models\TestPost;
use AndyDefer\Directive\Tests\Fixtures\Models\TestUser;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class LaravelDatabaseDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Plus simple : on passe seulement l'application
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

    public function test_database_directive_executes_successfully(): void
    {
        $this->seedTestData();

        $response = $this->service->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode, 'Output: ' . $response->output);
        $this->assertStringContainsString('Testing Laravel database integration', $response->output);
        $this->assertStringContainsString('Laravel is available', $response->output);
        $this->assertStringContainsString('Found 3 users in database', $response->output);
        $this->assertStringContainsString('Found 2 active users', $response->output);
        $this->assertStringContainsString('Found 3 published posts', $response->output);
    }

    public function test_database_directive_with_verbose_option(): void
    {
        $this->seedTestData();

        $response = $this->service->runDirective('test-laravel-db', ['--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertNotEmpty($response->output);
    }

    public function test_database_directive_shows_user_info(): void
    {
        $this->seedTestData();

        $response = $this->service->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('John Doe', $response->output);
        $this->assertStringContainsString('john@example.com', $response->output);
        $this->assertStringContainsString('Jane Smith', $response->output);
        $this->assertStringContainsString('jane@example.com', $response->output);
    }

    public function test_database_directive_with_empty_database(): void
    {
        $response = $this->service->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Found 0 users in database', $response->output);
        $this->assertStringContainsString('No verified users found', $response->output);
        $this->assertStringContainsString('Found 0 published posts', $response->output);
    }
}
