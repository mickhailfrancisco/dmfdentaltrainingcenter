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
        $this->seedAdminAccount();
        $this->seedDeveloperAccount();
    }

    /**
     * Primary dentist administrator account.
     * Set ADMIN_INITIAL_PASSWORD in the environment before seeding in production.
     */
    private function seedAdminAccount(): void
    {
        $password = env('ADMIN_INITIAL_PASSWORD');

        if (app()->environment('production') && blank($password)) {
            $this->command?->warn('Skipping primary admin: set ADMIN_INITIAL_PASSWORD to seed in production.');

            return;
        }

        $this->upsertAdmin(
            email: 'admin@dmfdental.com',
            name: 'DMF Dental Administrator',
            password: (string) ($password ?: 'password'),
        );
    }

    /**
     * Developer account for log access in production.
     * Set DEV_ADMIN_EMAIL and DEV_ADMIN_PASSWORD in the environment.
     */
    private function seedDeveloperAccount(): void
    {
        $email = env('DEV_ADMIN_EMAIL');
        $password = env('DEV_ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            if (app()->environment('production')) {
                $this->command?->warn('Skipping developer admin: set DEV_ADMIN_EMAIL and DEV_ADMIN_PASSWORD to seed in production.');
            }

            return;
        }

        $this->upsertAdmin(
            email: (string) $email,
            name: 'Developer',
            password: (string) $password,
        );
    }

    private function upsertAdmin(string $email, string $name, string $password): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => UserRole::Admin->value,
            ]
        );

        if ($user->role !== UserRole::Admin->value) {
            $user->update(['role' => UserRole::Admin->value]);
        }
    }
}
