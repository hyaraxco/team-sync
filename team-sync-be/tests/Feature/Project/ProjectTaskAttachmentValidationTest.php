<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectTaskAttachmentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProjectTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'task-edit', 'guard_name' => 'sanctum']);
        $role = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('task-edit');

        $this->user = User::factory()->create();
        $this->user->assignRole('staff');

        $profile = StaffMemberProfile::factory()->create(['user_id' => $this->user->id]);

        $project = Project::factory()->create();
        $team = Team::factory()->create();
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'staff_member_id' => $profile->id,
        ]);
        $project->teams()->attach($team->id);

        $this->task = ProjectTask::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $profile->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_rejects_php_file_upload(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');

        $this->postJson("/api/v1/project-tasks/{$this->task->id}/attachments", [
            'file' => $file,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['file']);
    }

    public function test_rejects_exe_file_upload(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $this->postJson("/api/v1/project-tasks/{$this->task->id}/attachments", [
            'file' => $file,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['file']);
    }

    public function test_rejects_shell_script_upload(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->create('script.sh', 100, 'application/x-sh');

        $this->postJson("/api/v1/project-tasks/{$this->task->id}/attachments", [
            'file' => $file,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['file']);
    }

    public function test_accepts_pdf_file_upload(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->postJson("/api/v1/project-tasks/{$this->task->id}/attachments", [
            'file' => $file,
        ])->assertCreated();
    }

    public function test_accepts_image_file_upload(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $this->postJson("/api/v1/project-tasks/{$this->task->id}/attachments", [
            'file' => $file,
        ])->assertCreated();
    }

    public function test_accepts_docx_file_upload(): void
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->create('report.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->postJson("/api/v1/project-tasks/{$this->task->id}/attachments", [
            'file' => $file,
        ])->assertCreated();
    }
}
