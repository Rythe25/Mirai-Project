<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\OrderReceipt;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Invoices\InvoicePdf;
use App\Services\Orders\PlaceOrder;
use App\Services\Payments\SyncPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payway.env' => 'sandbox']);
    }

    /** Order of 2 × $2.50 with a pending card payment. */
    private function pendingPayment(?string $email = 'bob@example.com'): Payment
    {
        $product = Product::factory()->create(['name' => 'Test Cable', 'sku' => 'TEST-CABLE-01', 'price' => '2.50', 'stock' => 5]);
        $order = app(PlaceOrder::class)->handle(
            ['name' => 'Bob', 'phone' => '012345678', 'email' => $email],
            [['product_id' => $product->id, 'qty' => 2]],
        );

        return $order->payments()->create([
            'method' => PaymentMethod::Card,
            'tran_id' => "MR{$order->id}T000000000000",
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'currency' => 'USD',
            'expires_at' => now()->addMinutes(6),
        ]);
    }

    private function paidOrder(?string $email = 'bob@example.com'): Order
    {
        $payment = $this->pendingPayment($email);
        app(SyncPayment::class)->markPaid($payment, '123456');

        return $payment->order->fresh();
    }

    public function test_a_paid_order_downloads_a_pdf_invoice(): void
    {
        Mail::fake();
        $order = $this->paidOrder();

        $response = $this->get("/api/orders/{$order->public_id}/invoice")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', "attachment; filename=\"Mirai-{$order->order_no}.pdf\"");

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_an_unpaid_order_has_no_invoice(): void
    {
        $order = $this->pendingPayment()->order;

        $this->getJson("/api/orders/{$order->public_id}/invoice")->assertStatus(409);
    }

    public function test_the_invoice_shows_the_order_lines_payment_and_sandbox_banner(): void
    {
        Mail::fake();
        $order = $this->paidOrder();

        $html = view('pdf.invoice', app(InvoicePdf::class)->data($order))->render();

        foreach ([$order->order_no, 'Bob', 'bob@example.com', 'Test Cable', 'TEST-CABLE-01', '5.00', 'Card via ABA PayWay', '123456', 'Sandbox test'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_paying_sends_one_receipt_email_with_the_pdf(): void
    {
        Mail::fake();
        $payment = $this->pendingPayment();

        app(SyncPayment::class)->markPaid($payment, '123456');
        app(SyncPayment::class)->markPaid($payment, '123456'); // duplicate confirmation

        Mail::assertSent(OrderReceipt::class, 1);
        Mail::assertSent(OrderReceipt::class, fn (OrderReceipt $mail) => $mail->hasTo('bob@example.com'));
        $this->assertDatabaseHas('notification_log', ['order_id' => $payment->order_id, 'channel' => 'receipt_email', 'status' => 'sent']);
    }

    public function test_the_receipt_attachment_is_a_pdf(): void
    {
        Mail::fake();
        $payment = $this->pendingPayment();
        app(SyncPayment::class)->markPaid($payment, '123456');

        Mail::assertSent(OrderReceipt::class, function (OrderReceipt $mail) use ($payment) {
            $mail->render(); // the markdown view must render

            $attachments = $mail->attachments();

            return count($attachments) === 1
                && $attachments[0]->as === "Mirai-{$payment->order->order_no}.pdf"
                && $attachments[0]->mime === 'application/pdf';
        });
    }

    public function test_no_email_is_sent_without_a_customer_email(): void
    {
        Mail::fake();
        $order = $this->paidOrder(email: null);

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('notification_log', ['order_id' => $order->id, 'channel' => 'receipt_email']);
        $this->assertSame('paid', $order->status->value);
    }
}
