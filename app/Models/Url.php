<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $original_url
 * @property string $short_code
 * @property string $device_id
 * @property int $clicks
 * @property string|null $expires_at
 * @property string $created_at
 * @property string $updated_at
 */
class Url extends Model
{
    protected $fillable = [
        'original_url',
        'short_code',
        'device_id',
        'clicks',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function generateShortCode(): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $length = rand(6, 8);

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('short_code', $code)->exists());

        return $code;
    }
}
