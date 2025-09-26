<?php

namespace App\Http\Requests;

use App\Models\Url;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUrlRequest extends FormRequest
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
        return [];
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

        abort(response()->json(['error' => 'Unauthorized to delete this URL'], 403));
    }

    /**
     * Get the device ID from headers
     */
    public function getDeviceId(): string
    {
        return $this->header('X-Device-ID');
    }

    /**
     * Get the URL model instance
     */
    public function getUrl(): ?Url
    {
        if (! isset($this->url)) {
            $this->url = Url::find($this->route('url'));
        }

        return $this->url;
    }

    protected ?Url $url = null;
}
