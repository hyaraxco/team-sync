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
        Schema::create('holiday_calendars', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('type');
            $table->json('applies_to')->nullable();
            $table->timestamps();

            $table->unique(['date', 'name']);
            $table->index(['date', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_calendars');
    }
};
