<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\PurchaseReturnService;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function index(PurchaseReturnService $returns)
    {
        $this->authorizePermission('purchases.return');

        $rows = PurchaseReturnItem::query()
            ->with([
                'product',
                'purchaseReturn.supplier',
                'purchaseReturn.user',
                'purchaseReturn.purchaseOrder.user',
            ])
            ->whereHas('purchaseReturn')
            ->orderByDesc('id')
            ->get();

        $purchases = $returns->returnablePurchases()->map(function (PurchaseOrder $purchase) {
            return [
                'id' => $purchase->id,
                'number' => $purchase->number,
                'supplier' => optional($purchase->supplier)->name ?: '-',
                'order_date' => optional($purchase->order_date)->format('d-m-Y'),
                'returnable_url' => route('purchases.returns.returnable', $purchase),
            ];
        });

        return view('purchases.returns', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'purchases.returns',
            'rows' => $rows,
            'returnablePurchases' => $purchases,
            'canReturn' => auth()->user()->hasPermission('purchases.return'),
        ]));
    }

    public function returnable(PurchaseOrder $purchase, PurchaseReturnService $returns)
    {
        $this->authorizePermission('purchases.return');

        return response()->json($returns->returnablePayload($purchase));
    }

    public function store(Request $request, PurchaseOrder $purchase, PurchaseReturnService $returns)
    {
        $this->authorizePermission('purchases.return');

        $data = $request->validate([
            'return_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|min:0',
        ]);

        try {
            $returns->createFromPurchase($purchase, $data);
        } catch (NegativeStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase-returns.index')->with('success', 'Purchase return saved.');
    }
}
