<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FleetSetting extends Model
{
    protected $fillable = [
        'company_name',
        'address',
        'date_format',
        'auto_backup',
        'phone_number',
        'email',
        'menu_position',
        'booking_id_prefix',
        'default_timezone',
        'admin_primary_color',
        'admin_secondary_color',
        'sidebar_gradient_start',
        'sidebar_gradient_end',
        'sidebar_text_color',
        'logo_path',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    protected static $cachedInstance = null;

    public static function clearCached(): void
    {
        static::$cachedInstance = null;
    }

    public static function current(): self
    {
        if (static::$cachedInstance instanceof self) {
            return static::$cachedInstance;
        }

        if (! Schema::hasTable('fleet_settings')) {
            return static::$cachedInstance = new self([
                'company_name' => 'One Translines Pvt Ltd',
                'date_format' => 'd/m/Y',
                'admin_primary_color' => '#3498DB',
                'admin_secondary_color' => '#2980B9',
                'sidebar_gradient_start' => '#2C3E50',
                'sidebar_gradient_end' => '#1A252F',
                'sidebar_text_color' => '#ECF0F1',
            ]);
        }

        $settings = static::query()->first();

        if (! $settings) {
            $settings = static::query()->create([
                'company_name' => 'One Translines Pvt Ltd',
                'date_format' => 'd/m/Y',
            ]);
        }

        return static::$cachedInstance = $settings;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : null;
    }

    public function logoPdfPath(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $path = storage_path('app/public/' . ltrim($this->logo_path, '/'));

        return is_file($path) ? $path : null;
    }

    public function companyProfile(): array
    {
        return [
            'companyName' => trim((string) $this->company_name) ?: 'Your Company Name',
            'companyAddress' => trim((string) $this->address) ?: '-',
            'companyPhone' => trim((string) $this->phone_number) ?: '-',
            'companyEmail' => trim((string) $this->email) ?: '-',
            'companyWebsite' => trim((string) $this->option('company', 'website', '')) ?: '',
            'companyTaxPin' => trim((string) $this->option('company', 'tax_pin', '')) ?: '',
            'invoiceFooter' => $this->documentText('invoice', 'footer'),
            'logo_url' => $this->logoUrl(),
            'logo_pdf_path' => $this->logoPdfPath(),
        ];
    }

    public static function defaultDocumentOptions(): array
    {
        return [
            'invoice' => [
                'header_text' => '',
                'header_image_path' => '',
                'footer_text' => 'Thank you for your business.',
                'footer_image_path' => '',
            ],
            'receipt' => [
                'header_text' => '',
                'header_image_path' => '',
                'footer_text' => 'Thank you for your payment. This receipt confirms payment received by {company_name}.',
                'footer_image_path' => '',
            ],
            'quotation' => [
                'header_text' => '',
                'header_image_path' => '',
                'footer_text' => 'This quotation is valid for 30 days unless otherwise stated.',
                'footer_image_path' => '',
            ],
        ];
    }

    public function documentText(string $type, string $part): string
    {
        $defaults = self::defaultDocumentOptions();
        $default = $defaults[$type][$part . '_text'] ?? '';

        $value = trim((string) $this->option('documents', $type . '.' . $part . '_text', ''));

        if ($value === '' && $type === 'invoice' && $part === 'footer') {
            $legacy = trim((string) $this->option('invoice', 'invoice_footer', ''));

            return $legacy !== '' ? $legacy : $default;
        }

        return $value !== '' ? $value : $default;
    }

    public function resolvedDocumentText(string $type, string $part): string
    {
        $text = $this->documentText($type, $part);
        $companyName = trim((string) $this->company_name) ?: 'Your Company Name';

        return str_replace('{company_name}', $companyName, $text);
    }

    public function documentHeaderImagePath(string $type): ?string
    {
        $path = trim((string) $this->option('documents', $type . '.header_image_path', ''));

        return $path !== '' ? $path : null;
    }

    public function documentHeaderImageUrl(string $type): ?string
    {
        $path = $this->documentHeaderImagePath($type);

        return $path ? asset('storage/' . $path) : null;
    }

    public function documentHeaderImagePdfPath(string $type): ?string
    {
        $path = $this->documentHeaderImagePath($type);

        if (! $path) {
            return null;
        }

        $fullPath = storage_path('app/public/' . ltrim($path, '/'));

        return is_file($fullPath) ? $fullPath : null;
    }

    public function documentFooterImagePath(string $type): ?string
    {
        $path = trim((string) $this->option('documents', $type . '.footer_image_path', ''));

        return $path !== '' ? $path : null;
    }

    public function documentFooterImageUrl(string $type): ?string
    {
        $path = $this->documentFooterImagePath($type);

        return $path ? asset('storage/' . $path) : null;
    }

    public function documentFooterImagePdfPath(string $type): ?string
    {
        $path = $this->documentFooterImagePath($type);

        if (! $path) {
            return null;
        }

        $fullPath = storage_path('app/public/' . ltrim($path, '/'));

        return is_file($fullPath) ? $fullPath : null;
    }

    public function documentImageHeightMm(?string $absolutePath, float $pageWidthMm = 210.0): ?float
    {
        if (! $absolutePath || ! is_file($absolutePath)) {
            return null;
        }

        $size = @getimagesize($absolutePath);

        if (! is_array($size) || ($size[0] ?? 0) <= 0) {
            return null;
        }

        return round(((float) $size[1] / (float) $size[0]) * $pageWidthMm, 1);
    }

    public function documentHeaderImageHeightMm(string $type): ?float
    {
        return $this->documentImageHeightMm($this->documentHeaderImagePdfPath($type));
    }

    public function documentFooterImageHeightMm(string $type): ?float
    {
        return $this->documentImageHeightMm($this->documentFooterImagePdfPath($type));
    }

    public function documentImageHeightPt(?string $absolutePath, float $pageWidthMm = 210.0): ?float
    {
        $heightMm = $this->documentImageHeightMm($absolutePath, $pageWidthMm);

        return $heightMm !== null ? round($heightMm * 72 / 25.4, 2) : null;
    }

    public function documentHeaderImageHeightPt(string $type): ?float
    {
        return $this->documentImageHeightPt($this->documentHeaderImagePdfPath($type));
    }

    public function documentFooterImageHeightPt(string $type): ?float
    {
        return $this->documentImageHeightPt($this->documentFooterImagePdfPath($type));
    }

    public function documentProfile(string $type): array
    {
        $profile = $this->companyProfile();
        $header = $this->resolvedDocumentText($type, 'header');
        $footer = $this->resolvedDocumentText($type, 'footer');
        $headerImagePdfPath = $this->documentHeaderImagePdfPath($type);
        $footerImagePdfPath = $this->documentFooterImagePdfPath($type);

        return array_merge($profile, [
            'documentHeader' => $header,
            'documentFooter' => $footer,
            'documentHeaderImageUrl' => $this->documentHeaderImageUrl($type),
            'documentHeaderImagePdfPath' => $headerImagePdfPath,
            'hasDocumentHeaderImage' => (bool) $headerImagePdfPath,
            'documentFooterImageUrl' => $this->documentFooterImageUrl($type),
            'documentFooterImagePdfPath' => $footerImagePdfPath,
            'hasDocumentFooterImage' => (bool) $footerImagePdfPath,
            'documentHeaderImageHeightMm' => $this->documentHeaderImageHeightMm($type),
            'documentFooterImageHeightMm' => $this->documentFooterImageHeightMm($type),
            'documentHeaderImageHeightPt' => $this->documentHeaderImageHeightPt($type),
            'documentFooterImageHeightPt' => $this->documentFooterImageHeightPt($type),
            'documentFooterIsCustom' => trim((string) $this->option('documents', $type . '.footer_text', '')) !== '',
            'invoiceFooter' => $type === 'invoice' ? $footer : ($profile['invoiceFooter'] ?? ''),
        ]);
    }

    public function option(string $group, string $key, $default = null)
    {
        return data_get($this->options, $group . '.' . $key, $default);
    }

    public static function defaultSmtpOptions(): array
    {
        return [
            'host' => 'smtp.gmail.com',
            'smtp_auth' => 'True',
            'username' => '',
            'password' => '',
            'smtp_secure' => 'SSL',
            'port' => '465',
        ];
    }

    public function smtpOptions(): array
    {
        return array_merge(self::defaultSmtpOptions(), $this->options['smtp'] ?? []);
    }

    public function smtpPassword(): ?string
    {
        $encrypted = $this->option('smtp', 'password');

        if (! $encrypted) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function applyMailConfig(): void
    {
        if (! Schema::hasTable('fleet_settings')) {
            return;
        }

        $settings = static::current();
        $smtp = $settings->smtpOptions();

        if (empty($smtp['host'])) {
            return;
        }

        $encryption = null;
        if (($smtp['smtp_secure'] ?? '') === 'SSL') {
            $encryption = 'ssl';
        } elseif (($smtp['smtp_secure'] ?? '') === 'TLS') {
            $encryption = 'tls';
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $smtp['host'],
            'mail.mailers.smtp.port' => (int) ($smtp['port'] ?: 587),
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.username' => ($smtp['smtp_auth'] ?? 'True') === 'True' ? ($smtp['username'] ?? null) : null,
            'mail.mailers.smtp.password' => ($smtp['smtp_auth'] ?? 'True') === 'True' ? $settings->smtpPassword() : null,
        ]);
    }

    public function timezoneLabel(): string
    {
        $map = collect(self::timezoneOptions())->firstWhere('value', $this->default_timezone);

        return $map['label'] ?? $this->default_timezone;
    }

    public static function dateFormatOptions(): array
    {
        return [
            'd/m/Y' => 'dd/mm/yyyy',
            'd/m/Y H:i' => 'dd/mm/yyyy HH:mm',
            'Y-m-d' => 'yyyy-mm-dd',
            'Y-m-d H:i' => 'yyyy-mm-dd HH:mm',
            'm/d/Y h:i A' => 'mm/dd/yyyy hh:mm AM/PM',
            'd M Y, H:i' => 'dd Mon yyyy, HH:mm',
        ];
    }

    public static function timezoneOptions(): array
    {
        return [
            ['value' => 'Asia/Kolkata', 'label' => 'UTC +5.5 - Asia/Kolkata'],
            ['value' => 'Africa/Nairobi', 'label' => 'UTC +3 - Africa/Nairobi'],
            ['value' => 'UTC', 'label' => 'UTC +0 - UTC'],
            ['value' => 'Europe/London', 'label' => 'UTC +0 - Europe/London'],
            ['value' => 'America/New_York', 'label' => 'UTC -5 - America/New_York'],
        ];
    }

    public static function themePresets(): array
    {
        return [
            'midnight_executive' => [
                'label' => 'Midnight Executive',
                'admin_primary_color' => '#3498DB',
                'admin_secondary_color' => '#2980B9',
                'sidebar_gradient_start' => '#2C3E50',
                'sidebar_gradient_end' => '#1A252F',
                'sidebar_text_color' => '#ECF0F1',
            ],
            'corporate_slate' => [
                'label' => 'Corporate Slate',
                'admin_primary_color' => '#5D6D7E',
                'admin_secondary_color' => '#34495E',
                'sidebar_gradient_start' => '#34495E',
                'sidebar_gradient_end' => '#2C3E50',
                'sidebar_text_color' => '#F8F9FA',
            ],
            'modern_teal' => [
                'label' => 'Modern Teal',
                'admin_primary_color' => '#1ABC9C',
                'admin_secondary_color' => '#16A085',
                'sidebar_gradient_start' => '#117A65',
                'sidebar_gradient_end' => '#0E6655',
                'sidebar_text_color' => '#E8F8F5',
            ],
            'deep_indigo' => [
                'label' => 'Deep Indigo',
                'admin_primary_color' => '#5B6CFF',
                'admin_secondary_color' => '#4A56E2',
                'sidebar_gradient_start' => '#2E3192',
                'sidebar_gradient_end' => '#1B1F5E',
                'sidebar_text_color' => '#EEF0FF',
            ],
            'onyx_gold' => [
                'label' => 'Onyx & Gold',
                'admin_primary_color' => '#D4AF37',
                'admin_secondary_color' => '#B7950B',
                'sidebar_gradient_start' => '#1C1C1C',
                'sidebar_gradient_end' => '#0F0F0F',
                'sidebar_text_color' => '#F5F5F5',
            ],
        ];
    }
}
