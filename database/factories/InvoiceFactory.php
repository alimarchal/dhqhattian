<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 5000);
        $hif = round($amount * 0.3, 2);

        return [
            'user_id' => User::factory(),
            'patient_id' => Patient::factory(),
            'government_non_government' => 0,
            'government_card_no' => null,
            'government_department_id' => null,
            'total_amount' => $amount,
            'hif_amount' => $hif,
            'govt_amount' => $amount - $hif,
            'actual_total_amount' => $amount,
        ];
    }
}
