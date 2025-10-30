<?php

namespace App\Models;

use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientTestController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'fee_type_id',
        'invoice_id',
        'government_non_gov',
        'government_department_id',
        'government_card_no',
        'total_amount',
        'hif_amount',
        'govt_amount',
        'status',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }


    public function fee_type(): BelongsTo
    {
        return $this->belongsTo(FeeType::class,'fee_type_id');
    }

}
