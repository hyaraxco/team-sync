<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\PerformanceReviewTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceReviewTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $templates = PerformanceReviewTemplate::withCount('sections')->get();
            
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
            return DB::transaction(function () use ($validated) {
                // If this is set as default, unset others
                if ($validated['is_default'] ?? false) {
                    PerformanceReviewTemplate::where('is_default', true)->update(['is_default' => false]);
                }

                $template = PerformanceReviewTemplate::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                    'is_default' => $validated['is_default'] ?? false,
                ]);

                foreach ($validated['sections'] as $section) {
                    $template->sections()->attach($section['id'], ['weight' => $section['weight']]);
                }

                return ResponseHelper::jsonResponse(true, 'Template created successfully', $template->load('sections'), 201);
            });
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
            $template = PerformanceReviewTemplate::with('sections')->findOrFail($id);
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
            $template = PerformanceReviewTemplate::findOrFail($id);

            return DB::transaction(function () use ($validated, $template) {
                if ($validated['is_default'] ?? false) {
                    PerformanceReviewTemplate::where('id', '!=', $template->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $template->update($validated);

                if (isset($validated['sections'])) {
                    $syncData = [];
                    foreach ($validated['sections'] as $section) {
                        $syncData[$section['id']] = ['weight' => $section['weight']];
                    }
                    $template->sections()->sync($syncData);
                }

                return ResponseHelper::jsonResponse(true, 'Template updated successfully', $template->load('sections'));
            });
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
            $template = PerformanceReviewTemplate::findOrFail($id);
            
            // Check if template is in use
            if ($template->performanceReviews()->exists()) {
                return ResponseHelper::jsonResponse(false, 'Cannot delete template that is already used in reviews', null, 422);
            }

            $template->delete();
            return ResponseHelper::jsonResponse(true, 'Template deleted successfully');
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, 'Failed to delete template', null, 500);
        }
    }
}
