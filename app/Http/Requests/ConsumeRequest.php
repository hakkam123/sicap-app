<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsumeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'part_number_id' => ['required', 'string', 'exists:part_numbers,id'],
            'area_id' => ['nullable', 'string', 'exists:areas,id'],
            'machine_id' => [
                'nullable',
                'string',
                'exists:machines,id',
                Rule::when($this->filled('area_id'), [
                    Rule::exists('machines', 'id')->where(function ($query) {
                        $query->where('area_id', $this->area_id);
                    }),
                ]),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'amount' => ['nullable', 'numeric'],
            'consumed_at' => ['required', 'date'],
        ];
    }

    /**
     * Custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'part_number_id.required' => 'Part Number wajib dipilih.',
            'part_number_id.exists' => 'Part Number tidak valid.',
            'area_id.exists' => 'Area yang dipilih tidak valid.',
            'machine_id.exists' => 'Mesin yang dipilih tidak valid atau tidak berada di area yang dipilih.',
            'quantity.required' => 'Quantity pemakaian wajib diisi.',
            'quantity.integer' => 'Quantity harus berupa bilangan bulat.',
            'quantity.min' => 'Quantity pemakaian minimal 1 (angka positif).',
            'amount.numeric' => 'Amount harus berupa angka.',
            'consumed_at.required' => 'Tanggal consume wajib diisi.',
            'consumed_at.date' => 'Format tanggal consume tidak valid.',
        ];
    }
}

