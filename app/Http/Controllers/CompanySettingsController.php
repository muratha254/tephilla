<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SettingLookup;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanySettingsController extends Controller
{
    public function general(Request $request, SettingsService $settings)
    {
        $user = auth()->user();
        $company = $user->company;

        abort_unless($user->hasPermission('settings.view') || $user->hasPermission('settings.company'), 403);

        $tab = $request->input('tab', 'site');
        if (! in_array($tab, ['site', 'sales', 'prefixes', 'documents'], true)) {
            $tab = 'site';
        }

        $site = [];
        foreach ($this->siteDefaults() as $key => $default) {
            $site[$key] = $settings->get($company->id, $key, $default);
        }

        return view('settings.general', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.general',
            'company' => $company,
            'tab' => $tab,
            'site' => $site,
            'currencies' => SettingLookup::query()->ofType(SettingLookup::TYPE_CURRENCY)->where('is_active', true)->orderBy('name')->get(),
            'systemVersion' => config('app.version', '4.0.0'),
            'canUpdate' => $user->hasPermission('settings.company'),
        ]));
    }

    public function updateGeneral(Request $request, SettingsService $settings)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('settings.company'), 403);

        $company = Company::query()->withoutGlobalScope('company')->findOrFail($user->company_id);
        $tab = $request->input('tab', 'site');

        if ($tab === 'sales') {
            $settings->setMany($company->id, $request->validate([
                'sales_default_tax' => 'nullable|string|max:64',
                'sales_allow_discount' => 'required|in:Yes,No',
                'sales_allow_negative_stock' => 'required|in:Yes,No',
                'sales_round_off' => 'required|in:Yes,No',
                'sales_invoice_terms' => 'nullable|string|max:2000',
                'sales_default_payment' => 'required|string|max:64',
            ]), 'site_sales');

            return redirect()->route('settings.general', ['tab' => 'sales'])->with('success', 'Sales settings saved.');
        }

        if ($tab === 'documents') {
            $request->validate([
                'invoice_header' => 'nullable|image|max:5120',
                'invoice_footer' => 'nullable|image|max:5120',
                'quotation_header' => 'nullable|image|max:5120',
                'quotation_footer' => 'nullable|image|max:5120',
                'remove_invoice_header' => 'nullable|boolean',
                'remove_invoice_footer' => 'nullable|boolean',
                'remove_quotation_header' => 'nullable|boolean',
                'remove_quotation_footer' => 'nullable|boolean',
            ]);

            $settingValues = [];
            foreach (['invoice_header', 'invoice_footer', 'quotation_header', 'quotation_footer'] as $input) {
                $settingKey = $input . '_path';
                if ($request->boolean('remove_' . $input)) {
                    $current = $settings->get($company->id, $settingKey);
                    if ($current) {
                        Storage::disk('public')->delete($current);
                    }
                    $settingValues[$settingKey] = null;
                }
                if ($request->hasFile($input)) {
                    $current = $settings->get($company->id, $settingKey);
                    if ($current) {
                        Storage::disk('public')->delete($current);
                    }
                    $settingValues[$settingKey] = $request->file($input)->store('document-letterheads', 'public');
                }
            }

            if ($settingValues !== []) {
                $settings->setMany($company->id, $settingValues, 'documents');
            }

            return redirect()->route('settings.general', ['tab' => 'documents'])->with('success', 'Invoice and quotation letterheads saved.');
        }

        if ($tab === 'prefixes') {
            $settings->setMany($company->id, $request->validate([
                'invoice_prefix' => 'required|string|max:20',
                'receipt_prefix' => 'required|string|max:20',
                'quotation_prefix' => 'required|string|max:20',
                'po_prefix' => 'required|string|max:20',
                'grn_prefix' => 'required|string|max:20',
                'expense_prefix' => 'required|string|max:20',
                'receipt_paper_size' => 'required|in:58mm,80mm,a4',
                'powered_by' => 'nullable|string|max:255',
                'powered_by_website' => 'nullable|string|max:255',
                'powered_by_email' => 'nullable|email|max:255',
            ]), 'site_prefixes');

            return redirect()->route('settings.general', ['tab' => 'prefixes'])->with('success', 'Prefix settings saved.');
        }

        $data = $request->validate([
            'site_name' => 'required|string|max:120',
            'timezone' => 'required|string|max:64',
            'date_format_ui' => 'required|in:dd-mm-yyyy,mm-dd-yyyy,yyyy-mm-dd',
            'time_format' => 'required|in:24 Hours,12 Hours',
            'currency_code' => 'required|string|max:16',
            'currency_symbol' => 'required|string|max:16',
            'currency_placement' => 'required|in:Before Amount,After Amount',
            'logo_width' => 'nullable|string|max:20',
            'logo_height' => 'nullable|string|max:20',
            'logo' => 'nullable|image|max:2048',
            'letter_head' => 'nullable|image|max:4096',
            'letter_footer' => 'nullable|image|max:4096',
            'login_wallpaper' => 'nullable|image|max:5120',
            'e_signature' => 'nullable|image|max:2048',
            'remove_logo' => 'nullable|boolean',
            'remove_letter_head' => 'nullable|boolean',
            'remove_letter_footer' => 'nullable|boolean',
            'remove_login_wallpaper' => 'nullable|boolean',
            'remove_e_signature' => 'nullable|boolean',
        ]);

        $dateMap = [
            'dd-mm-yyyy' => 'd-m-Y',
            'mm-dd-yyyy' => 'm-d-Y',
            'yyyy-mm-dd' => 'Y-m-d',
        ];

        $company->update([
            'timezone' => $data['timezone'],
            'date_format' => $dateMap[$data['date_format_ui']] ?? 'd-m-Y',
            'currency_code' => $data['currency_code'],
            'currency_symbol' => $data['currency_symbol'],
        ]);

        $settingValues = [
            'site_name' => $data['site_name'],
            'time_format' => $data['time_format'],
            'date_format_ui' => $data['date_format_ui'],
            'currency_placement' => $data['currency_placement'],
            'logo_width' => $data['logo_width'] ?? '150px',
            'logo_height' => $data['logo_height'] ?? '80px',
        ];

        if ($request->boolean('remove_logo') && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $company->update(['logo_path' => null]);
        }
        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $company->update(['logo_path' => $request->file('logo')->store('company-logos', 'public')]);
        }

        foreach (['letter_head', 'letter_footer', 'login_wallpaper', 'e_signature'] as $input) {
            $settingKey = $input . '_path';
            if ($request->boolean('remove_' . $input)) {
                $current = $settings->get($company->id, $settingKey);
                if ($current) {
                    Storage::disk('public')->delete($current);
                }
                $settingValues[$settingKey] = null;
            }
            if ($request->hasFile($input)) {
                $current = $settings->get($company->id, $settingKey);
                if ($current) {
                    Storage::disk('public')->delete($current);
                }
                $settingValues[$settingKey] = $request->file($input)->store('site-assets', 'public');
            }
        }

        $settings->setMany($company->id, $settingValues, 'site');

        return redirect()->route('settings.general', ['tab' => 'site'])->with('success', 'Site settings saved.');
    }

    public function company(Request $request, SettingsService $settings)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('settings.view') || $user->hasPermission('settings.company'), 403);

        $company = $user->company;
        $tab = $request->input('tab', 'basic');
        if (! in_array($tab, ['basic', 'receipt', 'till'], true)) {
            $tab = 'basic';
        }

        $profile = [];
        foreach ($this->profileDefaults() as $key => $default) {
            $profile[$key] = $settings->get($company->id, $key, $default);
        }

        return view('settings.company', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'settings.company',
            'company' => $company,
            'tab' => $tab,
            'profile' => $profile,
            'counties' => SettingLookup::query()->ofType(SettingLookup::TYPE_COUNTY)->where('is_active', true)->orderBy('name')->get(),
            'cities' => SettingLookup::query()->ofType(SettingLookup::TYPE_CITY)->where('is_active', true)->orderBy('name')->get(),
            'canUpdate' => $user->hasPermission('settings.company'),
        ]));
    }

    public function updateCompany(Request $request, SettingsService $settings)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('settings.company'), 403);

        $company = Company::query()->withoutGlobalScope('company')->findOrFail($user->company_id);
        $tab = $request->input('tab', 'basic');

        if ($tab === 'receipt') {
            $settings->setMany($company->id, $request->validate([
                'receipt_control' => 'required|string|max:64',
                'choose_print' => 'required|string|max:64',
                'captain_category' => 'required|string|max:64',
                'display_logo' => 'required|in:Yes,No',
                'display_name' => 'required|in:Yes,No',
                'display_address' => 'required|in:Yes,No',
                'display_phone' => 'required|in:Yes,No',
                'display_postcode' => 'required|in:Yes,No',
                'display_email' => 'required|in:Yes,No',
                'printer_type' => 'required|string|max:64',
                'display_vat_kra' => 'required|in:Yes,No',
                'display_tax_calc' => 'required|in:Yes,No',
                'display_prev_bal' => 'required|in:Yes,No',
                'display_standing_bal' => 'required|in:Yes,No',
                'receipt_footer' => 'nullable|string|max:500',
                'thermal_receipt_style' => 'nullable|string|max:40',
                'thermal_font_size' => 'nullable|string|max:20',
                'thermal_font_family' => 'nullable|string|max:40',
            ]), 'receipt');

            return redirect()->route('settings.company', ['tab' => 'receipt'])->with('success', 'Receipt settings saved.');
        }

        if ($tab === 'till') {
            $settings->setMany($company->id, $request->validate([
                'till_paybill' => 'nullable|string|max:64',
                'till_account_no' => 'nullable|string|max:64',
                'till2_paybill' => 'nullable|string|max:64',
                'till2_account_no' => 'nullable|string|max:64',
                'till2_display' => 'nullable|string|max:120',
                'till_branch_desc' => 'nullable|string|max:120',
                'bank_account_name' => 'nullable|string|max:120',
                'bank_name' => 'nullable|string|max:120',
                'bank_account_no' => 'nullable|string|max:64',
                'bank_branch' => 'nullable|string|max:120',
                'bank_code' => 'nullable|string|max:40',
                'bank_swift' => 'nullable|string|max:40',
            ]), 'till');

            return redirect()->route('settings.company', ['tab' => 'till'])->with('success', 'Till/Paybill settings saved.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'phone_alt' => 'nullable|string|max:50',
            'email' => 'required|email|max:255',
            'tax_pin' => 'nullable|string|max:100',
            'vat_number' => 'nullable|string|max:100',
            'employer_code' => 'nullable|string|max:64',
            'website' => 'nullable|string|max:255',
            'bank_details' => 'nullable|string|max:5000',
            'quotation_terms' => 'nullable|string|max:5000',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:40',
            'address' => 'required|string|max:2000',
            'logo' => 'nullable|image|max:2048',
            'remove_logo' => 'nullable|boolean',
            'customer_setup' => 'required|in:Yes,No',
            'login_ui' => 'required|string|max:64',
            'waiting_time' => 'required|integer|min:0|max:1440',
            'expiry_alert_days' => 'required|integer|min:0|max:3650',
            'loyalty_points_rate' => 'required|numeric|min:0',
            'allow_order_sale' => 'required|in:Yes,No',
            'allow_credit_sale' => 'required|in:Yes,No',
            'show_qty_pos' => 'required|in:Yes,No',
            'show_cost_pos' => 'required|in:Yes,No',
            'show_uom_pos' => 'required|in:Yes,No',
            'show_disc_pos' => 'required|in:Yes,No',
            'menu_order' => 'required|string|max:64',
            'quotation_module' => 'required|in:Yes,No',
            'delivery_note' => 'required|in:Yes,No',
            'task_module' => 'required|in:Yes,No',
            'enable_one_off_sale' => 'required|in:Yes,No',
            'capture_serial' => 'required|in:Yes,No',
            'duplicate_item_pos' => 'required|in:Yes,No',
            'item_cost_control' => 'required|string|max:64',
            'shift_control' => 'required|string|max:120',
            'default_price' => 'required|string|max:64',
        ]);

        if ($request->boolean('remove_logo') && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $companyFields = [
            'name', 'phone', 'phone_alt', 'email', 'tax_pin', 'vat_number', 'employer_code',
            'website', 'bank_details', 'quotation_terms', 'country', 'state', 'city', 'postcode', 'address', 'logo_path',
        ];
        $companyData = array_intersect_key($data, array_flip($companyFields));
        $companyData['slug'] = Str::slug($companyData['name']) ?: $company->slug;
        $company->update($companyData);

        $settingKeys = [
            'customer_setup', 'login_ui', 'waiting_time', 'expiry_alert_days', 'loyalty_points_rate',
            'allow_order_sale', 'allow_credit_sale', 'show_qty_pos', 'show_cost_pos', 'show_uom_pos',
            'show_disc_pos', 'menu_order', 'quotation_module', 'delivery_note', 'task_module',
            'enable_one_off_sale', 'capture_serial', 'duplicate_item_pos', 'item_cost_control',
            'shift_control', 'default_price',
        ];
        $settings->setMany($company->id, array_intersect_key($data, array_flip($settingKeys)), 'company_profile');

        return redirect()->route('settings.company', ['tab' => 'basic'])->with('success', 'Company profile saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function siteDefaults(): array
    {
        return [
            'site_name' => fleet_system_name(),
            'time_format' => '24 Hours',
            'date_format_ui' => 'dd-mm-yyyy',
            'currency_placement' => 'Before Amount',
            'logo_width' => '150px',
            'logo_height' => '80px',
            'letter_head_path' => '',
            'letter_footer_path' => '',
            'invoice_header_path' => '',
            'invoice_footer_path' => '',
            'quotation_header_path' => '',
            'quotation_footer_path' => '',
            'login_wallpaper_path' => '',
            'e_signature_path' => '',
            'sales_default_tax' => '',
            'sales_allow_discount' => 'Yes',
            'sales_allow_negative_stock' => 'No',
            'sales_round_off' => 'No',
            'sales_invoice_terms' => '',
            'sales_default_payment' => 'Cash',
            'invoice_prefix' => 'INV-',
            'receipt_prefix' => 'RCP-',
            'quotation_prefix' => 'QT-',
            'po_prefix' => 'PO-',
            'grn_prefix' => 'GRN-',
            'expense_prefix' => 'EXP-',
            'receipt_paper_size' => '80mm',
            'powered_by' => 'Powered by TEPHILLA SYSTEM',
            'powered_by_website' => '',
            'powered_by_email' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profileDefaults(): array
    {
        return [
            'customer_setup' => 'No',
            'login_ui' => 'Touch Login2',
            'waiting_time' => '15',
            'expiry_alert_days' => '5',
            'loyalty_points_rate' => '0',
            'allow_order_sale' => 'No',
            'allow_credit_sale' => 'Yes',
            'show_qty_pos' => 'Yes',
            'show_cost_pos' => 'No',
            'show_uom_pos' => 'No',
            'show_disc_pos' => 'No',
            'menu_order' => 'Fast Moving',
            'quotation_module' => 'No',
            'delivery_note' => 'No',
            'task_module' => 'No',
            'enable_one_off_sale' => 'No',
            'capture_serial' => 'Yes',
            'duplicate_item_pos' => 'No',
            'item_cost_control' => 'Get Average',
            'shift_control' => 'When Shift Invoices are fully settled',
            'default_price' => 'RETAIL PRICE',
            'receipt_control' => 'Print One Copy',
            'choose_print' => 'Invoice Only',
            'captain_category' => 'Captain Without Price',
            'display_logo' => 'No',
            'display_name' => 'Yes',
            'display_address' => 'Yes',
            'display_phone' => 'No',
            'display_postcode' => 'No',
            'display_email' => 'No',
            'printer_type' => 'Thermal Printer 80mm',
            'display_vat_kra' => 'Yes',
            'display_tax_calc' => 'No',
            'display_prev_bal' => 'No',
            'display_standing_bal' => 'Yes',
            'receipt_footer' => '~~Thank You.....Come Again.~~',
            'thermal_receipt_style' => 'dashed',
            'thermal_font_size' => '10px',
            'thermal_font_family' => 'Tahoma',
            'till_paybill' => '0',
            'till_account_no' => '',
            'till2_paybill' => '',
            'till2_account_no' => '',
            'till2_display' => '',
            'till_branch_desc' => '',
            'bank_account_name' => '',
            'bank_name' => '',
            'bank_account_no' => '',
            'bank_branch' => '',
            'bank_code' => '',
            'bank_swift' => '',
        ];
    }
}
