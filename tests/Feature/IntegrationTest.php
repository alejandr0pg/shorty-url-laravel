<?php

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('Complete URL Shortener Integration Flows', function () {

    describe('Full CRUD Workflow', function () {
        test('complete lifecycle: create, read, update, delete', function () {
            $deviceId = 'integration-test-device-123';

            // 1. CREATE: Create a new URL
            $createResponse = $this->postJson('/api/urls',
                ['url' => 'https://example.com/original-path'],
                ['X-Device-ID' => $deviceId]
            );

            $createResponse->assertStatus(201)
                ->assertJsonStructure(['short_url', 'original_url', 'code', 'sanitized', 'normalized']);

            $urlData = $createResponse->json();
            $shortCode = $urlData['code'];

            // Verify database record
            $this->assertDatabaseHas('urls', [
                'original_url' => 'https://example.com/original-path',
                'short_code' => $shortCode,
                'device_id' => $deviceId,
                'clicks' => 0,
            ]);

            // 2. READ: List URLs and verify our URL is there
            $listResponse = $this->getJson('/api/urls', ['X-Device-ID' => $deviceId]);

            $listResponse->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.short_code', $shortCode)
                ->assertJsonPath('data.0.original_url', 'https://example.com/original-path');

            // 3. REDIRECT: Test the redirect functionality
            $redirectResponse = $this->get("/{$shortCode}");

            $redirectResponse->assertRedirect('https://example.com/original-path');

            // Verify clicks incremented
            $this->assertDatabaseHas('urls', [
                'short_code' => $shortCode,
                'clicks' => 1,
            ]);

            // 4. UPDATE: Update the URL
            $urlRecord = Url::where('short_code', $shortCode)->first();

            $updateResponse = $this->putJson("/api/urls/{$urlRecord->id}",
                ['url' => 'https://updated-example.com/new-path'],
                ['X-Device-ID' => $deviceId]
            );

            $updateResponse->assertStatus(200)
                ->assertJsonPath('original_url', 'https://updated-example.com/new-path')
                ->assertJsonPath('short_code', $shortCode); // Code stays the same

            // Verify redirect now goes to updated URL
            $redirectResponse = $this->get("/{$shortCode}");
            $redirectResponse->assertRedirect('https://updated-example.com/new-path');

            // Verify clicks incremented again
            $this->assertDatabaseHas('urls', [
                'short_code' => $shortCode,
                'clicks' => 2,
            ]);

            // 5. DELETE: Delete the URL
            // Refresh the model to ensure we have the latest data
            $urlRecord->refresh();

            $deleteResponse = $this->deleteJson("/api/urls/{$urlRecord->id}",
                [],
                ['X-Device-ID' => $deviceId]
            );

            $deleteResponse->assertStatus(204);

            // Verify database record is gone
            $this->assertDatabaseMissing('urls', [
                'id' => $urlRecord->id,
            ]);

            // Also verify by short_code
            $this->assertDatabaseMissing('urls', [
                'short_code' => $shortCode,
            ]);

            // Verify redirect now returns 404
            $redirectResponse = $this->get("/{$shortCode}");
            $redirectResponse->assertStatus(404);

            // Verify list is now empty
            $listResponse = $this->getJson('/api/urls', ['X-Device-ID' => $deviceId]);
            $listResponse->assertStatus(200)
                ->assertJsonCount(0, 'data');
        });
    });

    describe('Multi-Device Isolation', function () {
        test('devices cannot access each others urls', function () {
            $device1 = 'device-1-isolation-test';
            $device2 = 'device-2-isolation-test';

            // Device 1 creates URL
            $device1Response = $this->postJson('/api/urls',
                ['url' => 'https://device1-url.com'],
                ['X-Device-ID' => $device1]
            );

            $device1Response->assertStatus(201);
            $device1UrlData = $device1Response->json();
            $device1UrlId = Url::where('short_code', $device1UrlData['code'])->first()->id;

            // Device 2 creates URL
            $device2Response = $this->postJson('/api/urls',
                ['url' => 'https://device2-url.com'],
                ['X-Device-ID' => $device2]
            );

            $device2Response->assertStatus(201);

            // Device 1 can only see their URL
            $device1ListResponse = $this->getJson('/api/urls', ['X-Device-ID' => $device1]);
            $device1ListResponse->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.original_url', 'https://device1-url.com');

            // Device 2 can only see their URL
            $device2ListResponse = $this->getJson('/api/urls', ['X-Device-ID' => $device2]);
            $device2ListResponse->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.original_url', 'https://device2-url.com');

            // Device 2 cannot update Device 1's URL
            $unauthorizedUpdateResponse = $this->putJson("/api/urls/{$device1UrlId}",
                ['url' => 'https://hacked-url.com'],
                ['X-Device-ID' => $device2]
            );

            $unauthorizedUpdateResponse->assertStatus(403)
                ->assertJson(['error' => 'Unauthorized to update this URL']);

            // Device 2 cannot delete Device 1's URL
            $unauthorizedDeleteResponse = $this->deleteJson("/api/urls/{$device1UrlId}",
                [],
                ['X-Device-ID' => $device2]
            );

            $unauthorizedDeleteResponse->assertStatus(403)
                ->assertJson(['error' => 'Unauthorized to delete this URL']);

            // Verify Device 1's URL is unchanged
            $this->assertDatabaseHas('urls', [
                'id' => $device1UrlId,
                'original_url' => 'https://device1-url.com',
            ]);
        });
    });

    describe('RFC 1738 Processing Pipeline', function () {
        test('complex url processing through sanitization and normalization', function () {
            $complexUrl = '  Example.COM:443/path with spaces//double-slashes/"quotes"/  ';
            $deviceId = 'rfc-processing-test';

            $response = $this->postJson('/api/urls',
                ['url' => $complexUrl],
                ['X-Device-ID' => $deviceId]
            );

            $response->assertStatus(201)
                ->assertJson([
                    'sanitized' => true,
                    'normalized' => true,
                ]);

            $processedUrl = $response->json('original_url');

            // Verify the processing pipeline worked correctly
            expect($processedUrl)->toStartWith('https://'); // Added scheme
            expect($processedUrl)->toContain('example.com'); // Lowercase host
            expect($processedUrl)->not->toContain(':443'); // Default port removed
            expect($processedUrl)->toContain('%20'); // Spaces encoded
            expect($processedUrl)->toContain('%22'); // Quotes encoded
            expect($processedUrl)->not->toContain('//double-slashes'); // Double slashes normalized

            // Verify redirect still works with processed URL
            $shortCode = $response->json('code');
            $redirectResponse = $this->get("/{$shortCode}");
            $redirectResponse->assertRedirect($processedUrl);
        });

        test('handles edge cases in url processing', function () {
            $edgeCases = [
                [
                    'input' => 'example.com', // Missing scheme
                    'expectedScheme' => 'https://',
                ],
                [
                    'input' => 'HTTP://UPPERCASE-DOMAIN.COM/PATH',
                    'expectedHost' => 'uppercase-domain.com',
                ],
                [
                    'input' => 'https://example.com:443/path/',
                    'expectedPort' => false, // Default port should be removed
                    'expectedTrailingSlash' => false,
                ],
            ];

            foreach ($edgeCases as $index => $testCase) {
                $response = $this->postJson('/api/urls',
                    ['url' => $testCase['input']],
                    ['X-Device-ID' => "edge-case-{$index}"]
                );

                $response->assertStatus(201);
                $processedUrl = $response->json('original_url');

                if (isset($testCase['expectedScheme'])) {
                    expect($processedUrl)->toStartWith($testCase['expectedScheme']);
                }

                if (isset($testCase['expectedHost'])) {
                    expect($processedUrl)->toContain($testCase['expectedHost']);
                }

                if (isset($testCase['expectedPort']) && $testCase['expectedPort'] === false) {
                    expect($processedUrl)->not->toContain(':443');
                }

                if (isset($testCase['expectedTrailingSlash']) && $testCase['expectedTrailingSlash'] === false) {
                    expect($processedUrl)->not->toEndWith('/');
                }
            }
        });
    });

    describe('Caching Integration', function () {
        test('caching behavior throughout url lifecycle', function () {
            $deviceId = 'cache-test-device';

            // Create URL
            $response = $this->postJson('/api/urls',
                ['url' => 'https://cache-test.com'],
                ['X-Device-ID' => $deviceId]
            );

            $shortCode = $response->json('code');

            // First access should cache the URL - verify operation succeeds
            $this->get("/{$shortCode}")->assertStatus(302);

            // Second access should use cache
            $startTime = microtime(true);
            $this->get("/{$shortCode}");
            $endTime = microtime(true);

            // Cache access should be very fast (less than 0.01 seconds typically)
            expect($endTime - $startTime)->toBeLessThan(0.1);

            // Update URL should not immediately clear cache (cache remains until deletion or expiry)
            $urlRecord = Url::where('short_code', $shortCode)->first();
            $this->putJson("/api/urls/{$urlRecord->id}",
                ['url' => 'https://updated-cache-test.com'],
                ['X-Device-ID' => $deviceId]
            );

            // Delete URL should clear cache
            $this->deleteJson("/api/urls/{$urlRecord->id}",
                [],
                ['X-Device-ID' => $deviceId]
            );

            expect(Cache::has("url_{$shortCode}"))->toBeFalse();
        });
    });

    describe('Search and Pagination Integration', function () {
        test('search and pagination work together correctly', function () {
            $deviceId = 'search-pagination-test';

            // Create URLs with different patterns
            $urls = [
                'https://github.com/user1/repo1',
                'https://github.com/user2/repo2',
                'https://gitlab.com/user1/repo1',
                'https://bitbucket.org/user1/repo1',
                'https://example.com/api/v1',
                'https://example.com/api/v2',
                'https://test.com/random',
                'https://google.com/search?q=test',
            ];

            foreach ($urls as $url) {
                $this->postJson('/api/urls',
                    ['url' => $url],
                    ['X-Device-ID' => $deviceId]
                );
            }

            // Test search functionality
            $githubSearch = $this->getJson('/api/urls?search=github',
                ['X-Device-ID' => $deviceId]
            );

            $githubSearch->assertStatus(200)
                ->assertJsonCount(2, 'data'); // Should find 2 github URLs

            // Test search with pagination
            $exampleSearch = $this->getJson('/api/urls?search=example&per_page=1',
                ['X-Device-ID' => $deviceId]
            );

            $exampleSearch->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('total', 2) // Total of 2 example URLs
                ->assertJsonPath('last_page', 2); // Should have 2 pages

            // Test empty search results
            $notFoundSearch = $this->getJson('/api/urls?search=nonexistent',
                ['X-Device-ID' => $deviceId]
            );

            $notFoundSearch->assertStatus(200)
                ->assertJsonCount(0, 'data')
                ->assertJsonPath('total', 0);

            // Test pagination without search
            $paginatedResponse = $this->getJson('/api/urls?per_page=3&page=2',
                ['X-Device-ID' => $deviceId]
            );

            $paginatedResponse->assertStatus(200)
                ->assertJsonCount(3, 'data')
                ->assertJsonPath('current_page', 2);

            // Verify total count
            $allUrls = $this->getJson('/api/urls',
                ['X-Device-ID' => $deviceId]
            );

            $allUrls->assertStatus(200)
                ->assertJsonPath('total', 8); // All 8 URLs created
        });
    });

    describe('Performance and Stress Testing', function () {
        test('handles multiple concurrent url creations', function () {
            $deviceId = 'concurrent-test-device';
            $numberOfUrls = 50;
            $createdCodes = [];

            // Create multiple URLs rapidly
            for ($i = 1; $i <= $numberOfUrls; $i++) {
                $response = $this->postJson('/api/urls',
                    ['url' => "https://concurrent-test-{$i}.com"],
                    ['X-Device-ID' => $deviceId]
                );

                $response->assertStatus(201);
                $code = $response->json('code');

                // Ensure all codes are unique
                expect($createdCodes)->not->toContain($code);
                $createdCodes[] = $code;
            }

            // Verify all URLs were created
            $listResponse = $this->getJson('/api/urls',
                ['X-Device-ID' => $deviceId]
            );

            $listResponse->assertStatus(200)
                ->assertJsonPath('total', $numberOfUrls);

            // Test all redirects work
            foreach ($createdCodes as $index => $code) {
                $redirectResponse = $this->get("/{$code}");
                $redirectResponse->assertRedirect('https://concurrent-test-'.($index + 1).'.com');
            }
        });

        test('handles large pagination requests efficiently', function () {
            $deviceId = 'pagination-performance-test';
            $numberOfUrls = 20;

            // Create many URLs
            for ($i = 1; $i <= $numberOfUrls; $i++) {
                $this->postJson('/api/urls',
                    ['url' => "https://pagination-test-{$i}.com"],
                    ['X-Device-ID' => $deviceId]
                );
            }

            // Test various pagination sizes
            $paginationSizes = [10, 25, 50];

            foreach ($paginationSizes as $perPage) {
                $startTime = microtime(true);

                $response = $this->getJson("/api/urls?per_page={$perPage}",
                    ['X-Device-ID' => $deviceId]
                );

                $endTime = microtime(true);

                $response->assertStatus(200)
                    ->assertJsonCount(min($perPage, $numberOfUrls), 'data')
                    ->assertJsonPath('total', $numberOfUrls);

                // Response should be reasonably fast (less than 1 second)
                expect($endTime - $startTime)->toBeLessThan(1.0);
            }
        });
    });

    describe('Error Recovery Integration', function () {
        test('graceful handling of database constraints and conflicts', function () {
            $deviceId = 'error-recovery-test';

            // Test duplicate short code handling (though very unlikely)
            $urls = [];
            $codes = [];

            // Create multiple URLs and ensure no conflicts
            for ($i = 1; $i <= 20; $i++) {
                $response = $this->postJson('/api/urls',
                    ['url' => "https://error-test-{$i}.com"],
                    ['X-Device-ID' => $deviceId]
                );

                $response->assertStatus(201);
                $code = $response->json('code');

                expect($codes)->not->toContain($code, "Duplicate short code generated: {$code}");
                $codes[] = $code;
            }

            // Test invalid operations don't corrupt data
            $validUrl = Url::where('device_id', $deviceId)->first();

            // Try to update with invalid data
            $this->putJson("/api/urls/{$validUrl->id}",
                ['url' => 'completely-invalid-url'],
                ['X-Device-ID' => $deviceId]
            )->assertStatus(422);

            // Verify original data is unchanged
            $validUrl->refresh();
            expect($validUrl->original_url)->toStartWith('https://error-test-');

            // Try operations on non-existent resources
            $this->putJson('/api/urls/99999',
                ['url' => 'https://example.com'],
                ['X-Device-ID' => $deviceId]
            )->assertStatus(404);

            $this->deleteJson('/api/urls/99999',
                [],
                ['X-Device-ID' => $deviceId]
            )->assertStatus(404);
        });
    });
});

describe('Edge Case Integration Scenarios', function () {
    test('handles unicode and international urls end-to-end', function () {
        $deviceId = 'unicode-test-device';

        $unicodeUrls = [
            'https://例え.テスト/パス', // Japanese
            'https://пример.испытание/путь', // Russian
            'https://مثال.اختبار/مسار', // Arabic
        ];

        foreach ($unicodeUrls as $url) {
            $response = $this->postJson('/api/urls',
                ['url' => $url],
                ['X-Device-ID' => $deviceId]
            );

            // Should either work or fail gracefully
            if ($response->status() === 201) {
                $shortCode = $response->json('code');

                // Test redirect
                $redirectResponse = $this->get("/{$shortCode}");
                expect($redirectResponse->status())->toBeIn([302, 301]);
            } else {
                // If it fails, should be 422 with proper error message
                $response->assertStatus(422);
            }
        }
    });

    test('handles very long processing chains', function () {
        $deviceId = 'long-processing-test';

        // Create URL that goes through extensive processing
        $complexUrl = '  EXAMPLE.COM:443/path with spaces//double//slashes/"quotes"/<brackets>{braces}|pipes|  ';

        $response = $this->postJson('/api/urls',
            ['url' => $complexUrl],
            ['X-Device-ID' => $deviceId]
        );

        $response->assertStatus(201);

        $processedUrl = $response->json('original_url');
        $shortCode = $response->json('code');

        // Test the complete chain: create -> process -> store -> cache -> redirect
        $redirectResponse = $this->get("/{$shortCode}");
        $redirectResponse->assertRedirect($processedUrl);

        // Test update with another complex URL
        $urlRecord = Url::where('short_code', $shortCode)->first();

        $anotherComplexUrl = '  Another-EXAMPLE.ORG:80/new path//with//more//"processing"//  ';

        $updateResponse = $this->putJson("/api/urls/{$urlRecord->id}",
            ['url' => $anotherComplexUrl],
            ['X-Device-ID' => $deviceId]
        );

        $updateResponse->assertStatus(200);

        // Test redirect with updated URL
        $newProcessedUrl = $updateResponse->json('original_url');
        $redirectResponse = $this->get("/{$shortCode}");
        $redirectResponse->assertRedirect($newProcessedUrl);
    });
});
