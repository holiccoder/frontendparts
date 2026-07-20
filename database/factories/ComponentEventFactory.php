<?php

namespace Database\Factories;

use App\Enums\ComponentEventType;
use App\Models\Component;
use App\Models\ComponentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComponentEvent>
 */
class ComponentEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'component_id' => Component::factory(),
            'user_id' => null,
            'type' => fake()->randomElement(ComponentEventType::cases()),
        ];
    }
}
