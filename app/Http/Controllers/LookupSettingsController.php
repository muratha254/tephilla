<?php

namespace App\Http\Controllers;

use App\Models\SettingLookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LookupSettingsController extends Controller
{
    private array $configs = [
        'salutations' => [
            'type' => SettingLookup::TYPE_SALUTATION,
            'title' => 'Salutation',
            'list_title' => 'Salutation List',
            'list_subtitle' => 'View/Search Salutation',
            'name_label' => 'Salutation Name',
            'add_label' => 'Add Salutation',
            'menu' => 'settings.salutation',
            'has_code' => false,
            'has_symbol' => false,
            'has_parent' => false,
        ],
        'progress-statuses' => [
            'type' => SettingLookup::TYPE_PROGRESS,
            'title' => 'Progress Status',
            'list_title' => 'Progress Status List',
            'list_subtitle' => 'View/Search Progress Status',
            'name_label' => 'Status Name',
            'add_label' => 'Add Status',
            'menu' => 'settings.progress',
            'has_code' => false,
            'has_symbol' => false,
            'has_parent' => false,
        ],
        'currencies' => [
            'type' => SettingLookup::TYPE_CURRENCY,
            'title' => 'Currency',
            'list_title' => 'Currencies List',
            'list_subtitle' => 'View/Search Items Currency',
            'name_label' => 'Currency Name',
            'add_label' => 'Add Currency',
            'menu' => 'settings.currency',
            'has_code' => true,
            'has_symbol' => true,
            'has_rate' => true,
            'has_parent' => false,
            'code_label' => 'Currency Code',
            'symbol_label' => 'Currency',
            'rate_label' => 'Rate (In ref to Default)',
        ],
        'counties' => [
            'type' => SettingLookup::TYPE_COUNTY,
            'title' => 'County',
            'list_title' => 'Counties List',
            'list_subtitle' => 'View/Search County',
            'name_label' => 'County Name',
            'add_label' => 'Add County',
            'menu' => 'settings.places.counties',
            'has_code' => false,
            'has_symbol' => false,
            'has_parent' => false,
        ],
        'cities' => [
            'type' => SettingLookup::TYPE_CITY,
            'title' => 'City / Town',
            'list_title' => 'Cities / Towns List',
            'list_subtitle' => 'View/Search City',
            'name_label' => 'City Name',
            'add_label' => 'Add City',
            'menu' => 'settings.places.cities',
            'has_code' => false,
            'has_symbol' => false,
            'has_parent' => true,
            'parent_type' => SettingLookup::TYPE_COUNTY,
            'parent_label' => 'County',
        ],
    ];

    public function index(string $kind)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);
        $config = $this->config($kind);

        $items = SettingLookup::query()
            ->ofType($config['type'])
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('settings.lookups.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => $config['menu'],
            'kind' => $kind,
            'config' => $config,
            'items' => $items,
        ]));
    }

    public function create(string $kind)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);
        $config = $this->config($kind);

        return view('settings.lookups.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => $config['menu'],
            'kind' => $kind,
            'config' => $config,
            'item' => new SettingLookup(['is_active' => true, 'type' => $config['type']]),
            'parents' => $this->parents($config),
        ]));
    }

    public function store(Request $request, string $kind)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);
        $config = $this->config($kind);
        $data = $this->validated($request, $config);

        SettingLookup::query()->create(array_merge($data, [
            'company_id' => auth()->user()->company_id,
            'type' => $config['type'],
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
            'rate' => $data['rate'] ?? 0,
        ]));

        return redirect()->route('settings.lookups', $kind)->with('success', $config['title'] . ' saved.');
    }

    public function edit(string $kind, SettingLookup $lookup)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);
        $config = $this->config($kind);
        abort_unless($lookup->type === $config['type'], 404);

        return view('settings.lookups.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => $config['menu'],
            'kind' => $kind,
            'config' => $config,
            'item' => $lookup,
            'parents' => $this->parents($config),
        ]));
    }

    public function update(Request $request, string $kind, SettingLookup $lookup)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);
        $config = $this->config($kind);
        abort_unless($lookup->type === $config['type'], 404);

        $data = $this->validated($request, $config, $lookup->id);
        $lookup->update(array_merge($data, [
            'is_active' => ($request->input('is_active', 'Yes') === 'Yes'),
        ]));

        return redirect()->route('settings.lookups', $kind)->with('success', $config['title'] . ' updated.');
    }

    public function destroy(string $kind, SettingLookup $lookup)
    {
        abort_unless(auth()->user()->hasPermission('settings.view'), 403);
        $config = $this->config($kind);
        abort_unless($lookup->type === $config['type'], 404);

        if ($lookup->children()->exists()) {
            return back()->withErrors(['error' => 'Item has child records and cannot be deleted.']);
        }

        $lookup->delete();

        return redirect()->route('settings.lookups', $kind)->with('success', $config['title'] . ' deleted.');
    }

    private function config(string $kind): array
    {
        abort_unless(isset($this->configs[$kind]), 404);

        return $this->configs[$kind];
    }

    private function parents(array $config)
    {
        if (empty($config['has_parent'])) {
            return collect();
        }

        return SettingLookup::query()
            ->ofType($config['parent_type'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function validated(Request $request, array $config, ?int $ignoreId = null): array
    {
        $companyId = auth()->user()->company_id;
        $rules = [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('setting_lookups', 'name')->ignore($ignoreId)->where(function ($q) use ($companyId, $config) {
                    $q->where('company_id', $companyId)->where('type', $config['type']);
                }),
            ],
        ];

        if (! empty($config['has_code'])) {
            $rules['code'] = ['required', 'string', 'max:16'];
        }
        if (! empty($config['has_symbol'])) {
            $rules['symbol'] = ['required', 'string', 'max:16'];
        }
        if (! empty($config['has_rate'])) {
            $rules['rate'] = ['required', 'numeric', 'min:0'];
        }
        if (! empty($config['has_parent'])) {
            $rules['parent_id'] = [
                'nullable',
                Rule::exists('setting_lookups', 'id')->where(fn ($q) => $q->where('type', $config['parent_type'])->where('company_id', $companyId)),
            ];
        }

        $data = $request->validate($rules);
        $data['code'] = $data['code'] ?? null;
        $data['symbol'] = $data['symbol'] ?? null;
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['rate'] = $data['rate'] ?? 0;

        return $data;
    }
}
