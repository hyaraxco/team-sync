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
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('proof_file_path')->nullable()->after('emergency_contact');
            $table->string('proof_file_name')->nullable()->after('proof_file_path');
            $table->string('proof_mime_type')->nullable()->after('proof_file_name');
            $table->unsignedInteger('proof_size_kb')->nullable()->after('proof_mime_type');
            $table->timestamp('proof_uploaded_at')->nullable()->after('proof_size_kb');
            $table->string('proof_review_status')->nullable()->after('proof_uploaded_at');
            $table->foreignId('proof_reviewed_by')->nullable()->after('approved_by')->constrained('employee_profiles')->nullOnDelete();
            $table->timestamp('proof_reviewed_at')->nullable()->after('proof_reviewed_by');
            $table->text('proof_review_notes')->nullable()->after('proof_reviewed_at');

            $table->index(['proof_review_status', 'leave_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['proof_review_status', 'leave_type']);
            $table->dropConstrainedForeignId('proof_reviewed_by');
            $table->dropColumn([
                'proof_file_path',
                'proof_file_name',
                'proof_mime_type',
                'proof_size_kb',
                'proof_uploaded_at',
                'proof_review_status',
                'proof_reviewed_at',
                'proof_review_notes',
            ]);
        });
    }
};
