<?php

namespace App\Http\Requests;

use App\Models\Url;
use App\Rules\ValidRfc1738Url;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUrlRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->hasHeader('X-Device-ID')) {
            return false;
        }

        $url = Url::find($this->route('url'));

        if (! $url) {
            return false;
        }

        return $url->device_id === $this->header('X-Device-ID');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', new ValidRfc1738Url],
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
        $url = Url::find($this->route('url'));

        if (! $this->hasHeader('X-Device-ID')) {
            abort(response()->json(['error' => 'Device ID required'], 400));
        }

        if (! $url) {
            abort(response()->json(['error' => 'URL not found'], 404));
        }

        abort(response()->json(['error' => 'Unauthorized to update this URL'], 403));
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
     */
    public function bodyParameters(): array
    {
        return [
            'url' => [
                'description' => 'The new URL to replace the existing one. Must be a valid RFC 1738 compliant URL.',
                'example' => 'https://www.updated-example.com/new/path',
                'required' => true,
            ],
        ];
    }
}
