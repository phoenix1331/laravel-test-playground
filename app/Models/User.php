<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            // Cast the role column to our Role enum automatically.
            // After this, $user->role is always a Role instance, never a raw string.
            'role'              => Role::class,
        ];
    }

    // ---------------------------------------------------------------------------
    // Convenience helpers — thin wrappers that read well in service/policy code
    // ---------------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function isCustomer(): bool
    {
        return ! $this->isAdmin();
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
