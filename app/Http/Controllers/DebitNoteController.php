<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PDF;

class DebitNoteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('purchases.view');

        $from = $request->input('from_date');
        $to = $request->input('to_date');
        $search = trim((string) $request->input('q', ''));

        $notes = PurchaseReturn::query()
            ->with(['supplier', 'user', 'purchaseOrder', 'items.product'])
            ->when($from, function ($q) use ($from) {
                $q->whereDate('return_date', '>=', $from);
            })
            ->when($to, function ($q) use ($to) {
                $q->whereDate('return_date', '<=', $to);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', '%' . $search . '%')
                        ->orWhere('notes', 'like', '%' . $search . '%')
                        ->orWhereHas('purchaseOrder', function ($po) use ($search) {
                            $po->where('number', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('supplier', function ($supplier) use ($search) {
                            $supplier->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->get();

        $user = auth()->user();

        return view('purchases.debit-notes', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'purchases.debit-notes',
            'notes' => $notes,
            'fromDate' => $from,
            'toDate' => $to,
            'search' => $search,
            'canCreate' => $user->hasPermission('purchases.return'),
        ]));
    }

    public function create(PurchaseReturnService $returns)
    {
        $this->authorizePermission('purchases.return');

        $purchases = $returns->returnablePurchases()->map(function (PurchaseOrder $purchase) {
            return [
                'id' => $purchase->id,
                'number' => $purchase->number,
                'supplier' => optional($purchase->supplier)->name ?: '-',
                'order_date' => optional($purchase->order_date)->format('d-m-Y'),
                'total' => number_format((float) $purchase->total, 2),
                'eligible_url' => route('debit-notes.eligible', $purchase),
            ];
        });

        return view('purchases.debit-notes-create', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'purchases.debit-notes',
            'purchases' => $purchases,
        ]));
    }

    public function eligible(PurchaseOrder $purchase, PurchaseReturnService $returns)
    {
        $this->authorizePermission('purchases.return');

        return response()->json($returns->returnablePayload($purchase));
    }

    public function store(Request $request, PurchaseReturnService $returns)
    {
        $this->authorizePermission('purchases.return');

        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'purchase_order_id' => [
                'required',
                Rule::exists('purchase_orders', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                }),
            ],
            'return_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|min:0',
        ]);

        $purchase = PurchaseOrder::query()->findOrFail($data['purchase_order_id']);

        try {
            $note = $returns->createFromPurchase($purchase, $data);
        } catch (NegativeStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('debit-notes.index')
            ->with('success', 'Debit note ' . $note->number . ' created.');
    }

    public function pdf(Request $request)
    {
        $this->authorizePermission('purchases.view');

        $from = $request->input('from_date', now()->toDateString());
        $to = $request->input('to_date', now()->toDateString());
        $notes = $this->noteRows($from, $to);

        $pdf = PDF::loadView('purchases.debit-notes-pdf', array_merge(fleet_shared_view_data(), [
            'fromDate' => $from,
            'toDate' => $to,
            'notes' => $notes,
            'totals' => [
                'bill' => $notes->sum('total'),
                'exclusive' => $notes->sum('exclusive'),
                'vat' => $notes->sum('vat'),
            ],
            'company' => auth()->user()->company,
        ]))->setPaper('a4', 'portrait');

        return $pdf->download('debit-notes-report-' . $from . '-to-' . $to . '.pdf');
    }

    private function noteRows(string $from, string $to)
    {
        return PurchaseReturn::query()
            ->with(['supplier', 'purchaseOrder.items', 'items'])
            ->whereDate('return_date', '>=', $from)
            ->whereDate('return_date', '<=', $to)
            ->orderBy('return_date')
            ->orderBy('id')
            ->get()
            ->map(function (PurchaseReturn $return) {
                $exclusive = round((float) $return->items->sum('line_total'), 2);
                $vat = 0.0;
                $poItems = optional($return->purchaseOrder)->items ?? collect();

                foreach ($return->items as $item) {
                    $poItem = $poItems->firstWhere('product_id', $item->product_id);
                    $rate = $poItem ? (float) $poItem->tax_rate : 0;
                    $vat += round((float) $item->line_total * ($rate / 100), 2);
                }

                return (object) [
                    'date' => optional($return->return_date)->format('Y-m-d'),
                    'company_name' => optional($return->supplier)->name,
                    'inv_no' => optional($return->purchaseOrder)->number ?: $return->number,
                    'exclusive' => $exclusive,
                    'vat' => round($vat, 2),
                    'total' => round($exclusive + $vat, 2),
                ];
            });
    }
}
