<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviewer_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('reviewee_role');       // Spatie role name of the person being reviewed
            $table->string('reviewer_role');        // Spatie role name of the reviewer
            $table->unsignedSmallInteger('priority')->default(1); // Lower = higher priority
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['reviewee_role', 'reviewer_role']);
            $table->index('reviewee_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_rules');
    }
};
