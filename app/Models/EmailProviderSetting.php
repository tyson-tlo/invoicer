<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $provider
 * @property string|null $from_name
 * @property string|null $from_address
 * @property string|null $account_email
 * @property string|null $google_client_id
 * @property string|null $google_client_secret
 * @property string|null $microsoft_client_id
 * @property string|null $microsoft_client_secret
 * @property string|null $microsoft_tenant
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property CarbonInterface|null $token_expires_at
 * @property string|null $api_key
 * @property CarbonInterface|null $connected_at
 */
#[Fillable([
    'provider',
    'from_name',
    'from_address',
    'account_email',
    'google_client_id',
    'google_client_secret',
    'microsoft_client_id',
    'microsoft_client_secret',
    'microsoft_tenant',
    'access_token',
    'refresh_token',
    'token_expires_at',
    'api_key',
    'connected_at',
])]
#[Hidden([
    'google_client_secret',
    'microsoft_client_secret',
    'access_token',
    'refresh_token',
    'api_key',
])]
class EmailProviderSetting extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'microsoft_tenant' => 'common',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'google_client_secret' => 'encrypted',
            'microsoft_client_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'api_key' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }
}
