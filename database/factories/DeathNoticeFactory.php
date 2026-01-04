<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeathNotice>
 */
class DeathNoticeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fullName = fake()->name();
        $funeralDate = fake()->dateTimeBetween('-30 days', '+30 days');
        $sourceUrl = fake()->url();

        return [
            'hash' => substr(hash('sha256', $fullName.$funeralDate->format('Y-m-d').$sourceUrl), 0, 12),
            'full_name' => $fullName,
            'funeral_date' => $funeralDate,
            'death_date' => fake()->dateTimeBetween('-40 days', '-1 days'),
            'source' => fake()->randomElement(['PSBK', 'PS Hajdukova', 'Sadovy Jan']),
            'source_url' => $sourceUrl,
            'announcement_text' => fake()->paragraph(3),
            'has_photo' => fake()->boolean(),
        ];
    }

    /**
     * Indicate that the death notice has no funeral date.
     */
    public function withoutFuneralDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'funeral_date' => null,
        ]);
    }

    /**
     * Indicate that the death notice has no announcement text.
     */
    public function withoutAnnouncementText(): static
    {
        return $this->state(fn (array $attributes) => [
            'announcement_text' => null,
        ]);
    }
}
