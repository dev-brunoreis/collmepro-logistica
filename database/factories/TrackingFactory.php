<?php

namespace Database\Factories;

use App\Models\Tracking;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tracking>
 */
class TrackingFactory extends Factory
{
    protected $model = Tracking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'volume_id' => $this->faker->numberBetween(1000, 9999),
            'pedido_venda_id' => $this->faker->numberBetween(1000000, 9999999),
            'nota_fiscal_id' => $this->faker->numberBetween(1000000, 9999999),
            'servico_id' => $this->faker->numberBetween(1, 10),
            'embalagem_id' => $this->faker->numberBetween(1, 5),
            'codigo_rastreamento' => $this->faker->regexify('[A-Z]{2}[0-9]{9}BR'),
            'descricao' => $this->faker->sentence(),
            'situacao' => $this->faker->numberBetween(1, 10),
            'origem' => $this->faker->city(),
            'destino' => $this->faker->city(),
            'ultima_alteracao' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'url_rastreamento' => $this->faker->url(),
            'peso' => $this->faker->randomFloat(2, 0.1, 10.0),
            'altura' => $this->faker->randomFloat(2, 1, 50),
            'largura' => $this->faker->randomFloat(2, 1, 50),
            'comprimento' => $this->faker->randomFloat(2, 1, 100),
            'diametro' => $this->faker->randomFloat(2, 1, 20),
            'data_saida' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'prazo_entrega_previsto' => $this->faker->numberBetween(1, 15),
            'frete_previsto' => $this->faker->randomFloat(2, 10, 100),
            'valor_declarado' => $this->faker->randomFloat(2, 50, 1000),
            'aviso_recebimento' => $this->faker->boolean(),
            'mao_propria' => $this->faker->boolean(),
            'last_sync_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'next_sync_at' => $this->faker->dateTimeBetween('now', '+1 day'),
            'sync_attempts' => $this->faker->numberBetween(0, 3),
            'last_error' => $this->faker->optional()->sentence(),
            'raw_data' => [
                'tracking_code' => $this->faker->regexify('[A-Z]{2}[0-9]{9}BR'),
                'status' => $this->faker->randomElement(['Pendente', 'Em Trânsito', 'Entregue']),
                'events' => [
                    [
                        'date' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d H:i:s'),
                        'location' => $this->faker->city(),
                        'description' => $this->faker->sentence(),
                    ]
                ]
            ],
        ];
    }

    /**
     * Indicate that the tracking is posted (situacao >= 2).
     */
    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacao' => $this->faker->numberBetween(2, 10),
        ]);
    }

    /**
     * Indicate that the tracking is not posted (situacao < 2).
     */
    public function notPosted(): static
    {
        return $this->state(fn (array $attributes) => [
            'situacao' => 1,
        ]);
    }

    /**
     * Indicate that the tracking has a specific tracking code.
     */
    public function withTrackingCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'codigo_rastreamento' => $code,
        ]);
    }
}
