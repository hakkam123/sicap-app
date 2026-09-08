<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartNumberRequest extends FormRequest
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
        $partNumber = $this->route('part_number') ?? $this->route('partNumber');

        return [
            'pn_baan' => [
                'required',
                'string',
                'max:100',
                Rule::unique('part_numbers', 'pn_baan')->ignore($partNumber),
            ],
            'description' => ['nullable', 'string'],
            'price_per_unit' => ['nullable', 'numeric', 'min:0'],
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['string', 'exists:areas,id'],
            'machine_ids' => ['nullable', 'array'],
            'machine_ids.*' => ['string', 'exists:machines,id'],
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
            'pn_baan.required' => 'Nomor part (PN BAAN) wajib diisi.',
            'pn_baan.unique' => 'Nomor part (PN BAAN) sudah terdaftar.',
            'pn_baan.max' => 'Nomor part maksimal 100 karakter.',
            'price_per_unit.numeric' => 'Harga satuan harus berupa angka.',
            'price_per_unit.min' => 'Harga satuan tidak boleh kurang dari 0.',
        ];
    }
}


