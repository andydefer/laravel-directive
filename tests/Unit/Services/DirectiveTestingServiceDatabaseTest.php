<?php

// tests/Unit/Services/DirectiveTestingServiceDatabaseTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\Fixtures\Models\TestPost;
use AndyDefer\Directive\Tests\Fixtures\Models\TestUser;
use AndyDefer\Directive\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DirectiveTestingServiceDatabaseTest extends IntegrationTestCase
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
            if (Schema::hasTable('test_users')) {
                TestUser::truncate();
            }
            if (Schema::hasTable('test_posts')) {
                TestPost::truncate();
            }
        } catch (\Exception $e) {
            // Ignorer
        }

        $this->service->destroy();
        parent::tearDown();
    }

    public function test_laravel_database_directive_executes_successfully(): void
    {
        $this->seedTestData();

        $response = $this->service->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Testing Laravel database integration', $response->output);
        $this->assertStringContainsString('Laravel is available', $response->output);
    }

    public function test_counts_users_correctly(): void
    {
        $this->seedTestData();

        $response = $this->service->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Found 3 users in database', $response->output);
        $this->assertStringContainsString('Found 2 active users', $response->output);
    }

    public function test_shows_published_posts(): void
    {
        $this->seedTestData();

        $response = $this->service->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Found 3 published posts', $response->output);
    }

    public function test_displays_user_info(): void
    {
        $this->seedTestData();

        $response = $this->service->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('John Doe', $response->output);
        $this->assertStringContainsString('john@example.com', $response->output);
    }

    public function test_handles_empty_database(): void
    {
        $response = $this->service->runDirective('test-laravel-db');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Found 0 users in database', $response->output);
        $this->assertStringContainsString('No verified users found', $response->output);
        $this->assertStringContainsString('Found 0 published posts', $response->output);
    }

    public function test_database_transaction_in_closure(): void
    {
        $initialCount = TestUser::count();

        $this->service->createTestDirective('test-db-transaction', function ($d) {
            $user = TestUser::create([
                'name' => 'Transaction User',
                'email' => 'transaction@example.com',
                'password' => bcrypt('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $d->info("User created with ID: {$user->id}");
            return ExitCode::SUCCESS;
        });

        $response = $this->service->runDirective('test-db-transaction');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('User created', $response->output);

        $newCount = TestUser::count();
        $this->assertEquals($initialCount + 1, $newCount);
    }

    public function test_query_specific_user_data(): void
    {
        $this->seedTestData();

        $this->service->createTestDirective('test-user-query', function ($d) {
            $user = TestUser::where('email', 'john@example.com')->first();

            if ($user) {
                $d->line("User: {$user->name}");
                $d->line("Email: {$user->email}");
                $d->info("User query successful!");
                return ExitCode::SUCCESS;
            }

            $d->error("User not found");
            return ExitCode::FAILURE;
        });

        $response = $this->service->runDirective('test-user-query');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('User: John Doe', $response->output);
        $this->assertStringContainsString('User query successful!', $response->output);
    }

    public function test_handles_database_errors_gracefully(): void
    {
        $this->service->createTestDirective('test-db-error', function ($d) {
            try {
                DB::table('non_existent_table')->get();
                return ExitCode::SUCCESS;
            } catch (\Exception $e) {
                $d->error("Database error caught: " . $e->getMessage());
                return ExitCode::FAILURE;
            }
        });

        $response = $this->service->runDirective('test-db-error');

        $this->assertSame(ExitCode::FAILURE, $response->exitCode);
        $this->assertStringContainsString('Database error caught', $response->output);
    }

    public function test_maintains_connection_across_multiple_directives(): void
    {
        $this->seedTestData();

        $this->service->createTestDirective('test-check-connection', function ($d) {
            $count = TestUser::count();
            $d->line("Connection still alive: {$count} users found");
            return ExitCode::SUCCESS;
        });

        $response1 = $this->service->runDirective('test-laravel-db');
        $response2 = $this->service->runDirective('test-check-connection');

        $this->assertSame(ExitCode::SUCCESS, $response1->exitCode);
        $this->assertSame(ExitCode::SUCCESS, $response2->exitCode);
        $this->assertStringContainsString('Connection still alive: 3 users found', $response2->output);
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
}
