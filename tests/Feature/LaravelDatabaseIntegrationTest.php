<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Feature;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Tests\Fixtures\Models\TestPost;
use AndyDefer\Directive\Tests\Fixtures\Models\TestUser;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use AndyDefer\Directive\Tests\TestDirectiveConfig;

final class LaravelDatabaseIntegrationTest extends IntegrationTestCase
{
    private DirectiveKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();

        $fixturesPath = realpath(__DIR__ . '/../Fixtures/Directives');
        $config = new TestDirectiveConfig($fixturesPath);
        $this->app->instance(DirectiveConfigInterface::class, $config);

        $this->kernel = $this->app->make(DirectiveKernel::class);
    }

    protected function tearDown(): void
    {
        try {
            TestUser::truncate();
            TestPost::truncate();
        } catch (\Exception $e) {
            // Ignorer
        }
        parent::tearDown();
    }

    private function runDirective(string $signature, array $arguments = []): array
    {
        $argv = array_merge(['directive', $signature], $arguments);

        ob_start();
        $exit_code = $this->kernel->run($argv);
        $output = ob_get_clean();

        return [
            'exit_code' => $exit_code,
            'output' => $output,
        ];
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

        $response = $this->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response['exit_code'], 'Output: ' . $response['output']);
        $this->assertStringContainsString('Laravel is available', $response['output']);
        $this->assertStringContainsString('3 users in database', $response['output']);
        $this->assertStringContainsString('2 active users', $response['output']);
    }

    public function test_database_directive_shows_user_table(): void
    {
        $this->seedTestData();

        $response = $this->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response['exit_code']);
        $this->assertStringContainsString('User', $response['output']);
        $this->assertStringContainsString('Email', $response['output']);
        $this->assertStringContainsString('John Doe', $response['output']);
        $this->assertStringContainsString('john@example.com', $response['output']);
    }

    public function test_database_directive_with_empty_database(): void
    {
        $response = $this->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response['exit_code']);
        $this->assertStringContainsString('0 users in database', $response['output']);
        $this->assertStringContainsString('No verified users found', $response['output']);
        $this->assertStringContainsString('0 published posts', $response['output']);
    }
}
