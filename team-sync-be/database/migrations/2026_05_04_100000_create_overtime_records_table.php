<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_member_id')->constrained('staff_member_profiles')->onDelete('cascade');
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('set null');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('hours', 4, 2);
            $table->enum('overtime_type', ['workday', 'weekend', 'holiday']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('set null');
            $table->timestamps();

            $table->index(['staff_member_id', 'date']);
            $table->index(['status', 'date']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_records');
    }
};
