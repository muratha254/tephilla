<?php

namespace App\Http\Controllers;

use App\Services\SummaryReportService;
use Illuminate\Http\Request;

class SummaryReportController extends Controller
{
    public function __construct(private SummaryReportService $reports)
    {
    }

    public function daily(Request $request)
    {
        $this->authorizePermission('reports.view');

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        return view('reports.summary.daily', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.summary.daily',
            'from' => $from,
            'to' => $to,
            'userId' => $userId,
            'generated' => $generated,
            'staff' => $this->reports->staffOptions(),
            'report' => $generated ? $this->reports->dailySummary($from, $to, $branchId, $userId) : null,
        ]));
    }

    public function employeeBranch(Request $request)
    {
        $this->authorizePermission('reports.view');

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        return view('reports.summary.employee-branch', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.summary.employee-branch',
            'from' => $from,
            'to' => $to,
            'userId' => $userId,
            'generated' => $generated,
            'staff' => $this->reports->staffOptions(),
            'rows' => $generated ? $this->reports->employeeBranchRows($from, $to, $branchId, $userId) : collect(),
        ]));
    }

    public function branch(Request $request)
    {
        $this->authorizePermission('reports.view');

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        return view('reports.summary.branch', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.summary.branch',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'rows' => $generated ? $this->reports->branchRows($from, $to, $branchId) : collect(),
        ]));
    }

    public function zReport(Request $request)
    {
        $this->authorizePermission('reports.view');

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $category = $request->input('category', 'detailed');
        $report = $request->input('report', 'all');
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        if (! in_array($category, ['summary', 'detailed'], true)) {
            $category = 'detailed';
        }

        $allowedReports = ['all', 'sales', 'payments', 'tax', 'voids', 'returns'];
        if (! in_array($report, $allowedReports, true)) {
            $report = 'all';
        }

        return view('reports.summary.z-report', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.summary.z',
            'from' => $from,
            'to' => $to,
            'category' => $category,
            'report' => $report,
            'generated' => $generated,
            'data' => $generated ? $this->reports->zReport($from, $to, $branchId, $category, $report) : null,
        ]));
    }

    public function debtorsCreditors(Request $request)
    {
        $this->authorizePermission('reports.view');

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        return view('reports.summary.debtors-creditors', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.summary.debtors-creditors',
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'report' => $generated ? $this->reports->debtorsCreditors($branchId) : null,
        ]));
    }
}
