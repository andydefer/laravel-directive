<?php

// tests/Fixtures/Directives/TestLaravelDatabaseDirective.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Tests\Fixtures\Models\TestPost;
use AndyDefer\Directive\Tests\Fixtures\Models\TestUser;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class TestLaravelDatabaseDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test-laravel-db';
    }

    public function getDescription(): string
    {
        return 'Test directive that requires Laravel and database access';
    }

    // Réactiver le bootstrap
    public function shouldBootLaravel(): bool
    {
        return true;  // ← Remis à true
    }

    public function execute(): ExitCode
    {
        $this->info('Testing Laravel database integration...');

        if (!$this->hasLaravel()) {
            $this->error('Laravel is not available!');
            return ExitCode::FAILURE;
        }

        $this->info('✓ Laravel is available');

        try {
            $userCount = TestUser::count();
            $this->info("✓ Found {$userCount} users in database");

            $activeUsers = TestUser::active()->get();
            $this->info('✓ Found ' . $activeUsers->count() . ' active users');

            $verifiedUsers = TestUser::verified()->with('posts')->get();

            if ($verifiedUsers->isNotEmpty()) {
                $headers = new StringTypedCollection();
                $headers->add('User', 'Email', 'Posts Count', 'Published Posts');

                $rows = new RowCollection();
                foreach ($verifiedUsers as $user) {
                    $row = new RowCollection();
                    $row->add(
                        $user->name,
                        $user->email,
                        $user->posts->count(),
                        $user->posts->where('is_published', true)->count()
                    );
                    $rows->add($row);
                }

                $this->table($headers, $rows);
            } else {
                $this->warn('No verified users found');
            }

            $publishedPosts = TestPost::published()->with('user')->get();
            $this->info('✓ Found ' . $publishedPosts->count() . ' published posts');

            $this->info('Database query successful');
            return ExitCode::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Database error: ' . $e->getMessage());
            return ExitCode::FAILURE;
        }
    }
}
