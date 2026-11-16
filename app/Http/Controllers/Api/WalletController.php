<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepositRequest;
use App\Http\Requests\TransactionsFilterRequest;
use App\Http\Requests\WithdrawRequest;
use App\Services\WalletService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $dados = $this->walletService->getDashboardSummary($request->user());

        return response()->json([
            'data' => $dados,
        ]);
    }

    public function deposit(DepositRequest $request): JsonResponse
    {
        try {
            $transacao = $this->walletService->deposit(
                user: $request->user(),
                amount: (float) $request->validated('amount'),
                description: $request->validated('description') ?? 'Depósito em conta',
            );

            return response()->json([
                'message' => 'Depósito realizado com sucesso.',
                'data'    => $transacao,
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function withdraw(WithdrawRequest $request): JsonResponse
    {
        try {
            $transacao = $this->walletService->withdraw(
                user: $request->user(),
                amount: (float) $request->validated('amount'),
                description: $request->validated('description') ?? 'Saque em conta',
            );

            return response()->json([
                'message' => 'Saque realizado com sucesso.',
                'data'    => $transacao,
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transactions(TransactionsFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $paginado = $this->walletService->getTransactionsHistory(
            user: $request->user(),
            type: $validated['type'] ?? null,
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null,
            perPage: (int) ($validated['per_page'] ?? 10),
        );

        return response()->json($paginado);
    }
}
