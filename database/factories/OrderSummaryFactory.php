<?php

namespace Database\Factories;

use App\Models\OrderSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderSummary>
 */
class OrderSummaryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OrderSummary::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bling_id' => $this->faker->unique()->randomNumber(8),
            'numero' => 'TEST-' . $this->faker->unique()->numberBetween(1000, 9999),
            'numero_loja' => $this->faker->optional()->numerify('###'),
            'data' => now(),
            'data_saida' => $this->faker->optional()->dateTimeBetween('now', '+7 days'),
            'data_prevista' => $this->faker->optional()->dateTimeBetween('+1 day', '+14 days'),
            'total_produtos' => $this->faker->numberBetween(1, 10),
            'total' => $this->faker->randomFloat(2, 10, 1000),
            'contato_id' => $this->faker->optional()->randomNumber(6),
            'contato_nome' => $this->faker->name(),
            'contato_tipo_pessoa' => $this->faker->randomElement(['F', 'J']),
            'contato_documento' => $this->faker->numerify('###########'),
            'situacao_id' => $this->faker->optional()->randomNumber(4),
            'situacao_valor' => $this->faker->optional()->numberBetween(1, 10),
            'loja_id' => $this->faker->optional()->randomNumber(4),
            'source' => 'bling',
            'data_processed' => false,
            'external_updated_at' => now(),
            'tracking_code' => null,
            'logistics_provider' => null,
            'service_type' => null,
            'volume' => null,
            'weight' => null,
            'raw_order_data' => null,
            'raw_invoice_data' => null,
            'raw_tracking_data' => null,
        ];
    }

    /**
     * Indicate that the order has a tracking code.
     */
    public function withTracking(): static
    {
        return $this->state(fn (array $attributes) => [
            'tracking_code' => 'BR' . $this->faker->numerify('#########') . 'BR',
            'logistics_provider' => $this->faker->randomElement(['Correios', 'Total Express', 'Melhor Envio']),
        ]);
    }

    /**
     * Indicate that the order has been processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_processed' => true,
        ]);
    }
}

