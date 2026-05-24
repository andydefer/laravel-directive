<?php

// tests/Fixtures/Models/TestPost.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TestPost extends Model
{
    protected $table = 'test_posts';

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'is_published',
        'published_at',
        'tags',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'tags' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
