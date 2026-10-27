<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Khqr = 'khqr';
    case Card = 'card';

    public function getLabel(): string
    {
        return match ($this) {
            self::Khqr => 'KHQR',
            self::Card => 'Card',
        };
    }
}
