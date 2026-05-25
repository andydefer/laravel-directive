<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Feature;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Tests\Fixtures\Models\TestPost;
use AndyDefer\Directive\Tests\Fixtures\Models\TestUser;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class LaravelDatabaseIntegrationTest extends IntegrationTestCase
{
    private DirectiveKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = $this->app->make(DirectiveKernel::class);
    }

    private function runAndCaptureOutput(array $argv): array
    {
        ob_start();
        $result = $this->kernel->run($argv);
        $output = ob_get_clean();

        return [
            'result' => $result,
            'output' => $output,
        ];
    }

    private function createTestData(): void
    {
        // Créer des utilisateurs de test
        $user1 = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user2 = TestUser::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user3 = TestUser::create([
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com',
            'is_active' => false,
            'email_verified_at' => null,
        ]);

        // Créer des posts pour les utilisateurs
        TestPost::create([
            'user_id' => $user1->id,
            'title' => 'John\'s first post',
            'content' => 'Content of first post',
            'is_published' => true,
            'published_at' => now(),
            'tags' => ['test', 'post'],
        ]);

        TestPost::create([
            'user_id' => $user1->id,
            'title' => 'John\'s second post',
            'content' => 'Content of second post',
            'is_published' => false,
            'published_at' => null,
            'tags' => ['draft'],
        ]);

        TestPost::create([
            'user_id' => $user2->id,
            'title' => 'Jane\'s post',
            'content' => 'Jane\'s content',
            'is_published' => true,
            'published_at' => now(),
            'tags' => ['test'],
        ]);
    }

    public function test_database_directive_executes_successfully(): void
    {
        // Créer les données de test pour CE test uniquement
        $this->createTestData();

        $response = $this->runAndCaptureOutput(['directive', 'test-laravel-db']);

        $this->assertSame(ExitCode::SUCCESS, $response['result'], 'Output: '.$response['output']);
        $this->assertStringContainsString('Laravel is available', $response['output']);
        $this->assertStringContainsString('3 users in database', $response['output']);
        $this->assertStringContainsString('2 active users', $response['output']);
        $this->assertStringContainsString('2 published posts', $response['output']);
    }

    public function test_database_directive_shows_user_table(): void
    {
        // Créer les données de test pour CE test uniquement
        $this->createTestData();

        $response = $this->runAndCaptureOutput(['directive', 'test-laravel-db']);

        $this->assertStringContainsString('User', $response['output']);
        $this->assertStringContainsString('Email', $response['output']);
        $this->assertStringContainsString('Posts Count', $response['output']);
        $this->assertStringContainsString('John Doe', $response['output']);
        $this->assertStringContainsString('john@example.com', $response['output']);
        $this->assertStringContainsString('Jane Smith', $response['output']);
    }

    public function test_database_directive_with_empty_database(): void
    {
        // Pas de création de données - base vide
        $response = $this->runAndCaptureOutput(['directive', 'test-laravel-db']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('0 users in database', $response['output']);
        $this->assertStringContainsString('No verified users found', $response['output']);
        $this->assertStringContainsString('0 published posts', $response['output']);
    }
}
