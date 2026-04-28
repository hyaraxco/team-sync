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

    public function index(): JsonResponse
    {
        $templates = $this->repository->getTemplates();

        return ResponseHelper::jsonResponse(true, 'Templates retrieved successfully', $templates);
    }

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

        $template = $this->repository->createTemplate([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_default' => $validated['is_default'] ?? false,
        ], $validated['sections']);

        return ResponseHelper::jsonResponse(true, 'Template created successfully', $template, 201);
    }

    public function show(int $id): JsonResponse
    {
        $template = $this->repository->getTemplateById($id);

        return ResponseHelper::jsonResponse(true, 'Template retrieved successfully', $template);
    }

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

        $template = $this->repository->updateTemplate(
            $id,
            array_diff_key($validated, ['sections' => true]),
            $validated['sections'] ?? null
        );

        return ResponseHelper::jsonResponse(true, 'Template updated successfully', $template);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->repository->deleteTemplate($id);

        return ResponseHelper::jsonResponse(true, 'Template deleted successfully');
    }
}
