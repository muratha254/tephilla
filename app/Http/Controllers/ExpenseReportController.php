<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use PDF;

class ExpenseReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeExpenseReport();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        return view('reports.expenses.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.expenses',
            'from' => $from,
            'to' => $to,
            'categoryId' => $categoryId,
            'generated' => $generated,
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'rows' => $generated ? $this->rows($from, $to, $branchId, $categoryId) : collect(),
        ]));
    }

    public function pdf(Request $request)
    {
        $this->authorizeExpenseReport();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $branchId = $this->currentBranchId();
        $rows = $this->rows($from, $to, $branchId, $categoryId);

        $pdf = PDF::loadView('reports.expenses.pdf', array_merge(fleet_shared_view_data(), [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ]))->setPaper('a4', 'landscape');

        return $pdf->download('expense-report-' . $from . '-to-' . $to . '.pdf');
    }

    private function rows(string $from, string $to, ?int $branchId = null, ?int $categoryId = null)
    {
        return Expense::query()
            ->with(['category', 'branch', 'user'])
            ->whereBetween('expense_date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get()
            ->map(function (Expense $expense, int $i) {
                return [
                    'index' => $i + 1,
                    'branch' => optional($expense->branch)->name ?: '-',
                    'code' => $expense->number ?: $expense->voucher_no ?: '-',
                    'expense_date' => optional($expense->expense_date)->format('d-m-Y'),
                    'expense_for' => optional($expense->category)->name ?: ($expense->description ?: '-'),
                    'amount' => round((float) $expense->amount, 2),
                    'note' => $expense->notes ?: $expense->description ?: '-',
                    'created_by' => optional($expense->user)->name ?: '-',
                ];
            })->values();
    }

    private function authorizeExpenseReport(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('reports.expenses') || $user->hasPermission('reports.view')), 403);
    }
}
