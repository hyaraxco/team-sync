<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);
        $dateOfBirth = fake()->dateTimeBetween('-55 years', '-22 years');

        // Create user with matching profile picture
        $profilePicture = $this->getProfilePictureByGender($gender);

        $religions = ['islam', 'kristen', 'katolik', 'hindu', 'budha', 'konghucu'];
        $maritalStatuses = ['single', 'married', 'widowed', 'divorced'];
        $bloodTypes = ['A', 'B', 'AB', 'O'];
        $ptkpStatuses = ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];

        return [
            'user_id' => User::factory()->state([
                'profile_photo' => $profilePicture,
            ])->afterCreating(function (User $user) {
                $user->assignRole('employee');
            }),
            'code' => $this->generateEmployeeCode(),
            'identity_number' => $this->generateIdentityNumber(),
            'npwp' => $this->generateNpwp(),
            'bpjs_ketenagakerjaan' => fake()->numerify('###########'),
            'bpjs_kesehatan' => fake()->numerify('#############'),
            'ptkp_status' => fake()->randomElement($ptkpStatuses),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => $dateOfBirth,
            'gender' => $gender,
            'religion' => fake()->randomElement($religions),
            'marital_status' => fake()->randomElement($maritalStatuses),
            'blood_type' => fake()->optional(0.8)->randomElement($bloodTypes),
            'place_of_birth' => fake()->city(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
        ];
    }

    /**
     * Get profile picture based on gender
     */
    private function getProfilePictureByGender(string $gender): string
    {
        $number = fake()->numberBetween(1, 3);

        return "profile-pictures/{$gender}/{$number}.avif";
    }

    /**
     * Generate employee code with format EMP-YYYY-XXXXXX (up to 999999 for 500k employees)
     */
    private function generateEmployeeCode(): string
    {
        static $counter = 1;

        $year = date('Y');
        $number = str_pad($counter, 6, '0', STR_PAD_LEFT);
        $counter++;

        return "EMP-{$year}-{$number}";
    }

    /**
     * Generate realistic identity number (NIK format for Indonesia)
     */
    private function generateIdentityNumber(): string
    {
        return fake()->numerify('##############');
    }

    /**
     * Generate realistic NPWP number (format: XX.XXX.XXX.X-XXX.XXX)
     */
    private function generateNpwp(): string
    {
        return fake()->numerify('##.###.###.#-###.###');
    }

    /**
     * Indicate that the employee is male.
     */
    public function male(): static
    {
        $profilePicture = $this->getProfilePictureByGender('male');

        return $this->state(fn(array $attributes) => [
            'gender' => 'male',
            'user_id' => User::factory()->state([
                'profile_photo' => $profilePicture,
            ])->afterCreating(function (User $user) {
                $user->assignRole('employee');
            }),
        ]);
    }

    /**
     * Indicate that the employee is female.
     */
    public function female(): static
    {
        $profilePicture = $this->getProfilePictureByGender('female');

        return $this->state(fn(array $attributes) => [
            'gender' => 'female',
            'user_id' => User::factory()->state([
                'profile_photo' => $profilePicture,
            ])->afterCreating(function (User $user) {
                $user->assignRole('employee');
            }),
        ]);
    }

    /**
     * Indicate that the employee is senior (older age).
     */
    public function senior(): static
    {
        return $this->state(fn(array $attributes) => [
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-40 years'),
        ]);
    }

    /**
     * Indicate that the employee is junior (younger age).
     */
    public function junior(): static
    {
        return $this->state(fn(array $attributes) => [
            'date_of_birth' => fake()->dateTimeBetween('-30 years', '-22 years'),
        ]);
    }

    /**
     * Create employee with specific user
     */
    public function forUser(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
