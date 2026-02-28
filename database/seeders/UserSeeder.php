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
        // Create Administrator user
        $admin = User::updateOrCreate(['id' => 1], [
            'name' => 'Administrator',
            'email' => 'admin@dhqhattian.test',
            'password' => Hash::make('password'),
            'department_id' => null,
            'status' => true,
            'profile_photo_path' => null,
        ]);

        $admin->syncRoles(['Super-Admin']);

        // Create personal team for admin
        if ($admin->ownedTeams->isEmpty()) {
            Team::forceCreate([
                'user_id' => $admin->id,
                'name' => "Administrator's Team",
                'personal_team' => true,
            ]);
        }

        // Create Administrator user
        $administrator = User::updateOrCreate(['id' => 3], [
            'name' => 'Administrator User',
            'email' => 'administrator@dhqhattian.test',
            'password' => Hash::make('password'),
            'department_id' => null,
            'status' => true,
            'profile_photo_path' => null,
        ]);

        $administrator->syncRoles(['Administrator']);

        // Create personal team for administrator
        if ($administrator->ownedTeams->isEmpty()) {
            Team::forceCreate([
                'user_id' => $administrator->id,
                'name' => "Administrator User's Team",
                'personal_team' => true,
            ]);
        }

        // Create Front Desk user (no permissions by default)
        $frontDesk = User::updateOrCreate(['id' => 2], [
            'name' => 'Front Desk User',
            'email' => 'receptionist@dhqhattian.test',
            'password' => Hash::make('password'),
            'department_id' => null,
            'status' => true,
            'profile_photo_path' => null,
        ]);

        $frontDesk->syncRoles(['Front Desk/Receptionist']);
        // Note: No permissions assigned - admin will assign via UI

        // Create personal team for front desk
        if ($frontDesk->ownedTeams->isEmpty()) {
            Team::forceCreate([
                'user_id' => $frontDesk->id,
                'name' => "Front Desk's Team",
                'personal_team' => true,
            ]);
        }
    }
}
