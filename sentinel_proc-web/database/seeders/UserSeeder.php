<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name'     => 'Administrator',
                'email'    => 'admin@sentinel.local',
                'password' => Hash::make('password'),
                'role'     => UserRole::Admin,
            ],
            [
                'name'     => 'Security Analyst',
                'email'    => 'analyst@sentinel.local',
                'password' => Hash::make('password'),
                'role'     => UserRole::Analyst,
            ],
            [
                'name'     => 'Viewer User',
                'email'    => 'viewer@sentinel.local',
                'password' => Hash::make('password'),
                'role'     => UserRole::Viewer,
            ],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                $account
            );
        }

        $this->command->info('Demo accounts seeded:');
        $this->command->table(
            ['Name', 'Email', 'Role', 'Password'],
            [
                ['Administrator',   'admin@sentinel.local',   'Admin',   'password'],
                ['Security Analyst','analyst@sentinel.local', 'Analyst', 'password'],
                ['Viewer User',     'viewer@sentinel.local',  'Viewer',  'password'],
            ]
        );
    }
}
