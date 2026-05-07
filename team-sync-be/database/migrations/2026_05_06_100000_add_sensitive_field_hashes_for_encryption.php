<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_member_profiles', function (Blueprint $table) {
            $table->string('identity_number_hash', 64)->nullable()->after('identity_number');
        });

        Schema::table('bank_information', function (Blueprint $table) {
            $table->string('account_number_hash', 64)->nullable()->after('account_number');
        });

        DB::table('staff_member_profiles')
            ->select(['id', 'identity_number'])
            ->orderBy('id')
            ->chunkById(100, function ($profiles) {
                foreach ($profiles as $profile) {
                    if ($profile->identity_number === null) {
                        continue;
                    }

                    DB::table('staff_member_profiles')
                        ->where('id', $profile->id)
                        ->update([
                            'identity_number_hash' => hash('sha256', trim((string) $profile->identity_number)),
                        ]);
                }
            });

        DB::table('bank_information')
            ->select(['id', 'account_number'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    if ($row->account_number === null) {
                        continue;
                    }

                    DB::table('bank_information')
                        ->where('id', $row->id)
                        ->update([
                            'account_number_hash' => hash('sha256', trim((string) $row->account_number)),
                        ]);
                }
            });

        Schema::table('staff_member_profiles', function (Blueprint $table) {
            $table->unique('identity_number_hash');
        });

        Schema::table('bank_information', function (Blueprint $table) {
            $table->dropUnique(['account_number']);
            $table->unique('account_number_hash');
        });
    }

    public function down(): void
    {
        Schema::table('bank_information', function (Blueprint $table) {
            $table->dropUnique(['account_number_hash']);
            $table->unique('account_number');
            $table->dropColumn('account_number_hash');
        });

        Schema::table('staff_member_profiles', function (Blueprint $table) {
            $table->dropUnique(['identity_number_hash']);
            $table->dropColumn('identity_number_hash');
        });
    }
};
