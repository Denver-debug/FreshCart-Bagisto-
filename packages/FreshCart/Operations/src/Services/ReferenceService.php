<?php

namespace FreshCart\Operations\Services;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free, per-year document numbers such as PR-2026-000001.
 */
class ReferenceService
{
    public function next(string $prefix): string
    {
        $period = now()->format('Y');

        return DB::transaction(function () use ($prefix, $period) {
            DB::table('fc_sequences')->insertOrIgnore([
                'key' => $prefix,
                'period' => $period,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('fc_sequences')
                ->where('key', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            DB::table('fc_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'next_value' => $sequence->next_value + 1,
                    'updated_at' => now(),
                ]);

            return sprintf('%s-%s-%06d', $prefix, $period, $sequence->next_value);
        });
    }
}
