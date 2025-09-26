<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for URL scheme validation
 */
class ValidUrlScheme implements ValidationRule
{
    private array $allowedSchemes;

    public function __construct(array $allowedSchemes = ['http', 'https'])
    {
        $this->allowedSchemes = $allowedSchemes;
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

        $parsedUrl = parse_url($value);

        if (!isset($parsedUrl['scheme'])) {
            $fail('The :attribute must include a valid scheme (http/https).');
            return;
        }

        $scheme = strtolower($parsedUrl['scheme']);

        if (!in_array($scheme, $this->allowedSchemes)) {
            $allowedList = implode(', ', $this->allowedSchemes);
            $fail("The :attribute must use one of the following schemes: {$allowedList}.");
        }
    }
}