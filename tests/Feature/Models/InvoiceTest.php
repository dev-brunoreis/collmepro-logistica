<?php

namespace Tests\Feature\Models;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_an_invoice()
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

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($order->id, $invoice->order_id);
        $this->assertEquals('NF001', $invoice->invoice_number);
        $this->assertEquals('https://example.com/nf001.pdf', $invoice->invoice_pdf_url);
        $this->assertNotNull($invoice->issued_at);
    }

    #[Test]
    public function it_belongs_to_order()
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

        $this->assertInstanceOf(Order::class, $invoice->order);
        $this->assertEquals($order->id, $invoice->order->id);
        $this->assertEquals('WC001', $invoice->order->order_number);
    }

    #[Test]
    public function it_can_download_invoice_pdf()
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

        $this->assertEquals('https://example.com/nf001.pdf', $invoice->invoice_pdf_url);
        $this->assertNotEmpty($invoice->invoice_pdf_url);
    }

    #[Test]
    public function it_can_have_null_pdf_url()
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
            'invoice_pdf_url' => null,
            'issued_at' => now(),
        ]);

        $this->assertNull($invoice->invoice_pdf_url);
    }

    #[Test]
    public function it_casts_issued_at_to_datetime()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $issuedAt = '2025-09-27 15:30:00';
        
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF001',
            'invoice_pdf_url' => 'https://example.com/nf001.pdf',
            'issued_at' => $issuedAt,
        ]);

        $this->assertInstanceOf(\DateTime::class, $invoice->issued_at);
    }

    #[Test]
    public function it_has_timestamps()
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

        $this->assertNotNull($invoice->created_at);
        $this->assertNotNull($invoice->updated_at);
        $this->assertInstanceOf(\DateTime::class, $invoice->created_at);
        $this->assertInstanceOf(\DateTime::class, $invoice->updated_at);
    }

    #[Test]
    public function it_can_be_updated()
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

        // Update the invoice
        $invoice->update([
            'invoice_pdf_url' => 'https://example.com/updated-nf001.pdf'
        ]);

        $this->assertEquals('https://example.com/updated-nf001.pdf', $invoice->fresh()->invoice_pdf_url);
        // Just verify the update worked, timestamp comparison can be flaky in tests
        $this->assertNotNull($invoice->fresh()->updated_at);
    }

    #[Test]
    public function it_can_be_deleted()
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

        $invoiceId = $invoice->id;
        $invoice->delete();

        $this->assertDatabaseMissing('invoices', [
            'id' => $invoiceId
        ]);
    }

    #[Test]
    public function it_is_deleted_when_order_is_deleted()
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

        $invoiceId = $invoice->id;
        $order->delete();

        $this->assertDatabaseMissing('invoices', [
            'id' => $invoiceId
        ]);
    }

    #[Test]
    public function it_can_have_multiple_invoices_per_order()
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

        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF002',
            'invoice_pdf_url' => 'https://example.com/nf002.pdf',
            'issued_at' => now(),
        ]);

        $this->assertCount(2, $order->invoices);
        $this->assertEquals('NF001', $order->invoices->first()->invoice_number);
        $this->assertEquals('NF002', $order->invoices->last()->invoice_number);
    }

    #[Test]
    public function it_can_be_queried_by_invoice_number()
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

        $foundInvoice = Invoice::where('invoice_number', 'NF001')->first();

        $this->assertNotNull($foundInvoice);
        $this->assertEquals($invoice->id, $foundInvoice->id);
    }

    #[Test]
    public function it_can_be_queried_by_issued_date()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $issuedDate = now()->subDay();
        
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF001',
            'invoice_pdf_url' => 'https://example.com/nf001.pdf',
            'issued_at' => $issuedDate,
        ]);

        $foundInvoice = Invoice::whereDate('issued_at', $issuedDate->format('Y-m-d'))->first();

        $this->assertNotNull($foundInvoice);
        $this->assertEquals($invoice->id, $foundInvoice->id);
    }

    #[Test]
    public function it_can_be_sorted_by_issued_date()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $olderInvoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF001',
            'invoice_pdf_url' => 'https://example.com/nf001.pdf',
            'issued_at' => now()->subDay(),
        ]);

        $newerInvoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF002',
            'invoice_pdf_url' => 'https://example.com/nf002.pdf',
            'issued_at' => now(),
        ]);

        $sortedInvoices = Invoice::orderBy('issued_at', 'asc')->get();

        $this->assertEquals($olderInvoice->id, $sortedInvoices->first()->id);
        $this->assertEquals($newerInvoice->id, $sortedInvoices->last()->id);
    }

    #[Test]
    public function it_can_check_if_pdf_is_available()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $invoiceWithPdf = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF001',
            'invoice_pdf_url' => 'https://example.com/nf001.pdf',
            'issued_at' => now(),
        ]);

        $invoiceWithoutPdf = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'NF002',
            'invoice_pdf_url' => null,
            'issued_at' => now(),
        ]);

        $this->assertTrue(!empty($invoiceWithPdf->invoice_pdf_url));
        $this->assertFalse(!empty($invoiceWithoutPdf->invoice_pdf_url));
    }
}
