<?php

namespace App\Http\Controllers;

use App\Services\StockReportsService;
use Illuminate\Http\Request;

class StockReportsController extends Controller
{
    public function __construct(private StockReportsService $reports)
    {
    }

    public function priceList(Request $request)
    {
        return $this->catalogPage($request, 'reports.stock.price-list', 'Price List', 'reports.stock.price-list', function ($generated, $branchId, $filters) {
            return $generated ? $this->reports->priceListRows($branchId, $filters) : collect();
        }, [
            'display_cost' => $request->input('display_cost', 'hide'),
            'price_category' => $request->input('price_category', 'retail'),
        ]);
    }

    public function stock(Request $request)
    {
        return $this->catalogPage($request, 'reports.stock.stock', 'Stock Report', 'reports.stock.stock', function ($generated, $branchId, $filters) {
            return $generated ? $this->reports->stockRows($branchId, $filters) : collect();
        });
    }

    public function stockAsAt(Request $request)
    {
        return $this->asAtPage($request, 'reports.stock.stock-as-at', 'Stock Report as at Selected Date', false);
    }

    public function stockAsAtDetailed(Request $request)
    {
        return $this->asAtPage($request, 'reports.stock.stock-as-at-detailed', 'Stock Report as at Selected Date', true);
    }

    public function template(Request $request)
    {
        $this->authorizeStock();
        $generated = $request->boolean('show');
        $branchId = $this->resolveBranchId(
            $request->filled('branch_id') ? (int) $request->input('branch_id') : null
        );
        $filters = [
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'ordering' => $request->input('ordering', 'asc'),
        ];

        return view('reports.stock.template', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.stock.template',
            'generated' => $generated,
            'filters' => $filters,
            'branchId' => $branchId,
            'categories' => $this->reports->categoryOptions(),
            'branches' => $this->reports->branchOptions(),
            'rows' => $generated ? $this->reports->templateRows($branchId, $filters) : collect(),
        ]));
    }

    public function ledger(Request $request)
    {
        return $this->movementPage($request, 'reports.stock.ledger', 'Items Ledger Report', 'ledger');
    }

    public function transfer(Request $request)
    {
        return $this->movementPage($request, 'reports.stock.transfer', 'Stock Transfer Report', 'transfer');
    }

    public function adjust(Request $request)
    {
        return $this->movementPage($request, 'reports.stock.adjust', 'Stock Adjustment Report', 'adjust');
    }

    public function alert(Request $request)
    {
        $this->authorizeStock();
        $generated = $request->boolean('show', true);
        $branchId = $this->currentBranchId();

        return view('reports.stock.alert', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.stock.alert',
            'generated' => $generated,
            'rows' => $generated ? $this->reports->alertRows($branchId) : collect(),
        ]));
    }

    public function valuation(Request $request)
    {
        return $this->catalogPage($request, 'reports.stock.valuation', 'Items Valuation Report', 'reports.stock.valuation', function ($generated, $branchId, $filters) {
            return $generated ? $this->reports->valuationRows($branchId, $filters) : collect();
        });
    }

    public function damaged(Request $request)
    {
        return $this->movementPage($request, 'reports.stock.damaged', 'Damaged Products Report', 'damaged');
    }

    public function issued(Request $request)
    {
        return $this->movementPage($request, 'reports.stock.issued', 'Issued Products Report', 'issued');
    }

    public function consumption(Request $request)
    {
        return $this->movementPage($request, 'reports.stock.consumption', 'Consumption Items Report', 'consumption');
    }

    public function production(Request $request)
    {
        return $this->movementPage($request, 'reports.stock.production', 'Production Report', 'production');
    }

    private function catalogPage(Request $request, string $view, string $title, string $menu, callable $rowsResolver, array $extraFilters = [])
    {
        $this->authorizeStock();
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();
        $filters = array_merge([
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'brand_id' => $request->filled('brand_id') ? (int) $request->input('brand_id') : null,
            'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
        ], $extraFilters);

        return view($view, array_merge(fleet_shared_view_data(), [
            'activeMenu' => $menu,
            'title' => $title,
            'generated' => $generated,
            'filters' => $filters,
            'categories' => $this->reports->categoryOptions(),
            'brands' => $this->reports->brandOptions(),
            'products' => $this->reports->productOptions(),
            'rows' => $rowsResolver($generated, $branchId, $filters),
        ]));
    }

    private function asAtPage(Request $request, string $view, string $title, bool $detailed)
    {
        $this->authorizeStock();
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();
        $reportDate = $request->input('report_date', now()->toDateString());
        $filters = [
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'brand_id' => $request->filled('brand_id') ? (int) $request->input('brand_id') : null,
        ];

        return view($view, array_merge(fleet_shared_view_data(), [
            'activeMenu' => $detailed ? 'reports.stock.stock-as-at-detailed' : 'reports.stock.stock-as-at',
            'title' => $title,
            'generated' => $generated,
            'reportDate' => $reportDate,
            'filters' => $filters,
            'categories' => $this->reports->categoryOptions(),
            'brands' => $this->reports->brandOptions(),
            'rows' => $generated ? $this->reports->stockAsAtRows($reportDate, $branchId, $filters, $detailed) : collect(),
        ]));
    }

    private function movementPage(Request $request, string $menu, string $title, string $view)
    {
        $this->authorizeStock();
        [$from, $to, $generated] = $this->dates($request);
        $branchId = $this->currentBranchId();
        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;

        $rows = match ($view) {
            'ledger' => $this->reports->ledgerRows($from, $to, $branchId, $productId),
            'transfer' => $this->reports->transferRows($from, $to, $branchId, $productId),
            'adjust' => $this->reports->adjustmentRows($from, $to, $branchId, $productId),
            'damaged' => $this->reports->damagedRows($from, $to, $branchId, $productId),
            'issued' => $this->reports->issuedRows($from, $to, $branchId, $productId),
            'consumption' => $this->reports->consumptionRows($from, $to, $branchId, $productId),
            'production' => $this->reports->productionRows($from, $to, $branchId, $productId),
            default => collect(),
        };

        return view('reports.stock.' . $view, array_merge(fleet_shared_view_data(), [
            'activeMenu' => $menu,
            'title' => $title,
            'from' => $from,
            'to' => $to,
            'generated' => $generated,
            'productId' => $productId,
            'products' => $this->reports->productOptions(),
            'rows' => $generated ? $rows : collect(),
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

    private function authorizeStock(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('reports.stock') || $user->hasPermission('reports.view')), 403);
    }
}
