<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. employee_profiles: remove unused fields, add identity fields
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['hobby', 'preferred_language', 'additional_notes']);
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('religion')->nullable()->after('gender');
            $table->string('marital_status')->nullable()->after('religion');
            $table->string('blood_type')->nullable()->after('marital_status');
            $table->string('npwp', 30)->nullable()->after('identity_number');
            $table->string('bpjs_ketenagakerjaan', 30)->nullable()->after('npwp');
            $table->string('bpjs_kesehatan', 30)->nullable()->after('bpjs_ketenagakerjaan');
            $table->string('ptkp_status', 10)->nullable()->after('bpjs_kesehatan');

            $table->index('religion');
            $table->index('marital_status');
        });

        // 2. job_information: remove years_experience and skill_level
        Schema::table('job_information', function (Blueprint $table) {
            $table->dropColumn(['years_experience', 'skill_level']);
        });

        // 3. bank_information: remove bank_branch and account_type
        Schema::table('bank_information', function (Blueprint $table) {
            $table->dropIndex(['account_type']);
            $table->dropColumn(['bank_branch', 'account_type']);
        });

        // 4. payroll_settings: add company payroll bank
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->string('payroll_bank_name')->nullable()->after('note_template');
            $table->string('payroll_bank_code', 10)->nullable()->after('payroll_bank_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['payroll_bank_name', 'payroll_bank_code']);
        });

        Schema::table('bank_information', function (Blueprint $table) {
            $table->string('bank_branch')->nullable()->after('account_holder_name');
            $table->string('account_type')->after('bank_branch');
            $table->index('account_type');
        });

        Schema::table('job_information', function (Blueprint $table) {
            $table->integer('years_experience')->after('team_id');
            $table->string('skill_level')->after('monthly_salary');
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropIndex(['religion']);
            $table->dropIndex(['marital_status']);
            $table->dropColumn(['religion', 'marital_status', 'blood_type', 'npwp', 'bpjs_ketenagakerjaan', 'bpjs_kesehatan', 'ptkp_status']);
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('hobby')->nullable()->after('gender');
            $table->string('preferred_language')->nullable()->after('postal_code');
            $table->text('additional_notes')->nullable()->after('preferred_language');
        });
    }
};
