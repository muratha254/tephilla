<?php

namespace App\Http\Controllers;

use App\Models\FleetCustomer;
use App\Models\FleetDriver;
use App\Models\FleetTrip;
use App\Models\FleetTripExpense;
use App\Models\FleetTripPayment;
use App\Models\FleetTripType;
use App\Models\FleetVehicle;
use App\Services\FleetVehicleAvailabilityService;
use App\Services\FleetTripPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use PDF;

class FleetTripController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $tab = trim((string) $request->query('tab', 'all'));

        $counts = [
            'all' => FleetTrip::query()->count(),
            'upcoming' => FleetTrip::query()->whereIn('status', ['Booked', 'pending', 'Pending'])->count(),
            'ongoing' => FleetTrip::query()->whereIn('status', ['ongoing', 'Ongoing'])->count(),
            'completed' => FleetTrip::query()->whereIn('status', ['completed', 'Completed'])->count(),
            'cancelled' => FleetTrip::query()->whereIn('status', ['cancelled', 'Cancelled'])->count(),
        ];

        $trips = FleetTrip::query()
            ->with(['vehicle', 'driver', 'payments'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('trip_code', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('pickup_location', 'like', "%{$search}%")
                        ->orWhere('drop_location', 'like', "%{$search}%")
                        ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                            $vehicleQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('registration_number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('driver', function ($driverQuery) use ($search) {
                            $driverQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($tab === 'upcoming', function ($query) {
                $query->whereIn('status', ['Booked', 'pending', 'Pending']);
            })
            ->when($tab === 'ongoing', function ($query) {
                $query->whereIn('status', ['ongoing', 'Ongoing']);
            })
            ->when($tab === 'completed', function ($query) {
                $query->whereIn('status', ['completed', 'Completed']);
            })
            ->when($tab === 'cancelled', function ($query) {
                $query->whereIn('status', ['cancelled', 'Cancelled']);
            })
            ->latest()
            ->get();

        return view('fleet.trips.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'trip-list',
            'openMenu' => 'trips',
            'trips' => $trips,
            'counts' => $counts,
            'search' => $search,
            'statusFilter' => $status,
            'activeTab' => $tab,
            'statuses' => FleetTrip::statusOptions(),
            'paymentMethods' => $this->paymentMethods(),
        ]));
    }

    public function show(FleetTrip $trip)
    {
        $trip->load(['vehicle', 'driver', 'payments']);

        return view('fleet.trips.show', array_merge($this->sharedViewData(), [
            'activeMenu' => 'trip-list',
            'openMenu' => 'trips',
            'trip' => $trip,
            'statuses' => FleetTrip::statusOptions(),
            'paymentMethods' => $this->paymentMethods(),
        ]));
    }

    public function invoicePdf(FleetTrip $trip)
    {
        $trip->load(['vehicle', 'driver']);

        $pdf = PDF::loadView('fleet.trips.invoice_pdf', array_merge(fleet_document_profile('invoice'), [
            'trip' => $trip,
            'invoiceDate' => now()->format('d M Y'),
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]))->setPaper('a4', 'portrait');

        $pdf->getDomPDF()->getOptions()->setIsPhpEnabled(true);

        $filename = strtolower(str_replace(['/', ' '], '-', $trip->invoiceNumber())) . '.pdf';

        return $pdf->stream($filename);
    }

    public function editInvoice(FleetTrip $trip)
    {
        $trip->load(['customer', 'vehicle', 'driver', 'payments']);

        return view('fleet.trips.edit_invoice', array_merge($this->sharedViewData(), [
            'activeMenu' => 'trip-list',
            'openMenu' => 'trips',
            'trip' => $trip,
            'formOptions' => $this->formOptions(),
        ]));
    }

    public function updateInvoice(Request $request, FleetTrip $trip)
    {
        $validated = $request->validate([
            'billing_type' => 'required|string|max:50',
            'billing_quantity' => 'nullable|numeric|min:0',
            'billing_rate' => 'nullable|numeric|min:0',
            'base_amount' => 'nullable|numeric|min:0',
            'tax_type' => 'nullable|string|max:50',
            'coupon_code' => 'nullable|string|max:50',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        $validated['discount_amount'] = round((float) ($validated['discount_amount'] ?? 0), 2);
        $this->applyBillingFields($validated);
        $validated['tax_type'] = $validated['tax_type'] ?? 'No Tax';
        $validated['coupon_code'] = trim((string) ($validated['coupon_code'] ?? '')) ?: null;

        $previousTotal = $trip->totalAmount();
        $previousCustomerId = $trip->fleet_customer_id;

        $trip->update([
            'billing_type' => $validated['billing_type'],
            'billing_quantity' => $validated['billing_quantity'],
            'billing_rate' => $validated['billing_rate'],
            'base_amount' => $validated['base_amount'],
            'tax_type' => $validated['tax_type'],
            'coupon_code' => $validated['coupon_code'],
            'discount_amount' => $validated['discount_amount'],
        ]);

        $trip->refresh();
        $this->adjustOutstandingAfterTripUpdate($trip, $previousCustomerId, $previousTotal);

        return redirect()
            ->route('trips.show', $trip)
            ->with('success', 'Invoice updated successfully. New total: ' . format_kes($trip->totalAmount()) . '.');
    }

    public function expenses(FleetTrip $trip)
    {
        $trip->load(['vehicle', 'driver', 'expenses']);

        return view('fleet.trips.expenses', array_merge($this->sharedViewData(), [
            'activeMenu' => 'trip-list',
            'openMenu' => 'trips',
            'trip' => $trip,
            'expenseCategories' => $this->expenseCategories(),
            'paymentMethods' => $this->paymentMethods(),
        ]));
    }

    public function storeExpense(Request $request, FleetTrip $trip)
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'reference_no' => 'nullable|string|max:100',
        ]);

        $trip->expenses()->create($validated);

        return redirect()
            ->route('trips.expenses', $trip)
            ->with('success', 'Expense added successfully.');
    }

    public function destroyExpense(FleetTrip $trip, FleetTripExpense $expense)
    {
        if ((int) $expense->fleet_trip_id !== (int) $trip->id) {
            abort(404);
        }

        $expense->delete();

        return redirect()
            ->route('trips.expenses', $trip)
            ->with('success', 'Expense removed successfully.');
    }

    public function payments(FleetTrip $trip)
    {
        $trip->load(['vehicle', 'driver', 'customer', 'payments']);

        return view('fleet.trips.payments', array_merge($this->sharedViewData(), [
            'activeMenu' => 'trip-list',
            'openMenu' => 'trips',
            'trip' => $trip,
            'paymentMethods' => $this->paymentMethods(),
        ]));
    }

    public function storePayment(Request $request, FleetTrip $trip, FleetTripPaymentService $paymentService)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $payment = $paymentService->record($trip, $validated);
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->back()
            ->with('success', 'Payment recorded. Remaining balance: ' . format_kes($trip->fresh()->remainingAmount()) . '.')
            ->with('payment_receipt', [
                'receipt_number' => $payment->receiptNumber(),
                'pdf_url' => route('payments.receipt-pdf', $payment),
                'print_url' => route('payments.receipt-print', $payment),
            ])
            ->with('open_payment_receipt', $request->boolean('open_receipt'));
    }

    public function destroyPayment(FleetTrip $trip, FleetTripPayment $payment, FleetTripPaymentService $paymentService)
    {
        $paymentService->delete($trip, $payment);

        return redirect()
            ->route('trips.payments', $trip)
            ->with('success', 'Payment removed.');
    }

    public function expensePdf(FleetTrip $trip)
    {
        $trip->load(['vehicle', 'driver', 'expenses']);

        $pdf = PDF::loadView('fleet.trips.expense_pdf', array_merge(fleet_company_profile(), [
            'trip' => $trip,
            'expenses' => $trip->expenses,
            'reportDate' => now()->format('d M Y'),
            'generatedAt' => now()->format('Y-m-d H:i'),
            'totalExpenses' => $trip->totalExpensesAmount(),
        ]))->setPaper('a4', 'portrait');

        $filename = strtolower(str_replace(['/', ' '], '-', $trip->expenseReportNumber())) . '.pdf';

        return $pdf->stream($filename);
    }

    public function liveMapData(FleetTrip $trip)
    {
        $pickup = $this->geocodeLocation($trip->pickup_location);
        $drop = $this->geocodeLocation($trip->drop_location);

        if (! $pickup || ! $drop) {
            return response()->json([
                'success' => false,
                'message' => 'Could not locate pickup or destination on the map. Check the trip addresses.',
            ], 422);
        }

        $route = $this->fetchDrivingRoute($pickup, $drop);
        $vehiclePosition = $this->vehicleMapPosition($trip, $route, $pickup, $drop);

        return response()->json([
            'success' => true,
            'trip_code' => $trip->displayTripCode(),
            'status' => $trip->statusLabel(),
            'pickup' => $pickup,
            'drop' => $drop,
            'route' => $route,
            'vehicle' => $vehiclePosition,
            'vehicle_label' => optional($trip->vehicle)->displayName() ?: 'Vehicle',
        ]);
    }

    public function create()
    {
        return view('fleet.trips.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'trip-add',
            'openMenu' => 'trips',
            'trip' => null,
            'formOptions' => $this->formOptions(),
        ]));
    }

    public function store(Request $request, FleetVehicleAvailabilityService $availability, FleetTripPaymentService $paymentService)
    {
        $validated = $this->validateTrip($request);

        if (! empty($validated['fleet_vehicle_id'])) {
            $availability->assertVehicleAvailableForTrip(
                (int) $validated['fleet_vehicle_id'],
                $validated['start_date'],
                $validated['end_date']
            );
        }

        $stops = collect($validated['additional_stops'] ?? [])
            ->filter(fn ($stop) => trim((string) $stop) !== '')
            ->values()
            ->all();

        $validated['additional_stops'] = $stops ?: null;
        $this->normalizeContainerFields($validated);
        $this->applyBillingFields($validated);
        $validated['base_amount'] = $validated['base_amount'] ?? 0;
        $validated['tax_type'] = $validated['tax_type'] ?? 'No Tax';
        $validated['discount_amount'] = 0;
        $validated['status'] = 'Booked';
        $validated['trip_code'] = $this->generateTripCode();
        $this->applyCustomerFields($validated);

        $tripPreview = new FleetTrip([
            'base_amount' => $validated['base_amount'],
            'discount_amount' => $validated['discount_amount'],
            'tax_type' => $validated['tax_type'],
        ]);
        $depositPayload = $this->validateDeposit($request, $tripPreview->totalAmount(), $validated['start_date']);

        $trip = DB::transaction(function () use ($validated, $depositPayload, $paymentService) {
            $trip = FleetTrip::create($validated);
            $this->addOutstandingForTrip($trip);

            if ($depositPayload !== []) {
                $paymentService->record($trip, $depositPayload);
            }

            return $trip;
        });

        $message = 'Trip booked successfully.';
        if ($depositPayload !== []) {
            $message .= ' Deposit of ' . format_kes($depositPayload['amount']) . ' recorded.';
        }

        return redirect()
            ->route('trips.index')
            ->with('success', $message);
    }

    public function edit(FleetTrip $trip)
    {
        return view('fleet.trips.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'trip-list',
            'openMenu' => 'trips',
            'trip' => $trip,
            'formOptions' => $this->formOptions(),
            'statuses' => FleetTrip::statusOptions(),
        ]));
    }

    public function updateStatus(Request $request, FleetTrip $trip)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', FleetTrip::allowedStatuses()),
        ]);

        $trip->update(['status' => $validated['status']]);

        return redirect()
            ->back()
            ->with('success', 'Trip status updated to ' . FleetTrip::statusOptions()[$validated['status']] . '.');
    }

    public function update(Request $request, FleetTrip $trip, FleetVehicleAvailabilityService $availability)
    {
        $validated = $this->validateTrip($request);

        if (! empty($validated['fleet_vehicle_id'])) {
            $availability->assertVehicleAvailableForTrip(
                (int) $validated['fleet_vehicle_id'],
                $validated['start_date'],
                $validated['end_date'],
                $trip->id
            );
        }

        $stops = collect($validated['additional_stops'] ?? [])
            ->filter(fn ($stop) => trim((string) $stop) !== '')
            ->values()
            ->all();

        $validated['additional_stops'] = $stops ?: null;
        $this->normalizeContainerFields($validated);
        $this->applyBillingFields($validated);
        $validated['base_amount'] = $validated['base_amount'] ?? 0;
        $validated['tax_type'] = $validated['tax_type'] ?? 'No Tax';
        $this->applyCustomerFields($validated);

        if (empty($validated['status'])) {
            unset($validated['status']);
        }

        $previousTotal = $trip->totalAmount();
        $previousCustomerId = $trip->fleet_customer_id;

        $trip->update($validated);
        $trip->refresh();

        $this->adjustOutstandingAfterTripUpdate($trip, $previousCustomerId, $previousTotal);

        return redirect()
            ->route('trips.index')
            ->with('success', 'Trip updated successfully.');
    }

    public function destroy(FleetTrip $trip)
    {
        $this->reverseOutstandingForTrip($trip);
        $trip->delete();

        return redirect()
            ->route('trips.index')
            ->with('success', 'Trip deleted successfully.');
    }

    private function validateTrip(Request $request): array
    {
        $this->normalizeRequestDates($request);

        return $request->validate([
            'trip_type' => 'required|string|max:50',
            'fleet_vehicle_id' => 'nullable|exists:fleet_vehicles,id',
            'fleet_driver_id' => 'nullable|exists:fleet_drivers,id',
            'fleet_customer_id' => 'required|exists:fleet_customers,id',
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'pickup_location' => 'required|string|max:255',
            'drop_location' => 'required|string|max:255',
            'container_number' => 'nullable|string|max:50',
            'container_empty_drop_point' => 'nullable|string|max:255',
            'additional_stops' => 'nullable|array',
            'additional_stops.*' => 'nullable|string|max:255',
            'billing_type' => 'required|string|max:50',
            'billing_quantity' => 'nullable|numeric|min:0',
            'billing_rate' => 'nullable|numeric|min:0',
            'base_amount' => 'nullable|numeric|min:0',
            'tax_type' => 'nullable|string|max:50',
            'coupon_code' => 'nullable|string|max:50',
            'status' => 'nullable|in:' . implode(',', FleetTrip::allowedStatuses()),
        ]);
    }

    private function normalizeRequestDates(Request $request): void
    {
        $messages = [];

        foreach (['start_date', 'end_date'] as $field) {
            $raw = trim((string) $request->input($field, ''));
            $parsed = parse_fleet_date_input($raw);

            if ($raw !== '' && $parsed === null) {
                $messages[$field] = 'Enter a valid date in dd/mm/yyyy format.';
                continue;
            }

            if ($parsed !== null) {
                $request->merge([$field => $parsed]);
            }
        }

        if ($messages !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($messages);
        }
    }

    private function applyCustomerFields(array &$validated): void
    {
        $customer = FleetCustomer::query()->findOrFail($validated['fleet_customer_id']);

        $validated['customer_name'] = $customer->name;
        $validated['customer_phone'] = $customer->mobile;
    }

    private function normalizeContainerFields(array &$validated): void
    {
        $validated['container_number'] = trim((string) ($validated['container_number'] ?? '')) ?: null;
        $validated['container_empty_drop_point'] = trim((string) ($validated['container_empty_drop_point'] ?? '')) ?: null;
    }

    private function applyBillingFields(array &$validated): void
    {
        $billingType = $validated['billing_type'] ?? 'Fixed';

        if ($billingType === 'Fixed') {
            $validated['billing_quantity'] = null;
            $validated['billing_rate'] = null;
            $validated['base_amount'] = round((float) ($validated['base_amount'] ?? 0), 2);

            return;
        }

        $quantity = (float) ($validated['billing_quantity'] ?? 0);
        $rate = (float) ($validated['billing_rate'] ?? 0);

        if ($quantity <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'billing_quantity' => 'Enter the quantity for the selected billing type.',
            ]);
        }

        if ($rate < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'billing_rate' => 'Enter a valid rate for the selected billing type.',
            ]);
        }

        $validated['billing_quantity'] = round($quantity, 3);
        $validated['billing_rate'] = round($rate, 2);
        $validated['base_amount'] = round($quantity * $rate, 2);
    }

    private function validateDeposit(Request $request, float $tripTotal, string $paymentDate): array
    {
        $amount = round((float) $request->input('deposit_amount', 0), 2);

        if ($amount <= 0) {
            return [];
        }

        if ($tripTotal <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deposit_amount' => 'Set a trip total before recording a deposit.',
            ]);
        }

        $validated = $request->validate([
            'deposit_amount' => 'required|numeric|min:0.01|max:' . $tripTotal,
            'deposit_payment_method' => ['required', 'string', 'max:50', Rule::in($this->paymentMethods())],
            'deposit_reference_no' => 'nullable|string|max:100',
            'deposit_notes' => 'nullable|string|max:255',
        ], [
            'deposit_amount.max' => 'Deposit cannot exceed the trip total of ' . format_kes($tripTotal) . '.',
        ]);

        return [
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'payment_method' => $validated['deposit_payment_method'],
            'reference_no' => $validated['deposit_reference_no'] ?? null,
            'notes' => trim((string) ($validated['deposit_notes'] ?? '')) ?: 'Deposit on booking',
        ];
    }

    private function addOutstandingForTrip(FleetTrip $trip): void
    {
        if (! $trip->fleet_customer_id) {
            return;
        }

        FleetCustomer::query()
            ->whereKey($trip->fleet_customer_id)
            ->increment('outstanding_payment', $trip->totalAmount());
    }

    private function reverseOutstandingForTrip(FleetTrip $trip): void
    {
        if (! $trip->fleet_customer_id) {
            return;
        }

        $customer = FleetCustomer::query()->find($trip->fleet_customer_id);

        if (! $customer) {
            return;
        }

        $customer->outstanding_payment = max(0, round((float) $customer->outstanding_payment - $trip->remainingAmount(), 2));
        $customer->save();
    }

    private function adjustOutstandingAfterTripUpdate(FleetTrip $trip, ?int $previousCustomerId, float $previousTotal): void
    {
        if ($previousCustomerId && $previousCustomerId !== (int) $trip->fleet_customer_id) {
            $previousCustomer = FleetCustomer::query()->find($previousCustomerId);

            if ($previousCustomer) {
                $previousCustomer->outstanding_payment = max(0, round((float) $previousCustomer->outstanding_payment - $previousTotal, 2));
                $previousCustomer->save();
            }

            $this->addOutstandingForTrip($trip);

            return;
        }

        if (! $trip->fleet_customer_id) {
            return;
        }

        $difference = round($trip->totalAmount() - $previousTotal, 2);

        if ($difference === 0.0) {
            return;
        }

        $customer = FleetCustomer::query()->find($trip->fleet_customer_id);

        if (! $customer) {
            return;
        }

        if ($difference > 0) {
            $customer->increment('outstanding_payment', $difference);
        } else {
            $customer->outstanding_payment = max(0, round((float) $customer->outstanding_payment + $difference, 2));
            $customer->save();
        }
    }

    private function generateTripCode(): string
    {
        $year = now()->format('Y');
        $latest = FleetTrip::query()
            ->where('trip_code', 'like', "OT-{$year}-%")
            ->orderByDesc('id')
            ->value('trip_code');

        $next = 1;
        if ($latest && preg_match('/OT-' . $year . '-(\d+)/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('OT-%s-%03d', $year, $next);
    }

    private function formOptions(): array
    {
        return [
            'trip_types' => FleetTripType::query()->orderBy('name')->pluck('name')->all(),
            'customers' => FleetCustomer::query()->where('status', 'Active')->orderBy('name')->get(),
            'billing_types' => ['Fixed', 'Per Tonne', 'Per KG', 'Per KM', 'Per Trip', 'Per Day', 'Per Hour', 'Per Litre', 'Per Bag'],
            'billing_unit_config' => FleetTrip::billingUnitConfig(),
            'tax_types' => ['No Tax', 'VAT 16%'],
            'statuses' => array_keys(FleetTrip::statusOptions()),
            'vehicles' => FleetVehicle::query()->where('status', 'Active')->orderBy('name')->get(),
            'drivers' => FleetDriver::query()->where('status', 'Active')->orderBy('name')->get(),
            'payment_methods' => $this->paymentMethods(),
        ];
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }

    private function expenseCategories(): array
    {
        return ['Fuel', 'Toll', 'Parking', 'Driver Allowance', 'Maintenance', 'Meals', 'Other'];
    }

    private function paymentMethods(): array
    {
        return ['Cash', 'M-Pesa', 'Bank Transfer', 'Petty Cash'];
    }

    private function geocodeLocation(string $address): ?array
    {
        $address = trim($address);

        if ($address === '') {
            return null;
        }

        $cacheKey = 'fleet_geocode:' . md5(strtolower($address));

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'OneTranslinesFleet/1.0'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json()[0] ?? null;

            if (! $result) {
                return null;
            }

            $location = [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
                'label' => $address,
            ];

            Cache::put($cacheKey, $location, now()->addDays(7));

            return $location;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchDrivingRoute(array $pickup, array $drop): ?array
    {
        $cacheKey = 'fleet_route:' . md5($pickup['lat'] . ',' . $pickup['lng'] . '|' . $drop['lat'] . ',' . $drop['lng']);

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($pickup, $drop) {
            try {
                $coords = $pickup['lng'] . ',' . $pickup['lat'] . ';' . $drop['lng'] . ',' . $drop['lat'];
                $response = Http::timeout(12)->get(
                    'https://router.project-osrm.org/route/v1/driving/' . $coords,
                    [
                        'overview' => 'full',
                        'geometries' => 'geojson',
                    ]
                );

                if (! $response->successful()) {
                    return null;
                }

                $route = $response->json('routes.0');

                if (! $route) {
                    return null;
                }

                $points = collect($route['geometry']['coordinates'] ?? [])
                    ->map(fn ($point) => ['lat' => (float) $point[1], 'lng' => (float) $point[0]])
                    ->values()
                    ->all();

                return [
                    'points' => $points,
                    'distance_km' => round(((float) ($route['distance'] ?? 0)) / 1000, 1),
                    'duration_minutes' => round(((float) ($route['duration'] ?? 0)) / 60),
                ];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    private function vehicleMapPosition(FleetTrip $trip, ?array $route, array $pickup, array $drop): ?array
    {
        $status = strtolower((string) $trip->status);
        $progress = 0.0;

        if ($status === 'completed') {
            $progress = 1.0;
        } elseif (in_array($status, ['ongoing', 'in progress'], true)) {
            $progress = 0.55;
        } else {
            return null;
        }

        $points = $route['points'] ?? [$pickup, $drop];

        if (count($points) < 2) {
            return [
                'lat' => $pickup['lat'] + (($drop['lat'] - $pickup['lat']) * $progress),
                'lng' => $pickup['lng'] + (($drop['lng'] - $pickup['lng']) * $progress),
            ];
        }

        $index = (int) round($progress * (count($points) - 1));

        return $points[$index];
    }
}
