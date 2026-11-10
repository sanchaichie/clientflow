<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->unique()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'description' => fake()->optional()->sentence(12),
            'status' => fake()->randomElement(ClientStatus::cases()),
            'account_manager_id' => null,
            'created_by' => null,
        ];
    }

    public function lead(): static
    {
        return $this->state(fn () => ['status' => ClientStatus::Lead]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => ClientStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
