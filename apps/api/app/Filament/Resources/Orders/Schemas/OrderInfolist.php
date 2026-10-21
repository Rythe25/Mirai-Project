<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('order_no')->label('Order'),
                TextEntry::make('status')->badge(),
                TextEntry::make('total')->money('USD'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('paid_at')->dateTime()->placeholder('Not paid'),
                TextEntry::make('public_id')->label('Public ID')->copyable(),
            ]),
            Section::make('Customer')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('customer_name')->label('Name'),
                TextEntry::make('customer_phone')->label('Phone'),
                TextEntry::make('customer_email')->label('Email')->placeholder('—'),
            ]),
            Section::make('Items')->columnSpanFull()->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('product_name')->label('Product'),
                    TextEntry::make('unit_price')->money('USD'),
                    TextEntry::make('qty'),
                    TextEntry::make('line_total')->money('USD'),
                ]),
            ]),
        ]);
    }
}
