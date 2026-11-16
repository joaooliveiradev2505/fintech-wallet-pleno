<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case CREDIT = 'credit';
    case DEBIT  = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT => 'Crédito (Depósito)',
            self::DEBIT  => 'Débito (Saque)',
        };
    }
}
