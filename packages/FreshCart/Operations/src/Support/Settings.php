<?php

namespace FreshCart\Operations\Support;

/**
 * Typed access to the FreshCart settings stored through Bagisto's configuration screen
 * (Configure → Operations). Defaults live in Config/system.php.
 */
class Settings
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = core()->getConfigData('operations.'.$key);

        return $value === null || $value === '' ? $default : $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function float(string $key, float $default = 0): float
    {
        return (float) $this->get($key, $default);
    }

    public function allowSelfApproval(): bool
    {
        return $this->bool('approvals.general.allow_self_approval');
    }

    /**
     * Value at or above which a document needs a second (management) approval.
     * Zero disables the second level.
     */
    public function levelTwoThreshold(string $document): float
    {
        return $this->float('approvals.thresholds.'.$document, 0);
    }

    public function interBranchApprovalRequired(): bool
    {
        return $this->bool('transfers.general.require_approval', true);
    }

    public function transferOverdueHours(): int
    {
        return $this->int('transfers.general.overdue_hours', 24);
    }

    public function overReceiptTolerancePercent(): float
    {
        return $this->float('receiving.general.over_receipt_tolerance', 0);
    }

    public function defaultReorderLevel(): int
    {
        return $this->int('inventory.alerts.default_reorder_level', 0);
    }

    public function lowStockCooldownHours(): int
    {
        return $this->int('inventory.alerts.cooldown_hours', 24);
    }

    public function expiryWarningDays(): int
    {
        return $this->int('retirement.criteria.expiry_warning_days', 7);
    }

    public function slowMovingDays(): int
    {
        return $this->int('retirement.criteria.slow_moving_days', 90);
    }

    public function slowMovingMaxUnits(): int
    {
        return $this->int('retirement.criteria.slow_moving_max_units', 0);
    }

    public function maxDiscountPercent(): float
    {
        return $this->float('promotions.general.max_discount_percent', 50);
    }

    public function promotionExpiryWarningDays(): int
    {
        return $this->int('promotions.general.expiry_warning_days', 2);
    }

    public function emailNotificationsEnabled(): bool
    {
        return $this->bool('notifications.general.email_enabled');
    }
}
