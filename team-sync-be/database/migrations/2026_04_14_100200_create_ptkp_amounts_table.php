<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptkp_amounts', function (Blueprint $table) {
            $table->id();
            $table->string('status', 10)->unique(); // TK/0, K/1, K/I/2, etc.
            $table->decimal('annual_amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptkp_amounts');
    }
};
