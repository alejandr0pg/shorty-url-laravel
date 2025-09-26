<?php

namespace App\Services;

/**
 * URL Validator Service according to RFC 1738
 * https://www.rfc-editor.org/rfc/rfc1738.txt
 */
class UrlValidatorService
{
    // RFC 1738 compliant patterns
    private const SCHEME_PATTERN = '/^[a-zA-Z][a-zA-Z0-9+.-]*$/';
    private const COMMON_SCHEMES = ['http', 'https', 'ftp', 'ftps'];

    // Characters that need encoding according to RFC 1738
    private const UNSAFE_CHARACTERS = ['<', '>', '"', ' ', '{', '}', '|', '\\', '^', '`'];
    private const SAFE_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789$-_.+!*\'(),';

    /**
     * Validates URL according to RFC 1738 standards
     */
    public function validateUrl(string $url): array
    {
        $errors = [];

        if (empty($url)) {
            $errors[] = 'URL is required';
            return ['valid' => false, 'errors' => $errors, 'parts' => null];
        }

        // First try to sanitize the URL if it doesn't match basic pattern
        $sanitizedUrl = $this->sanitizeUrl($url);

        // Basic URL structure validation on sanitized URL
        $pattern = '/^([a-zA-Z][a-zA-Z0-9+.-]*):\/\/([^:\/\s]+)(:\d+)?(\/.*)?$/';
        if (!preg_match($pattern, $sanitizedUrl, $matches)) {
            $errors[] = 'Invalid URL format. URL must follow the pattern: scheme://host[:port][/path]';
            return ['valid' => false, 'errors' => $errors, 'parts' => null];
        }

        // Use sanitized URL for further validation
        $url = $sanitizedUrl;

        [$fullMatch, $scheme, $host, $port, $path] = $matches + [null, null, null, null, null];

        // Validate scheme
        if (!$this->validateScheme($scheme)) {
            $errors[] = "Invalid scheme: {$scheme}. Scheme must start with a letter and contain only letters, digits, +, -, or .";
        }

        // Check for common schemes (only allow http/https for our use case)
        if (!in_array(strtolower($scheme), ['http', 'https'])) {
            $errors[] = "Uncommon scheme: {$scheme}. Common schemes are: http, https";
        }

        // Validate host
        if (!$this->validateHost($host)) {
            $errors[] = "Invalid host: {$host}. Host must be a valid domain name or IP address";
        }

        // Validate port if present
        if ($port && !$this->validatePort(ltrim($port, ':'))) {
            $errors[] = "Invalid port: " . ltrim($port, ':') . ". Port must be a number between 1 and 65535";
        }

        // Validate path if present
        if ($path && !$this->validatePath($path)) {
            $errors[] = "Invalid path: {$path}. Path contains invalid characters";
        }

        // Check URL length
        if (strlen($url) > 2048) {
            $errors[] = 'URL is too long. Maximum length is 2048 characters';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'parts' => empty($errors) ? [
                'scheme' => $scheme,
                'host' => $host,
                'port' => $port ? ltrim($port, ':') : null,
                'path' => $path
            ] : null
        ];
    }

    /**
     * Validates URL scheme according to RFC 1738
     */
    private function validateScheme(string $scheme): bool
    {
        return preg_match(self::SCHEME_PATTERN, $scheme) === 1;
    }

    /**
     * Validates host according to RFC 1738
     */
    private function validateHost(string $host): bool
    {
        if (empty($host)) {
            return false;
        }

        // IPv4 address validation
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        // Domain name validation
        $domainPattern = '/^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z]{2,}$/';
        return preg_match($domainPattern, $host) === 1;
    }

    /**
     * Validates port according to RFC 1738
     */
    private function validatePort(string $port): bool
    {
        $portNum = (int) $port;
        return $portNum > 0 && $portNum <= 65535;
    }

    /**
     * Validates path according to RFC 1738
     */
    private function validatePath(string $path): bool
    {
        // Check for invalid characters in path
        foreach (self::UNSAFE_CHARACTERS as $char) {
            if (strpos($path, $char) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitizes URL to be RFC 1738 compliant
     */
    public function sanitizeUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // Remove leading/trailing whitespace
        $url = trim($url);

        // Ensure scheme is present
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        // Parse URL components
        $pattern = '/^([a-zA-Z][a-zA-Z0-9+.-]*):\/\/([^:\/\s]+)(:\d+)?(\/.*)?$/';
        if (!preg_match($pattern, $url, $matches)) {
            // If URL doesn't match pattern, do basic sanitization
            return $this->encodeUnsafeCharacters($url);
        }

        [$fullMatch, $scheme, $host, $port, $path] = $matches + [null, null, null, null, null];

        // Sanitize components
        $sanitizedScheme = strtolower($scheme); // Schemes are case-insensitive
        $sanitizedHost = strtolower($host); // Domain names are case-insensitive
        $sanitizedPort = $port ?? '';

        // Sanitize path with proper encoding
        $sanitizedPath = $path ? $this->sanitizePath($path) : '';

        return $sanitizedScheme . '://' . $sanitizedHost . $sanitizedPort . $sanitizedPath;
    }

    /**
     * Sanitizes URL path with proper encoding
     */
    private function sanitizePath(string $path): string
    {
        // Split path into segments to preserve '/' characters
        $segments = explode('/', $path);

        return implode('/', array_map(function ($segment) {
            return $this->encodeUnsafeCharacters($segment);
        }, $segments));
    }

    /**
     * Encodes unsafe characters according to RFC 1738
     */
    private function encodeUnsafeCharacters(string $string): string
    {
        $result = '';

        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];

            // Don't encode forward slashes in path
            if ($char === '/') {
                $result .= $char;
                continue;
            }

            // Don't encode reserved characters that are valid
            if (strpos('!*\'();:@&=+$,/?#[]', $char) !== false) {
                $result .= $char;
                continue;
            }

            // Encode characters that need encoding
            if ($this->needsEncoding($char)) {
                $result .= sprintf('%%%02X', ord($char));
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    /**
     * Checks if character needs encoding according to RFC 1738
     */
    private function needsEncoding(string $char): bool
    {
        // Safe characters don't need encoding
        return strpos(self::SAFE_CHARACTERS, $char) === false;
    }

    /**
     * Normalizes URL according to RFC 1738 standards
     */
    public function normalizeUrl(string $url): string
    {
        $sanitized = $this->sanitizeUrl($url);

        $parsedUrl = parse_url($sanitized);
        if (!$parsedUrl) {
            return $sanitized;
        }

        // Normalize scheme and host to lowercase
        $scheme = strtolower($parsedUrl['scheme'] ?? '');
        $host = strtolower($parsedUrl['host'] ?? '');
        $port = $parsedUrl['port'] ?? null;
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';
        $fragment = $parsedUrl['fragment'] ?? '';

        // Remove default ports
        if (($scheme === 'http' && $port === 80) ||
            ($scheme === 'https' && $port === 443) ||
            ($scheme === 'ftp' && $port === 21)) {
            $port = null;
        }

        // Normalize path - remove consecutive slashes
        $path = preg_replace('/\/+/', '/', $path);

        // Remove trailing slash for non-root paths
        if (strlen($path) > 1 && substr($path, -1) === '/') {
            $path = substr($path, 0, -1);
        }

        // Build normalized URL
        $normalized = $scheme . '://' . $host;
        if ($port) {
            $normalized .= ':' . $port;
        }
        $normalized .= $path;
        if ($query) {
            $normalized .= '?' . $query;
        }
        if ($fragment) {
            $normalized .= '#' . $fragment;
        }

        return $normalized;
    }

    /**
     * Checks if URL needs sanitization
     */
    public function needsSanitization(string $url): bool
    {
        return $url !== $this->sanitizeUrl($url);
    }

    /**
     * Processes URL with detailed feedback
     */
    public function processUrl(string $url): array
    {
        $original = $url;
        $sanitized = $this->sanitizeUrl($url);
        $normalized = $this->normalizeUrl($sanitized);
        $validation = $this->validateUrl($normalized);
        $needsSanitization = $this->needsSanitization($original);

        return [
            'original' => $original,
            'sanitized' => $sanitized,
            'normalized' => $normalized,
            'validation' => $validation,
            'needs_sanitization' => $needsSanitization
        ];
    }
}