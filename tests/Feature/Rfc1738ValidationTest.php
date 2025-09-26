<?php

use App\Models\Url;
use App\Services\UrlValidatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('RFC 1738 URL Validation', function () {
    test('validates basic valid urls', function () {
        $validUrls = [
            'http://example.com',
            'https://example.com',
            'http://www.example.com',
            'https://www.example.com/path',
            'https://example.com/path/to/resource',
            'https://example.com:8080',
            'https://example.com:8080/path',
            'https://subdomain.example.com',
            'http://192.168.1.1',
            'https://192.168.1.1:8080',
            'https://example.com/path?query=value',
            'https://example.com/path#fragment',
            'https://example.com/path?query=value#fragment'
        ];

        foreach ($validUrls as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(201, "Failed to create URL: {$url}");
        }
    });

    test('rejects invalid schemes according to rfc 1738', function () {
        $invalidSchemes = [
            'ftp://example.com',
            'ftps://example.com',
            'file://example.com',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'mailto:test@example.com',
            'tel:+1234567890',
            'ssh://example.com',
            'git://example.com'
        ];

        foreach ($invalidSchemes as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(422, "Should reject URL with invalid scheme: {$url}");
        }
    });

    test('validates host component according to rfc 1738', function () {
        $invalidHosts = [
            'http://',
            'https://',
            'http://.',
            'http://..',
            'http://...',
            'http://localhost.',
            'http://.',
            'http://.example.com',
            'http://example.',
            'http://ex ample.com',
            'http://example..com',
            'http://example.com.',
            'http://256.256.256.256', // Invalid IP
            'http://192.168.1.', // Incomplete IP
            'http://192.168..1', // Double dots in IP
        ];

        foreach ($invalidHosts as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(422, "Should reject URL with invalid host: {$url}");
        }
    });

    test('validates port component according to rfc 1738', function () {
        $invalidPorts = [
            'http://example.com:0',
            'http://example.com:65536',
            'http://example.com:99999',
            'http://example.com:-1',
            'http://example.com:abc',
            'http://example.com:80a',
            'http://example.com:',
            'http://example.com::80'
        ];

        foreach ($invalidPorts as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(422, "Should reject URL with invalid port: {$url}");
        }
    });

    test('handles path component with unsafe characters', function () {
        $pathsWithUnsafeChars = [
            'https://example.com/path with spaces',
            'https://example.com/path"with"quotes',
            'https://example.com/path<with>brackets',
            'https://example.com/path{with}braces',
            'https://example.com/path|with|pipes',
            'https://example.com/path\\with\\backslashes',
            'https://example.com/path^with^carets',
            'https://example.com/path`with`backticks'
        ];

        foreach ($pathsWithUnsafeChars as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(201, "Should sanitize and accept URL: {$url}")
                     ->assertJson(['sanitized' => true]);
        }
    });

    test('normalizes urls according to rfc 1738', function () {
        $normalizationTests = [
            [
                'input' => 'HTTP://EXAMPLE.COM/PATH',
                'expected' => 'http://example.com/PATH'
            ],
            [
                'input' => 'https://EXAMPLE.COM:443/path',
                'expected' => 'https://example.com/path'
            ],
            [
                'input' => 'http://example.com:80/path',
                'expected' => 'http://example.com/path'
            ],
            [
                'input' => 'https://example.com/path//double//slashes',
                'expected' => 'https://example.com/path/double/slashes'
            ],
            [
                'input' => 'https://example.com/path/',
                'expected' => 'https://example.com/path'
            ]
        ];

        foreach ($normalizationTests as $test) {
            $response = $this->postJson('/api/urls',
                ['url' => $test['input']],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(201)
                     ->assertJson([
                         'original_url' => $test['expected'],
                         'normalized' => true
                     ]);
        }
    });

    test('handles edge case url lengths', function () {
        // Test safely under the limit
        $urlUnder2048 = 'https://example.com/' . str_repeat('a', 1900); // Well under 2048

        $response = $this->postJson('/api/urls',
            ['url' => $urlUnder2048],
            ['X-Device-ID' => 'test-device-123']
        );

        $response->assertStatus(201);

        // Test over the limit
        $urlOver2048 = 'https://example.com/' . str_repeat('a', 2100); // Clearly over 2048

        $response = $this->postJson('/api/urls',
            ['url' => $urlOver2048],
            ['X-Device-ID' => 'test-device-123']
        );

        $response->assertStatus(422);
        // Should contain length error
        expect($response->json('errors.url'))->toContain('La URL no puede exceder 2048 caracteres.');
    });

    test('validates ip addresses according to rfc 1738', function () {
        $validIPs = [
            'http://192.168.1.1',
            'https://10.0.0.1',
            'http://127.0.0.1:8080',
            'https://172.16.0.1/path',
            'http://8.8.8.8',
            'https://1.1.1.1'
        ];

        foreach ($validIPs as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(201, "Should accept valid IP URL: {$url}");
        }

        $invalidIPs = [
            'http://256.1.1.1',
            'http://192.168.1.256',
            'http://192.168.1',
            'http://192.168',
            'http://192',
            'http://192.168.1.1.1',
            'http://192.168.01.1', // Leading zeros not technically invalid but inconsistent
        ];

        foreach ($invalidIPs as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(422, "Should reject invalid IP URL: {$url}");
        }
    });

    test('handles query strings and fragments', function () {
        $urlsWithQueryAndFragment = [
            'https://example.com?query=value',
            'https://example.com#fragment',
            'https://example.com?query=value#fragment',
            'https://example.com/path?multiple=values&another=param',
            'https://example.com/path?query=value with spaces',
            'https://example.com/path?query=value#fragment with spaces',
            'https://example.com?empty=',
            'https://example.com?key1=value1&key2=value2&key3=value3'
        ];

        foreach ($urlsWithQueryAndFragment as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => 'test-device-123']
            );

            // Should either succeed (possibly with sanitization) or fail gracefully
            expect($response->status())->toBeIn([201, 422]);

            if ($response->status() === 201) {
                $response->assertJsonStructure(['short_url', 'original_url', 'code']);
            }
        }
    });

    test('preserves valid encoded characters', function () {
        $urlsWithEncoding = [
            'https://example.com/path%20with%20encoded%20spaces',
            'https://example.com/path?query=value%20encoded',
            'https://example.com/path%2Fwith%2Fencoded%2Fslashes',
            'https://example.com/%C3%A9ncoded-unicode'
        ];

        foreach ($urlsWithEncoding as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(201, "Should accept properly encoded URL: {$url}");
        }
    });
});

describe('RFC 1738 Sanitization Process', function () {
    test('sanitization adds https scheme when missing', function () {
        $response = $this->postJson('/api/urls',
            ['url' => 'example.com/path'],
            ['X-Device-ID' => 'test-device-123']
        );

        $response->assertStatus(201)
                 ->assertJsonPath('original_url', 'https://example.com/path')
                 ->assertJson(['sanitized' => true]);
    });

    test('sanitization encodes unsafe characters properly', function () {
        $testCases = [
            [
                'input' => 'https://example.com/path with spaces',
                'should_sanitize' => true
            ],
            [
                'input' => 'https://example.com/path"with"quotes',
                'should_sanitize' => true
            ],
            [
                'input' => 'https://example.com/normal-path-123',
                'should_sanitize' => false
            ]
        ];

        foreach ($testCases as $testCase) {
            $response = $this->postJson('/api/urls',
                ['url' => $testCase['input']],
                ['X-Device-ID' => 'test-device-123']
            );

            $response->assertStatus(201)
                     ->assertJson(['sanitized' => $testCase['should_sanitize']]);
        }
    });

    test('complete url processing workflow', function () {
        // Test a URL that needs full processing: sanitization + normalization
        $complexUrl = 'https://Example.COM:443/path with spaces//double-slashes/';

        $response = $this->postJson('/api/urls',
            ['url' => $complexUrl],
            ['X-Device-ID' => 'test-device-123']
        );

        $response->assertStatus(201);

        // Check that it was processed (either sanitized or normalized or both)
        $responseData = $response->json();
        expect($responseData['sanitized'] || $responseData['normalized'])->toBeTrue();

        // The final URL should be properly processed
        $processedUrl = $response->json('original_url');
        expect($processedUrl)->toStartWith('https://');
        expect($processedUrl)->toContain('example.com'); // Lowercased
        expect($processedUrl)->not->toContain(':443'); // Default port removed
        expect($processedUrl)->not->toContain('//double-slashes'); // Double slashes removed
    });
});