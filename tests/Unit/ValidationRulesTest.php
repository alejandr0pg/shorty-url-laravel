<?php

use App\Rules\ValidRfc1738Url;
use App\Rules\ValidUrlScheme;

describe('Validation Rules Unit Tests', function () {

    describe('ValidRfc1738Url Rule', function () {
        beforeEach(function () {
            $this->rule = new ValidRfc1738Url;
        });

        test('validates valid rfc 1738 compliant urls', function () {
            $validUrls = [
                'http://example.com',
                'https://example.com',
                'https://www.example.com/path',
                'http://subdomain.example.com:8080',
                'https://192.168.1.1/api',
                'http://localhost:3000/development',
            ];

            foreach ($validUrls as $url) {
                $failed = false;
                $errorMessage = '';

                $this->rule->validate('url', $url, function ($message) use (&$failed, &$errorMessage) {
                    $failed = true;
                    $errorMessage = $message;
                });

                expect($failed)->toBeFalse("Should validate successfully: {$url}. Error: {$errorMessage}");
            }
        });

        test('rejects invalid rfc 1738 urls', function () {
            $invalidUrls = [
                'not-a-url',
                'ftp://example.com',
                'javascript:alert(1)',
                'http://',
                'https://',
                'http://.',
                '', // Empty string
            ];

            foreach ($invalidUrls as $url) {
                $failed = false;
                $errorMessage = '';

                $this->rule->validate('url', $url, function ($message) use (&$failed, &$errorMessage) {
                    $failed = true;
                    $errorMessage = $message;
                });

                expect($failed)->toBeTrue("Should fail validation: {$url}");
                expect($errorMessage)->toContain('RFC 1738');
            }
        });

        test('rejects non-string values', function () {
            $nonStringValues = [
                123,
                null,
                [],
                (object) ['url' => 'test'],
                true,
                false,
            ];

            foreach ($nonStringValues as $value) {
                $failed = false;
                $errorMessage = '';

                $this->rule->validate('url', $value, function ($message) use (&$failed, &$errorMessage) {
                    $failed = true;
                    $errorMessage = $message;
                });

                expect($failed)->toBeTrue('Should fail for non-string value: '.gettype($value));
                expect($errorMessage)->toContain('must be a valid URL string');
            }
        });

        test('provides detailed error messages for different validation failures', function () {
            $testCases = [
                [
                    'url' => 'http://',
                    'expectedErrorParts' => ['Invalid URL format', 'URL must follow the pattern'],
                ],
                [
                    'url' => 'ftp://example.com',
                    'expectedErrorParts' => ['Uncommon scheme'],
                ],
                [
                    'url' => 'http://256.256.256.256',
                    'expectedErrorParts' => ['Invalid host'],
                ],
                [
                    'url' => 'http://example.com:99999',
                    'expectedErrorParts' => ['Invalid port'],
                ],
            ];

            foreach ($testCases as $testCase) {
                $failed = false;
                $errorMessage = '';

                $this->rule->validate('url', $testCase['url'], function ($message) use (&$failed, &$errorMessage) {
                    $failed = true;
                    $errorMessage = $message;
                });

                expect($failed)->toBeTrue("Should fail for: {$testCase['url']}");

                foreach ($testCase['expectedErrorParts'] as $expectedPart) {
                    expect(strpos($errorMessage, $expectedPart) !== false)->toBeTrue(
                        "Error message should contain '{$expectedPart}' for URL: {$testCase['url']}. Got: {$errorMessage}");
                }
            }
        });
    });

    describe('ValidUrlScheme Rule', function () {
        test('validates default allowed schemes', function () {
            $rule = new ValidUrlScheme;

            $validUrls = [
                'http://example.com',
                'https://example.com',
                'HTTP://example.com', // Case insensitive
                'HTTPS://example.com',
            ];

            foreach ($validUrls as $url) {
                $failed = false;

                $rule->validate('url', $url, function ($message) use (&$failed) {
                    $failed = true;
                });

                expect($failed)->toBeFalse("Should accept scheme in: {$url}");
            }
        });

        test('rejects disallowed schemes with default config', function () {
            $rule = new ValidUrlScheme;

            $invalidUrls = [
                'ftp://example.com',
                'ftps://example.com',
                'ssh://example.com',
                'file://example.com',
                'javascript:alert(1)',
                'data:text/html,test',
            ];

            foreach ($invalidUrls as $url) {
                $failed = false;
                $errorMessage = '';

                $rule->validate('url', $url, function ($message) use (&$failed, &$errorMessage) {
                    $failed = true;
                    $errorMessage = $message;
                });

                expect($failed)->toBeTrue("Should reject scheme in: {$url}");
                expect($errorMessage)->toContain('http, https');
            }
        });

        test('accepts custom allowed schemes', function () {
            $rule = new ValidUrlScheme(['http', 'https', 'ftp']);

            $validUrls = [
                'http://example.com',
                'https://example.com',
                'ftp://example.com',
            ];

            foreach ($validUrls as $url) {
                $failed = false;

                $rule->validate('url', $url, function ($message) use (&$failed) {
                    $failed = true;
                });

                expect($failed)->toBeFalse("Should accept custom scheme in: {$url}");
            }

            // Should still reject non-allowed schemes
            $failed = false;
            $rule->validate('url', 'ssh://example.com', function ($message) use (&$failed) {
                $failed = true;
            });
            expect($failed)->toBeTrue();
        });

        test('rejects urls without schemes', function () {
            $rule = new ValidUrlScheme;

            $urlsWithoutScheme = [
                'example.com',
                'www.example.com',
                '//example.com',
                'localhost:8080',
            ];

            foreach ($urlsWithoutScheme as $url) {
                $failed = false;
                $errorMessage = '';

                $rule->validate('url', $url, function ($message) use (&$failed, &$errorMessage) {
                    $failed = true;
                    $errorMessage = $message;
                });

                expect($failed)->toBeTrue("Should require scheme for: {$url}");
                expect($errorMessage)->toContain('must include a valid scheme');
            }
        });

        test('accepts urls that can be sanitized', function () {
            $rule = new ValidRfc1738Url;
            $sanitizableUrls = [
                'example.com', // Will get https:// added
                'subdomain.example.com',
                'example.com/path',
            ];

            foreach ($sanitizableUrls as $url) {
                $failed = false;

                $rule->validate('url', $url, function ($message) use (&$failed) {
                    $failed = true;
                });

                expect($failed)->toBeFalse("Should accept sanitizable URL: {$url}");
            }
        });

        test('rejects non-string values', function () {
            $rule = new ValidUrlScheme;

            $nonStringValues = [123, null, [], true, false];

            foreach ($nonStringValues as $value) {
                $failed = false;
                $errorMessage = '';

                $rule->validate('url', $value, function ($message) use (&$failed, &$errorMessage) {
                    $failed = true;
                    $errorMessage = $message;
                });

                expect($failed)->toBeTrue('Should reject non-string: '.gettype($value));
                expect($errorMessage)->toContain('must be a valid URL string');
            }
        });

        test('handles malformed urls gracefully', function () {
            $rule = new ValidUrlScheme;

            $malformedUrls = [
                '://',
                'http://',
                'https://',
                ':',
                'scheme:',
                'scheme:/',
                '',
            ];

            foreach ($malformedUrls as $url) {
                $failed = false;
                $errorMessage = '';

                $rule->validate('url', $url, function ($message) use (&$failed, &$errorMessage) {
                    $failed = true;
                    $errorMessage = $message;
                });

                expect($failed)->toBeTrue("Should reject malformed URL: '{$url}'");
                // Should provide meaningful error message
                expect($errorMessage)->toBeString();
                expect(strlen($errorMessage))->toBeGreaterThan(0);
            }
        });

        test('case insensitive scheme validation', function () {
            $rule = new ValidUrlScheme;

            $casedUrls = [
                'HTTP://example.com',
                'HTTPS://example.com',
                'Http://example.com',
                'Https://example.com',
                'hTTp://example.com',
                'hTTpS://example.com',
            ];

            foreach ($casedUrls as $url) {
                $failed = false;

                $rule->validate('url', $url, function ($message) use (&$failed) {
                    $failed = true;
                });

                expect($failed)->toBeFalse("Should accept case variation: {$url}");
            }
        });

        test('provides correct error message for custom schemes', function () {
            $customSchemes = ['custom', 'special', 'myscheme'];
            $rule = new ValidUrlScheme($customSchemes);

            $failed = false;
            $errorMessage = '';

            $rule->validate('url', 'http://example.com', function ($message) use (&$failed, &$errorMessage) {
                $failed = true;
                $errorMessage = $message;
            });

            expect($failed)->toBeTrue();
            expect($errorMessage)->toContain('custom, special, myscheme');
        });
    });

    describe('Rule Integration', function () {
        test('both rules can be used together', function () {
            $rfc1738Rule = new ValidRfc1738Url;
            $schemeRule = new ValidUrlScheme(['http', 'https']);

            $testUrl = 'https://example.com/path';

            // Both rules should pass for valid URL
            $rfc1738Failed = false;
            $schemeRuleFailed = false;

            $rfc1738Rule->validate('url', $testUrl, function ($message) use (&$rfc1738Failed) {
                $rfc1738Failed = true;
            });

            $schemeRule->validate('url', $testUrl, function ($message) use (&$schemeRuleFailed) {
                $schemeRuleFailed = true;
            });

            expect($rfc1738Failed)->toBeFalse();
            expect($schemeRuleFailed)->toBeFalse();

            // Test with invalid URL - both should fail
            $invalidUrl = 'ftp://example.com';

            $rfc1738Failed = false;
            $schemeRuleFailed = false;

            $rfc1738Rule->validate('url', $invalidUrl, function ($message) use (&$rfc1738Failed) {
                $rfc1738Failed = true;
            });

            $schemeRule->validate('url', $invalidUrl, function ($message) use (&$schemeRuleFailed) {
                $schemeRuleFailed = true;
            });

            expect($rfc1738Failed)->toBeTrue();
            expect($schemeRuleFailed)->toBeTrue();
        });

        test('rules provide different but complementary validation', function () {
            $rfc1738Rule = new ValidRfc1738Url;
            $schemeRule = new ValidUrlScheme(['ftp', 'ftps']); // Different allowed schemes

            $testUrl = 'ftp://example.com';

            $rfc1738Failed = false;
            $rfc1738Error = '';
            $schemeRuleFailed = false;

            // RFC rule should fail (doesn't allow ftp)
            $rfc1738Rule->validate('url', $testUrl, function ($message) use (&$rfc1738Failed, &$rfc1738Error) {
                $rfc1738Failed = true;
                $rfc1738Error = $message;
            });

            // Scheme rule should pass (allows ftp)
            $schemeRule->validate('url', $testUrl, function ($message) use (&$schemeRuleFailed) {
                $schemeRuleFailed = true;
            });

            expect($rfc1738Failed)->toBeTrue();
            expect($schemeRuleFailed)->toBeFalse();
            expect($rfc1738Error)->toContain('Uncommon scheme');
        });
    });
});
