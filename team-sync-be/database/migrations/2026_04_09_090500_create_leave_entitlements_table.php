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
        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('employment_type');
            $table->string('leave_type');
            $table->boolean('is_eligible')->default(true);
            $table->boolean('is_paid')->default(false);
            $table->string('quota_scope')->nullable();
            $table->decimal('quota_days', 8, 2)->nullable();
            $table->unsignedInteger('carry_over_max_days')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('requires_reason')->default(false);
            $table->json('allowed_mime_types')->nullable();
            $table->unsignedInteger('max_attachment_size_kb')->nullable();
            $table->timestamps();

            $table->unique(['employment_type', 'leave_type']);
            $table->index(['leave_type', 'quota_scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_entitlements');
    }
};
