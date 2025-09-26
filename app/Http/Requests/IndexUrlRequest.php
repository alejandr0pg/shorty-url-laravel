<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexUrlRequest extends FormRequest
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
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'search.string' => 'El término de búsqueda debe ser una cadena de texto.',
            'search.max' => 'El término de búsqueda no puede exceder 255 caracteres.',
            'per_page.integer' => 'El número de elementos por página debe ser un número entero.',
            'per_page.min' => 'Debe mostrar al menos 1 elemento por página.',
            'per_page.max' => 'No puede mostrar más de 100 elementos por página.',
            'page.integer' => 'El número de página debe ser un número entero.',
            'page.min' => 'El número de página debe ser mayor a 0.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'search' => 'término de búsqueda',
            'per_page' => 'elementos por página',
            'page' => 'número de página',
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

    /**
     * Get the body parameters for API documentation
     *
     * @return array
     */
    public function bodyParameters(): array
    {
        return [
            'search' => [
                'description' => 'Search term to filter URLs by original URL or short code.',
                'example' => 'example.com',
                'required' => false,
            ],
            'per_page' => [
                'description' => 'Number of items to return per page (1-100).',
                'example' => 15,
                'required' => false,
            ],
            'page' => [
                'description' => 'Page number for pagination.',
                'example' => 1,
                'required' => false,
            ],
        ];
    }
}
