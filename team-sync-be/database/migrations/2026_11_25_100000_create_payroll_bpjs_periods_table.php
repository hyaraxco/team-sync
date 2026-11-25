<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_bpjs_periods', function (Blueprint $table) {
            $table->id();
            $table->string('contribution_month', 7)->unique();
            $table->json('facts');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_bpjs_periods');
    }
};
