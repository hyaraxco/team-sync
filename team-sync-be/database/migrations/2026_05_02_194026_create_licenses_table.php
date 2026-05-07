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
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->text('license_key')->comment('Base64-encoded signed license payload');
            $table->string('license_hash', 64)->unique()->comment('SHA-256 hash of license key');
            $table->string('company_name')->comment('Company name from license');
            $table->string('contact_email')->comment('Contact email from license');
            $table->date('issued_at')->comment('License issuance date');
            $table->date('expires_at')->comment('License expiration date');
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable()->comment('Feature flags: [\"payroll\", \"performance\", ...]');
            $table->unsignedInteger('max_users')->default(100);
            $table->unsignedInteger('current_users')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->text('signature')->comment('Base64 signature of canonical payload');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
