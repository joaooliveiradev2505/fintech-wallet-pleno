<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'      => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min'     => 'O valor do saque deve ser positivo e no mínimo R$ 0,01.',
            'amount.decimal' => 'Não é permitido sacar valor fracionado abaixo de R$ 0,01.',
        ];
    }
}
