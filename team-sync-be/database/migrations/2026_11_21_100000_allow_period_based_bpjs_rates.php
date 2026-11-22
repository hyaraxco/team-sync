<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpjs_rates', function (Blueprint $table) {
            $table->dropUnique(['component']);
        });

        DB::table('bpjs_rates')
            ->whereNull('effective_date')
            ->update(['effective_date' => '2020-01-01']);

        Schema::table('bpjs_rates', function (Blueprint $table) {
            $table->date('effective_date')->nullable(false)->default(null)->change();
            $table->unique(['component', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::table('bpjs_rates', function (Blueprint $table) {
            $table->dropUnique(['component', 'effective_date']);
        });

        Schema::table('bpjs_rates', function (Blueprint $table) {
            $table->date('effective_date')->nullable()->change();
        });

        $components = DB::table('bpjs_rates')
            ->select('component')
            ->groupBy('component')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('component');

        foreach ($components as $component) {
            $keepId = DB::table('bpjs_rates')
                ->where('component', $component)
                ->orderByDesc('effective_date')
                ->value('id');

            DB::table('bpjs_rates')
                ->where('component', $component)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('bpjs_rates', function (Blueprint $table) {
            $table->unique('component');
        });
    }
};
