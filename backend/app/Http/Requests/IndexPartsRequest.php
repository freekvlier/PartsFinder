<?php

namespace App\Http\Requests;

use App\Enums\Condition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPartsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'brand' => ['array'],
            'brand.*' => ['string', 'max:100'],
            'condition' => ['nullable', Rule::enum(Condition::class)],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc'])],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:50'],
        ];
    }
}
