<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'       => ['nullable', Rule::enum(TransactionType::class)],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date'   => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($this->filled('start_date'), ['after_or_equal:start_date']),
            ],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.enum'                 => 'O tipo deve ser credit ou debit.',
            'start_date.date_format'    => 'A data inicial deve estar no formato AAAA-MM-DD.',
            'end_date.date_format'      => 'A data final deve estar no formato AAAA-MM-DD.',
            'end_date.after_or_equal'   => 'A data final deve ser igual ou posterior à data inicial.',
            'per_page.min'              => 'O limite por página deve ser no mínimo 1.',
            'per_page.max'              => 'O limite por página não pode ultrapassar 100 registros.',
        ];
    }
}
