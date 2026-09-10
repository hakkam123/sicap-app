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
            'consumed_at'    => ['required', 'date'],
            'part_number_id' => ['required', 'string', 'exists:part_numbers,id'],
            'quantity'       => ['required', 'integer', 'not_in:0'],
            'amount'         => ['required', 'numeric', 'not_in:0'],
            'area_id'        => ['nullable', 'string', 'exists:areas,id'],
            'machine_id'     => [
                'nullable',
                'string',
                'exists:machines,id',
                Rule::when($this->filled('area_id'), [
                    Rule::exists('machines', 'id')->where(function ($query) {
                        $query->where('area_id', $this->area_id);
                    }),
                ]),
            ],
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
            'consumed_at.required' => 'Tanggal consume wajib diisi.',
            'consumed_at.date' => 'Format tanggal consume tidak valid.',
            'part_number_id.required' => 'Part Number wajib dipilih.',
            'part_number_id.exists' => 'Part Number tidak valid atau tidak ditemukan.',
            'quantity.required' => 'Quantity pemakaian wajib diisi.',
            'quantity.integer' => 'Quantity harus berupa bilangan bulat.',
            'quantity.not_in' => 'Quantity pemakaian tidak boleh 0.',
            'amount.required' => 'Nilai Amount (nominal pemakaian) wajib diisi.',
            'amount.numeric' => 'Amount harus berupa angka numerik.',
            'amount.not_in' => 'Amount tidak boleh 0.',
            'area_id.exists' => 'Area yang dipilih tidak valid.',
            'machine_id.exists' => 'Mesin yang dipilih tidak valid atau tidak berada di area yang dipilih.',
        ];
    }
}
