<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // One known admin account — useful for manual testing via the browser.
        $admin = User::factory()->admin()->create([
            'name'  => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // One known customer account.
        $customer = User::factory()->customer()->create([
            'name'  => 'Jane Customer',
            'email' => 'customer@example.com',
        ]);

        // A handful of published posts so there's something to look at.
        Post::factory()->published()->count(5)->create(['user_id' => $admin->id]);
        Post::factory()->published()->count(3)->create(['user_id' => $customer->id]);

        // A couple of drafts to show the status distinction.
        Post::factory()->draft()->count(2)->create(['user_id' => $customer->id]);
    }
}
