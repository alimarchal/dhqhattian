<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['id' => 25, 'name' => 'Gynecologist Specialist', 'daily_patient_limit' => 3000, 'category' => 'OPD', 'deleted_at' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 26, 'name' => 'Child Specialist', 'daily_patient_limit' => 3000, 'category' => 'OPD', 'deleted_at' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 27, 'name' => 'Medical Specialist', 'daily_patient_limit' => 3000, 'category' => 'OPD', 'deleted_at' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 28, 'name' => 'ENT Specialist', 'daily_patient_limit' => 3000, 'category' => 'OPD', 'deleted_at' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 29, 'name' => 'Eye Specialist', 'daily_patient_limit' => 3000, 'category' => 'OPD', 'deleted_at' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 30, 'name' => 'Skin Specialist', 'daily_patient_limit' => 3000, 'category' => 'OPD', 'deleted_at' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 31, 'name' => 'Surgical Specialist', 'daily_patient_limit' => 3000, 'category' => 'OPD', 'deleted_at' => null, 'created_at' => null, 'updated_at' => null],
            ['id' => 32, 'name' => 'Cardiologist Specialist', 'daily_patient_limit' => 3000, 'category' => 'OPD', 'deleted_at' => null, 'created_at' => null, 'updated_at' => null],
        ];

        DB::table('departments')->insert($departments);
    }
}
