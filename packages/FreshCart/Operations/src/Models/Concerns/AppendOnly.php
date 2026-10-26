<?php

namespace FreshCart\Operations\Models\Concerns;

use LogicException;

/**
 * Audit records may be created but never changed or removed.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function () {
            throw new LogicException('Audit records are append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new LogicException('Audit records are append-only and cannot be deleted.');
        });
    }
}
