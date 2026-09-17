<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public function get(int $companyId, string $key, $default = null)
    {
        $settings = $this->all($companyId);

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(int $companyId): array
    {
        return Cache::remember($this->cacheKey($companyId), 300, function () use ($companyId) {
            return Setting::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->pluck('value', 'key')
                ->all();
        });
    }

    public function set(int $companyId, string $key, $value, string $group = 'general'): void
    {
        Setting::query()->withoutGlobalScope('company')->updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            ['group' => $group, 'value' => $value === null ? null : (string) $value]
        );

        Cache::forget($this->cacheKey($companyId));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(int $companyId, array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            $this->set($companyId, $key, $value, $group);
        }
    }

    public function forgetCache(int $companyId): void
    {
        Cache::forget($this->cacheKey($companyId));
    }

    private function cacheKey(int $companyId): string
    {
        return 'sellix.settings.' . $companyId;
    }
}
