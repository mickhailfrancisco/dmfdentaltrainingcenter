<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the primary administrator account.
     *
     * In production, set ADMIN_INITIAL_PASSWORD in the environment before seeding.
     * When unset in production, this seeder is skipped to avoid default credentials.
     *
     * @author CKD
     *
     * @created 2026-04-24
     */
    public function run(): void
    {
        $password = env('ADMIN_INITIAL_PASSWORD');

        if (app()->environment('production') && blank($password)) {
            $this->command?->warn('Skipping AdminUserSeeder: set ADMIN_INITIAL_PASSWORD to seed admin in production.');

            return;
        }

        $password = (string) ($password ?: 'password');

        $user = User::firstOrCreate(
            ['email' => 'admin@dmfdental.com'],
            [
                'name' => 'DMF Dental Administrator',
                'password' => Hash::make($password),
                'role' => UserRole::Admin->value,
            ]
        );

        if ($user->role !== UserRole::Admin->value) {
            $user->update(['role' => UserRole::Admin->value]);
        }
    }
}
