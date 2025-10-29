<?php

namespace Database\Factories;

use App\Models\OrderData;
use App\Models\OrderSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderData>
 */
class OrderDataFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OrderData::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_summary_id' => OrderSummary::factory(),
            'numero_pedido_compra' => $this->faker->optional()->numerify('PED-####'),
            'outras_despesas' => $this->faker->randomFloat(2, 0, 50),
            'observacoes' => $this->faker->optional()->sentence(),
            'observacoes_internas' => $this->faker->optional()->sentence(),
            'desconto_valor' => $this->faker->randomFloat(2, 0, 100),
            'desconto_unidade' => $this->faker->randomElement(['REAL', 'PERCENTUAL']),
            'categoria_id' => $this->faker->optional()->randomNumber(4),
            'nota_fiscal_id' => $this->faker->optional()->randomNumber(4),
            'total_icms' => $this->faker->randomFloat(2, 0, 50),
            'total_ipi' => $this->faker->randomFloat(2, 0, 30),
            'vendedor_id' => $this->faker->optional()->randomNumber(4),
            'intermediador_cnpj' => $this->faker->optional()->cnpj(false),
            'intermediador_nome_usuario' => $this->faker->optional()->userName(),
            'taxa_comissao' => $this->faker->randomFloat(2, 0, 20),
            'custo_frete' => $this->faker->randomFloat(2, 0, 100),
            'valor_base' => $this->faker->randomFloat(2, 100, 1000),
            'frete_por_conta' => $this->faker->numberBetween(0, 1),
            'frete' => $this->faker->randomFloat(2, 10, 50),
            'quantidade_volumes' => $this->faker->numberBetween(1, 5),
            'peso_bruto' => $this->faker->randomFloat(3, 0.1, 10.0),
            'prazo_entrega' => $this->faker->numberBetween(1, 15),
            'transportador_id' => $this->faker->optional()->randomNumber(6),
            'transportador_nome' => $this->faker->optional()->company(),
            'etiqueta_nome' => $this->faker->optional()->name(),
            'etiqueta_endereco' => $this->faker->optional()->streetAddress(),
            'etiqueta_numero' => $this->faker->optional()->buildingNumber(),
            'etiqueta_complemento' => $this->faker->optional()->secondaryAddress(),
            'etiqueta_municipio' => $this->faker->optional()->city(),
            'etiqueta_uf' => $this->faker->optional()->stateAbbr(),
            'etiqueta_cep' => $this->faker->optional()->postcode(),
            'etiqueta_bairro' => $this->faker->optional()->city(),
            'etiqueta_nome_pais' => $this->faker->optional()->country(),
            'itens' => $this->generateSampleItems(),
            'parcelas' => $this->generateSampleParcelas(),
            'volumes' => $this->generateSampleVolumes(),
            'raw_data' => null,
        ];
    }

    /**
     * Generate sample items for testing
     */
    private function generateSampleItems(): array
    {
        $itemCount = $this->faker->numberBetween(1, 3);
        $items = [];

        for ($i = 0; $i < $itemCount; $i++) {
            $items[] = [
                'id' => $this->faker->randomNumber(8),
                'codigo' => 'PROD-' . $this->faker->unique()->numberBetween(100, 999),
                'unidade' => 'Un',
                'quantidade' => $this->faker->numberBetween(1, 5),
                'desconto' => $this->faker->randomFloat(2, 0, 20),
                'valor' => $this->faker->randomFloat(2, 10, 200),
                'aliquotaIPI' => 0,
                'descricao' => $this->faker->words(2, true),
                'descricaoDetalhada' => $this->faker->optional()->sentence(),
                'produto' => [
                    'id' => $this->faker->randomNumber(8)
                ],
                'comissao' => [
                    'base' => 0,
                    'aliquota' => 0,
                    'valor' => 0
                ]
            ];
        }

        return $items;
    }

    /**
     * Generate sample parcelas for testing
     */
    private function generateSampleParcelas(): array
    {
        return [
            [
                'id' => $this->faker->randomNumber(8),
                'dataVencimento' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
                'valor' => $this->faker->randomFloat(2, 50, 500),
                'observacoes' => 'Total Parcelas: 1 | Método pagamento: Cartão de Crédito',
                'caut' => '',
                'formaPagamento' => [
                    'id' => $this->faker->randomNumber(6)
                ]
            ]
        ];
    }

    /**
     * Generate sample volumes for testing
     */
    private function generateSampleVolumes(): array
    {
        return [
            [
                'id' => $this->faker->randomNumber(8),
                'servico' => $this->faker->randomElement(['SEDEX', 'PAC', 'Total Express']),
                'codigoRastreamento' => $this->faker->optional()->regexify('BR[0-9]{9}BR')
            ]
        ];
    }
}
