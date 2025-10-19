<?php

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_returns_403_without_valid_user_agent(): void
    {
        $response = $this->getJson('/api/metrics');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_metrics_returns_403_with_invalid_user_agent(): void
    {
        $response = $this->withHeader('User-Agent', 'InvalidUserAgent/1.0')
            ->getJson('/api/metrics');

        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_metrics_returns_data_with_valid_user_agent(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'orders' => [
                    'total',
                    'active',
                    'cancelled',
                    'revenue',
                    'with_tracking',
                    'by_status',
                    'by_source',
                    'by_day',
                ],
                'invoices' => [
                    'total',
                    'total_value',
                    'total_freight',
                    'by_day',
                ],
                'tracking' => [
                    'with_tracking',
                    'without_tracking',
                    'by_carrier',
                ],
                'users' => [
                    'total',
                    'active',
                    'by_role',
                ],
                'period' => [
                    'start',
                    'end',
                ],
            ]);
    }

    public function test_metrics_with_period_day_filter(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?period=day');

        $response->assertStatus(200)
            ->assertJsonStructure(['period' => ['start', 'end']]);
    }

    public function test_metrics_with_period_week_filter(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?period=week');

        $response->assertStatus(200)
            ->assertJsonStructure(['period']);
    }

    public function test_metrics_with_custom_date_range(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?period=custom&start_date=2024-01-01&end_date=2024-01-31');

        $response->assertStatus(200)
            ->assertJson([
                'period' => [
                    'start' => '2024-01-01',
                    'end' => '2024-01-31',
                ],
            ]);
    }

    public function test_metrics_with_source_filter(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?source=collmepro');

        $response->assertStatus(200);
    }

    public function test_metrics_validates_invalid_period(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?period=invalid_period');

        $response->assertStatus(422);
    }

    public function test_metrics_validates_invalid_source(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?source=invalid_source');

        $response->assertStatus(422);
    }

    public function test_metrics_validates_date_format(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?period=custom&start_date=invalid-date&end_date=2024-01-31');

        $response->assertStatus(422);
    }

    public function test_metrics_validates_date_range(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?period=custom&start_date=2024-12-31&end_date=2024-01-01');

        $response->assertStatus(422);
    }

    public function test_metrics_returns_correct_orders_count(): void
    {
        // Create test orders
        OrderSummary::factory()->count(5)->create();

        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics');

        $response->assertStatus(200)
            ->assertJson([
                'orders' => [
                    'total' => 5,
                ],
            ]);
    }

    public function test_metrics_returns_correct_cancelled_orders_count(): void
    {
        // Create active orders
        OrderSummary::factory()->count(3)->create(['situacao_id' => 9]);
        
        // Create cancelled orders
        OrderSummary::factory()->count(2)->create(['situacao_id' => 12]);

        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics');

        $response->assertStatus(200)
            ->assertJson([
                'orders' => [
                    'total' => 5,
                    'active' => 3,
                    'cancelled' => 2,
                ],
            ]);
    }

    public function test_metrics_filters_by_source(): void
    {
        // Create orders from different sources
        OrderSummary::factory()->count(3)->create(['source' => 'collmepro']);
        OrderSummary::factory()->count(2)->create(['source' => 'collme_beauty']);

        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?source=collmepro');

        $response->assertStatus(200)
            ->assertJson([
                'orders' => [
                    'total' => 3,
                ],
            ]);
    }

    public function test_metrics_can_exclude_sections(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?include_orders=1&include_invoices=0&include_tracking=0&include_users=0');

        $response->assertStatus(200)
            ->assertJsonStructure(['orders', 'period'])
            ->assertJsonMissing(['invoices', 'tracking', 'users']);
    }

    public function test_metrics_sql_injection_prevention_in_source(): void
    {
        // Try SQL injection in source parameter
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?source=collmepro\'; DROP TABLE order_summary; --');

        // Should return validation error, not execute SQL
        $response->assertStatus(422);
    }

    public function test_metrics_sql_injection_prevention_in_dates(): void
    {
        // Try SQL injection in date parameters
        $response = $this->withHeader('User-Agent', 'DevHub-MetricsCollector/1.0')
            ->getJson('/api/metrics?start_date=2024-01-01\' OR 1=1 --&end_date=2024-01-31');

        // Should return validation error, not execute SQL
        $response->assertStatus(422);
    }
}

