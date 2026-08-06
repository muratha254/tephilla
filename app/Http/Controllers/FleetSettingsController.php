<?php

namespace App\Http\Controllers;

use App\Models\FleetSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FleetSettingsController extends Controller
{
    public function index()
    {
        return redirect()->route('settings.general');
    }

    public function general()
    {
        $settings = FleetSetting::current();

        return view('fleet.settings.general', array_merge($this->sharedViewData(), [
            'activeMenu' => 'settings-general',
            'openMenu' => 'settings',
            'settings' => $settings,
            'dateFormats' => FleetSetting::dateFormatOptions(),
            'timezones' => FleetSetting::timezoneOptions(),
            'themePresets' => FleetSetting::themePresets(),
        ]));
    }

    public function updateGeneral(Request $request)
    {
        $settings = FleetSetting::current();

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:2000',
            'date_format' => 'required|string|max:50',
            'auto_backup' => 'required|in:Enabled,Disabled',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'menu_position' => 'required|string|max:50',
            'booking_id_prefix' => 'nullable|string|max:50',
            'default_timezone' => 'required|string|max:100',
            'admin_primary_color' => 'required|string|max:20',
            'admin_secondary_color' => 'required|string|max:20',
            'sidebar_gradient_start' => 'required|string|max:20',
            'sidebar_gradient_end' => 'required|string|max:20',
            'sidebar_text_color' => 'required|string|max:20',
            'logo' => 'nullable|image|max:2048',
            'company_website' => 'nullable|string|max:255',
            'company_tax_pin' => 'nullable|string|max:100',
            'remove_logo' => 'nullable|boolean',
            'display_records_per_page' => 'nullable|string|max:20',
            'display_currency_symbol' => 'nullable|string|max:20',
            'invoice_prefix' => 'nullable|string|max:50',
            'documents_invoice_header' => 'nullable|string|max:2000',
            'documents_invoice_footer' => 'nullable|string|max:2000',
            'documents_invoice_header_image' => 'nullable|image|max:5120',
            'remove_documents_invoice_header_image' => 'nullable|boolean',
            'documents_invoice_footer_image' => 'nullable|image|max:5120',
            'remove_documents_invoice_footer_image' => 'nullable|boolean',
            'documents_receipt_header' => 'nullable|string|max:2000',
            'documents_receipt_footer' => 'nullable|string|max:2000',
            'documents_receipt_header_image' => 'nullable|image|max:5120',
            'remove_documents_receipt_header_image' => 'nullable|boolean',
            'documents_receipt_footer_image' => 'nullable|image|max:5120',
            'remove_documents_receipt_footer_image' => 'nullable|boolean',
            'documents_quotation_header' => 'nullable|string|max:2000',
            'documents_quotation_footer' => 'nullable|string|max:2000',
            'documents_quotation_header_image' => 'nullable|image|max:5120',
            'remove_documents_quotation_header_image' => 'nullable|boolean',
            'documents_quotation_footer_image' => 'nullable|image|max:5120',
            'remove_documents_quotation_footer_image' => 'nullable|boolean',
            'map_default_latitude' => 'nullable|string|max:50',
            'map_default_longitude' => 'nullable|string|max:50',
            'map_provider' => 'nullable|string|max:100',
            'mobile_app_name' => 'nullable|string|max:150',
            'mobile_android_url' => 'nullable|string|max:255',
            'mobile_ios_url' => 'nullable|string|max:255',
        ]);

        if ($request->boolean('remove_logo') && $settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('fleet/settings', 'public');
        }

        $options = $settings->options ?? [];
        $options['company'] = [
            'website' => $data['company_website'] ?? '',
            'tax_pin' => $data['company_tax_pin'] ?? '',
        ];
        $options['display'] = [
            'records_per_page' => $data['display_records_per_page'] ?? '25',
            'currency_symbol' => $data['display_currency_symbol'] ?? 'KSh',
        ];
        $options['invoice'] = [
            'invoice_prefix' => $data['invoice_prefix'] ?? 'INV-',
            'invoice_footer' => $data['documents_invoice_footer'] ?? '',
        ];
        $existingDocuments = $settings->options['documents'] ?? [];
        $options['documents'] = [];
        foreach (['invoice', 'receipt', 'quotation'] as $documentType) {
            $existingHeaderImage = data_get($existingDocuments, $documentType . '.header_image_path');
            $existingFooterImage = data_get($existingDocuments, $documentType . '.footer_image_path');

            $headerRemoveKey = 'remove_documents_' . $documentType . '_header_image';
            $headerUploadKey = 'documents_' . $documentType . '_header_image';
            $footerRemoveKey = 'remove_documents_' . $documentType . '_footer_image';
            $footerUploadKey = 'documents_' . $documentType . '_footer_image';

            if ($request->boolean($headerRemoveKey) && $existingHeaderImage) {
                Storage::disk('public')->delete($existingHeaderImage);
                $existingHeaderImage = null;
            }

            if ($request->hasFile($headerUploadKey)) {
                if ($existingHeaderImage) {
                    Storage::disk('public')->delete($existingHeaderImage);
                }

                $existingHeaderImage = $request->file($headerUploadKey)->store('fleet/document-headers', 'public');
            }

            if ($request->boolean($footerRemoveKey) && $existingFooterImage) {
                Storage::disk('public')->delete($existingFooterImage);
                $existingFooterImage = null;
            }

            if ($request->hasFile($footerUploadKey)) {
                if ($existingFooterImage) {
                    Storage::disk('public')->delete($existingFooterImage);
                }

                $existingFooterImage = $request->file($footerUploadKey)->store('fleet/document-footers', 'public');
            }

            $options['documents'][$documentType] = [
                'header_text' => $data['documents_' . $documentType . '_header'] ?? '',
                'footer_text' => $data['documents_' . $documentType . '_footer'] ?? '',
                'header_image_path' => $existingHeaderImage,
                'footer_image_path' => $existingFooterImage,
            ];
        }
        $options['map'] = [
            'default_latitude' => $data['map_default_latitude'] ?? '',
            'default_longitude' => $data['map_default_longitude'] ?? '',
            'map_provider' => $data['map_provider'] ?? 'OpenStreetMap',
        ];
        $options['mobile'] = [
            'app_name' => $data['mobile_app_name'] ?? '',
            'android_url' => $data['mobile_android_url'] ?? '',
            'ios_url' => $data['mobile_ios_url'] ?? '',
        ];

        $settings->fill([
            'company_name' => $data['company_name'],
            'address' => $data['address'] ?? null,
            'date_format' => $data['date_format'],
            'auto_backup' => $data['auto_backup'],
            'phone_number' => $data['phone_number'] ?? null,
            'email' => $data['email'] ?? null,
            'menu_position' => $data['menu_position'],
            'booking_id_prefix' => $data['booking_id_prefix'] ?? null,
            'default_timezone' => $data['default_timezone'],
            'admin_primary_color' => $data['admin_primary_color'],
            'admin_secondary_color' => $data['admin_secondary_color'],
            'sidebar_gradient_start' => $data['sidebar_gradient_start'],
            'sidebar_gradient_end' => $data['sidebar_gradient_end'],
            'sidebar_text_color' => $data['sidebar_text_color'],
            'options' => $options,
        ]);

        if (array_key_exists('logo_path', $data)) {
            $settings->logo_path = $data['logo_path'];
        } elseif (! empty($data['logo_path'] ?? null)) {
            $settings->logo_path = $data['logo_path'];
        }

        $settings->save();

        FleetSetting::clearCached();

        return redirect()
            ->route('settings.general')
            ->with('success', 'Settings saved successfully.');
    }

    public function smtp()
    {
        $settings = FleetSetting::current();
        $smtp = $settings->smtpOptions();

        return view('fleet.settings.smtp', array_merge($this->sharedViewData(), [
            'activeMenu' => 'settings-smtp',
            'openMenu' => 'settings',
            'smtp' => $smtp,
            'hasPassword' => ! empty($settings->option('smtp', 'password')),
        ]));
    }

    public function updateSmtp(Request $request)
    {
        $settings = FleetSetting::current();

        $data = $request->validate([
            'host' => 'required|string|max:255',
            'smtp_auth' => 'required|in:True,False',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'smtp_secure' => 'required|in:SSL,TLS,None',
            'port' => 'required|integer|min:1|max:65535',
        ]);

        $options = $settings->options ?? [];
        $existingPassword = $settings->option('smtp', 'password');

        $smtp = [
            'host' => $data['host'],
            'smtp_auth' => $data['smtp_auth'],
            'username' => $data['username'] ?? '',
            'smtp_secure' => $data['smtp_secure'],
            'port' => (string) $data['port'],
        ];

        if (! empty($data['password'])) {
            $smtp['password'] = \Illuminate\Support\Facades\Crypt::encryptString($data['password']);
        } elseif ($existingPassword) {
            $smtp['password'] = $existingPassword;
        }

        $options['smtp'] = $smtp;
        $settings->options = $options;
        $settings->save();

        FleetSetting::applyMailConfig();

        return redirect()
            ->route('settings.smtp')
            ->with('success', 'SMTP configuration saved successfully.');
    }

    public function placeholder(string $section)
    {
        $labels = [
            'cron' => 'Cron Settings',
            'menu-ordering' => 'Menu Ordering',
            'languages' => 'Languages',
            'email-template' => 'Email Template',
            'sms' => 'SMS Configuration',
        ];

        $label = $labels[$section] ?? 'Settings';

        return view('fleet.settings.placeholder', array_merge($this->sharedViewData(), [
            'activeMenu' => 'settings-' . str_replace('-', '_', $section),
            'openMenu' => 'settings',
            'sectionLabel' => $label,
        ]));
    }

    private function sharedViewData(): array
    {
        return fleet_shared_view_data(5);
    }
}
