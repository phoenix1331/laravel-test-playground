<?php

namespace App\Enums;

/**
 * The lifecycle states a post moves through.
 *
 * Keeping this as an enum instead of plain strings prevents impossible states
 * and makes intent clear in the service and controller logic.
 */
enum PostStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
