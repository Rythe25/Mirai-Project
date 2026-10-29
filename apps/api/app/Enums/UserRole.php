<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Backoffice roles. A user without a role can't open the backoffice. */
enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Owner = 'owner';
    case Employee = 'employee';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
