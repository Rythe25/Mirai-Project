<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, ?string $state, Set $set) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->alphaDash()
                ->unique(ignoreRecord: true),
            TextInput::make('sku')
                ->label('SKU')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true),
            TextInput::make('price')
                ->label('Price (USD)')
                ->required()
                ->numeric()
                ->minValue(0.01)
                ->step(0.01)
                ->prefix('$'),
            TextInput::make('stock')
                ->required()
                ->integer()
                ->minValue(0)
                ->default(0),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            Textarea::make('description')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }
}
