<?php

namespace App\Http\Controllers;

use App\Services\PurchaseReportsService;
use Illuminate\Http\Request;

class PurchaseReportsController extends Controller
{
    public function __construct(private PurchaseReportsService $reports)
    {
    }

    public function purchase(Request $request)
    {
        $this->authorizePurchases();
        [$from, $to, $generated] = $this->dates($request);
        $filters = [
            'supplier_id' => $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null,
            'status' => $request->input('status') ?: null,
        ];

        return view('reports.purchases.purchase', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.purchases.purchase',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'filters' => $filters,
            'suppliers' => $this->reports->supplierOptions(),
            'statuses' => $this->reports->statuses(),
            'rows' => $generated ? $this->reports->purchaseRows($from, $to, $this->currentBranchId(), $filters) : collect(),
        ]));
    }

    public function items(Request $request)
    {
        $this->authorizePurchases();
        [$from, $to, $generated] = $this->dates($request);
        $filters = [
            'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
            'supplier_id' => $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null,
            'status' => $request->input('status') ?: null,
        ];

        return view('reports.purchases.items', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.purchases.items',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'filters' => $filters,
            'products' => $this->reports->productOptions(),
            'suppliers' => $this->reports->supplierOptions(),
            'statuses' => $this->reports->statuses(),
            'rows' => $generated ? $this->reports->itemPurchaseRows($from, $to, $this->currentBranchId(), $filters) : collect(),
        ]));
    }

    public function payments(Request $request)
    {
        $this->authorizePurchases();
        [$from, $to, $generated] = $this->dates($request);
        $supplierId = $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null;

        return view('reports.purchases.payments', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.purchases.payments',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'supplierId' => $supplierId,
            'suppliers' => $this->reports->supplierOptions(),
            'rows' => $generated ? $this->reports->paymentRows($from, $to, $this->currentBranchId(), $supplierId) : collect(),
        ]));
    }

    private function dates(Request $request): array
    {
        return [
            $request->input('from', now()->toDateString()),
            $request->input('to', now()->toDateString()),
            $request->boolean('show'),
        ];
    }

    private function authorizePurchases(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('reports.purchases') || $user->hasPermission('reports.view')), 403);
    }
}
