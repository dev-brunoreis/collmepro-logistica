<?php

namespace Database\Factories;

use App\Models\Label;
use App\Models\OrderSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Label>
 */
class LabelFactory extends Factory
{
    protected $model = Label::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_summary_id' => OrderSummary::factory(),
            'bling_label_id' => $this->faker->unique()->numberBetween(1000000, 9999999),
            'tracking_code' => $this->faker->optional()->regexify('[A-Z]{2}[0-9]{9}BR'),
            'format' => $this->faker->randomElement(['PDF', 'ZPL']),
            'issue_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'link' => $this->faker->url(),
            'observation' => $this->faker->optional()->sentence(),
            'raw_data' => [
                'id' => $this->faker->numberBetween(1000000, 9999999),
                'link' => $this->faker->url(),
                'observacao' => $this->faker->optional()->sentence(),
            ],
        ];
    }

    /**
     * Indicate that the label has no tracking code.
     */
    public function withoutTracking(): static
    {
        return $this->state(fn (array $attributes) => [
            'tracking_code' => null,
        ]);
    }

    /**
     * Indicate that the label has a specific tracking code.
     */
    public function withTrackingCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'tracking_code' => $code,
        ]);
    }

    /**
     * Indicate that the label was issued today.
     */
    public function issuedToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'issue_date' => now(),
        ]);
    }

    /**
     * Indicate that the label is in PDF format.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'PDF',
        ]);
    }

    /**
     * Indicate that the label is in ZPL format.
     */
    public function zpl(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'ZPL',
        ]);
    }
}

