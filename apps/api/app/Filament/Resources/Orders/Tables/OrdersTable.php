<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Actions\ApprovePaymentAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_no')->label('Order')->searchable()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('customer_name')->label('Customer')->searchable(),
                TextColumn::make('customer_phone')->label('Phone')->searchable(),
                TextColumn::make('total')->money('USD')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                ApprovePaymentAction::make(),
            ]);
    }
}
