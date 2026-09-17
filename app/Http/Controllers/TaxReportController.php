<?php

namespace App\Http\Controllers;

use App\Services\TaxReportService;
use Illuminate\Http\Request;
use PDF;

class TaxReportController extends Controller
{
    public function __construct(private TaxReportService $reports)
    {
    }

    public function tax(Request $request)
    {
        $this->authorizeTax();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        return view('reports.tax.tax', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.tax.index',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'report' => $generated ? $this->reports->taxSummary($from, $to, $branchId) : null,
        ]));
    }

    public function vat(Request $request)
    {
        $this->authorizeTax();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        return view('reports.tax.vat', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.tax.vat',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'rows' => $generated ? $this->reports->vatSummaryRows($from, $to, $branchId) : collect(),
        ]));
    }

    public function vatPdf(Request $request)
    {
        $this->authorizeTax();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $branchId = $this->currentBranchId();
        $rows = $this->reports->vatSummaryRows($from, $to, $branchId);

        $pdf = PDF::loadView('reports.tax.vat-pdf', array_merge(fleet_shared_view_data(), [
            'title' => 'VAT Report',
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ]))->setPaper('a4', 'landscape');

        return $pdf->download('vat-report-' . $from . '-to-' . $to . '.pdf');
    }

    public function salesVat(Request $request)
    {
        $this->authorizeTax();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $taxId = $request->filled('tax_id') ? (int) $request->input('tax_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        return view('reports.tax.sales-vat', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.tax.sales-vat',
            'from' => $from,
            'to' => $to,
            'taxId' => $taxId,
            'generated' => $generated,
            'taxes' => $this->reports->taxOptions(),
            'rows' => $generated ? $this->reports->salesVatRows($from, $to, $branchId, $taxId) : collect(),
        ]));
    }

    public function monthlyVat(Request $request)
    {
        $this->authorizeTax();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        return view('reports.tax.monthly-vat', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.tax.monthly-vat',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'rows' => $generated ? $this->reports->monthlyVatRows($from, $to, $branchId) : collect(),
        ]));
    }

    public function monthlyVatPdf(Request $request)
    {
        $this->authorizeTax();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $branchId = $this->currentBranchId();
        $rows = $this->reports->monthlyVatRows($from, $to, $branchId);

        $pdf = PDF::loadView('reports.tax.vat-pdf', array_merge(fleet_shared_view_data(), [
            'title' => 'Monthly VAT Report',
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'monthly' => true,
        ]))->setPaper('a4', 'landscape');

        return $pdf->download('monthly-vat-report-' . $from . '-to-' . $to . '.pdf');
    }

    private function authorizeTax(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('reports.tax') || $user->hasPermission('reports.view')), 403);
    }
}
