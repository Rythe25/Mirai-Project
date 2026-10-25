<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\PlaceOrder;
use App\Services\Payments\SyncPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManualApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payway.env' => 'sandbox']);
        $this->staff = User::factory()->create(['name' => 'Test Staff']);
    }

    /** Order of 2 × $2.50 with an open KHQR; stock 5. */
    private function pendingPayment(array $attributes = []): Payment
    {
        $this->product = Product::factory()->create(['price' => '2.50', 'stock' => 5]);
        $order = app(PlaceOrder::class)->handle(
            ['name' => 'Test Buyer', 'phone' => '012345678'],
            [['product_id' => $this->product->id, 'qty' => 2]],
        );

        return $order->payments()->create($attributes + [
            'tran_id' => "MR{$order->id}T000000000000",
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'currency' => 'USD',
            'expires_at' => now()->addMinutes(6),
        ]);
    }

    public function test_staff_can_mark_a_sandbox_payment_paid(): void
    {
        $payment = $this->pendingPayment();

        $this->actingAs($this->staff);
        Livewire::test(ViewOrder::class, ['record' => $payment->order->getRouteKey()])
            ->callAction('approvePayment')
            ->assertNotified();

        $this->assertSame('paid', $payment->order->fresh()->status->value);
        $this->assertSame(3, $this->product->fresh()->stock);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
            'apv' => 'MANUAL',
            'confirmed_by' => $this->staff->id,
        ]);
    }

    public function test_button_is_hidden_outside_the_sandbox(): void
    {
        config(['services.payway.env' => 'production']);
        $payment = $this->pendingPayment();

        $this->actingAs($this->staff);
        Livewire::test(ViewOrder::class, ['record' => $payment->order->getRouteKey()])
            ->assertActionHidden('approvePayment');
    }

    public function test_staff_can_approve_an_expired_qr(): void
    {
        $payment = $this->pendingPayment(['status' => PaymentStatus::Expired, 'expires_at' => now()->subMinute()]);

        app(SyncPayment::class)->markPaid($payment, 'MANUAL', $this->staff);

        $this->assertSame('paid', $payment->order->fresh()->status->value);
    }

    public function test_approving_twice_reduces_stock_once(): void
    {
        $payment = $this->pendingPayment();

        app(SyncPayment::class)->markPaid($payment, 'MANUAL', $this->staff);
        app(SyncPayment::class)->markPaid($payment, 'MANUAL', $this->staff);

        $this->assertSame(3, $this->product->fresh()->stock);
    }
}
