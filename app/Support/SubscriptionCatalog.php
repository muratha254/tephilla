<?php

namespace App\Support;

class SubscriptionCatalog
{
    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_QUARTERLY = 'quarterly';
    public const PERIOD_SEMI_ANNUALLY = 'semi_annually';
    public const PERIOD_ANNUALLY = 'annually';
    public const PERIOD_CUSTOM = 'custom';

    public const STATUS_TRIAL = 'trial';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRING_SOON = 'expiring_soon';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DEACTIVATED = 'deactivated';

    public const ACTION_CREATED = 'created';
    public const ACTION_INVOICE_SENT = 'invoice_sent';
    public const ACTION_PAYMENT_RECORDED = 'payment_recorded';
    public const ACTION_REQUESTED = 'requested';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_RENEWED = 'renewed';
    public const ACTION_EXTENDED = 'extended';
    public const ACTION_PLAN_CHANGED = 'plan_changed';
    public const ACTION_SUSPENDED = 'suspended';
    public const ACTION_ACTIVATED = 'activated';
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_RESET = 'reset';
    public const ACTION_DEACTIVATED = 'deactivated';
    public const ACTION_BUSINESS_UPDATED = 'business_updated';

    /**
     * @return array<string, string>
     */
    public static function periods(): array
    {
        return [
            self::PERIOD_MONTHLY => 'Monthly',
            self::PERIOD_QUARTERLY => 'Quarterly',
            self::PERIOD_SEMI_ANNUALLY => 'Semi-annually',
            self::PERIOD_ANNUALLY => 'Annually',
            self::PERIOD_CUSTOM => 'Custom duration',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_TRIAL => 'Trial',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_EXPIRING_SOON => 'Expiring Soon',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_DEACTIVATED => 'Deactivated',
        ];
    }

    /**
     * Modules a plan can enable. Dashboard/users/settings/branches stay available
     * whenever the subscription itself allows access.
     *
     * @return array<string, string>
     */
    public static function features(): array
    {
        return [
            'pos' => 'POS',
            'products' => 'Products',
            'inventory' => 'Inventory',
            'sales' => 'Sales',
            'invoices' => 'Invoices',
            'purchases' => 'Purchases',
            'suppliers' => 'Suppliers',
            'customers' => 'Customers',
            'payments' => 'Payments',
            'quotations' => 'Quotations',
            'expenses' => 'Expenses',
            'accounting' => 'Accounting',
            'documents' => 'Documents',
            'hr' => 'Human resources',
            'manufacturing' => 'Manufacturing',
            'reports' => 'Reports',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function coreModules(): array
    {
        return ['dashboard', 'users', 'settings', 'branches'];
    }

    /**
     * @return array<int, string>
     */
    public static function allFeatureKeys(): array
    {
        return array_keys(self::features());
    }
}
