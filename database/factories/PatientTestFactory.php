<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientTest>
 */
class PatientTestFactory extends Factory
{
    protected $model = PatientTest::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 2000);
        $hif = round($amount * 0.3, 2);

        return [
            'patient_id' => Patient::factory(),
            'fee_type_id' => null,
            'invoice_id' => Invoice::factory(),
            'government_non_gov' => 0,
            'government_department_id' => null,
            'government_card_no' => null,
            'total_amount' => $amount,
            'hif_amount' => $hif,
            'govt_amount' => $amount - $hif,
            'actual_total_amount' => $amount,
            'status' => 'Normal',
        ];
    }
}
