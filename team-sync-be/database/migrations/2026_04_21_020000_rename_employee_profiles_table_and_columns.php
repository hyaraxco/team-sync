<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tablePrefix = DB::getTablePrefix();
        $driver = DB::getDriverName();

        Schema::disableForeignKeyConstraints();

        Schema::rename('employee_profiles', 'staff_member_profiles');

        Schema::table('job_information', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'job_information_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->onDelete('cascade');
        });

        Schema::table('bank_information', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'bank_information_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->onDelete('cascade');
        });

        Schema::table('emergency_contacts', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'emergency_contacts_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->onDelete('cascade');
        });

        Schema::table('team_members', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'team_members_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->onDelete('cascade');
        });

        Schema::table('attendances', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'attendances_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->onDelete('cascade');
        });

        Schema::table('leave_requests', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'leave_requests_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->onDelete('cascade');
        });

        Schema::table('payroll_details', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'payroll_details_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->onDelete('cascade');
        });

        Schema::table('project_task_comments', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'project_task_comments_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->onDelete('cascade');
        });

        Schema::table('project_task_attachments', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'project_task_attachments_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->onDelete('cascade');
        });

        Schema::table('hybrid_work_schedules', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'hybrid_work_schedules_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('hybrid_schedule_overrides', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'hybrid_schedule_overrides_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('attendance_policy_mismatches', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'attendance_policy_mismatches_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('payroll_adjustments', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'payroll_adjustments_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('payroll_notification_deliveries', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'payroll_notification_deliveries_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->nullOnDelete();
        });

        Schema::table('attendance_corrections', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'attendance_corrections_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('performance_reviews', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'performance_reviews_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('performance_goals', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'performance_goals_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('performance_feedback', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'performance_feedback_employee_id_foreign');
            }
            $table->renameColumn('employee_id', 'staff_member_id');
            $table->foreign('staff_member_id')
                ->references('id')
                ->on('staff_member_profiles')
                ->cascadeOnDelete();
        });

        // Step 4: Re-enable FK constraints
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tablePrefix = DB::getTablePrefix();
        $driver = DB::getDriverName();

        Schema::disableForeignKeyConstraints();

        // Reverse in opposite order
        Schema::table('performance_feedback', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'performance_feedback_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('performance_goals', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'performance_goals_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('performance_reviews', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'performance_reviews_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('attendance_corrections', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'attendance_corrections_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('payroll_notification_deliveries', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'payroll_notification_deliveries_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->nullOnDelete();
        });

        Schema::table('payroll_adjustments', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'payroll_adjustments_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('attendance_policy_mismatches', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'attendance_policy_mismatches_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('hybrid_schedule_overrides', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'hybrid_schedule_overrides_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('hybrid_work_schedules', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'hybrid_work_schedules_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('project_task_attachments', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'project_task_attachments_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->onDelete('cascade');
        });

        Schema::table('project_task_comments', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'project_task_comments_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->onDelete('cascade');
        });

        Schema::table('payroll_details', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'payroll_details_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->onDelete('cascade');
        });

        Schema::table('leave_requests', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'leave_requests_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->onDelete('cascade');
        });

        Schema::table('attendances', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'attendances_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->onDelete('cascade');
        });

        Schema::table('team_members', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'team_members_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->onDelete('cascade');
        });

        Schema::table('emergency_contacts', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'emergency_contacts_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->onDelete('cascade');
        });

        Schema::table('bank_information', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'bank_information_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->onDelete('cascade');
        });

        Schema::table('job_information', function (Blueprint $table) use ($tablePrefix, $driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign($tablePrefix.'job_information_staff_member_id_foreign');
            }
            $table->renameColumn('staff_member_id', 'employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employee_profiles')
                ->onDelete('cascade');
        });

        Schema::rename('staff_member_profiles', 'employee_profiles');

        Schema::enableForeignKeyConstraints();
    }
};
