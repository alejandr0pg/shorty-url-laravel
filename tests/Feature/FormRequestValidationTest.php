<?php

use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Form Request Validation', function () {

    describe('StoreUrlRequest', function () {
        test('requires device id header', function () {
            $response = $this->postJson('/api/urls', ['url' => 'https://example.com']);

            $response->assertStatus(400)
                     ->assertJson(['error' => 'Device ID required']);
        });

        test('validates required url field', function () {
            $response = $this->postJson('/api/urls',
                [],
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.url.0', 'La URL es requerida.');
        });

        test('validates url must be string', function () {
            $response = $this->postJson('/api/urls',
                ['url' => 123],
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.url.0', 'La URL debe ser una cadena de texto válida.');
        });

        test('validates url max length', function () {
            $longUrl = 'https://example.com/' . str_repeat('a', 2100);

            $response = $this->postJson('/api/urls',
                ['url' => $longUrl],
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.url.0', 'La URL no puede exceder 2048 caracteres.');
        });

        test('validates url with rfc 1738 compliance', function () {
            $invalidUrls = [
                'not-a-url',
                'ftp://example.com',
                'javascript:alert(1)',
                'http://'
            ];

            foreach ($invalidUrls as $url) {
                $response = $this->postJson('/api/urls',
                    ['url' => $url],
                    ['X-Device-ID' => 'test-device']
                );

                $response->assertStatus(422);
                expect($response->json('errors.url.0'))->toContain('RFC 1738');
            }
        });

        test('accepts valid rfc 1738 compliant urls', function () {
            $validUrls = [
                'http://example.com',
                'https://example.com/path',
                'https://subdomain.example.com:8080',
                'http://192.168.1.1/api'
            ];

            foreach ($validUrls as $url) {
                $response = $this->postJson('/api/urls',
                    ['url' => $url],
                    ['X-Device-ID' => 'test-device']
                );

                $response->assertStatus(201);
            }
        });
    });

    describe('UpdateUrlRequest', function () {
        test('requires device id header for authorization', function () {
            $url = Url::create([
                'original_url' => 'https://example.com',
                'short_code' => 'test123',
                'device_id' => 'owner-device'
            ]);

            $response = $this->putJson("/api/urls/{$url->id}",
                ['url' => 'https://updated.com']
            );

            $response->assertStatus(400)
                     ->assertJson(['error' => 'Device ID required']);
        });

        test('requires ownership of url to update', function () {
            // Create URL through API first
            $createResponse = $this->postJson('/api/urls',
                ['url' => 'https://example.com'],
                ['X-Device-ID' => 'owner-device']
            );

            $createResponse->assertStatus(201);
            $shortCode = $createResponse->json('code');
            $url = Url::where('short_code', $shortCode)->first();

            // Try to update with different device ID
            $response = $this->putJson("/api/urls/{$url->id}",
                ['url' => 'https://updated.com'],
                ['X-Device-ID' => 'different-device']
            );

            $response->assertStatus(403)
                     ->assertJson(['error' => 'Unauthorized to update this URL']);
        });

        test('validates url field in update request', function () {
            // Create URL through API first
            $createResponse = $this->postJson('/api/urls',
                ['url' => 'https://example.com'],
                ['X-Device-ID' => 'owner-device']
            );

            $createResponse->assertStatus(201);
            $shortCode = $createResponse->json('code');
            $url = Url::where('short_code', $shortCode)->first();

            // Test empty URL
            $response = $this->putJson("/api/urls/{$url->id}",
                [],
                ['X-Device-ID' => 'owner-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.url.0', 'La URL es requerida.');

            // Test invalid URL
            $response = $this->putJson("/api/urls/{$url->id}",
                ['url' => 'invalid-url'],
                ['X-Device-ID' => 'owner-device']
            );

            $response->assertStatus(422);
        });

        test('successfully updates url with valid data', function () {
            $url = Url::create([
                'original_url' => 'https://example.com',
                'short_code' => 'test123',
                'device_id' => 'owner-device'
            ]);

            $response = $this->putJson("/api/urls/{$url->id}",
                ['url' => 'https://updated-example.com'],
                ['X-Device-ID' => 'owner-device']
            );

            $response->assertStatus(200)
                     ->assertJsonPath('original_url', 'https://updated-example.com');

            $this->assertDatabaseHas('urls', [
                'id' => $url->id,
                'original_url' => 'https://updated-example.com'
            ]);
        });

        test('returns 404 for non-existent url', function () {
            $response = $this->putJson('/api/urls/99999',
                ['url' => 'https://example.com'],
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(404)
                     ->assertJson(['error' => 'URL not found']);
        });
    });

    describe('DeleteUrlRequest', function () {
        test('requires device id header for authorization', function () {
            $url = Url::create([
                'original_url' => 'https://example.com',
                'short_code' => 'test123',
                'device_id' => 'owner-device'
            ]);

            $response = $this->deleteJson("/api/urls/{$url->id}");

            $response->assertStatus(400)
                     ->assertJson(['error' => 'Device ID required']);
        });

        test('requires ownership of url to delete', function () {
            $url = Url::create([
                'original_url' => 'https://example.com',
                'short_code' => 'test123',
                'device_id' => 'owner-device'
            ]);

            // Try to delete with different device ID
            $response = $this->deleteJson("/api/urls/{$url->id}",
                [],
                ['X-Device-ID' => 'different-device']
            );

            $response->assertStatus(403)
                     ->assertJson(['error' => 'Unauthorized to delete this URL']);
        });

        test('successfully deletes owned url', function () {
            $url = Url::create([
                'original_url' => 'https://example.com',
                'short_code' => 'test123',
                'device_id' => 'owner-device'
            ]);

            $response = $this->deleteJson("/api/urls/{$url->id}",
                [],
                ['X-Device-ID' => 'owner-device']
            );

            $response->assertStatus(204);

            $this->assertDatabaseMissing('urls', [
                'id' => $url->id
            ]);
        });

        test('returns 404 for non-existent url', function () {
            $response = $this->deleteJson('/api/urls/99999',
                [],
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(404)
                     ->assertJson(['error' => 'URL not found']);
        });

        test('clears cache when deleting url', function () {
            $url = Url::create([
                'original_url' => 'https://example.com',
                'short_code' => 'test123',
                'device_id' => 'owner-device'
            ]);

            // Access the URL to ensure it's cached
            $this->get('/test123');
            expect(\Illuminate\Support\Facades\Cache::has('url_test123'))->toBeTrue();

            // Delete the URL
            $response = $this->deleteJson("/api/urls/{$url->id}",
                [],
                ['X-Device-ID' => 'owner-device']
            );

            $response->assertStatus(204);

            // Cache should be cleared
            expect(\Illuminate\Support\Facades\Cache::has('url_test123'))->toBeFalse();
        });
    });

    describe('IndexUrlRequest', function () {
        test('requires device id header', function () {
            $response = $this->getJson('/api/urls');

            $response->assertStatus(400)
                     ->assertJson(['error' => 'Device ID required']);
        });

        test('validates search parameter', function () {
            $longSearch = str_repeat('a', 300); // Over 255 chars

            $response = $this->getJson('/api/urls?search=' . $longSearch,
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.search.0', 'El término de búsqueda no puede exceder 255 caracteres.');
        });

        test('validates per_page parameter', function () {
            // Test negative per_page
            $response = $this->getJson('/api/urls?per_page=-1',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.per_page.0', 'Debe mostrar al menos 1 elemento por página.');

            // Test per_page over limit
            $response = $this->getJson('/api/urls?per_page=150',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.per_page.0', 'No puede mostrar más de 100 elementos por página.');

            // Test non-integer per_page
            $response = $this->getJson('/api/urls?per_page=abc',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.per_page.0', 'El número de elementos por página debe ser un número entero.');
        });

        test('validates page parameter', function () {
            // Test negative page
            $response = $this->getJson('/api/urls?page=-1',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.page.0', 'El número de página debe ser mayor a 0.');

            // Test non-integer page
            $response = $this->getJson('/api/urls?page=abc',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(422)
                     ->assertJsonPath('errors.page.0', 'El número de página debe ser un número entero.');
        });

        test('accepts valid query parameters', function () {
            Url::create([
                'original_url' => 'https://example.com',
                'short_code' => 'test123',
                'device_id' => 'test-device'
            ]);

            $response = $this->getJson('/api/urls?search=example&per_page=10&page=1',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(200)
                     ->assertJsonStructure([
                         'current_page',
                         'data',
                         'per_page',
                         'total'
                     ]);
        });

        test('search functionality works correctly', function () {
            Url::create([
                'original_url' => 'https://github.com/example',
                'short_code' => 'github1',
                'device_id' => 'test-device'
            ]);

            Url::create([
                'original_url' => 'https://google.com',
                'short_code' => 'google1',
                'device_id' => 'test-device'
            ]);

            // Search for github
            $response = $this->getJson('/api/urls?search=github',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(200)
                     ->assertJsonCount(1, 'data')
                     ->assertJsonPath('data.0.original_url', 'https://github.com/example');

            // Search for google
            $response = $this->getJson('/api/urls?search=google',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(200)
                     ->assertJsonCount(1, 'data')
                     ->assertJsonPath('data.0.original_url', 'https://google.com');
        });

        test('pagination works correctly', function () {
            // Create multiple URLs
            for ($i = 1; $i <= 25; $i++) {
                Url::create([
                    'original_url' => "https://example{$i}.com",
                    'short_code' => "code{$i}",
                    'device_id' => 'test-device'
                ]);
            }

            // Test first page
            $response = $this->getJson('/api/urls?per_page=10&page=1',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(200)
                     ->assertJsonPath('current_page', 1)
                     ->assertJsonPath('per_page', 10)
                     ->assertJsonPath('total', 25)
                     ->assertJsonCount(10, 'data');

            // Test second page
            $response = $this->getJson('/api/urls?per_page=10&page=2',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(200)
                     ->assertJsonPath('current_page', 2)
                     ->assertJsonCount(10, 'data');

            // Test third page (should have 5 items)
            $response = $this->getJson('/api/urls?per_page=10&page=3',
                ['X-Device-ID' => 'test-device']
            );

            $response->assertStatus(200)
                     ->assertJsonPath('current_page', 3)
                     ->assertJsonCount(5, 'data');
        });
    });
});