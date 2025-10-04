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
        $situacaoValores = [1, 2, 3, 4]; // Em Aberto, Em Andamento, Finalizado, Cancelado
        
        return [
            'bling_id' => fake()->unique()->numberBetween(10000000, 99999999),
            'numero' => fake()->unique()->numberBetween(1000, 9999),
            'numero_loja' => fake()->optional(0.7)->regexify('Loja_[0-9]{3}'),
            'data' => fake()->dateTimeBetween('-30 days', 'now'),
            'data_saida' => fake()->optional(0.6)->dateTimeBetween('-20 days', 'now'),
            'data_prevista' => fake()->optional(0.8)->dateTimeBetween('now', '+7 days'),
            'total_produtos' => fake()->numberBetween(1, 10),
            'total' => fake()->randomFloat(2, 50, 2000),
            'contato_id' => fake()->numberBetween(10000000, 99999999),
            'contato_nome' => fake()->name(),
            'contato_tipo_pessoa' => fake()->randomElement(['F', 'J']),
            'contato_documento' => fake()->randomElement([fake()->cpf(), fake()->cnpj()]),
            'situacao_id' => fake()->numberBetween(10000000, 99999999),
            'situacao_valor' => fake()->randomElement($situacaoValores),
            'loja_id' => fake()->numberBetween(10000000, 99999999),
            'source' => 'bling',
        ];
    }

    /**
     * Indicate that the order is from Bling.
     */
    public function fromBling(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'bling',
        ]);
    }

    /**
     * Indicate that the order is finalized.
     */
    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacao_valor' => 3, // Finalizado
        ]);
    }

    /**
     * Indicate that the order is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacao_valor' => 1, // Em Aberto
        ]);
    }

    /**
     * Indicate that the order is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacao_valor' => 2, // Em Andamento
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacao_valor' => 4, // Cancelado
        ]);
    }
}
