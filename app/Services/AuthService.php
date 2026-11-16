<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ============================================================================
 * ETAPA 1 DO PROJETO: AuthService (Autenticação + Criação Automática da Carteira)
 * ============================================================================
 * Para rodar os testes desta etapa:
 *   cd /home/joao-oliveira/Projects/fintech-wallet-pleno
 *   php artisan test --filter=test_etapa_1
 */
class AuthService
{
    /**
     *
     * MÉTODO 1: Registro de Usuário + Carteira Inicial R$ 0,00 (Escopo 2.1)
     *
     */
    public function register(string $name, string $email, string $password): array
    {
        return DB::transaction(function () use ($name, $email, $password) {
            $user = User::create ([
                'name'      => $name,
                'email'     => $email,
                'password'  => $password,
            ]);

            $user->wallet()->create([
                'balance' => 0.00,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user'  => $user->load('wallet'),
                'token' => $token
            ];
        });
    }
    /**
     *
     * MÉTODO 2: Login e Emissão de Token Sanctum (Escopo 2.1)
     *
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()
                    ->where('email', $email)
                    ->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            throw new DomainException('Credenciais inválidas.');
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        return [
            'user'  => $user->load('wallet'),
            'token' => $token,
        ];
    }

    /**
     *
     * MÉTODO 3: Logout e Revogação de Tokens (Escopo 2.1)
     *
     * */
    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }
}
