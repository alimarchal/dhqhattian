<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patient_emergency_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // The user who created the record
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('disease_id')->constrained()->nullable();
            $table->text('treatment_details')->nullable();
            $table->text('medications')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_emergency_treatments');
    }
};
