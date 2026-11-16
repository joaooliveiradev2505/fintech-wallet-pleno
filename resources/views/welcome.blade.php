<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fintech Wallet — Carteira Digital (Laravel 11 + Vue 3 + Pinia + Vue Router)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://unpkg.com/vue-router@4/dist/vue-router.global.js"></script>
    <script src="https://unpkg.com/vue-demi"></script>
    <script src="https://unpkg.com/pinia@2.1.7/dist/pinia.iife.js"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans">
    <div id="app" class="max-w-6xl mx-auto px-4 py-8">
        <!-- Navbar Superior -->
        <header class="border-b border-slate-800 pb-5 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 mb-1">
                    💳 Fintech Wallet · Laravel 11 + Sanctum + Vue 3 + Pinia + Vue Router
                </span>
                <h1 class="text-2xl font-bold text-white">Carteira Digital Pessoal</h1>
            </div>

            <nav v-if="store.isAuthenticated" class="flex items-center gap-3">
                <router-link to="/dashboard"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition"
                    :class="$route.path === '/dashboard' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'">
                    📊 Dashboard & Operações
                </router-link>
                <router-link to="/transactions"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition"
                    :class="$route.path === '/transactions' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'">
                    📜 Histórico Completo
                </router-link>
                <button @click="fazerLogout"
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-rose-900/50 text-rose-300 border border-rose-700/50 hover:bg-rose-800/60 transition">
                    Sair (@{{ store.user?.name }})
                </button>
            </nav>
        </header>

        <!-- Feedback Global -->
        <div v-if="store.feedback.text"
            class="mb-6 p-4 rounded-xl border text-sm font-medium flex items-center justify-between"
            :class="store.feedback.type === 'error' ? 'bg-rose-950/50 border-rose-700 text-rose-300' : 'bg-emerald-950/50 border-emerald-700 text-emerald-300'">
            <span>@{{ store.feedback.text }}</span>
            <button @click="store.clearFeedback()" class="text-xs underline ml-4">Fechar</button>
        </div>

        <!-- Vue Router View -->
        <router-view></router-view>
    </div>

    <script>
        const { createApp, ref, reactive, computed, onMounted } = Vue;
        const { createRouter, createWebHashHistory, useRouter } = VueRouter;
        const { createPinia, defineStore } = Pinia;

        /**
         * =========================================================================
         * PINIA STORE — Gerenciamento de Estado Global (Auth + Carteira + Histórico)
         * =========================================================================
         */
        const useWalletStore = defineStore('wallet', () => {
            const token = ref(localStorage.getItem('wallet_token') || '');
            const user = ref(JSON.parse(localStorage.getItem('wallet_user') || 'null'));
            const loading = ref(false);
            const feedback = reactive({ text: '', type: 'success' });

            const dashboard = reactive({
                balance: 0,
                month_deposits: 0,
                month_withdrawals: 0,
                recent_transactions: [],
            });

            const pagination = reactive({
                data: [],
                current_page: 1,
                last_page: 1,
                total: 0,
            });

            const isAuthenticated = computed(() => Boolean(token.value));

            function setFeedback(text, type = 'success') {
                feedback.text = text;
                feedback.type = type;
            }

            function clearFeedback() {
                feedback.text = '';
            }

            function headers() {
                return {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token.value}`,
                };
            }

            function formatCurrency(value) {
                return Number(value || 0).toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL',
                });
            }

            function extractErrorMessage(json, fallback) {
                if (json?.errors) {
                    const firstField = Object.values(json.errors)[0];
                    if (Array.isArray(firstField) && firstField.length > 0) {
                        return firstField[0];
                    }
                }
                return json?.message || fallback;
            }

            function resetSession() {
                token.value = '';
                user.value = null;
                dashboard.balance = 0;
                dashboard.month_deposits = 0;
                dashboard.month_withdrawals = 0;
                dashboard.recent_transactions = [];
                pagination.data = [];
                pagination.current_page = 1;
                pagination.last_page = 1;
                pagination.total = 0;
                localStorage.removeItem('wallet_token');
                localStorage.removeItem('wallet_user');
            }

            async function loginOrRegister(endpoint, payload) {
                loading.value = true;
                clearFeedback();
                try {
                    const resp = await fetch(`/api/${endpoint}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const json = await resp.json();
                    if (!resp.ok) {
                        throw new Error(extractErrorMessage(json, 'Falha na autenticação.'));
                    }
                    token.value = json.data.token;
                    user.value = json.data.user;
                    localStorage.setItem('wallet_token', token.value);
                    localStorage.setItem('wallet_user', JSON.stringify(user.value));
                    setFeedback(json.message, 'success');
                    return true;
                } catch (err) {
                    setFeedback(err.message, 'error');
                    return false;
                } finally {
                    loading.value = false;
                }
            }

            async function logout() {
                try {
                    if (token.value) {
                        await fetch('/api/logout', { method: 'POST', headers: headers() });
                    }
                } finally {
                    resetSession();
                    clearFeedback();
                }
            }

            async function fetchDashboard() {
                if (!token.value) return;
                const resp = await fetch('/api/wallet/dashboard', { headers: headers() });
                if (resp.status === 401) {
                    resetSession();
                    return;
                }
                if (resp.ok) {
                    const json = await resp.json();
                    Object.assign(dashboard, json.data);
                }
            }

            async function operate(type, amount, description) {
                loading.value = true;
                clearFeedback();
                try {
                    const resp = await fetch(`/api/wallet/${type}`, {
                        method: 'POST',
                        headers: headers(),
                        body: JSON.stringify({ amount, description }),
                    });
                    if (resp.status === 401) {
                        resetSession();
                        throw new Error('Sessão expirada. Faça login novamente.');
                    }
                    const json = await resp.json();
                    if (!resp.ok) {
                        throw new Error(extractErrorMessage(json, 'Erro ao processar operação.'));
                    }
                    setFeedback(json.message, 'success');
                    await fetchDashboard();
                    return true;
                } catch (err) {
                    setFeedback(err.message, 'error');
                    return false;
                } finally {
                    loading.value = false;
                }
            }

            async function fetchTransactions(filters = {}) {
                if (!token.value) return;
                loading.value = true;
                try {
                    const params = new URLSearchParams();
                    if (filters.type) params.append('type', filters.type);
                    if (filters.start_date) params.append('start_date', filters.start_date);
                    if (filters.end_date) params.append('end_date', filters.end_date);
                    if (filters.page) params.append('page', filters.page);

                    const resp = await fetch(`/api/wallet/transactions?${params.toString()}`, { headers: headers() });
                    if (resp.status === 401) {
                        resetSession();
                        return;
                    }
                    if (resp.ok) {
                        const json = await resp.json();
                        pagination.data = json.data || [];
                        pagination.current_page = json.current_page || 1;
                        pagination.last_page = json.last_page || 1;
                        pagination.total = json.total || 0;
                    }
                } finally {
                    loading.value = false;
                }
            }

            return {
                token, user, loading, feedback, dashboard, pagination, isAuthenticated,
                formatCurrency, setFeedback, clearFeedback, loginOrRegister, logout, fetchDashboard, operate, fetchTransactions,
            };
        });

        /**
         * =========================================================================
         * VIEW 1: Autenticação (Login e Registro)
         * =========================================================================
         */
        const AuthView = {
            template: `
                <div class="max-w-md mx-auto bg-slate-900 border border-slate-800 rounded-xl p-6">
                    <div class="flex gap-2 mb-6">
                        <button @click="alternarModo('login')"
                            :class="modo === 'login' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400'"
                            class="flex-1 py-2 rounded-lg text-sm font-semibold">Entrar</button>
                        <button @click="alternarModo('register')"
                            :class="modo === 'register' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400'"
                            class="flex-1 py-2 rounded-lg text-sm font-semibold">Criar Conta (R$ 0,00)</button>
                    </div>

                    <form @submit.prevent="submeter" class="space-y-4">
                        <div v-if="modo === 'register'">
                            <label class="block text-xs text-slate-400 mb-1">Nome Completo</label>
                            <input v-model="form.name" type="text" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">E-mail</label>
                            <input v-model="form.email" type="email" required
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Senha</label>
                            <input v-model="form.password" type="password" required minlength="6"
                                class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                        </div>
                        <button type="submit" :disabled="store.loading"
                            class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg text-sm">
                            @{{ store.loading ? 'Processando...' : (modo === 'login' ? 'Acessar Carteira' : 'Registrar Agora') }}
                        </button>
                    </form>
                </div>
            `,
            setup() {
                const store = useWalletStore();
                const router = useRouter();
                const modo = ref('login');
                const form = reactive({ name: '', email: 'teste@fintech.com', password: 'password123' });

                function alternarModo(novoModo) {
                    modo.value = novoModo;
                    store.clearFeedback();
                    if (novoModo === 'register') {
                        form.name = '';
                        form.email = '';
                        form.password = '';
                    } else {
                        form.email = 'teste@fintech.com';
                        form.password = 'password123';
                    }
                }

                async function submeter() {
                    const ok = await store.loginOrRegister(modo.value, form);
                    if (ok) router.push('/dashboard');
                }

                return { store, modo, form, alternarModo, submeter };
            }
        };

        /**
         * =========================================================================
         * VIEW 2: Dashboard + Formulários de Depósito e Saque
         * =========================================================================
         */
        const DashboardView = {
            template: `
                <div class="space-y-6">
                    <!-- 3 Cards de Resumo (Escopo 2.4) -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                            <span class="text-xs text-slate-400 uppercase">Saldo Atual Disponível</span>
                            <div class="text-3xl font-bold text-white mt-1">
                                @{{ store.formatCurrency(store.dashboard.balance) }}
                            </div>
                        </div>
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                            <span class="text-xs text-slate-400 uppercase">Total Depositado (Mês Atual)</span>
                            <div class="text-2xl font-bold text-emerald-400 mt-1">
                                + @{{ store.formatCurrency(store.dashboard.month_deposits) }}
                            </div>
                        </div>
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                            <span class="text-xs text-slate-400 uppercase">Total Sacado (Mês Atual)</span>
                            <div class="text-2xl font-bold text-rose-400 mt-1">
                                - @{{ store.formatCurrency(store.dashboard.month_withdrawals) }}
                            </div>
                        </div>
                    </div>

                    <!-- Operações: Depósito e Saque com validação reativa (Escopo 2.5) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
                            <h2 class="text-lg font-bold text-emerald-400 mb-4">➕ Realizar Depósito</h2>
                            <form @submit.prevent="depositar" class="space-y-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Valor do Depósito (R$)</label>
                                    <input v-model.number="depAmount" type="number" step="0.01" min="0.01" required
                                        class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Descrição (Opcional)</label>
                                    <input v-model="depDesc" type="text" placeholder="Ex: Depósito via PIX"
                                        class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                                </div>
                                <button type="submit" :disabled="store.loading || depAmount < 0.01"
                                    class="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg text-sm">
                                    Confirmar Depósito
                                </button>
                            </form>
                        </div>

                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
                            <h2 class="text-lg font-bold text-rose-400 mb-4">➖ Realizar Saque</h2>
                            <form @submit.prevent="sacar" class="space-y-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Valor do Saque (R$)</label>
                                    <input v-model.number="witAmount" type="number" step="0.01" min="0.01" required
                                        class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                                </div>
                                <!-- Validação Reativa de Saldo Insuficiente no Frontend (Escopo 2.5) -->
                                <div v-if="saldoInsuficienteNoFront" class="p-3 rounded-lg bg-rose-950/60 border border-rose-700 text-rose-300 text-xs">
                                    ⚠️ Saldo insuficiente! Seu saldo atual é de @{{ store.formatCurrency(store.dashboard.balance) }}.
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Descrição (Opcional)</label>
                                    <input v-model="witDesc" type="text" placeholder="Ex: Saque TED"
                                        class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                                </div>
                                <button type="submit" :disabled="store.loading || witAmount < 0.01 || saldoInsuficienteNoFront"
                                    class="w-full bg-rose-600 hover:bg-rose-500 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg text-sm">
                                    Confirmar Saque
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Últimas 5 Transações (Escopo 2.4) -->
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
                        <h3 class="text-lg font-bold text-white mb-4">🕒 Últimas 5 Movimentações</h3>
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-slate-800 text-xs text-slate-400 uppercase">
                                    <th class="py-2 px-2">Data/Hora</th>
                                    <th class="py-2 px-2">Tipo</th>
                                    <th class="py-2 px-2">Descrição</th>
                                    <th class="py-2 px-2">Valor</th>
                                    <th class="py-2 px-2">Saldo Após</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/70">
                                <tr v-if="store.dashboard.recent_transactions.length === 0">
                                    <td colspan="5" class="py-6 text-center text-slate-400">Nenhuma movimentação registrada ainda.</td>
                                </tr>
                                <tr v-for="t in store.dashboard.recent_transactions" :key="t.id">
                                    <td class="py-2.5 px-2 text-xs text-slate-400">@{{ new Date(t.created_at).toLocaleString('pt-BR') }}</td>
                                    <td class="py-2.5 px-2">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold"
                                            :class="t.type === 'credit' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'">
                                            @{{ t.type === 'credit' ? 'CRÉDITO' : 'DÉBITO' }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-2 text-slate-200">@{{ t.description }}</td>
                                    <td class="py-2.5 px-2 font-semibold" :class="t.type === 'credit' ? 'text-emerald-400' : 'text-rose-400'">
                                        @{{ store.formatCurrency(t.amount) }}
                                    </td>
                                    <td class="py-2.5 px-2 text-white">@{{ store.formatCurrency(t.balance_after) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `,
            setup() {
                const store = useWalletStore();
                const depAmount = ref(100);
                const depDesc = ref('Depósito via PIX');
                const witAmount = ref(50);
                const witDesc = ref('Saque para Conta');

                const saldoInsuficienteNoFront = computed(() => {
                    return Number(witAmount.value || 0) > Number(store.dashboard.balance || 0);
                });

                onMounted(() => store.fetchDashboard());

                async function depositar() {
                    await store.operate('deposit', depAmount.value, depDesc.value);
                }

                async function sacar() {
                    if (saldoInsuficienteNoFront.value) return;
                    await store.operate('withdraw', witAmount.value, witDesc.value);
                }

                return { store, depAmount, depDesc, witAmount, witDesc, saldoInsuficienteNoFront, depositar, sacar };
            }
        };

        /**
         * =========================================================================
         * VIEW 3: Histórico Paginado com Filtros de Tipo e Data (Escopo 2.3 & 2.5)
         * =========================================================================
         */
        const TransactionsView = {
            template: `
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-6">
                    <div class="flex flex-col md:flex-row md:items-end gap-4 border-b border-slate-800 pb-5">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Tipo</label>
                            <select v-model="filters.type" class="bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                                <option value="">Todos</option>
                                <option value="credit">Crédito (Depósitos)</option>
                                <option value="debit">Débito (Saques)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Data Inicial</label>
                            <input v-model="filters.start_date" type="date" class="bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Data Final</label>
                            <input v-model="filters.end_date" type="date" class="bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                        </div>
                        <button @click="filtrar(1)" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-sm font-semibold text-white">
                            🔍 Filtrar Histórico
                        </button>
                        <button @click="limparFiltros" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm font-semibold text-slate-300">
                            Limpar
                        </button>
                    </div>

                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-800 text-xs text-slate-400 uppercase">
                                <th class="py-2 px-2">Data/Hora</th>
                                <th class="py-2 px-2">Tipo</th>
                                <th class="py-2 px-2">Descrição</th>
                                <th class="py-2 px-2">Valor</th>
                                <th class="py-2 px-2">Saldo Após Operação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/70">
                            <tr v-if="store.loading">
                                <td colspan="5" class="py-6 text-center text-slate-400">Carregando movimentações...</td>
                            </tr>
                            <tr v-else-if="store.pagination.data.length === 0">
                                <td colspan="5" class="py-6 text-center text-slate-400">Nenhuma transação encontrada para os filtros informados.</td>
                            </tr>
                            <tr v-else v-for="t in store.pagination.data" :key="t.id">
                                <td class="py-2.5 px-2 text-xs text-slate-400">@{{ new Date(t.created_at).toLocaleString('pt-BR') }}</td>
                                <td class="py-2.5 px-2">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold"
                                        :class="t.type === 'credit' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'">
                                        @{{ t.type === 'credit' ? 'CRÉDITO' : 'DÉBITO' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-2 text-slate-200">@{{ t.description }}</td>
                                <td class="py-2.5 px-2 font-semibold" :class="t.type === 'credit' ? 'text-emerald-400' : 'text-rose-400'">
                                    @{{ store.formatCurrency(t.amount) }}
                                </td>
                                <td class="py-2.5 px-2 text-white">@{{ store.formatCurrency(t.balance_after) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Paginação -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-800 text-xs text-slate-400">
                        <span>Total de registros: @{{ store.pagination.total }}</span>
                        <div class="flex gap-2">
                            <button @click="filtrar(store.pagination.current_page - 1)"
                                :disabled="store.pagination.current_page <= 1"
                                class="px-3 py-1.5 rounded bg-slate-800 disabled:opacity-40 text-white">Anterior</button>
                            <span class="px-3 py-1.5">Página @{{ store.pagination.current_page }} de @{{ store.pagination.last_page }}</span>
                            <button @click="filtrar(store.pagination.current_page + 1)"
                                :disabled="store.pagination.current_page >= store.pagination.last_page"
                                class="px-3 py-1.5 rounded bg-slate-800 disabled:opacity-40 text-white">Próxima</button>
                        </div>
                    </div>
                </div>
            `,
            setup() {
                const store = useWalletStore();
                const filters = reactive({ type: '', start_date: '', end_date: '', page: 1 });

                function filtrar(page = 1) {
                    filters.page = page;
                    store.fetchTransactions(filters);
                }

                function limparFiltros() {
                    filters.type = '';
                    filters.start_date = '';
                    filters.end_date = '';
                    filtrar(1);
                }

                onMounted(() => filtrar(1));

                return { store, filters, filtrar, limparFiltros };
            }
        };

        const routes = [
            { path: '/', redirect: '/dashboard' },
            { path: '/login', component: AuthView },
            { path: '/dashboard', component: DashboardView },
            { path: '/transactions', component: TransactionsView },
        ];

        const router = createRouter({
            history: createWebHashHistory(),
            routes,
        });

        const pinia = createPinia();
        const app = createApp({
            setup() {
                const store = useWalletStore();
                async function fazerLogout() {
                    await store.logout();
                    router.push('/login');
                }
                return { store, fazerLogout };
            }
        });

        app.use(pinia);

        router.beforeEach((to) => {
            const store = useWalletStore();
            if (to.path !== '/login' && !store.isAuthenticated) return '/login';
            if (to.path === '/login' && store.isAuthenticated) return '/dashboard';
        });

        app.use(router);
        app.mount('#app');
    </script>
</body>
</html>
