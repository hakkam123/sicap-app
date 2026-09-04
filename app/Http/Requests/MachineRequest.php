<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MachineRequest extends FormRequest
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
        $machine = $this->route('machine');

        return [
            'area_id' => ['required', 'string', 'exists:areas,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('machines', 'code')->ignore($machine),
            ],
            'description' => ['nullable', 'string'],
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
            'area_id.required' => 'Area wajib dipilih.',
            'area_id.exists' => 'Area yang dipilih tidak valid.',
            'name.required' => 'Nama mesin wajib diisi.',
            'code.required' => 'Kode mesin wajib diisi.',
            'code.unique' => 'Kode mesin sudah terdaftar.',
            'code.max' => 'Kode mesin maksimal 50 karakter.',
        ];
    }
}

