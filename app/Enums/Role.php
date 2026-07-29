<?php

namespace App\Enums;

/**
 * The two roles a user can hold.
 *
 * Using a backed enum means the value stored in the database is always one of
 * these two strings — never a magic number or a typo. The enum is cast
 * automatically on the User model, so $user->role is always a Role instance.
 */
enum Role: string
{
    case Admin    = 'admin';
    case Customer = 'customer';

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
