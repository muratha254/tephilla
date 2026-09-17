<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSupplierReport();

        $supplierId = $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = collect();
        if ($generated) {
            $rows = Supplier::query()
                ->with('branch')
                ->when($supplierId, fn ($q) => $q->where('id', $supplierId))
                ->when($branchId, fn ($q) => $q->where(function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)->orWhereNull('branch_id');
                }))
                ->orderBy('name')
                ->get()
                ->map(function (Supplier $supplier, int $i) {
                    return [
                        'index' => $i + 1,
                        'branch' => optional($supplier->branch)->name ?: '-',
                        'code' => str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT),
                        'name' => $supplier->name,
                        'phone' => $supplier->phone ?: $supplier->mobile ?: '-',
                        'registration_date' => optional($supplier->created_at)->format('d-m-Y') ?: '-',
                        'registered_by' => '-',
                        'balance' => $supplier->currentBalance(),
                    ];
                })->values();
        }

        return view('reports.suppliers.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.suppliers',
            'supplierId' => $supplierId,
            'generated' => $generated,
            'suppliers' => $suppliers,
            'rows' => $rows,
        ]));
    }

    private function authorizeSupplierReport(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasPermission('reports.view'), 403);
    }
}
