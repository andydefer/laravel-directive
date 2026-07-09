<?php

// tests/database/migrations/2024_01_01_000002_create_test_posts_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('test_users')->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_posts');
    }
};
