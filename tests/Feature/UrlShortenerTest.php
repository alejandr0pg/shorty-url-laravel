<?php

use App\Models\Url;
use App\Services\UrlValidatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('generates unique short code', function () {
    $code1 = Url::generateShortCode();
    $code2 = Url::generateShortCode();

    expect($code1)->toBeString();
    expect($code2)->toBeString();
    expect(strlen($code1))->toBeLessThanOrEqual(8);
    expect(strlen($code2))->toBeLessThanOrEqual(8);
    expect($code1)->not()->toBe($code2);
});

test('short code uses readable characters', function () {
    $code = Url::generateShortCode();

    expect($code)->toMatch('/^[A-HJ-KM-NP-Z2-9]+$/');
});

test('short code generation avoids confusing characters', function () {
    // Generate multiple codes to test consistency
    for ($i = 0; $i < 100; $i++) {
        $code = Url::generateShortCode();

        // Should not contain confusing characters: 0, O, 1, I, L
        expect($code)->not->toContain('0');
        expect($code)->not->toContain('O');
        expect($code)->not->toContain('1');
        expect($code)->not->toContain('I');
        expect($code)->not->toContain('L');
    }
});

test('creates short url via api with device id', function () {
    $response = $this->postJson('/api/urls',
        ['url' => 'https://example.com'],
        ['X-Device-ID' => 'test-device-123']
    );

    $response->assertStatus(201)
             ->assertJsonStructure(['short_url', 'original_url', 'code', 'sanitized', 'normalized'])
             ->assertJson([
                 'original_url' => 'https://example.com',
                 'sanitized' => false,
                 'normalized' => false
             ]);

    $this->assertDatabaseHas('urls', [
        'original_url' => 'https://example.com',
        'device_id' => 'test-device-123'
    ]);
});

test('validates url input with device id', function () {
    $response = $this->postJson('/api/urls',
        ['url' => 'invalid-url'],
        ['X-Device-ID' => 'test-device-123']
    );

    $response->assertStatus(422)
             ->assertJsonStructure(['errors', 'message'])
             ->assertJsonStructure(['errors']);
});

test('requires device id header', function () {
    $response = $this->postJson('/api/urls', ['url' => 'https://example.com']);

    $response->assertStatus(400)
             ->assertJson(['error' => 'Device ID required']);
});

test('redirects to original url and increments clicks', function () {
    $url = Url::create([
        'original_url' => 'https://example.com',
        'short_code' => Url::generateShortCode(),
        'device_id' => 'test-device-123',
        'clicks' => 0
    ]);

    $response = $this->get("/{$url->short_code}");

    $response->assertRedirect($url->original_url);

    $url->refresh();
    expect($url->clicks)->toBe(1);
});

test('returns 404 for invalid code', function () {
    $response = $this->get('/invalidcode');

    $response->assertStatus(404);
});

// Edge Cases Tests
test('handles very long urls', function () {
    $longUrl = 'https://example.com/' . str_repeat('a', 1900); // Close to 2048 limit

    $response = $this->postJson('/api/urls',
        ['url' => $longUrl],
        ['X-Device-ID' => 'test-device-123']
    );

    $response->assertStatus(201);
});

test('rejects urls exceeding 2048 characters', function () {
    $longUrl = 'https://example.com/' . str_repeat('a', 2100); // Over 2048 limit

    $response = $this->postJson('/api/urls',
        ['url' => $longUrl],
        ['X-Device-ID' => 'test-device-123']
    );

    $response->assertStatus(422)
             ->assertJsonPath('errors.url.0', 'La URL no puede exceder 2048 caracteres.');
});

test('handles urls with special characters that need encoding', function () {
    $urlWithSpaces = 'https://example.com/path with spaces and special chars';

    $response = $this->postJson('/api/urls',
        ['url' => $urlWithSpaces],
        ['X-Device-ID' => 'test-device-123']
    );

    $response->assertStatus(201)
             ->assertJson(['sanitized' => true]);
});

test('normalizes urls with default ports', function () {
    $response = $this->postJson('/api/urls',
        ['url' => 'https://example.com:443/path'],
        ['X-Device-ID' => 'test-device-123']
    );

    $response->assertStatus(201)
             ->assertJson([
                 'original_url' => 'https://example.com/path',
                 'normalized' => true
             ]);
});

test('handles concurrent requests for same short code generation', function () {
    $responses = [];

    // Simulate concurrent requests
    for ($i = 0; $i < 5; $i++) {
        $responses[] = $this->postJson('/api/urls',
            ['url' => "https://example{$i}.com"],
            ['X-Device-ID' => "device-{$i}"]
        );
    }

    // All should succeed with unique codes
    $codes = [];
    foreach ($responses as $response) {
        $response->assertStatus(201);
        $code = $response->json('code');
        expect($codes)->not->toContain($code);
        $codes[] = $code;
    }
});

test('caches url lookups for redirects', function () {
    // Create URL through API to ensure proper setup
    $response = $this->postJson('/api/urls',
        ['url' => 'https://example.com'],
        ['X-Device-ID' => 'test-device-123']
    );

    $shortCode = $response->json('code');

    // First request should cache
    $this->get("/{$shortCode}")->assertRedirect('https://example.com');

    // Check cache exists
    expect(Cache::has("url_{$shortCode}"))->toBeTrue();

    // Second request should use cache
    $this->get("/{$shortCode}")->assertRedirect('https://example.com');
});

test('handles invalid schemes', function () {
    $invalidSchemes = [
        'javascript:alert(1)',
        'data:text/html,<script>alert(1)</script>'
    ];

    foreach ($invalidSchemes as $url) {
        $response = $this->postJson('/api/urls',
            ['url' => $url],
            ['X-Device-ID' => 'test-device-123']
        );

        $response->assertStatus(422);
    }

    // These should be sanitized and accepted
    $sanitizableSchemes = [
        'ftp://example.com', // Will be rejected due to scheme
        'file://example.com' // Will be rejected due to scheme
    ];

    foreach ($sanitizableSchemes as $url) {
        $response = $this->postJson('/api/urls',
            ['url' => $url],
            ['X-Device-ID' => 'test-device-123']
        );

        $response->assertStatus(422); // Still rejected due to uncommon scheme
    }
});

test('handles malformed urls', function () {
    $malformedUrls = [
        '',
        'not-a-url',
        'http://',
        'https://',
        'http://.',
        'http://..',
        'http://../',
        'http://?',
        'http://??/',
        'http://#',
        'http://##/',
        'http:// shouldfail.com',
        ':// should fail',
        'http://foo.bar?q=Spaces should be encoded'
    ];

    foreach ($malformedUrls as $url) {
        $response = $this->postJson('/api/urls',
            ['url' => $url],
            ['X-Device-ID' => 'test-device-123']
        );

        $response->assertStatus(422);
    }
});

test('handles unicode and international domain names', function () {
    $unicodeUrls = [
        'https://例え.テスト/path', // Japanese
        'https://пример.испытание/path', // Russian
        'https://例え.テスト', // Without path
    ];

    foreach ($unicodeUrls as $url) {
        $response = $this->postJson('/api/urls',
            ['url' => $url],
            ['X-Device-ID' => 'test-device-123']
        );

        // These should either succeed or fail gracefully with proper error
        expect($response->status())->toBeIn([201, 422]);
    }
});

test('device isolation - users can only see their own urls', function () {
    // Create URLs for different devices
    Url::create([
        'original_url' => 'https://device1.com',
        'short_code' => 'dev1code',
        'device_id' => 'device-1'
    ]);

    Url::create([
        'original_url' => 'https://device2.com',
        'short_code' => 'dev2code',
        'device_id' => 'device-2'
    ]);

    // Device 1 should only see their URL
    $response = $this->getJson('/api/urls', ['X-Device-ID' => 'device-1']);

    $response->assertStatus(200)
             ->assertJsonCount(1, 'data')
             ->assertJsonPath('data.0.original_url', 'https://device1.com');

    // Device 2 should only see their URL
    $response = $this->getJson('/api/urls', ['X-Device-ID' => 'device-2']);

    $response->assertStatus(200)
             ->assertJsonCount(1, 'data')
             ->assertJsonPath('data.0.original_url', 'https://device2.com');
});
