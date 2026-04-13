<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            ['name' => 'Subhankar Manager', 'email' => 'manager@example.com', 'role' => 'manager'],
            ['name' => 'Ayashree Analyst', 'email' => 'ayashree@example.com', 'role' => 'analyst'],
            ['name' => 'Mobashir Analyst', 'email' => 'mobashir@example.com', 'role' => 'analyst'],
            ['name' => 'Rohit Developer', 'email' => 'rohit@example.com', 'role' => 'employee'],
            ['name' => 'Sneha Developer', 'email' => 'sneha@example.com', 'role' => 'employee'],
            ['name' => 'Soumyadip Developer', 'email' => 'soumyadip@example.com', 'role' => 'employee'],
        ];

        foreach ($members as $member) {
            DB::table('team_members')->updateOrInsert(
                ['email' => $member['email']],
                [
                    'name'       => $member['name'],
                    'password'   => Hash::make('password'),
                    'role'       => $member['role'],
                    'is_active'  => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
