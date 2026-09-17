<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Sale;
use App\Services\SalesReportsService;
use Illuminate\Http\Request;

class SalesReportsController extends Controller
{
    public function __construct(private SalesReportsService $reports)
    {
    }

    public function employeeClearance(Request $request)
    {
        return $this->clearancePage($request, 'reports.sales.employee-clearance', 'Employee Clearance Report', 'reports.sales.employee-clearance');
    }

    public function cashierClearance(Request $request)
    {
        return $this->clearancePage($request, 'reports.sales.cashier-clearance', 'Cashier Clearance Report', 'reports.sales.cashier-clearance');
    }

    public function sales(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $filters = [
            'customer_id' => $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
            'payment_status' => $request->input('payment_status') ?: null,
            'kra' => $request->input('kra') ?: null,
        ];

        return $this->page('reports.sales.sales', 'reports.sales.sales', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'filters' => $filters,
            'customers' => $this->reports->customerOptions(),
            'rows' => $generated ? $this->reports->salesRows($from, $to, $this->currentBranchId(), $filters) : collect(),
        ]);
    }

    public function salesCustom(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $filters = [
            'customer_id' => $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
            'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
            'payment_status' => $request->input('payment_status') ?: null,
        ];

        return $this->page('reports.sales.sales-custom', 'reports.sales.sales-custom', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'filters' => $filters,
            'customers' => $this->reports->customerOptions(),
            'staff' => $this->reports->staffOptions(),
            'rows' => $generated ? $this->reports->salesRows($from, $to, $this->currentBranchId(), $filters) : collect(),
        ]);
    }

    public function salesEmployees(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        return $this->page('reports.sales.sales-employees', 'reports.sales.sales-employees', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'userId' => $userId,
            'staff' => $this->reports->staffOptions(),
            'rows' => $generated ? $this->reports->salesByEmployeeRows($from, $to, $this->currentBranchId(), $userId) : collect(),
        ]);
    }

    public function salesSummary(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);

        return $this->page('reports.sales.sales-summary', 'reports.sales.sales-summary', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'rows' => $generated ? $this->reports->salesSummaryRows($from, $to, $this->currentBranchId()) : collect(),
        ]);
    }

    public function itemSales(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $filters = [
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'brand_id' => $request->filled('brand_id') ? (int) $request->input('brand_id') : null,
            'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
            'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
            'from_time' => $request->input('from_time') ?: null,
            'to_time' => $request->input('to_time') ?: null,
        ];

        return $this->page('reports.sales.item-sales', 'reports.sales.item-sales', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'filters' => $filters,
            'categories' => $this->reports->categoryOptions(),
            'brands' => $this->reports->brandOptions(),
            'products' => $this->reports->productOptions(),
            'staff' => $this->reports->staffOptions(),
            'rows' => $generated ? $this->reports->itemSalesRows($from, $to, $this->currentBranchId(), $filters) : collect(),
        ]);
    }

    public function itemsCategorySummary(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $filters = [
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'brand_id' => $request->filled('brand_id') ? (int) $request->input('brand_id') : null,
            'branch_id' => $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
        ];

        return $this->page('reports.sales.items-category-summary', 'reports.sales.items-category-summary', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'filters' => $filters,
            'categories' => $this->reports->categoryOptions(),
            'brands' => $this->reports->brandOptions(),
            'branchList' => $this->reports->branchOptions(),
            'rows' => $generated ? $this->reports->categorySummaryRows($from, $to, $this->currentBranchId(), $filters) : collect(),
        ]);
    }

    public function itemSalesSummary(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);

        return $this->page('reports.sales.item-sales-summary', 'reports.sales.item-sales-summary', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'rows' => $generated ? $this->reports->itemSalesSummaryRows($from, $to, $this->currentBranchId()) : collect(),
        ]);
    }

    public function salesPayments(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $filters = [
            'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
            'method' => $request->input('method') ?: null,
            'from_time' => $request->input('from_time') ?: null,
            'to_time' => $request->input('to_time') ?: null,
        ];

        return $this->page('reports.sales.payments', 'reports.sales.payments', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'filters' => $filters,
            'staff' => $this->reports->staffOptions(),
            'methods' => [
                Payment::METHOD_CASH,
                Payment::METHOD_MPESA,
                Payment::METHOD_CARD,
                Payment::METHOD_BANK_TRANSFER,
                Payment::METHOD_CHEQUE,
                Payment::METHOD_COMPLEMENTARY,
                Payment::METHOD_ADVANCE,
                Payment::METHOD_OTHER,
            ],
            'rows' => $generated ? $this->reports->paymentRows($from, $to, $this->currentBranchId(), $filters) : collect(),
        ]);
    }

    public function salesCommission(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $filters = [
            'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
            'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
        ];

        return $this->page('reports.sales.commission', 'reports.sales.commission', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'filters' => $filters,
            'products' => $this->reports->productOptions(),
            'staff' => $this->reports->staffOptions(),
            'rows' => $generated ? $this->reports->commissionRows($from, $to, $this->currentBranchId(), $filters) : collect(),
        ]);
    }

    public function salesReturn(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        return $this->page('reports.sales.returns', 'reports.sales.returns', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'userId' => $userId,
            'staff' => $this->reports->staffOptions(),
            'rows' => $generated ? $this->reports->returnRows($from, $to, $this->currentBranchId(), $userId) : collect(),
        ]);
    }

    public function salesCancel(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        return $this->page('reports.sales.cancelled', 'reports.sales.cancelled', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'userId' => $userId,
            'staff' => $this->reports->staffOptions(),
            'rows' => $generated ? $this->reports->cancelledRows($from, $to, $this->currentBranchId(), $userId) : collect(),
        ]);
    }

    public function complementary(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;

        return $this->page('reports.sales.complementary', 'reports.sales.complementary', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'customerId' => $customerId,
            'customers' => $this->reports->customerOptions(),
            'rows' => $generated ? $this->reports->complementaryRows($from, $to, $this->currentBranchId(), $customerId) : collect(),
        ]);
    }

    public function creditAging(Request $request)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $filters = [
            'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
            'customer_id' => $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
            'payment_status' => $request->input('payment_status') ?: null,
        ];

        return $this->page('reports.sales.credit-aging', 'reports.sales.credit-aging', [
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'filters' => $filters,
            'staff' => $this->reports->staffOptions(),
            'customers' => $this->reports->customerOptions(),
            'rows' => $generated ? $this->reports->creditAgingRows($from, $to, $this->currentBranchId(), $filters) : collect(),
        ]);
    }

    private function clearancePage(Request $request, string $menu, string $title, string $routeName)
    {
        $this->authorizeSales();
        [$from, $to, $generated] = $this->dates($request);
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        return $this->page('reports.sales.clearance', $menu, [
            'title' => $title,
            'routeName' => $routeName,
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'userId' => $userId,
            'staff' => $this->reports->staffOptions(),
            'rows' => $generated ? $this->reports->clearanceRows($from, $to, $this->currentBranchId(), $userId) : collect(),
        ]);
    }

    private function dates(Request $request): array
    {
        return [
            $request->input('from', now()->toDateString()),
            $request->input('to', now()->toDateString()),
            $request->boolean('show'),
        ];
    }

    private function page(string $view, string $activeMenu, array $data)
    {
        return view($view, array_merge(fleet_shared_view_data(), $data, [
            'activeMenu' => $activeMenu,
            'paymentStatuses' => [
                Sale::PAYMENT_UNPAID,
                Sale::PAYMENT_PARTIAL,
                Sale::PAYMENT_PAID,
            ],
        ]));
    }

    private function authorizeSales(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('reports.sales') || $user->hasPermission('reports.view')), 403);
    }
}
