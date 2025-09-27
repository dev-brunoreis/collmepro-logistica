<?php

namespace Tests\Feature\Models;

use App\Models\Invoice;
use App\Models\Label;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_an_order()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals('collmepro', $order->system);
        $this->assertEquals('WC001', $order->order_number);
        $this->assertEquals('João Silva', $order->customer_name);
        $this->assertEquals('Maria Santos', $order->seller_name);
    }

    #[Test]
    public function it_has_order_status_relationship()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $this->assertInstanceOf(OrderStatus::class, $order->orderStatus);
        $this->assertEquals($orderStatus->id, $order->orderStatus->id);
    }

    #[Test]
    public function it_has_invoices_relationship()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF001',
            'invoice_pdf_url' => 'https://example.com/nf001.pdf',
            'issued_at' => now(),
        ]);

        $this->assertCount(1, $order->invoices);
        $this->assertInstanceOf(Invoice::class, $order->invoices->first());
        $this->assertEquals('NF001', $order->invoices->first()->invoice_number);
    }

    #[Test]
    public function it_has_labels_relationship()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->assertCount(1, $order->labels);
        $this->assertInstanceOf(Label::class, $order->labels->first());
        $this->assertEquals('ET001', $order->labels->first()->label_number);
    }

    #[Test]
    public function it_can_filter_by_system()
    {
        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        Order::create([
            'system' => 'shopify',
            'order_number' => 'SB001',
            'customer_name' => 'Ana Costa',
            'seller_name' => 'Pedro Lima',
            'order_date' => now(),
        ]);

        $collmeproOrders = Order::bySystem('collmepro')->get();
        $shopifyOrders = Order::bySystem('shopify')->get();

        $this->assertCount(1, $collmeproOrders);
        $this->assertCount(1, $shopifyOrders);
        $this->assertEquals('WC001', $collmeproOrders->first()->order_number);
        $this->assertEquals('SB001', $shopifyOrders->first()->order_number);
    }

    #[Test]
    public function it_can_filter_by_order_number()
    {
        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC002',
            'customer_name' => 'Ana Costa',
            'seller_name' => 'Pedro Lima',
            'order_date' => now(),
        ]);

        $orders = Order::byOrderNumber('WC001')->get();

        $this->assertCount(1, $orders);
        $this->assertEquals('WC001', $orders->first()->order_number);
    }

    #[Test]
    public function it_can_filter_by_customer_name()
    {
        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC002',
            'customer_name' => 'Ana Costa',
            'seller_name' => 'Pedro Lima',
            'order_date' => now(),
        ]);

        $orders = Order::byCustomerName('João')->get();

        $this->assertCount(1, $orders);
        $this->assertEquals('João Silva', $orders->first()->customer_name);
    }

    #[Test]
    public function it_can_filter_by_seller_name()
    {
        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC002',
            'customer_name' => 'Ana Costa',
            'seller_name' => 'Pedro Lima',
            'order_date' => now(),
        ]);

        $orders = Order::bySellerName('Maria')->get();

        $this->assertCount(1, $orders);
        $this->assertEquals('Maria Santos', $orders->first()->seller_name);
    }

    #[Test]
    public function it_can_filter_by_date_range()
    {
        $yesterday = now()->subDay();
        $today = now();

        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => $yesterday,
        ]);

        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC002',
            'customer_name' => 'Ana Costa',
            'seller_name' => 'Pedro Lima',
            'order_date' => $today,
        ]);

        $orders = Order::byDateRange($yesterday, $today)->get();

        $this->assertCount(2, $orders);
    }

    #[Test]
    public function it_can_filter_by_tracking_status()
    {
        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
            'tracking_status' => 'in_transit',
        ]);

        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC002',
            'customer_name' => 'Ana Costa',
            'seller_name' => 'Pedro Lima',
            'order_date' => now(),
            'tracking_status' => 'delivered',
        ]);

        $orders = Order::byTrackingStatus('in_transit')->get();

        $this->assertCount(1, $orders);
        $this->assertEquals('in_transit', $orders->first()->tracking_status);
    }

    #[Test]
    public function it_can_filter_by_logistics_provider()
    {
        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
            'logistics_provider' => 'correios_pac',
        ]);

        Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC002',
            'customer_name' => 'Ana Costa',
            'seller_name' => 'Pedro Lima',
            'order_date' => now(),
            'logistics_provider' => 'total_express',
        ]);

        $orders = Order::byLogisticsProvider('correios_pac')->get();

        $this->assertCount(1, $orders);
        $this->assertEquals('correios_pac', $orders->first()->logistics_provider);
    }

    #[Test]
    public function it_formats_order_date_correctly()
    {
        $orderDate = now()->setTime(14, 30, 0);
        
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => $orderDate,
        ]);

        $formattedDate = $order->getFormattedOrderDateAttribute();
        
        $this->assertStringContainsString('14:30', $formattedDate);
        $this->assertStringContainsString('/', $formattedDate);
    }

    #[Test]
    public function it_checks_if_order_has_nfe()
    {
        $orderWithNfe = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
            'nfe_number' => 'NF001',
        ]);

        $orderWithoutNfe = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC002',
            'customer_name' => 'Ana Costa',
            'seller_name' => 'Pedro Lima',
            'order_date' => now(),
        ]);

        $this->assertTrue($orderWithNfe->hasNfe());
        $this->assertFalse($orderWithoutNfe->hasNfe());
    }

    #[Test]
    public function it_checks_if_order_has_tracking_code()
    {
        $orderWithTracking = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
            'tracking_code' => 'TR001',
        ]);

        $orderWithoutTracking = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC002',
            'customer_name' => 'Ana Costa',
            'seller_name' => 'Pedro Lima',
            'order_date' => now(),
        ]);

        $this->assertTrue($orderWithTracking->hasTrackingCode());
        $this->assertFalse($orderWithoutTracking->hasTrackingCode());
    }

    #[Test]
    public function it_gets_latest_invoice()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF001',
            'invoice_pdf_url' => 'https://example.com/nf001.pdf',
            'issued_at' => now()->subHour(),
        ]);

        // Add a small delay to ensure different timestamps
        sleep(1);

        $latestInvoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF002',
            'invoice_pdf_url' => 'https://example.com/nf002.pdf',
            'issued_at' => now(),
        ]);

        $retrievedInvoice = $order->getLatestInvoice();

        $this->assertInstanceOf(Invoice::class, $retrievedInvoice);
        $this->assertEquals('NF002', $retrievedInvoice->invoice_number);
    }

    #[Test]
    public function it_gets_latest_label()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $firstLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now()->subHour(),
        ]);

        // Add a small delay to ensure different timestamps
        sleep(1);

        $latestLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET002',
            'status' => 'posted',
            'issued_at' => now(),
        ]);

        $retrievedLabel = $order->getLatestLabel();

        $this->assertInstanceOf(Label::class, $retrievedLabel);
        $this->assertEquals('ET002', $retrievedLabel->label_number);
    }

    #[Test]
    public function it_casts_order_date_to_datetime()
    {
        $orderDate = '2025-09-27 15:30:00';
        
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => $orderDate,
        ]);

        $this->assertInstanceOf(\DateTime::class, $order->order_date);
    }

    #[Test]
    public function it_returns_customer_full_name()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva Santos',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $this->assertEquals('João Silva Santos', $order->getCustomerFullNameAttribute());
    }
}
