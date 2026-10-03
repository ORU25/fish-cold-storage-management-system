<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the first Owner account; the Owner creates every other user from the app.
     */
    public function run(): void
    {
        User::firstOrCreate(['username' => 'owner'], [
            'name' => 'Owner',
            'password' => env('OWNER_PASSWORD', 'password'),
            'role' => Role::Owner,
        ]);
    }
}
