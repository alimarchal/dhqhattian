<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $feeTypes = [
            ['fee_category_id' => 13, 'type' => 'Gynecologist Specialist', 'amount' => 50, 'hif' => 20, 'status' => 'Normal', 'created_at' => null, 'updated_at' => null],
            ['fee_category_id' => 13, 'type' => 'Child Specialist', 'amount' => 50, 'hif' => 20, 'status' => 'Normal', 'created_at' => null, 'updated_at' => null],
            ['fee_category_id' => 13, 'type' => 'Medical Specialist', 'amount' => 50, 'hif' => 20, 'status' => 'Normal', 'created_at' => null, 'updated_at' => null],
            ['fee_category_id' => 13, 'type' => 'ENT Specialist', 'amount' => 50, 'hif' => 20, 'status' => 'Normal', 'created_at' => null, 'updated_at' => null],
            ['fee_category_id' => 13, 'type' => 'Eye Specialist', 'amount' => 50, 'hif' => 20, 'status' => 'Normal', 'created_at' => null, 'updated_at' => null],
            ['fee_category_id' => 13, 'type' => 'Skin Specialist', 'amount' => 50, 'hif' => 20, 'status' => 'Normal', 'created_at' => null, 'updated_at' => null],
            ['fee_category_id' => 13, 'type' => 'Surgical Specialist', 'amount' => 50, 'hif' => 20, 'status' => 'Normal', 'created_at' => null, 'updated_at' => null],
            ['fee_category_id' => 13, 'type' => 'Cardiologist Specialist', 'amount' => 50, 'hif' => 20, 'status' => 'Normal', 'created_at' => null, 'updated_at' => null],
        ];

        DB::table('fee_types')->insert($feeTypes);
    }
}
