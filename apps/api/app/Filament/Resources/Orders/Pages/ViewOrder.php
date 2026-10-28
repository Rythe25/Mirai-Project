<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Actions\ApprovePaymentAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApprovePaymentAction::make(),
            Action::make('invoice')
                ->label('Download invoice')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url(fn (Order $record): string => url("/api/orders/{$record->public_id}/invoice"))
                ->openUrlInNewTab()
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Paid),
        ];
    }
}
