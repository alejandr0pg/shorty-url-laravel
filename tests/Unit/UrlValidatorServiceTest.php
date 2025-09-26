<?php

use App\Services\UrlValidatorService;

describe('UrlValidatorService', function () {
    beforeEach(function () {
        $this->service = new UrlValidatorService();
    });

    describe('validateUrl method', function () {
        test('validates empty url', function () {
            $result = $this->service->validateUrl('');

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('URL is required');
        });

        test('validates basic url format', function () {
            $validUrls = [
                'http://example.com',
                'https://example.com',
                'https://www.example.com',
                'http://subdomain.example.com',
                'https://example.com/path',
                'http://192.168.1.1',
                'https://localhost:8080'
            ];

            foreach ($validUrls as $url) {
                $result = $this->service->validateUrl($url);
                expect($result['valid'])->toBeTrue("Failed for URL: {$url}");
                expect($result['parts'])->toHaveKeys(['scheme', 'host', 'port', 'path']);
            }
        });

        test('rejects invalid url formats', function () {
            $invalidUrls = [
                'not-a-url',
                'http://',
                'https://',
                '://example.com',
                'http://.',
                'http://..'
            ];

            foreach ($invalidUrls as $url) {
                $result = $this->service->validateUrl($url);
                expect($result['valid'])->toBeFalse("Should be invalid: {$url}");
                expect($result['errors'])->toBeArray();
                expect(count($result['errors']))->toBeGreaterThan(0);
            }

            // These should be sanitized and then validated
            $sanitizableUrls = [
                'example.com', // Will get https:// added
                'ftp://example.com' // Will be rejected due to scheme
            ];

            $result = $this->service->validateUrl('example.com');
            expect($result['valid'])->toBeTrue(); // Should be sanitized to https://example.com

            $result = $this->service->validateUrl('ftp://example.com');
            expect($result['valid'])->toBeFalse(); // Invalid scheme
        });

        test('validates scheme according to rfc 1738', function () {
            // Valid schemes
            $result = $this->service->validateUrl('http://example.com');
            expect($result['valid'])->toBeTrue();

            $result = $this->service->validateUrl('https://example.com');
            expect($result['valid'])->toBeTrue();

            // Invalid scheme format - will be sanitized first, so it becomes https://1http://example.com
            $result = $this->service->validateUrl('1http://example.com'); // Can't start with number
            expect($result['valid'])->toBeFalse();
            // The error could be about invalid format or invalid host

            // Uncommon but valid scheme format
            $result = $this->service->validateUrl('custom+scheme://example.com');
            expect($result['valid'])->toBeFalse(); // Our validator only allows http/https
            expect($result['errors'])->toContain('Uncommon scheme: custom+scheme. Common schemes are: http, https');
        });

        test('validates host component', function () {
            // Valid hosts
            $validHosts = [
                'http://example.com',
                'http://www.example.com',
                'http://sub.domain.example.com',
                'http://192.168.1.1',
                'http://10.0.0.1',
                'http://localhost'
            ];

            foreach ($validHosts as $url) {
                $result = $this->service->validateUrl($url);
                expect($result['valid'])->toBeTrue("Host should be valid: {$url}");
            }

            // Invalid hosts
            $invalidHosts = [
                'http://.',
                'http://..',
                'http://example.',
                'http://.example.com',
                'http://ex ample.com', // Space in host
                'http://example..com', // Double dot
                'http://256.256.256.256', // Invalid IP
                'http://', // Empty host
            ];

            foreach ($invalidHosts as $url) {
                $result = $this->service->validateUrl($url);
                expect($result['valid'])->toBeFalse("Host should be invalid: {$url}");
            }
        });

        test('validates port component', function () {
            // Valid ports
            $validPorts = [
                'http://example.com:80',
                'http://example.com:443',
                'http://example.com:8080',
                'http://example.com:1',
                'http://example.com:65535'
            ];

            foreach ($validPorts as $url) {
                $result = $this->service->validateUrl($url);
                expect($result['valid'])->toBeTrue("Port should be valid: {$url}");
            }

            // Invalid ports
            $invalidPorts = [
                'http://example.com:0',
                'http://example.com:65536',
                'http://example.com:-1',
                'http://example.com:abc',
                'http://example.com:',
                'http://example.com::80'
            ];

            foreach ($invalidPorts as $url) {
                $result = $this->service->validateUrl($url);
                expect($result['valid'])->toBeFalse("Port should be invalid: {$url}");
            }
        });

        test('validates path component', function () {
            // Valid paths
            $validPaths = [
                'http://example.com/',
                'http://example.com/path',
                'http://example.com/path/to/resource',
                'http://example.com/path-with-dashes',
                'http://example.com/path_with_underscores',
                'http://example.com/path123'
            ];

            foreach ($validPaths as $url) {
                $result = $this->service->validateUrl($url);
                expect($result['valid'])->toBeTrue("Path should be valid: {$url}");
            }

            // Paths with unsafe characters should be sanitized and then be valid
            $pathsWithUnsafeChars = [
                'http://example.com/path with spaces',
                'http://example.com/path"with"quotes',
                'http://example.com/path<with>brackets',
                'http://example.com/path{with}braces',
                'http://example.com/path|with|pipes'
            ];

            foreach ($pathsWithUnsafeChars as $url) {
                $result = $this->service->validateUrl($url);
                expect($result['valid'])->toBeTrue("Path should be sanitized and valid: {$url}");
            }
        });

        test('validates url length', function () {
            // URL just under 2048 characters should work
            $baseUrl = 'http://example.com/';
            $urlUnder2048 = $baseUrl . str_repeat('a', 1900 - strlen($baseUrl));
            $result = $this->service->validateUrl($urlUnder2048);
            expect($result['valid'])->toBeTrue();

            // Shorter URL should work
            $shortUrl = 'http://example.com/' . str_repeat('a', 100);
            $result = $this->service->validateUrl($shortUrl);
            expect($result['valid'])->toBeTrue();

            // URL over 2048 characters
            $urlOver2048 = 'http://example.com/' . str_repeat('a', 2048);
            $result = $this->service->validateUrl($urlOver2048);
            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('URL is too long. Maximum length is 2048 characters');
        });

        test('returns detailed validation parts for valid urls', function () {
            $result = $this->service->validateUrl('https://subdomain.example.com:8080/path/to/resource');

            expect($result['valid'])->toBeTrue();
            expect($result['parts'])->toBe([
                'scheme' => 'https',
                'host' => 'subdomain.example.com',
                'port' => '8080',
                'path' => '/path/to/resource'
            ]);
        });

        test('returns null parts for invalid urls', function () {
            $result = $this->service->validateUrl('invalid-url');

            expect($result['valid'])->toBeFalse();
            expect($result)->toHaveKey('parts');
            expect($result['parts'])->toBeNull();
        });
    });

    describe('sanitizeUrl method', function () {
        test('returns empty string for empty input', function () {
            $result = $this->service->sanitizeUrl('');
            expect($result)->toBe('');
        });

        test('trims whitespace', function () {
            $result = $this->service->sanitizeUrl('  https://example.com  ');
            expect($result)->toBe('https://example.com');
        });

        test('adds https scheme when missing', function () {
            $result = $this->service->sanitizeUrl('example.com/path');
            expect($result)->toBe('https://example.com/path');
        });

        test('preserves existing scheme', function () {
            $result = $this->service->sanitizeUrl('http://example.com/path');
            expect($result)->toBe('http://example.com/path');
        });

        test('converts scheme to lowercase', function () {
            $result = $this->service->sanitizeUrl('HTTPS://EXAMPLE.COM/PATH');
            expect($result)->toStartWith('https://example.com');
        });

        test('encodes unsafe characters in path', function () {
            $result = $this->service->sanitizeUrl('https://example.com/path with spaces');
            expect($result)->toContain('%20');
            expect($result)->not->toContain(' ');
        });

        test('preserves safe characters', function () {
            $safeUrl = 'https://example.com/path-with_safe.chars123';
            $result = $this->service->sanitizeUrl($safeUrl);
            expect($result)->toBe($safeUrl);
        });

        test('handles complex urls', function () {
            $complexUrl = 'Example.COM/path with spaces/resource"with"quotes';
            $result = $this->service->sanitizeUrl($complexUrl);

            expect($result)->toStartWith('https://example.com');
            expect($result)->toContain('%20'); // Encoded spaces
            expect($result)->toContain('%22'); // Encoded quotes
        });
    });

    describe('normalizeUrl method', function () {
        test('converts scheme and host to lowercase', function () {
            $result = $this->service->normalizeUrl('HTTPS://EXAMPLE.COM/PATH');
            expect($result)->toStartWith('https://example.com');
        });

        test('removes default ports', function () {
            $testCases = [
                ['http://example.com:80/path', 'http://example.com/path'],
                ['https://example.com:443/path', 'https://example.com/path'],
                ['ftp://example.com:21/path', 'ftp://example.com/path']
            ];

            foreach ($testCases as [$input, $expected]) {
                $result = $this->service->normalizeUrl($input);
                expect($result)->toBe($expected);
            }
        });

        test('preserves non-default ports', function () {
            $result = $this->service->normalizeUrl('https://example.com:8080/path');
            expect($result)->toBe('https://example.com:8080/path');
        });

        test('normalizes path by removing consecutive slashes', function () {
            $result = $this->service->normalizeUrl('https://example.com/path//with///multiple////slashes');
            expect($result)->toBe('https://example.com/path/with/multiple/slashes');
        });

        test('removes trailing slash from non-root paths', function () {
            $result = $this->service->normalizeUrl('https://example.com/path/');
            expect($result)->toBe('https://example.com/path');

            // But preserves root slash
            $result = $this->service->normalizeUrl('https://example.com/');
            expect($result)->toBe('https://example.com/');
        });

        test('preserves query and fragment', function () {
            $result = $this->service->normalizeUrl('https://example.com:443/path/?query=value#fragment');
            expect($result)->toBe('https://example.com/path?query=value#fragment');
        });

        test('handles malformed urls gracefully', function () {
            $malformedUrl = 'not-a-valid-url';
            $result = $this->service->normalizeUrl($malformedUrl);
            // Should return sanitized version when parsing fails
            expect($result)->toBeString();
        });
    });

    describe('needsSanitization method', function () {
        test('returns false for clean urls', function () {
            $cleanUrls = [
                'https://example.com',
                'http://example.com/path',
                'https://subdomain.example.com:8080/path'
            ];

            foreach ($cleanUrls as $url) {
                $result = $this->service->needsSanitization($url);
                expect($result)->toBeFalse("Should not need sanitization: {$url}");
            }
        });

        test('returns true for urls needing sanitization', function () {
            $dirtyUrls = [
                '  https://example.com  ', // Whitespace
                'example.com', // Missing scheme
                'HTTPS://EXAMPLE.COM', // Uppercase
                'https://example.com/path with spaces' // Unsafe characters
            ];

            foreach ($dirtyUrls as $url) {
                $result = $this->service->needsSanitization($url);
                expect($result)->toBeTrue("Should need sanitization: {$url}");
            }
        });
    });

    describe('processUrl method', function () {
        test('processes url through complete workflow', function () {
            $inputUrl = '  Example.COM:443/path with spaces/  ';

            $result = $this->service->processUrl($inputUrl);

            expect($result)->toHaveKeys([
                'original',
                'sanitized',
                'normalized',
                'validation',
                'needs_sanitization'
            ]);

            expect($result['original'])->toBe($inputUrl);
            expect($result['needs_sanitization'])->toBeTrue();
            expect($result['sanitized'])->not->toBe($inputUrl);
            expect($result['normalized'])->not->toBe($result['sanitized']);
            expect($result['validation']['valid'])->toBeTrue();
        });

        test('handles invalid urls in processing workflow', function () {
            $invalidUrl = 'completely-invalid-url';

            $result = $this->service->processUrl($invalidUrl);

            expect($result['original'])->toBe($invalidUrl);
            expect($result['validation']['valid'])->toBeFalse();
            expect($result['validation']['errors'])->toBeArray();
        });
    });

    describe('private helper methods integration', function () {
        test('validateScheme method integration', function () {
            $validSchemes = ['http', 'https'];
            foreach ($validSchemes as $scheme) {
                $result = $this->service->validateUrl("{$scheme}://example.com");
                expect($result['valid'])->toBeTrue();
            }

            // Test uncommon schemes - these will fail validation
            $result = $this->service->validateUrl('ftp://example.com');
            expect($result['valid'])->toBeFalse();

            $result = $this->service->validateUrl('custom-scheme://example.com');
            expect($result['valid'])->toBeFalse();

            $invalidSchemes = ['1scheme', '+scheme', '.scheme', '-scheme'];
            foreach ($invalidSchemes as $scheme) {
                $result = $this->service->validateUrl("{$scheme}://example.com");
                expect($result['valid'])->toBeFalse();
            }
        });

        test('validateHost method integration', function () {
            // Test IPv4 addresses
            $validIPs = ['192.168.1.1', '10.0.0.1', '8.8.8.8', '127.0.0.1'];
            foreach ($validIPs as $ip) {
                $result = $this->service->validateUrl("http://{$ip}");
                expect($result['valid'])->toBeTrue("IP should be valid: {$ip}");
            }

            $invalidIPs = ['256.1.1.1', '192.168.1.256', '192.168.1', '192.168'];
            foreach ($invalidIPs as $ip) {
                $result = $this->service->validateUrl("http://{$ip}");
                expect($result['valid'])->toBeFalse("IP should be invalid: {$ip}");
            }

            // Test domain names
            $validDomains = ['example.com', 'sub.example.com', '123.com'];
            foreach ($validDomains as $domain) {
                $result = $this->service->validateUrl("http://{$domain}");
                expect($result['valid'])->toBeTrue("Domain should be valid: {$domain}");
            }

            // Very short domain might not pass domain validation
            $result = $this->service->validateUrl("http://a.b");
            expect($result['valid'])->toBeFalse("Very short domain should be invalid");
        });

        test('needsEncoding method integration', function () {
            $urlsNeedingEncoding = [
                'https://example.com/path with spaces',
                'https://example.com/path"with"quotes',
                'https://example.com/path<with>brackets'
            ];

            foreach ($urlsNeedingEncoding as $url) {
                $sanitized = $this->service->sanitizeUrl($url);
                expect($sanitized)->not->toBe($url);
                expect($sanitized)->toMatch('/%[0-9A-F]{2}/'); // Contains URL encoding
            }
        });
    });
});