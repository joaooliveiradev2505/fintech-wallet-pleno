<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $resultado = $this->authService->register(
            name: (string) $request->validated('name'),
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
        );

        return response()->json([
            'message' => 'Usuário registrado e carteira criada com sucesso.',
            'data'    => $resultado,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $resultado = $this->authService->login(
                email: (string) $request->validated('email'),
                password: (string) $request->validated('password'),
            );

            return response()->json([
                'message' => 'Login realizado com sucesso.',
                'data'    => $resultado,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }
}
