<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Tax;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TaxReportService
{
    public function taxSummary(string $from, string $to, ?int $branchId = null): array
    {
        $sales = $this->salesQuery($from, $to, $branchId)->get();

        $taxable = round($sales->sum(function (Sale $sale) {
            return max(0, (float) $sale->subtotal - (float) $sale->discount_amount);
        }), 2);
        $vat = round($sales->sum('tax_amount'), 2);
        $gross = round($sales->sum('total'), 2);
        $zeroRated = round($sales->filter(fn (Sale $sale) => (float) $sale->tax_amount <= 0)->sum('total'), 2);

        return [
            'invoices' => $sales->count(),
            'taxable' => $taxable,
            'vat' => $vat,
            'zero_rated' => $zeroRated,
            'gross' => $gross,
            'net_of_vat' => round($gross - $vat, 2),
        ];
    }

    public function vatSummaryRows(string $from, string $to, ?int $branchId = null): Collection
    {
        return $this->salesQuery($from, $to, $branchId)
            ->selectRaw('DATE(sale_date) as sale_day, COUNT(*) as invoices, SUM(subtotal - discount_amount) as taxable, SUM(tax_amount) as vat, SUM(total) as gross')
            ->groupBy('sale_day')
            ->orderBy('sale_day')
            ->get()
            ->map(function ($row) {
                $taxable = round((float) $row->taxable, 2);
                $vat = round((float) $row->vat, 2);
                $gross = round((float) $row->gross, 2);

                return [
                    'date' => Carbon::parse($row->sale_day)->format('d-m-Y'),
                    'invoices' => (int) $row->invoices,
                    'taxable' => $taxable,
                    'vat' => $vat,
                    'zero_rated' => round(max(0, $gross - $taxable - $vat), 2),
                    'gross' => $gross,
                ];
            })->values();
    }

    public function salesVatRows(string $from, string $to, ?int $branchId = null, ?int $taxId = null): Collection
    {
        $sales = $this->salesQuery($from, $to, $branchId)
            ->with('customer')
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get();

        if ($taxId) {
            $tax = Tax::query()->find($taxId);
            $rate = $tax ? (float) $tax->rate : null;
            if ($rate !== null) {
                $sales = $sales->filter(function (Sale $sale) use ($rate) {
                    $taxable = max(0, (float) $sale->subtotal - (float) $sale->discount_amount);
                    if ($taxable <= 0) {
                        return (float) $sale->tax_amount <= 0 && $rate <= 0;
                    }
                    $implied = round(((float) $sale->tax_amount / $taxable) * 100, 1);
                    return abs($implied - $rate) < 0.6;
                })->values();
            }
        }

        return $sales->map(function (Sale $sale, int $index) {
            $taxable = round(max(0, (float) $sale->subtotal - (float) $sale->discount_amount), 2);
            $vat = round((float) $sale->tax_amount, 2);
            $gross = round((float) $sale->total, 2);
            $zeroRated = $vat <= 0 ? $gross : 0.0;

            return [
                'index' => $index + 1,
                'invoice_no' => $sale->invoice_number ?: $sale->receipt_number ?: $sale->number,
                'invoice_date' => optional($sale->sale_date)->format('d-m-Y'),
                'customer' => optional($sale->customer)->name ?: 'Walk-in',
                'vat_no' => optional($sale->customer)->tax_number ?: '-',
                'taxable' => $taxable,
                'vat' => $vat,
                'zero_rated' => $zeroRated,
                'gross' => $gross,
            ];
        })->values();
    }

    public function monthlyVatRows(string $from, string $to, ?int $branchId = null): Collection
    {
        return $this->salesQuery($from, $to, $branchId)
            ->selectRaw("DATE_FORMAT(sale_date, '%Y-%m') as sale_month, COUNT(*) as invoices, SUM(subtotal - discount_amount) as taxable, SUM(tax_amount) as vat, SUM(total) as gross")
            ->groupBy('sale_month')
            ->orderBy('sale_month')
            ->get()
            ->map(function ($row) {
                $taxable = round((float) $row->taxable, 2);
                $vat = round((float) $row->vat, 2);
                $gross = round((float) $row->gross, 2);

                return [
                    'month' => Carbon::createFromFormat('Y-m', $row->sale_month)->format('M Y'),
                    'invoices' => (int) $row->invoices,
                    'taxable' => $taxable,
                    'vat' => $vat,
                    'zero_rated' => round(max(0, $gross - $taxable - $vat), 2),
                    'gross' => $gross,
                ];
            })->values();
    }

    public function taxOptions(): Collection
    {
        return Tax::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rate']);
    }

    private function salesQuery(string $from, string $to, ?int $branchId = null)
    {
        return Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }
}
