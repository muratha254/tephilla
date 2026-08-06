<?php

namespace App\Http\Controllers;

use App\Models\FleetCustomer;
use App\Models\FleetTrip;
use App\Models\FleetTripPayment;
use App\Services\FleetTripPaymentService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PDF;

class FleetCustomerPaymentController extends Controller
{
    public function index(Request $request)
    {
        $customerId = (int) $this->queryFilter($request, 'customer_id', '0');
        $context = $this->customerPaymentContext($customerId);

        return view('fleet.payments.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'payments-list',
            'openMenu' => 'payments',
            'customers' => $context['customers'],
            'customerBalances' => $context['customerBalances'],
            'selectedCustomerBalance' => $context['selectedCustomerBalance'],
            'paymentMethods' => $this->paymentMethods(),
            'customerFilter' => $customerId,
            'retryCustomerId' => $context['retryCustomerId'],
            'summary' => $context['summary'],
        ]));
    }

    public function history(Request $request)
    {
        $search = $this->queryFilter($request, 'search');
        $customerId = (int) $this->queryFilter($request, 'customer_id', '0');
        $dateFrom = $this->queryFilter($request, 'date_from');
        $dateTo = $this->queryFilter($request, 'date_to');

        $payments = $this->filteredPayments($search, $customerId, $dateFrom, $dateTo)->get();
        $context = $this->customerPaymentContext($customerId);

        return view('fleet.payments.history', array_merge($this->sharedViewData(), [
            'activeMenu' => 'payments-history',
            'openMenu' => 'payments',
            'payments' => $payments,
            'customers' => $context['customers'],
            'selectedCustomerBalance' => $context['selectedCustomerBalance'],
            'search' => $search,
            'customerFilter' => $customerId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'summary' => array_merge($context['summary'], [
                'total_collected' => round((float) $payments->sum('amount'), 2),
                'payment_count' => $payments->count(),
            ]),
        ]));
    }

    public function store(Request $request, FleetTripPaymentService $paymentService)
    {
        $validated = $request->validate([
            'fleet_trip_id' => 'required|exists:fleet_trips,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $trip = FleetTrip::query()
            ->with('payments')
            ->findOrFail($validated['fleet_trip_id']);

        if (! $trip->fleet_customer_id) {
            return back()
                ->withInput()
                ->withErrors(['fleet_trip_id' => 'Selected trip has no customer assigned.']);
        }

        try {
            $payment = $paymentService->record($trip, $validated);
        } catch (InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('payments.index', array_filter([
                'customer_id' => $trip->fleet_customer_id,
            ]))
            ->with('success', 'Payment recorded for ' . $trip->displayTripCode() . '. Remaining balance: ' . format_kes($trip->fresh()->remainingAmount()) . '.')
            ->with('payment_receipt', [
                'receipt_number' => $payment->receiptNumber(),
                'pdf_url' => route('payments.receipt-pdf', $payment),
                'print_url' => route('payments.receipt-print', $payment),
            ])
            ->with('open_payment_receipt', $request->boolean('open_receipt'));
    }

    public function destroy(FleetTripPayment $payment, FleetTripPaymentService $paymentService)
    {
        $trip = $payment->trip;

        if (! $trip) {
            abort(404);
        }

        $paymentService->delete($trip, $payment);

        return redirect()
            ->back()
            ->with('success', 'Payment removed. Customer outstanding balance has been updated.');
    }

    public function receiptPdf(FleetTripPayment $payment)
    {
        $payment->load(['customer', 'trip.payments']);

        $pdf = PDF::loadView('fleet.payments.receipt_pdf', $this->receiptViewData($payment))
            ->setPaper('a4', 'portrait');

        $pdf->getDomPDF()->getOptions()->setIsPhpEnabled(true);

        $filename = strtolower($payment->receiptNumber()) . '.pdf';

        return $pdf->download($filename);
    }

    public function receiptPrint(FleetTripPayment $payment)
    {
        $payment->load(['customer', 'trip.payments']);

        return view('fleet.payments.receipt_print', array_merge($this->sharedViewData(), $this->receiptViewData($payment)));
    }

    public function exportReportPdf(Request $request)
    {
        $search = $this->queryFilter($request, 'search');
        $customerId = (int) $this->queryFilter($request, 'customer_id', '0');
        $dateFrom = $this->queryFilter($request, 'date_from');
        $dateTo = $this->queryFilter($request, 'date_to');

        $payments = $this->filteredPayments($search, $customerId, $dateFrom, $dateTo)->get();

        $customerLabel = 'All Customers';
        $customerOutstanding = null;
        if ($customerId > 0) {
            $customer = FleetCustomer::query()->find($customerId);
            $customerLabel = $customer?->name ?: $customerLabel;
            $customerOutstanding = $customer ? round((float) $customer->outstanding_payment, 2) : null;
        }

        $pdf = PDF::loadView('fleet.payments.report_pdf', array_merge(fleet_company_profile(), [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'customerLabel' => $customerLabel,
            'customerOutstanding' => $customerOutstanding,
            'payments' => $payments,
            'summary' => [
                'payment_count' => $payments->count(),
                'total_collected' => round((float) $payments->sum('amount'), 2),
            ],
            'generatedAt' => now()->format('d M Y H:i'),
        ]))->setPaper('a4', 'landscape');

        $filename = 'customer-payments-report-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function customerTrips(FleetCustomer $customer)
    {
        $customer->load(['trips.payments']);
        $financial = $customer->financialSummary();

        $trips = $customer->trips
            ->filter(fn (FleetTrip $trip) => $trip->remainingAmount() > 0)
            ->map(fn (FleetTrip $trip) => [
                'id' => $trip->id,
                'label' => $trip->tripDisplayName(),
                'trip_code' => $trip->displayTripCode(),
                'total_amount' => $trip->totalAmount(),
                'paid_amount' => $trip->paidAmount(),
                'remaining_amount' => $trip->remainingAmount(),
                'remaining_formatted' => format_kes($trip->remainingAmount()),
                'invoice_number' => $trip->invoiceNumber(),
            ])
            ->values();

        return response()->json([
            'customer' => $this->customerFinancialPayload($customer, $financial),
            'trips' => $trips,
        ]);
    }

    public function customerHistory(FleetCustomer $customer)
    {
        $customer->load(['trips.payments']);
        $financial = $customer->financialSummary();

        $payments = FleetTripPayment::query()
            ->with(['trip.payments'])
            ->where('fleet_customer_id', $customer->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FleetTripPayment $payment) => [
                'id' => $payment->id,
                'payment_date' => $payment->payment_date?->format('Y-m-d'),
                'payment_date_formatted' => format_fleet_date($payment->payment_date),
                'payment_method' => $payment->payment_method,
                'reference_no' => $payment->reference_no,
                'notes' => $payment->notes,
                'amount' => (float) $payment->amount,
                'amount_formatted' => format_kes($payment->amount),
                'remaining_balance' => $payment->remainingAfterPayment(),
                'remaining_balance_formatted' => format_kes($payment->remainingAfterPayment()),
                'trip_id' => $payment->fleet_trip_id,
                'trip_code' => $payment->trip?->displayTripCode(),
                'invoice_number' => $payment->trip?->invoiceNumber(),
                'trip_url' => $payment->trip ? route('trips.show', $payment->trip) : null,
                'receipt_pdf_url' => route('payments.receipt-pdf', $payment),
                'receipt_print_url' => route('payments.receipt-print', $payment),
            ])
            ->values();

        return response()->json([
            'customer' => $this->customerFinancialPayload($customer, $financial),
            'payments' => $payments,
        ]);
    }

    private function customerPaymentContext(int $customerId): array
    {
        $customers = FleetCustomer::query()
            ->withSum('payments as total_amount_paid', 'amount')
            ->with(['trips.payments'])
            ->orderBy('name')
            ->get();

        $customerBalances = $customers->map(function (FleetCustomer $customer) {
            $totalInvoiced = round((float) $customer->trips->sum(fn (FleetTrip $trip) => $trip->totalAmount()), 2);
            $amountPaid = round((float) ($customer->total_amount_paid ?? 0), 2);

            return [
                'customer' => $customer,
                'total_invoiced' => $totalInvoiced,
                'amount_paid' => $amountPaid,
                'outstanding_balance' => round((float) $customer->outstanding_payment, 2),
            ];
        });

        $selectedCustomerBalance = $customerId > 0
            ? $customerBalances->first(fn ($row) => (int) $row['customer']->id === $customerId)
            : null;

        $retryCustomerId = 0;
        if (old('fleet_trip_id')) {
            $retryCustomerId = (int) (FleetTrip::query()
                ->whereKey(old('fleet_trip_id'))
                ->value('fleet_customer_id') ?? 0);
        }

        $outstandingTrips = FleetTrip::query()
            ->with('payments')
            ->whereNotNull('fleet_customer_id')
            ->get()
            ->filter(fn (FleetTrip $trip) => $trip->remainingAmount() > 0)
            ->count();

        return [
            'customers' => $customers,
            'customerBalances' => $customerBalances,
            'selectedCustomerBalance' => $selectedCustomerBalance,
            'retryCustomerId' => $retryCustomerId,
            'summary' => [
                'outstanding_trips' => $outstandingTrips,
                'total_outstanding' => round((float) $customerBalances->sum('outstanding_balance'), 2),
                'total_paid' => round((float) $customerBalances->sum('amount_paid'), 2),
            ],
        ];
    }

    private function filteredPayments(string $search, int $customerId, string $dateFrom, string $dateTo)
    {
        return FleetTripPayment::query()
            ->with(['customer', 'trip.payments'])
            ->when($customerId > 0, fn ($query) => $query->where('fleet_customer_id', $customerId))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('payment_date', '<=', $dateTo))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('trip', fn ($trip) => $trip->where('trip_code', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }

    private function receiptViewData(FleetTripPayment $payment): array
    {
        $trip = $payment->trip;
        $customer = $payment->customer;

        return array_merge(fleet_document_profile('receipt'), [
            'payment' => $payment,
            'trip' => $trip,
            'generatedAt' => now()->format('d M Y H:i'),
            'tripTotal' => $trip ? $trip->totalAmount() : 0,
            'tripPaid' => $trip ? $payment->paidAfterPayment() : 0,
            'tripRemaining' => $trip ? $payment->remainingAfterPayment() : 0,
            'customerOutstanding' => $customer ? round((float) $customer->outstanding_payment, 2) : null,
        ]);
    }

    private function queryFilter(Request $request, string $key, string $default = ''): string
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return trim((string) $value);
    }

    private function customerFinancialPayload(FleetCustomer $customer, array $financial): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'total_invoiced' => $financial['total_invoiced'],
            'total_invoiced_formatted' => format_kes($financial['total_invoiced']),
            'total_paid' => $financial['total_paid'],
            'total_paid_formatted' => format_kes($financial['total_paid']),
            'outstanding_payment' => $financial['outstanding_balance'],
            'outstanding_formatted' => format_kes($financial['outstanding_balance']),
        ];
    }

    private function paymentMethods(): array
    {
        return ['Cash', 'M-Pesa', 'Bank Transfer', 'Petty Cash'];
    }

    private function sharedViewData(): array
    {
        return fleet_shared_view_data();
    }
}
