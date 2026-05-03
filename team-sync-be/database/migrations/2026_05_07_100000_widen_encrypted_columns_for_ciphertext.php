<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen columns that use Laravel's `encrypted` cast.
 *
 * The encrypted cast produces base64-encoded ciphertext (~200-300 chars)
 * which exceeds the original varchar(30) or varchar(255) limits.
 * Converting to TEXT removes the length constraint entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        if ($isMysql) {
            // Drop legacy unique index on identity_number (uniqueness now enforced via identity_number_hash)
            $this->dropIndexIfExists('staff_member_profiles', 'employee_profiles_identity_number_unique');
        }

        Schema::table('staff_member_profiles', function (Blueprint $table) {
            $table->text('identity_number')->nullable()->change();
            $table->text('npwp')->nullable()->change();
            $table->text('bpjs_ketenagakerjaan')->nullable()->change();
            $table->text('bpjs_kesehatan')->nullable()->change();
        });

        if ($isMysql) {
            // Drop legacy indexes on bank_information encrypted columns
            $this->dropIndexIfExists('bank_information', 'bank_information_account_number_unique');
            $this->dropIndexIfExists('bank_information', 'bank_information_bank_name_index');
            $this->dropIndexIfExists('bank_information', 'bank_information_account_number_index');
        }

        Schema::table('bank_information', function (Blueprint $table) {
            $table->text('bank_name')->nullable()->change();
            $table->text('account_number')->nullable()->change();
            $table->text('account_holder_name')->nullable()->change();
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        if (in_array($indexName, $indexes, true)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }

    public function down(): void
    {
        Schema::table('staff_member_profiles', function (Blueprint $table) {
            $table->string('identity_number', 255)->nullable()->change();
            $table->string('npwp', 30)->nullable()->change();
            $table->string('bpjs_ketenagakerjaan', 30)->nullable()->change();
            $table->string('bpjs_kesehatan', 30)->nullable()->change();
        });

        Schema::table('bank_information', function (Blueprint $table) {
            $table->string('bank_name', 255)->nullable()->change();
            $table->string('account_number', 255)->nullable()->change();
            $table->string('account_holder_name', 255)->nullable()->change();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            $this->addIndexIfMissing(
                'staff_member_profiles',
                'employee_profiles_identity_number_unique',
                'ALTER TABLE `staff_member_profiles` ADD UNIQUE `employee_profiles_identity_number_unique` (`identity_number`)'
            );

            $this->addIndexIfMissing(
                'bank_information',
                'bank_information_bank_name_index',
                'ALTER TABLE `bank_information` ADD INDEX `bank_information_bank_name_index` (`bank_name`)'
            );

            $this->addIndexIfMissing(
                'bank_information',
                'bank_information_account_number_index',
                'ALTER TABLE `bank_information` ADD INDEX `bank_information_account_number_index` (`account_number`)'
            );

            $this->addIndexIfMissing(
                'bank_information',
                'bank_information_account_number_unique',
                'ALTER TABLE `bank_information` ADD UNIQUE `bank_information_account_number_unique` (`account_number`)'
            );
        }
    }

    private function addIndexIfMissing(string $table, string $indexName, string $statement): void
    {
        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->unique()
            ->toArray();

        if (! in_array($indexName, $indexes, true)) {
            DB::statement($statement);
        }
    }
};
