<?php

namespace App\Http\Requests;

use App\Support\IndonesianFormatParser;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConsumeApiRequest extends FormRequest
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
            'consumes' => ['required', 'array', 'min:1', 'max:500'],
            'consumes.*.date' => ['required', function ($attribute, $value, $fail) {
                if (IndonesianFormatParser::parseDate($value) === null) {
                    $fail("Format tanggal pada {$attribute} tidak valid.");
                }
            }],
            'consumes.*.part_number' => ['required', 'string'],
            'consumes.*.desc' => ['nullable', 'string'],
            'consumes.*.qty' => ['required', 'integer', 'not_in:0'],
            'consumes.*.amount' => ['required', function ($attribute, $value, $fail) {
                if (IndonesianFormatParser::parseAmount($value) === null) {
                    $fail("Format nominal amount pada {$attribute} tidak valid.");
                }
            }],
        ];
    }

    /**
     * Custom messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consumes.required' => 'Payload consumes wajib disertakan.',
            'consumes.min' => 'Payload consumes minimal berisi 1 rekaman data.',
            'consumes.max' => 'Payload consumes maksimal berisi 500 rekaman data per request.',
            'consumes.*.date.required' => 'Kolom date wajib diisi.',
            'consumes.*.part_number.required' => 'Kolom part_number wajib diisi.',
            'consumes.*.qty.required' => 'Kolom qty wajib diisi.',
            'consumes.*.qty.integer' => 'Kolom qty harus berupa bilangan bulat.',
            'consumes.*.qty.not_in' => 'Kolom qty tidak boleh bernilai 0.',
            'consumes.*.amount.required' => 'Kolom amount wajib diisi.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $formattedErrors = [];
        foreach ($validator->errors()->messages() as $key => $messages) {
            if (preg_match('/consumes\.(\d+)\.(.+)/', $key, $matches)) {
                $formattedErrors[] = [
                    'row' => (int) $matches[1] + 1,
                    'field' => $matches[2],
                    'message' => $messages[0],
                ];
            } else {
                $formattedErrors[] = [
                    'row' => null,
                    'field' => $key,
                    'message' => $messages[0],
                ];
            }
        }

        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $formattedErrors,
        ], 422));
    }
}

