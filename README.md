# Fintech Wallet — Carteira Digital com Depósitos e Saques

MVP de carteira digital pessoal desenvolvida com **Laravel 11**, **Laravel Sanctum**, **Vue.js 3 (Composition API)**, **Pinia**, **Vue Router** e **TailwindCSS**, permitindo que usuários gerenciem seu saldo com depósitos, saques, resumo mensal no dashboard e histórico completo de movimentações com filtros e paginação.

---

## 🔗 Links do Projeto

- **Repositório GitHub:** https://github.com/joaooliveiradev2505/fintech-wallet-pleno
- **Deploy Público:** https://fintech-wallet-pleno.onrender.com

---

## 🔐 Credenciais de Teste (Usuário Seed)

Ao rodar as migrations com seeders, um usuário de demonstração estará disponível:

- **E-mail:** `teste@fintech.com`
- **Senha:** `password123`
- **Saldo inicial:** `R$ 1.850,00` (com 1 depósito de R$ 2.500,00 e 1 saque de R$ 650,00)

---

## 🛠️ Decisões Técnicas e Arquitetura

- **Controllers Finos + Camada de Service (`app/Services/`):** Regras de negócio de autenticação (`AuthService`) e operações financeiras (`WalletService`) isoladas da camada HTTP.
- **Validação via Form Requests (`app/Http/Requests/`):** Padronização de validação e mensagens de erro (`422`) para registro, login, depósito, saque e filtros/paginação do histórico (`TransactionsFilterRequest`, limitando `per_page` entre `1` e `100`).
- **Integridade e Atomicidade Financeira:**
  - Colunas monetárias armazenadas com `DECIMAL(15, 2)`.
  - Operações de depósito e saque executadas dentro de `DB::transaction()` com bloqueio pessimista (`lockForUpdate()`) na carteira do usuário autenticado para evitar *race conditions*.
  - Bloqueio de valores menores que `R$ 0,01`, valores negativos ou frações abaixo de 1 centavo (`decimal:0,2`).
  - Tipagem de movimentações via Backed Enum (`App\Enums\TransactionType`: `credit` / `debit`).
- **Autenticação e Segurança:**
  - Rotas da carteira protegidas com **Laravel Sanctum** (`auth:sanctum`), criando automaticamente a carteira com saldo `R$ 0,00` no registro.
  - Rate limiting (`throttle:10,1`) aplicado em `/api/register` e `/api/login`.
- **Frontend SPA em Vue.js 3:** Construído em `resources/views/welcome.blade.php` com Composition API, gerenciamento de estado global via **Pinia**, navegação protegida via **Vue Router** e validação reativa de saldo insuficiente no formulário de saque.

---

## 📋 Pré-requisitos

- **PHP:** `>= 8.2` (extensões `pdo_mysql` ou `pdo_pgsql` / `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`)
- **Composer:** `>= 2.x`
- **Node.js & NPM:** `>= 18.x` (opcional para build de assets Vite)
- **Banco de Dados:** MySQL `8.0+` ou PostgreSQL `14+`

---

## 🚀 Como Rodar Localmente

### 1. Clonar o repositório e instalar dependências
```bash
git clone https://github.com/joaooliveiradev2505/fintech-wallet-pleno.git
cd fintech-wallet-pleno

composer install
npm install
```

### 2. Configurar variáveis de ambiente (`.env`)
```bash
cp .env.example .env
php artisan key:generate
```

Configure a conexão com o banco de dados no arquivo `.env` (exemplo com MySQL):
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fintech_wallet
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

### 3. Executar Migrations e Seeders
```bash
php artisan migrate --seed
```

### 4. Iniciar o Servidor (Backend + Frontend)
```bash
php artisan serve
```
*(Opcional, caso utilize o Vite em desenvolvimento: `npm run dev` em outro terminal. A SPA Vue 3 já é servida diretamente por `php artisan serve`.)*

Acesse a aplicação em: **http://127.0.0.1:8000**

---

## 🧪 Testes Automatizados

Os testes utilizam SQLite em memória (configurado em `phpunit.xml`) e cobrem registro/criação automática da carteira, login, depósito, saque, saldo insuficiente, valores inválidos/fracionados, isolamento entre carteiras de usuários distintos, dashboard, histórico com filtros de tipo/período, paginação, proteção de rotas e rate limiting.

```bash
php artisan test
```

---

## ☁️ Deploy (Render / Docker)

O repositório já inclui `Dockerfile`, `docker/start.sh` e `render.yaml` prontos para deploy:
- **Render (Blueprint):** conecte o repositório em **New → Blueprint**; o `render.yaml` provisiona o serviço Web Docker e o banco PostgreSQL, executando `php artisan migrate --force` e `php artisan db:seed --force` na inicialização.
- **Railway / Fly.io:** compatível via `Dockerfile` (extensões `pdo_mysql` e `pdo_pgsql` instaladas).

---

## 📡 Endpoints da API RESTful

| Método | Rota | Autenticação | Descrição |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/register` | Pública (`throttle:10,1`) | Registra usuário e cria carteira com saldo `R$ 0,00` |
| `POST` | `/api/login` | Pública (`throttle:10,1`) | Autentica e retorna token Sanctum |
| `POST` | `/api/logout` | `Bearer Token` | Revoga os tokens do usuário autenticado |
| `GET` | `/api/wallet/dashboard` | `Bearer Token` | Saldo atual, totais do mês e últimas 5 transações |
| `POST` | `/api/wallet/deposit` | `Bearer Token` | Realiza depósito atômico na carteira |
| `POST` | `/api/wallet/withdraw` | `Bearer Token` | Realiza saque atômico validando saldo suficiente |
| `GET` | `/api/wallet/transactions` | `Bearer Token` | Histórico paginado com filtros (`type`, `start_date`, `end_date`, `per_page`) |
