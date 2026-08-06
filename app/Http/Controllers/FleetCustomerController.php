<?php

namespace App\Http\Controllers;

use App\Models\FleetCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class FleetCustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $tab = trim((string) $request->query('tab', 'all'));

        $counts = [
            'all' => FleetCustomer::query()->count(),
            'active' => FleetCustomer::query()->where('status', 'Active')->count(),
            'inactive' => FleetCustomer::query()->where('status', 'Inactive')->count(),
        ];

        $customers = FleetCustomer::query()
            ->withSum('payments as total_amount_paid', 'amount')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($tab === 'active', function ($query) {
                $query->where('status', 'Active');
            })
            ->when($tab === 'inactive', function ($query) {
                $query->where('status', 'Inactive');
            })
            ->orderBy('name')
            ->get();

        return view('fleet.customers.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'customer-list',
            'openMenu' => 'customer',
            'customers' => $customers,
            'counts' => $counts,
            'search' => $search,
            'statusFilter' => $status,
            'activeTab' => $tab,
        ]));
    }

    public function create()
    {
        return view('fleet.customers.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'customer-add',
            'openMenu' => 'customer',
            'customer' => null,
        ]));
    }

    public function show(FleetCustomer $customer)
    {
        $customer->load([
            'trips' => function ($query) {
                $query->with('payments')->orderByDesc('start_date')->orderByDesc('id');
            },
            'payments' => function ($query) {
                $query->with('trip')->orderByDesc('payment_date')->orderByDesc('id');
            },
            'quotations' => function ($query) {
                $query->with('trip')->orderByDesc('created_at')->orderByDesc('id');
            },
        ]);

        return view('fleet.customers.show', array_merge($this->sharedViewData(), [
            'activeMenu' => 'customer-list',
            'openMenu' => 'customer',
            'customer' => $customer,
            'financial' => $customer->financialSummary(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validateCustomer($request);
        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = 'Active';
        $validated['outstanding_payment'] = 0;

        if (! empty($validated['whatsapp_same_as_mobile'])) {
            $validated['whatsapp'] = $validated['mobile'];
        }

        FleetCustomer::create($validated);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer added successfully.');
    }

    public function storeQuick(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'mobile' => 'required|string|max:30',
            'email' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('fleet_customers', 'email'),
            ],
            'address' => 'required|string|max:1000',
        ]);

        $customer = FleetCustomer::create([
            'name' => $validated['name'],
            'mobile' => $validated['mobile'],
            'whatsapp' => $validated['mobile'],
            'whatsapp_same_as_mobile' => true,
            'email' => $validated['email'] ?? null,
            'password' => Hash::make('1234'),
            'address' => $validated['address'],
            'whatsapp_notifications' => true,
            'status' => 'Active',
            'outstanding_payment' => 0,
        ]);

        return response()->json([
            'message' => 'Customer created successfully.',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
            ],
        ]);
    }

    public function edit(FleetCustomer $customer)
    {
        return view('fleet.customers.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'customer-list',
            'openMenu' => 'customer',
            'customer' => $customer,
        ]));
    }

    public function update(Request $request, FleetCustomer $customer)
    {
        $validated = $this->validateCustomer($request, $customer);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (! empty($validated['whatsapp_same_as_mobile'])) {
            $validated['whatsapp'] = $validated['mobile'];
        }

        $customer->update($validated);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(FleetCustomer $customer)
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer deleted successfully..');
    }

    private function validateCustomer(Request $request, ?FleetCustomer $customer = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'mobile' => 'required|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'whatsapp_same_as_mobile' => 'nullable|boolean',
            'email' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('fleet_customers', 'email')->ignore($customer?->id),
            ],
            'password' => ($customer ? 'nullable' : 'required') . '|string|min:4|max:100',
            'address' => 'required|string|max:1000',
            'whatsapp_notifications' => 'nullable|boolean',
            'status' => 'nullable|in:Active,Inactive',
        ]);

        $validated['whatsapp_same_as_mobile'] = $request->boolean('whatsapp_same_as_mobile');
        $validated['whatsapp_notifications'] = $request->boolean('whatsapp_notifications');

        if ($customer && ! $request->filled('status')) {
            $validated['status'] = $customer->status;
        } elseif (! isset($validated['status'])) {
            $validated['status'] = 'Active';
        }

        return $validated;
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
