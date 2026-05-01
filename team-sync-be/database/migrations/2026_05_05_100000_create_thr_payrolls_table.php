<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thr_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('religion_event'); // e.g., 'idul_fitri', 'natal', 'nyepi', 'waisak', 'imlek'
            $table->date('religion_holiday_date'); // The actual holiday date
            $table->date('payment_deadline'); // Must be >= 7 days before holiday
            $table->date('payment_date')->nullable(); // Actual payment date
            $table->string('status')->default('draft'); // draft, processing, pending, approved, paid
            $table->unsignedInteger('total_employees')->default(0);
            $table->decimal('total_thr_amount', 15, 2)->default(0);
            $table->decimal('total_tax_amount', 15, 2)->default(0);
            $table->decimal('total_net_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['year', 'religion_event', 'company_id'], 'thr_year_event_company_unique');
            $table->index(['year', 'status']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thr_payrolls');
    }
};
