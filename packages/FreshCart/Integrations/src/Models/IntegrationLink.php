<?php

namespace FreshCart\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLink extends Model
{
    protected $table = 'fc_integration_links';

    protected $fillable = ['system', 'local_type', 'local_id', 'remote_id'];

    public static function remoteId(string $system, string $localType, string|int $localId): ?string
    {
        return static::where(compact('system') + ['local_type' => $localType, 'local_id' => (string) $localId])
            ->value('remote_id');
    }

    public static function localId(string $system, string $localType, string $remoteId): ?string
    {
        return static::where(compact('system') + ['local_type' => $localType, 'remote_id' => $remoteId])
            ->value('local_id');
    }

    public static function put(string $system, string $localType, string|int $localId, string|int $remoteId): void
    {
        static::updateOrCreate(
            ['system' => $system, 'local_type' => $localType, 'local_id' => (string) $localId],
            ['remote_id' => (string) $remoteId],
        );
    }

    public static function forget(string $system, string $localType, string|int $localId): void
    {
        static::where(compact('system') + ['local_type' => $localType, 'local_id' => (string) $localId])->delete();
    }
}
