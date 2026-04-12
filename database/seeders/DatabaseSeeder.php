<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Admin', 'Supervisor', 'Intern'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        $institution = Institution::firstOrCreate([
            'name' => 'Default Institution',
        ], [
            'type' => 'university',
        ]);

        $admin = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'System Admin',
            'password' => 'password',
            'institution_id' => $institution->id,
        ]);

        $admin->assignRole('Admin');
    }
}
