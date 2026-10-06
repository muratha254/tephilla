<?php

namespace App\Support;

use App\Models\Company;
use App\Services\SettingsService;

class RuntimeSettings
{
    /** @var array<string, mixed> */
    private $values;

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public static function forCompany(?int $companyId): self
    {
        if (! $companyId) {
            return new self([]);
        }

        try {
            return new self(app(SettingsService::class)->all($companyId));
        } catch (\Throwable $e) {
            return new self([]);
        }
    }

    public static function forLogin(): self
    {
        try {
            $company = Company::query()->orderBy('id')->first();

            return $company ? self::forCompany($company->id) : new self([]);
        } catch (\Throwable $e) {
            return new self([]);
        }
    }

    public function get(string $key, $default = null)
    {
        if (! array_key_exists($key, $this->values) || $this->values[$key] === null || $this->values[$key] === '') {
            return $default;
        }

        return $this->values[$key];
    }

    public function poweredByText(): string
    {
        return (string) $this->get('powered_by', 'Powered by TEPHILLAH SYSTEM');
    }

    public function poweredByWebsite(): string
    {
        return (string) $this->get('powered_by_website', '');
    }

    public function poweredByWebsiteUrl(): string
    {
        $website = $this->poweredByWebsite();
        if ($website === '') {
            return '#';
        }

        if (preg_match('#^https?://#i', $website)) {
            return $website;
        }

        return 'https://' . ltrim($website, '/');
    }

    public function poweredByEmail(): string
    {
        return (string) $this->get('powered_by_email', '');
    }
}
