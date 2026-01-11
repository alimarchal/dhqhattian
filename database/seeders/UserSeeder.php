<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = json_decode(file_get_contents(database_path('seeders/data/users.json')), true);
        $userRoles = json_decode(file_get_contents(database_path('seeders/data/user_roles.json')), true);

        foreach ($users as $userData) {
            $user = User::updateOrCreate(['id' => $userData['id']], [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $userData['password'],
                'department_id' => $userData['department_id'] === 'NULL' ? null : $userData['department_id'],
                'status' => $userData['status'],
                'profile_photo_path' => $userData['profile_photo_path'] === 'NULL' ? null : $userData['profile_photo_path'],
            ]);

            // Assign role from mapping or CSV
            $roleName = $userRoles[$user->id] ?? $userData['role'] ?? null;

            if ($roleName && $roleName !== 'NULL') {
                $user->syncRoles([$roleName]);
            }

            // Fallback for core users to be Super-Admin/Administrator
            if (in_array($user->id, [1, 2])) {
                $user->assignRole('Super-Admin');
                $user->assignRole('Administrator');
            }

            // Create personal team if not exists (Jetstream requirement)
            if ($user->ownedTeams->isEmpty()) {
                Team::forceCreate([
                    'user_id' => $user->id,
                    'name' => explode(' ', $user->name, 2)[0]."'s Team",
                    'personal_team' => true,
                ]);
            }
        }
    }
}
