<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function deposit(User $user, float $amount, string $description = 'Depósito'): Transaction
    {
        if ($amount < 0.01 || round($amount, 2) !== $amount) {
            throw new DomainException('O valor deve ser positivo e ter no máximo 2 casas decimais.');
        }
        return DB::transaction(function () use($user, $amount, $description) {
            $wallet = $user->wallet()->lockForUpdate()->firstOrFail();
            $newBalance = round($wallet->balance + $amount, 2);
            $wallet->balance = $newBalance;
            $wallet->save();
            return $wallet->transactions()->create([
                'type'          => TransactionType::CREDIT,
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'description'   => $description,

            ]); 
        });
    }


    public function withdraw(User $user, float $amount, string $description = 'Saque'): Transaction
    {
        if ($amount < 0.01 || round($amount, 2) !== $amount) {
            throw new DomainException('O valor deve ser positivo e ter no máximo 2 casas decimais.');
        }
        return DB::transaction(function () use($user, $amount, $description) {
            $wallet = $user->wallet()->lockForUpdate()->firstOrFail();
            if ($wallet->balance < $amount) {
                throw new DomainException('Saldo insuficiente.');
            }
            $newBalance = round($wallet->balance - $amount, 2);
            $wallet->balance = $newBalance;
            $wallet->save();
            return $wallet->transactions()->create([
                'type'          => TransactionType::DEBIT,
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'description'   => $description,

            ]);
        });
    }

    public function getDashboardSummary(User $user): array
    {
        $wallet = $user->wallet()->firstOrFail();
        $ultimosCinco = $wallet->transactions()
            ->latest('id')
            ->limit(5)
            ->get();
        $ganhosMensal = $wallet->transactions()
            ->where('type', TransactionType::CREDIT)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $resgatesMensal = $wallet->transactions()
            ->where('type', TransactionType::DEBIT)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        return [
            'balance'               => (float) $wallet->balance,
            'month_deposits'        => (float) $ganhosMensal,
            'month_withdrawals'     => (float) $resgatesMensal,
            'recent_transactions'   =>$ultimosCinco,
        ];
    }

    public function getTransactionsHistory(
        User $user,
        ?string $type = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $perPage = 10,
    ): LengthAwarePaginator {
        $wallet = $user->wallet()->firstOrFail();
        $query = $wallet->transactions()->latest('id');
        if ($type) {
            $query->where('type', $type);
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        return $query->paginate($perPage);
    }
}
