<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('diseases', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique()->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed diseases data
        $diseases = [
            'Chest pain',
            'Trauma',
            'RTA',
            'AC DIARRHEA',
            'Dysentery',
            'URTI',
            'LRTI',
            'PNEUMONIA',
            'A.E.Ch.Asthma',
            'COPD. AE',
            'Unexplained fever',
            'Pul.embolism',
            'Congestive cardiac failure',
            'AC myocardial ischemia',
            'Stroke',
            'Headache',
            'AC pulmonary edema',
            'AC LVF',
            'AC. ABDOMEN',
            'AC APPENDICITIS',
            'BLUNT ABDOMINAL TRAUMA',
            'GUN SHOT INJURY',
            'STREET FIGHT',
            'CERVICAL SPINE INJURY',
            'PNEUMOTHORAX',
            'HEMOTHORAX',
            'PERSISTENT VOMITTING',
            'AC POISONING',
            'SNAKE BITE',
            'INSECT BITE',
            'DOG BITE',
            'D.K.A',
            'SEPSIS',
            'HYPOTENSION',
            'HYPERTENSIVE EMERGENCY',
            'HYPERTENSIVE CRISIS',
            'HYPERTENSIVE URGENCY',
            'ACS',
            'ANAPHYLAXIS',
        ];

        $timestamp = now();
        $data = [];

        foreach ($diseases as $index => $disease) {
            $data[] = [
                'name' => $disease,
                'code' => 'DIS-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('diseases')->insert($data);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diseases');
    }
};
