<?php

namespace App\Http\Requests;

use App\Rules\ValidRfc1738Url;
use Illuminate\Foundation\Http\FormRequest;

class StoreUrlRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->hasHeader('X-Device-ID');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', new ValidRfc1738Url()],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'url.required' => 'La URL es requerida.',
            'url.string' => 'La URL debe ser una cadena de texto válida.',
            'url.max' => 'La URL no puede exceder 2048 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'url' => 'URL',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        abort(response()->json(['error' => 'Device ID required'], 400));
    }

    /**
     * Get the device ID from headers
     */
    public function getDeviceId(): string
    {
        return $this->header('X-Device-ID');
    }
}