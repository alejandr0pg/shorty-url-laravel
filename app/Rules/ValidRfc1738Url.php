<?php

namespace App\Rules;

use App\Services\UrlValidatorService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for RFC 1738 compliant URLs
 */
class ValidRfc1738Url implements ValidationRule
{
    private UrlValidatorService $validator;

    public function __construct()
    {
        $this->validator = new UrlValidatorService();
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a valid URL string.');
            return;
        }

        $result = $this->validator->validateUrl($value);

        if (!$result['valid']) {
            $errors = implode('. ', $result['errors']);
            $fail("The :attribute is not a valid RFC 1738 compliant URL. {$errors}");
        }
    }
}