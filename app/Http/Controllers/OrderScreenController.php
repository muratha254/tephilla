<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class OrderScreenController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeScreen();

        if ($request->wantsJson()) {
            return response()->json(['orders' => $this->ordersPayload()]);
        }

        return view('sales.orders', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'sales.orders',
            'orders' => $this->ordersPayload(),
        ]));
    }

    private function authorizeScreen(): void
    {
        abort_unless(
            auth()->user()->hasPermission('pos.view')
                || auth()->user()->hasPermission('pos.operate')
                || auth()->user()->hasPermission('sales.create'),
            403
        );
    }

    private function ordersPayload(): array
    {
        return Sale::query()
            ->with(['items', 'customer', 'cashier'])
            ->where('status', Sale::STATUS_HELD)
            ->orderByDesc('held_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Sale $sale) {
                return [
                    'id' => $sale->id,
                    'number' => $sale->number ?: $sale->documentNumber(),
                    'customer' => $sale->customerDisplayName(),
                    'cashier' => optional($sale->cashier)->name ?: '-',
                    'held_at' => optional($sale->held_at ?: $sale->created_at)->format('d-m-Y H:i'),
                    'total' => number_format((float) $sale->total, 2),
                    'resume_url' => route('pos.index', ['hold' => $sale->id]),
                    'items' => $sale->items->map(function ($item) {
                        $qty = rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.');

                        return [
                            'name' => $item->name,
                            'qty' => $qty,
                        ];
                    })->values(),
                ];
            })
            ->values()
            ->all();
    }
}
