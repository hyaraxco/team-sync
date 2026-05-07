<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProjectTaskAttachmentStoreRequest;
use App\Http\Requests\ProjectTaskCommentStoreRequest;
use App\Http\Requests\ProjectTaskCommentUpdateRequest;
use App\Http\Requests\ProjectTaskStoreRequest;
use App\Http\Requests\ProjectTaskUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProjectTaskAttachmentResource;
use App\Http\Resources\ProjectTaskCommentResource;
use App\Http\Resources\ProjectTaskResource;
use App\Http\Resources\ProjectTaskStatusLogResource;
use App\Interfaces\ProjectTaskRepositoryInterface;
use App\Models\ProjectTask;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectTaskController extends Controller implements HasMiddleware
{
    private ProjectTaskRepositoryInterface $projectTaskRepository;

    public function __construct(ProjectTaskRepositoryInterface $projectTaskRepository)
    {
        $this->projectTaskRepository = $projectTaskRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['task-list|task-create|task-edit|task-delete']), only: ['index', 'getAllPaginated', 'getByProject', 'getByProjectPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['task-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['task-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['task-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['task-list|task-edit']), only: ['getComments', 'getAttachments', 'getStatusLogs']),
            new Middleware(PermissionMiddleware::using(['task-edit']), only: ['storeComment', 'updateComment', 'deleteComment', 'storeAttachment', 'deleteAttachment']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $tasks = $this->projectTaskRepository->getAll(
                $request->search,
                $request->project_id,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Tasks Retrieved Successfully', ProjectTaskResource::collection($tasks), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'project_id' => 'nullable|integer',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $tasks = $this->projectTaskRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['project_id'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Tasks Retrieved Successfully', PaginateResource::make($tasks, ProjectTaskResource::class), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getByProject(Request $request, int $projectId)
    {
        try {
            $tasks = $this->projectTaskRepository->getByProjectId($projectId);

            return ResponseHelper::jsonResponse(true, 'Project Tasks Retrieved Successfully', ProjectTaskResource::collection($tasks), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProjectTaskStoreRequest $request)
    {
        $data = $request->validated();

        try {
            $response = Gate::inspect('create', [ProjectTask::class, $data]);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $task = $this->projectTaskRepository->create($data);

            return ResponseHelper::jsonResponse(true, 'Task Created Successfully', new ProjectTaskResource($task), 201);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $task = $this->projectTaskRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Task Retrieved Successfully', new ProjectTaskResource($task), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProjectTaskUpdateRequest $request, string $id)
    {
        $data = $request->validated();

        try {
            $task = ProjectTask::with('project')->findOrFail($id);

            $response = Gate::inspect('update', [$task, $data]);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $task = $this->projectTaskRepository->update($id, $data);

            return ResponseHelper::jsonResponse(true, 'Task Updated Successfully', new ProjectTaskResource($task), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $task = ProjectTask::findOrFail($id);

            $response = Gate::inspect('delete', $task);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $this->projectTaskRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Task Deleted Successfully', null, 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getComments(string $id)
    {
        try {
            $comments = $this->projectTaskRepository->getComments($id);

            return ResponseHelper::jsonResponse(true, 'Task Comments Retrieved Successfully', ProjectTaskCommentResource::collection($comments), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function storeComment(ProjectTaskCommentStoreRequest $request, string $id)
    {
        $payload = $request->validated();

        try {
            $task = ProjectTask::with('project')->findOrFail($id);

            $response = Gate::inspect('collaborate', $task);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $comment = $this->projectTaskRepository->createComment($id, $payload);

            return ResponseHelper::jsonResponse(true, 'Task Comment Created Successfully', new ProjectTaskCommentResource($comment), 201);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function updateComment(ProjectTaskCommentUpdateRequest $request, string $id, string $commentId)
    {
        $payload = $request->validated();

        try {
            $comment = $this->projectTaskRepository->updateComment($id, $commentId, $payload);

            return ResponseHelper::jsonResponse(true, 'Task Comment Updated Successfully', new ProjectTaskCommentResource($comment), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task/Comment Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function deleteComment(string $id, string $commentId)
    {
        try {
            $this->projectTaskRepository->deleteComment($id, $commentId);

            return ResponseHelper::jsonResponse(true, 'Task Comment Deleted Successfully', null, 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task/Comment Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAttachments(string $id)
    {
        try {
            $attachments = $this->projectTaskRepository->getAttachments($id);

            return ResponseHelper::jsonResponse(true, 'Task Attachments Retrieved Successfully', ProjectTaskAttachmentResource::collection($attachments), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getStatusLogs(string $id)
    {
        try {
            $logs = $this->projectTaskRepository->getStatusLogs($id);

            return ResponseHelper::jsonResponse(true, 'Task Status Logs Retrieved Successfully', ProjectTaskStatusLogResource::collection($logs), 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function storeAttachment(ProjectTaskAttachmentStoreRequest $request, string $id)
    {
        $payload = $request->validated();

        try {
            $task = ProjectTask::with('project')->findOrFail($id);

            $response = Gate::inspect('collaborate', $task);
            if ($response->denied()) {
                return ResponseHelper::jsonResponse(false, $response->message(), null, 403);
            }

            $attachment = $this->projectTaskRepository->createAttachment($id, $payload);

            return ResponseHelper::jsonResponse(true, 'Task Attachment Uploaded Successfully', new ProjectTaskAttachmentResource($attachment), 201);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function deleteAttachment(string $id, string $attachmentId)
    {
        try {
            $this->projectTaskRepository->deleteAttachment($id, $attachmentId);

            return ResponseHelper::jsonResponse(true, 'Task Attachment Deleted Successfully', null, 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task/Attachment Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('ProjectTaskController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
