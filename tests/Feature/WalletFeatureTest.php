<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TESTE 1 (Etapa 1): Registro cria usuário + carteira com saldo R$ 0,00 e retorna token Sanctum
     */
    public function test_etapa_1_registro_cria_usuario_e_carteira_com_saldo_zero(): void
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'João Gabriel',
            'email'    => 'joao@fintech.com',
            'password' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => ['user' => ['id', 'name', 'email', 'wallet' => ['balance']], 'token'],
            ]);

        $this->assertDatabaseHas('wallets', [
            'balance' => 0.00,
        ]);
    }

    /**
     * TESTE 2 (Etapa 1): Login autentica com credenciais válidas e barra credenciais inválidas
     */
    public function test_etapa_1_login_valida_credenciais_e_retorna_token(): void
    {
        $this->postJson('/api/register', [
            'name'     => 'João Gabriel',
            'email'    => 'joao@fintech.com',
            'password' => 'secret123',
        ]);

        $ok = $this->postJson('/api/login', [
            'email'    => 'joao@fintech.com',
            'password' => 'secret123',
        ]);
        $ok->assertOk()->assertJsonStructure(['data' => ['token']]);

        $falha = $this->postJson('/api/login', [
            'email'    => 'joao@fintech.com',
            'password' => 'senha-errada',
        ]);
        $falha->assertStatus(401);
    }

    /**
     * TESTE 3 (Etapa 2): Depósito adiciona saldo e cria transação do tipo 'credit'
     */
    public function test_etapa_2_deposito_atualiza_saldo_e_registra_historico_credito(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 0.00]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallet/deposit', [
            'amount'      => 250.50,
            'description' => 'Depósito PIX',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'credit')
            ->assertJsonPath('data.balance_after', 250.5);

        $this->assertSame(250.50, $user->wallet->fresh()->balance);
    }

    /**
     * TESTE 4 (Etapa 2): Saque com saldo suficiente desconta valor e registra transação 'debit'
     */
    public function test_etapa_2_saque_com_saldo_suficiente_atualiza_carteira(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 500.00]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallet/withdraw', [
            'amount'      => 150.00,
            'description' => 'Saque TED',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'debit')
            ->assertJsonPath('data.balance_after', 350);

        $this->assertSame(350.00, $user->wallet->fresh()->balance);
    }

    /**
     * TESTE 5 (Etapa 2 - Edge Case Crítico): Saque com saldo insuficiente falha e mantém saldo intacto
     */
    public function test_etapa_2_saque_com_saldo_insuficiente_retorna_erro_sem_alterar_saldo(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 100.00]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallet/withdraw', [
            'amount' => 150.00,
        ]);

        $response->assertStatus(422);
        $this->assertSame(100.00, $user->wallet->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * TESTE 6 (Etapa 2 - Edge Case): Bloqueia valores negativos, zero ou fracionados abaixo de R$ 0,01
     */
    public function test_etapa_2_bloqueia_valores_invalidos_ou_fracionados_abaixo_de_um_centavo(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 100.00]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wallet/deposit', ['amount' => 0])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wallet/withdraw', ['amount' => -10])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wallet/withdraw', ['amount' => 10.005])
            ->assertStatus(422);
    }

    /**
     * TESTE 7 (Etapa 3): Dashboard retorna saldo atual, resumo do mês e últimas 5 transações
     */
    public function test_etapa_3_dashboard_retorna_resumo_do_mes_e_ultimas_cinco_transacoes(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 0.00]);

        $this->actingAs($user, 'sanctum')->postJson('/api/wallet/deposit', ['amount' => 1000.00]);
        $this->actingAs($user, 'sanctum')->postJson('/api/wallet/withdraw', ['amount' => 250.00]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/wallet/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.balance', 750)
            ->assertJsonPath('data.month_deposits', 1000)
            ->assertJsonPath('data.month_withdrawals', 250);
    }

    /**
     * TESTE 8 (Etapa 4): Histórico paginado filtra por tipo ('credit' / 'debit') e período
     */
    public function test_etapa_4_historico_filtra_por_tipo_e_periodo_com_paginacao(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 0.00]);

        $this->actingAs($user, 'sanctum')->postJson('/api/wallet/deposit', ['amount' => 500.00]);
        $this->actingAs($user, 'sanctum')->postJson('/api/wallet/withdraw', ['amount' => 120.00]);

        $apenasDebitos = $this->actingAs($user, 'sanctum')->getJson('/api/wallet/transactions?type=debit');

        $apenasDebitos->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'debit');
    }

    /**
     * TESTE 9 (Isolamento): Depósito e saque de um usuário não afetam a carteira de outro usuário
     */
    public function test_isolamento_de_carteira_entre_usuarios_diferentes(): void
    {
        $userA = User::factory()->create();
        $userA->wallet()->create(['balance' => 100.00]);

        $userB = User::factory()->create();
        $userB->wallet()->create(['balance' => 200.00]);

        $this->actingAs($userB, 'sanctum')
            ->postJson('/api/wallet/deposit', ['amount' => 300.00])
            ->assertCreated()
            ->assertJsonPath('data.balance_after', 500);

        $this->actingAs($userB, 'sanctum')
            ->postJson('/api/wallet/withdraw', ['amount' => 50.00])
            ->assertCreated()
            ->assertJsonPath('data.balance_after', 450);

        $this->assertSame(100.00, $userA->wallet->fresh()->balance);
        $this->assertSame(450.00, $userB->wallet->fresh()->balance);
        $this->assertSame(0, $userA->wallet->transactions()->count());
        $this->assertSame(2, $userB->wallet->transactions()->count());
    }

    /**
     * TESTE 10 (Período e Paginação): Histórico filtra corretamente por start_date/end_date e pagina resultados
     */
    public function test_historico_filtra_por_periodo_e_pagina_resultados(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet()->create(['balance' => 300.00]);

        $antiga = $wallet->transactions()->create([
            'type'          => 'credit',
            'amount'        => 100.00,
            'balance_after' => 100.00,
            'description'   => 'Transação antiga',
        ]);
        $antiga->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->save();

        for ($i = 1; $i <= 3; $i++) {
            $t = $wallet->transactions()->create([
                'type'          => 'credit',
                'amount'        => 50.00,
                'balance_after' => 100.00 + ($i * 50.00),
                'description'   => "Depósito recente {$i}",
            ]);
            $t->forceFill(['created_at' => now()->subDays($i), 'updated_at' => now()->subDays($i)])->save();
        }

        $startDate = now()->subDays(5)->toDateString();
        $endDate = now()->toDateString();

        $pagina1 = $this->actingAs($user, 'sanctum')
            ->getJson("/api/wallet/transactions?start_date={$startDate}&end_date={$endDate}&per_page=2&page=1");

        $pagina1->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2);

        $pagina2 = $this->actingAs($user, 'sanctum')
            ->getJson("/api/wallet/transactions?start_date={$startDate}&end_date={$endDate}&per_page=2&page=2");

        $pagina2->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('data.0.description', 'Depósito recente 1');
    }

    /**
     * TESTE 11 (Segurança): Rotas da carteira exigem autenticação via Sanctum
     */
    public function test_rotas_da_carteira_exigem_autenticacao(): void
    {
        $this->getJson('/api/wallet/dashboard')->assertStatus(401);
        $this->postJson('/api/wallet/deposit', ['amount' => 100])->assertStatus(401);
        $this->postJson('/api/wallet/withdraw', ['amount' => 50])->assertStatus(401);
        $this->getJson('/api/wallet/transactions')->assertStatus(401);
    }

    /**
     * TESTE 12 (Segurança): Histórico valida filtros e limita per_page entre 1 e 100
     */
    public function test_historico_valida_filtros_e_limite_de_paginacao(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 0.00]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/wallet/transactions?type=invalid')
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/wallet/transactions?per_page=101')
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/wallet/transactions?start_date=16-11-2026')
            ->assertStatus(422);
    }

    /**
     * TESTE 13 (Segurança): Rate limiting bloqueia excesso de tentativas no login (429)
     */
    public function test_login_aplica_rate_limiting_apos_exceder_limite(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
                ->postJson('/api/login', [
                    'email'    => 'naoexiste@fintech.com',
                    'password' => 'errada',
                ])
                ->assertStatus(401);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/api/login', [
                'email'    => 'naoexiste@fintech.com',
                'password' => 'errada',
            ])
            ->assertStatus(429);
    }
}
