<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PerformanceReviewTemplateController extends Controller implements HasMiddleware
{
    public function __construct(private PerformanceReviewRepositoryInterface $repository) {}

    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('review-cycle-manage')),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $templates = $this->repository->getTemplates();
            
            return ResponseHelper::jsonResponse(true, 'Templates retrieved successfully', $templates);
        } catch (\Exception $e) {
            Log::error('Template index error: ' . $e->getMessage());
            return ResponseHelper::jsonResponse(false, 'Failed to retrieve templates', null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:performance_review_sections,id',
            'sections.*.weight' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $template = $this->repository->createTemplate([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'is_default' => $validated['is_default'] ?? false,
            ], $validated['sections']);

            return ResponseHelper::jsonResponse(true, 'Template created successfully', $template, 201);
        } catch (\Exception $e) {
            Log::error('Template store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Failed to create template', null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $template = $this->repository->getTemplateById($id);
            return ResponseHelper::jsonResponse(true, 'Template retrieved successfully', $template);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, 'Template not found', null, 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sections' => 'sometimes|required|array',
            'sections.*.id' => 'required|exists:performance_review_sections,id',
            'sections.*.weight' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $template = $this->repository->updateTemplate(
                $id,
                array_diff_key($validated, ['sections' => true]),
                $validated['sections'] ?? null
            );

            return ResponseHelper::jsonResponse(true, 'Template updated successfully', $template);
        } catch (\Exception $e) {
            Log::error('Template update error: ' . $e->getMessage());
            return ResponseHelper::jsonResponse(false, 'Failed to update template', null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->repository->deleteTemplate($id);

            return ResponseHelper::jsonResponse(true, 'Template deleted successfully');
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'Cannot delete template that is already used in reviews') {
                return ResponseHelper::jsonResponse(false, 'Cannot delete template that is already used in reviews', null, 422);
            }

            return ResponseHelper::jsonResponse(false, 'Failed to delete template', null, 500);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, 'Failed to delete template', null, 500);
        }
    }
}
