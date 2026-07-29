<?php

namespace App\Models;

use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'title', 'body', 'status', 'published_at'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // Cast status column to PostStatus enum automatically.
            'status'       => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------------------
    // Convenience helpers
    // ---------------------------------------------------------------------------

    public function isPublished(): bool
    {
        return $this->status->isPublished();
    }

    public function isDraft(): bool
    {
        return ! $this->isPublished();
    }

    /**
     * Returns true when the given user is the author of this post.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
