<?php

namespace Database\Seeders;

use App\Models\PerformanceReviewSection;
use Illuminate\Database\Seeder;

class PerformanceReviewSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'name' => 'Technical Skills & Quality of Work',
                'description' => 'Evaluates the employee\'s technical proficiency, accuracy, and overall quality of deliverables.',
                'weight' => 25.00,
                'topsis_category' => 'kpi',
                'order' => 1,
            ],
            [
                'name' => 'Productivity & Time Management',
                'description' => 'Assesses the employee\'s ability to meet deadlines, manage workload efficiently, and output volume.',
                'weight' => 20.00,
                'topsis_category' => 'kpi',
                'order' => 2,
            ],
            [
                'name' => 'Communication & Collaboration',
                'description' => 'Measures effectiveness in sharing information, teamwork, and interacting with peers and stakeholders.',
                'weight' => 20.00,
                'topsis_category' => 'competency',
                'order' => 3,
            ],
            [
                'name' => 'Initiative & Problem Solving',
                'description' => 'Evaluates proactivity, ability to identify and resolve issues, and continuous improvement efforts.',
                'weight' => 15.00,
                'topsis_category' => 'kpi',
                'order' => 4,
            ],
            [
                'name' => 'Leadership & Core Values',
                'description' => 'Assesses alignment with company values, mentorship, and positive influence on the team culture.',
                'weight' => 20.00,
                'topsis_category' => 'competency',
                'order' => 5,
            ],
            [
                'name' => 'Team Performance Score',
                'description' => 'Evaluates the manager\'s ability to drive team results, develop talent, and maintain high-performing teams. Applicable to Manager templates only.',
                'weight' => 20.00,
                'topsis_category' => 'kpi',
                'order' => 6,
            ],
        ];

        foreach ($sections as $section) {
            PerformanceReviewSection::firstOrCreate(
                ['name' => $section['name']],
                $section
            );
        }
    }
}
