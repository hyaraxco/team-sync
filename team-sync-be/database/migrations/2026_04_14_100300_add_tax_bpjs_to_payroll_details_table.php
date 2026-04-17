<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->decimal('pph21_amount', 12, 2)->default(0)->after('deduction_amount');
            $table->decimal('bpjs_tk_employee', 12, 2)->default(0)->after('pph21_amount');
            $table->decimal('bpjs_tk_employer', 12, 2)->default(0)->after('bpjs_tk_employee');
            $table->decimal('bpjs_kes_employee', 12, 2)->default(0)->after('bpjs_tk_employer');
            $table->decimal('bpjs_kes_employer', 12, 2)->default(0)->after('bpjs_kes_employee');
            $table->json('tax_calculation_meta')->nullable()->after('bpjs_kes_employer');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropColumn([
                'pph21_amount',
                'bpjs_tk_employee',
                'bpjs_tk_employer',
                'bpjs_kes_employee',
                'bpjs_kes_employer',
                'tax_calculation_meta',
            ]);
        });
    }
};
