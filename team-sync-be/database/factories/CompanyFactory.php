<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'domain' => fake()->optional()->domainName(),
            'logo_url' => null,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
            'settings' => null,
        ];
    }

    /**
     * The default "Team Sync Pro" company used in single-tenant mode.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Team Sync Pro',
            'slug' => 'team-sync-pro',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
        ]);
    }
}
