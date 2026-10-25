<?php

namespace FreshCart\Operations\Support;

class StatusBadge
{
    public static function cssClass(?string $status): string
    {
        return match ($status) {
            'draft', 'nominated' => 'label-info',
            'submitted', 'pending_approval', 'quarantined', 'open', 'pending', 'scheduled' => 'label-pending',
            'issued', 'picking', 'in_transit', 'partially_received', 'partially_allocated', 'inspected', 'valued', 'classified', 'dispatched', 'partially_applied' => 'label-processing',
            'approved', 'received', 'posted', 'fulfilled', 'allocated', 'active', 'applied', 'completed', 'disposed', 'returned_to_supplier', 'resolved', 'converted', 'passed' => 'label-active',
            'rejected', 'cancelled', 'expired', 'closed_with_discrepancy', 'failed' => 'label-canceled',
            default => 'label-closed',
        };
    }

    public static function label(?string $status): string
    {
        return $status ? trans('operations::app.statuses.'.$status) : '—';
    }

    public static function html(?string $status): string
    {
        return '<span class="'.self::cssClass($status).'">'.e(self::label($status)).'</span>';
    }
}
