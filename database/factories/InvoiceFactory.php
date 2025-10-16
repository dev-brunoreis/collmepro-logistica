<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\OrderSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_summary_id' => OrderSummary::factory(),
            'bling_id' => $this->faker->unique()->randomNumber(8),
            'tipo' => 1,
            'situacao' => 6,
            'numero' => 'NF-' . $this->faker->unique()->numberBetween(1000, 99999),
            'data_emissao' => now(),
            'data_operacao' => now(),
            'chave_acesso' => $this->faker->numerify('################'),
            'valor_nota' => $this->faker->randomFloat(2, 10, 1000),
            'valor_frete' => $this->faker->randomFloat(2, 5, 50),
            'xml' => null,
            'link_danfe' => $this->faker->optional()->url(),
            'link_pdf' => $this->faker->optional()->url(),
            'optante_simples_nacional' => $this->faker->boolean(),
            'numero_pedido_loja' => $this->faker->optional()->numerify('####'),
            'serie' => $this->faker->numberBetween(1, 3),
            'contato_id' => $this->faker->optional()->randomNumber(6),
            'contato_nome' => $this->faker->name(),
            'contato_documento' => $this->faker->numerify('###########'),
            'contato_ie' => $this->faker->optional()->numerify('########'),
            'contato_rg' => $this->faker->optional()->numerify('########'),
            'contato_telefone' => $this->faker->optional()->phoneNumber(),
            'contato_email' => $this->faker->optional()->email(),
            'contato_endereco' => $this->faker->optional()->streetAddress(),
            'contato_numero' => $this->faker->optional()->buildingNumber(),
            'contato_complemento' => $this->faker->optional()->secondaryAddress(),
            'contato_bairro' => $this->faker->optional()->city(),
            'contato_cep' => $this->faker->optional()->postcode(),
            'contato_municipio' => $this->faker->optional()->city(),
            'contato_uf' => $this->faker->optional()->stateAbbr(),
            'contato_pais' => $this->faker->optional()->country(),
            'natureza_operacao_id' => $this->faker->optional()->randomNumber(4),
            'loja_id' => $this->faker->optional()->randomNumber(4),
            'vendedor_id' => $this->faker->optional()->randomNumber(4),
            'frete_por_conta' => 0,
            'transportador_nome' => $this->faker->optional()->company(),
            'transportador_documento' => $this->faker->optional()->numerify('##############'),
            'etiqueta_nome' => $this->faker->optional()->name(),
            'etiqueta_endereco' => $this->faker->optional()->streetAddress(),
            'etiqueta_numero' => $this->faker->optional()->buildingNumber(),
            'etiqueta_complemento' => $this->faker->optional()->secondaryAddress(),
            'etiqueta_municipio' => $this->faker->optional()->city(),
            'etiqueta_uf' => $this->faker->optional()->stateAbbr(),
            'etiqueta_cep' => $this->faker->optional()->postcode(),
            'etiqueta_bairro' => $this->faker->optional()->city(),
            'itens' => null,
            'parcelas' => null,
            'volumes' => null,
            'raw_data' => null,
        ];
    }

    /**
     * Indicate that the invoice is for a specific order.
     */
    public function forOrder(OrderSummary $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_summary_id' => $order->id,
            'contato_nome' => $order->contato_nome,
            'contato_documento' => $order->contato_documento,
        ]);
    }
}

