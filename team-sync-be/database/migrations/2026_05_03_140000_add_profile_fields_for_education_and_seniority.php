<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_member_profiles', function (Blueprint $table) {
            $table->string('last_education')->nullable()->after('postal_code');
            $table->string('seniority_level')->nullable()->after('last_education');
        });
    }

    public function down(): void
    {
        Schema::table('staff_member_profiles', function (Blueprint $table) {
            $table->dropColumn(['last_education', 'seniority_level']);
        });
    }
};
