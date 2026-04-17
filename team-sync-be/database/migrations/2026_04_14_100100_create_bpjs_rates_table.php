<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpjs_rates', function (Blueprint $table) {
            $table->id();
            $table->string('component', 30)->unique(); // jht, jkk, jkm, jp, bpjs_kesehatan
            $table->decimal('employee_rate', 5, 2)->default(0); // percentage
            $table->decimal('employer_rate', 5, 2)->default(0); // percentage
            $table->decimal('max_salary_base', 15, 2)->nullable(); // salary cap for calculation
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_rates');
    }
};
