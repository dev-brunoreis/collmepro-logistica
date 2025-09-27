<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $systems = ['collmepro', 'collmepro_wholesale', 'collme_beauty'];
        $logisticsProviders = ['correios_pac', 'correios_sedex', 'total_express', 'melhor_envio'];
        $trackingStatuses = ['label_issued', 'dispatched', 'in_transit', 'delivered'];

        return [
            'system' => fake()->randomElement($systems),
            'order_number' => fake()->unique()->regexify('[A-Z]{2}[0-9]{3,6}'),
            'customer_name' => fake()->name(),
            'seller_name' => fake()->name(),
            'order_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'nfe_number' => fake()->optional(0.7)->regexify('[0-9]{44}'),
            'nfe_pdf_url' => fake()->optional(0.5)->url(),
            'tracking_code' => fake()->optional(0.6)->regexify('[A-Z]{2}[0-9]{9}[A-Z]{2}'),
            'logistics_provider' => fake()->optional(0.6)->randomElement($logisticsProviders),
            'tracking_status' => fake()->optional(0.6)->randomElement($trackingStatuses),
        ];
    }

    /**
     * Indicate that the order is from Collmepro.
     */
    public function collmepro(): static
    {
        return $this->state(fn (array $attributes) => [
            'system' => 'collmepro',
        ]);
    }

    /**
     * Indicate that the order is from Collmepro Wholesale.
     */
    public function collmeproWholesale(): static
    {
        return $this->state(fn (array $attributes) => [
            'system' => 'collmepro_wholesale',
        ]);
    }

    /**
     * Indicate that the order is from Collme Beauty.
     */
    public function collmeBeauty(): static
    {
        return $this->state(fn (array $attributes) => [
            'system' => 'collme_beauty',
        ]);
    }

    /**
     * Indicate that the order has NFE.
     */
    public function withNfe(): static
    {
        return $this->state(fn (array $attributes) => [
            'nfe_number' => fake()->regexify('[0-9]{44}'),
            'nfe_pdf_url' => fake()->url(),
        ]);
    }

    /**
     * Indicate that the order has tracking.
     */
    public function withTracking(): static
    {
        return $this->state(fn (array $attributes) => [
            'tracking_code' => fake()->regexify('[A-Z]{2}[0-9]{9}[A-Z]{2}'),
            'logistics_provider' => fake()->randomElement(['correios_pac', 'correios_sedex', 'total_express', 'melhor_envio']),
            'tracking_status' => fake()->randomElement(['label_issued', 'dispatched', 'in_transit', 'delivered']),
        ]);
    }
}
