<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\SyncPayment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Sandbox only: ABA sandbox QRs can't be paid, so staff confirm them by hand to test the paid flow. */
class ApprovePaymentAction
{
    public static function make(): Action
    {
        return Action::make('approvePayment')
            ->label('Mark as paid (sandbox)')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Sandbox testing only. Marks the latest KHQR as paid without ABA confirming it and reduces stock.')
            ->visible(fn (Order $record): bool => config('services.payway.env') === 'sandbox'
                && $record->status === OrderStatus::Pending
                && $record->payments()->exists())
            ->action(function (Order $record): void {
                $payment = app(SyncPayment::class)->markPaid(
                    $record->payments()->latest('id')->firstOrFail(),
                    'MANUAL',
                    auth()->user(),
                );

                $payment->status === PaymentStatus::Approved
                    ? Notification::make()->title("{$record->order_no} marked as paid")->success()->send()
                    : Notification::make()->title("Latest payment is {$payment->status->value}; nothing changed")->warning()->send();
            });
    }
}
