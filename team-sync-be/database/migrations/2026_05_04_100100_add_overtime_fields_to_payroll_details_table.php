<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->decimal('overtime_hours', 6, 2)->default(0)->after('deduction_amount');
            $table->decimal('overtime_amount', 15, 2)->default(0)->after('overtime_hours');
            $table->unsignedInteger('overtime_records_count')->default(0)->after('overtime_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropColumn([
                'overtime_hours',
                'overtime_amount',
                'overtime_records_count',
            ]);
        });
    }
};
