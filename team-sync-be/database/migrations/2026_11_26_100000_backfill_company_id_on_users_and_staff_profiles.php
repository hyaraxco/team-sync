<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill company_id for users and staff profiles created before the
     * creation hooks existed. Only safe when the install has exactly one
     * company; with several, ownership is ambiguous and left untouched.
     */
    public function up(): void
    {
        if (DB::table('companies')->count() !== 1) {
            return;
        }

        $companyId = DB::table('companies')->value('id');

        DB::table('users')->whereNull('company_id')->update(['company_id' => $companyId]);
        DB::table('staff_member_profiles')->whereNull('company_id')->update(['company_id' => $companyId]);
    }

    public function down(): void
    {
        // Data backfill: no reversible state to restore.
    }
};
