<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'teste@fintech.com'],
            [
                'name'     => 'João Gabriel (Usuário Demo)',
                'password' => Hash::make('password123'),
            ]
        );

        $wallet = $user->wallet()->updateOrCreate(
            ['user_id' => $user->id],
            ['balance' => 1850.00]
        );

        if ($wallet->transactions()->count() === 0) {
            $wallet->transactions()->createMany([
                [
                    'type'          => TransactionType::CREDIT,
                    'amount'        => 2500.00,
                    'balance_after' => 2500.00,
                    'description'   => 'Depósito Inicial via PIX',
                ],
                [
                    'type'          => TransactionType::DEBIT,
                    'amount'        => 650.00,
                    'balance_after' => 1850.00,
                    'description'   => 'Saque para Conta Corrente',
                ],
            ]);
        }
    }
}
