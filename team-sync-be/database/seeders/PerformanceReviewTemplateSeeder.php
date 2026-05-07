<?php

namespace Database\Seeders;

use App\Models\PerformanceReviewSection;
use App\Models\PerformanceReviewTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class PerformanceReviewTemplateSeeder extends Seeder
{
    /**
     * Seed default review templates.
     *
     * Staff Template:  All 5 sections with equal emphasis on KPI sections.
     * Manager Template: Adds heavier weighting on Leadership & Communication (competency).
     */
    public function run(): void
    {
        $sections = PerformanceReviewSection::all()->keyBy('name');

        if ($sections->isEmpty()) {
            $this->command?->warn('No performance review sections found. Run PerformanceReviewSectionSeeder first.');

            return;
        }

        // ── Staff Template (default) ───────────────────────────────────
        $staffTemplate = PerformanceReviewTemplate::firstOrCreate(
            ['name' => 'Staff Review Template'],
            [
                'description' => 'Standard assessment template for individual contributors. Balanced weighting across technical, productivity, and soft skills.',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        $staffWeights = [
            'Technical Skills & Quality of Work' => 25,
            'Productivity & Time Management' => 20,
            'Communication & Collaboration' => 20,
            'Initiative & Problem Solving' => 15,
            'Leadership & Core Values' => 20,
        ];

        $this->syncSections($staffTemplate, $sections, $staffWeights);

        // ── Manager Template ───────────────────────────────────────────
        $managerTemplate = PerformanceReviewTemplate::firstOrCreate(
            ['name' => 'Manager Review Template'],
            [
                'description' => 'Assessment template for managers and team leads. Higher emphasis on leadership, communication, and team performance.',
                'is_active' => true,
                'is_default' => false,
            ]
        );

        $managerWeights = [
            'Technical Skills & Quality of Work' => 15,
            'Productivity & Time Management' => 15,
            'Communication & Collaboration' => 15,
            'Initiative & Problem Solving' => 10,
            'Leadership & Core Values' => 25,
            'Team Performance Score' => 20,
        ];

        $this->syncSections($managerTemplate, $sections, $managerWeights);

        $this->command?->info('Seeded 2 review templates (Staff + Manager).');
    }

    /**
     * Sync sections with weights for a template.
     */
    private function syncSections(
        PerformanceReviewTemplate $template,
        Collection $sections,
        array $weights
    ): void {
        $syncData = [];

        foreach ($weights as $sectionName => $weight) {
            $section = $sections->get($sectionName);
            if ($section) {
                $syncData[$section->id] = ['weight' => $weight];
            }
        }

        $template->sections()->sync($syncData);
    }
}
