<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = json_decode(file_get_contents(database_path('seeders/data/users.json')), true);

        foreach ($users as $userData) {
            $user = User::updateOrCreate(['id' => $userData['id']], [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password'),
                'department_id' => $userData['department_id'] === 'NULL' ? null : $userData['department_id'],
                'status' => $userData['status'],
                'profile_photo_path' => $userData['profile_photo_path'] === 'NULL' ? null : $userData['profile_photo_path'],
            ]);

            // Assign role if it exists in data
            if (! empty($userData['role']) && $userData['role'] !== 'NULL') {
                $user->syncRoles([$userData['role']]);
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
