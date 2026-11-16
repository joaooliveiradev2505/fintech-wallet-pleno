<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
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
            'amount.min'     => 'O valor do depósito deve ser positivo e no mínimo R$ 0,01.',
            'amount.decimal' => 'O valor não pode ter frações menores que R$ 0,01 (máximo 2 casas decimais).',
        ];
    }
}
