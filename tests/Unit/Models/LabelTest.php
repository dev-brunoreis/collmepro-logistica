<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Label;
use App\Models\OrderSummary;
use App\Models\Tracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class LabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_belongs_to_order_summary(): void
    {
        $orderSummary = OrderSummary::factory()->create();
        $label = Label::factory()->create(['order_summary_id' => $orderSummary->id]);

        $this->assertInstanceOf(OrderSummary::class, $label->orderSummary);
        $this->assertEquals($orderSummary->id, $label->orderSummary->id);
    }

    public function test_is_posted_returns_false_when_no_tracking(): void
    {
        $label = Label::factory()->create(['tracking_code' => null]);

        $this->assertFalse($label->is_posted);
    }

    public function test_is_posted_returns_false_when_tracking_situacao_less_than_2(): void
    {
        $orderSummary = OrderSummary::factory()->create();
        $invoice = \App\Models\Invoice::factory()->create(['order_summary_id' => $orderSummary->id]);
        $tracking = Tracking::factory()->create([
            'invoice_id' => $invoice->id,
            'codigo_rastreamento' => 'TEST123',
            'situacao' => 1,
        ]);

        $label = Label::factory()->create([
            'tracking_code' => 'TEST123',
            'order_summary_id' => $orderSummary->id,
        ]);

        $this->assertFalse($label->is_posted);
    }

    public function test_is_posted_returns_true_when_tracking_situacao_2_or_more(): void
    {
        $orderSummary = OrderSummary::factory()->create();
        $invoice = \App\Models\Invoice::factory()->create(['order_summary_id' => $orderSummary->id]);
        $tracking = Tracking::factory()->create([
            'invoice_id' => $invoice->id,
            'codigo_rastreamento' => 'TEST123',
            'situacao' => 2,
        ]);

        $label = Label::factory()->create([
            'tracking_code' => 'TEST123',
            'order_summary_id' => $orderSummary->id,
        ]);

        $this->assertTrue($label->is_posted);
    }

    public function test_status_text_returns_correct_values(): void
    {
        $labelNotPosted = Label::factory()->create(['tracking_code' => null]);
        $this->assertEquals('Não Postado', $labelNotPosted->status_text);

        $orderSummary = OrderSummary::factory()->create();
        $invoice = \App\Models\Invoice::factory()->create(['order_summary_id' => $orderSummary->id]);
        $tracking = Tracking::factory()->create([
            'invoice_id' => $invoice->id,
            'codigo_rastreamento' => 'TEST456',
            'situacao' => 3,
        ]);

        $labelPosted = Label::factory()->create([
            'tracking_code' => 'TEST456',
            'order_summary_id' => $orderSummary->id,
        ]);
        
        $this->assertEquals('Postado', $labelPosted->status_text);
    }

    public function test_generated_today_scope_filters_correctly(): void
    {
        Label::factory()->create(['issue_date' => Carbon::today()]);
        Label::factory()->create(['issue_date' => Carbon::yesterday()]);

        $todayLabels = Label::generatedToday()->get();

        $this->assertEquals(1, $todayLabels->count());
    }

    public function test_by_date_range_scope_filters_correctly(): void
    {
        Label::factory()->create(['issue_date' => Carbon::parse('2025-01-01')]);
        Label::factory()->create(['issue_date' => Carbon::parse('2025-01-15')]);
        Label::factory()->create(['issue_date' => Carbon::parse('2025-01-31')]);

        $filteredLabels = Label::byDateRange('2025-01-10', '2025-01-20')->get();

        $this->assertEquals(1, $filteredLabels->count());
    }

    public function test_search_scope_searches_tracking_code(): void
    {
        Label::factory()->create(['tracking_code' => 'ABC123']);
        Label::factory()->create(['tracking_code' => 'XYZ789']);

        $results = Label::search('ABC')->get();

        $this->assertEquals(1, $results->count());
        $this->assertEquals('ABC123', $results->first()->tracking_code);
    }

    public function test_search_scope_searches_order_number(): void
    {
        $order1 = OrderSummary::factory()->create(['numero' => '12345']);
        $order2 = OrderSummary::factory()->create(['numero' => '67890']);
        
        Label::factory()->create(['order_summary_id' => $order1->id]);
        Label::factory()->create(['order_summary_id' => $order2->id]);

        $results = Label::search('123')->get();

        $this->assertEquals(1, $results->count());
    }
}

