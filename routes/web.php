<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\DebitNoteController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\OrderScreenController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\HrDashboardController;
use App\Http\Controllers\HrDepartmentController;
use App\Http\Controllers\HrDesignationController;
use App\Http\Controllers\HrEmployeeCategoryController;
use App\Http\Controllers\HrEmployeeController;
use App\Http\Controllers\HrAdvanceSalaryController;
use App\Http\Controllers\HrAllowanceDeductionController;
use App\Http\Controllers\HrLeaveController;
use App\Http\Controllers\HrAttendanceController;
use App\Http\Controllers\HrPayrollController;
use App\Http\Controllers\HrSalaryPaymentController;
use App\Http\Controllers\HrReportController;
use App\Http\Controllers\ManufacturingController;
use App\Http\Controllers\SummaryReportController;
use App\Http\Controllers\TaxReportController;
use App\Http\Controllers\SalesReportsController;
use App\Http\Controllers\PurchaseReportsController;
use App\Http\Controllers\StockReportsController;
use App\Http\Controllers\ExpenseReportController;
use App\Http\Controllers\SupplierReportController;
use App\Http\Controllers\LoyaltyReportController;
use App\Http\Controllers\ExpiredItemsReportController;
use App\Http\Controllers\CustomerReportsController;
use App\Http\Controllers\AuditTrailReportController;
use App\Http\Controllers\UserLogsReportController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\UserAuthLogsController;
use App\Http\Controllers\BranchSettingsController;
use App\Http\Controllers\TaxSettingsController;
use App\Http\Controllers\LookupSettingsController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\SettingsBackupController;
use App\Http\Controllers\SettingsAuditController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/login', function () {
    return view('auth.login');
})->middleware('guest')->name('login');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
    Route::post('/branch/switch', [DashboardController::class, 'switchBranch'])->name('branch.switch');

    Route::get('/products/search', [ProductController::class, 'search'])
        ->middleware('permission:products.view')
        ->name('products.search');
    Route::get('/products/labels', [ProductController::class, 'labels'])
        ->middleware('permission:products.view')
        ->name('products.labels');
    Route::post('/products/labels/preview', [ProductController::class, 'previewLabels'])
        ->middleware('permission:products.view')
        ->name('products.labels.preview');
    Route::post('/products/labels/print', [ProductController::class, 'printLabels'])
        ->middleware('permission:products.view')
        ->name('products.labels.print');
    Route::get('/products/price-log', [ProductController::class, 'priceLog'])
        ->middleware('permission:products.view')
        ->name('products.prices');
    Route::get('/products/create', [ProductController::class, 'create'])
        ->middleware('permission:products.create')
        ->name('products.create');
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:products.view')
        ->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:products.create')
        ->name('products.store');
    Route::get('/products/{product}/children', [ProductController::class, 'children'])
        ->middleware('permission:products.view')
        ->name('products.children');
    Route::post('/products/{product}/children', [ProductController::class, 'storeChild'])
        ->middleware('permission:products.create')
        ->name('products.children.store');
    Route::post('/products/{product}/child-stock', [ProductController::class, 'storeChildStock'])
        ->middleware('permission:products.create')
        ->name('products.child-stock.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('permission:products.update')
        ->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:products.update')
        ->name('products.update');
    Route::post('/products/{product}/image', [ProductController::class, 'updateImage'])
        ->middleware('permission:products.update')
        ->name('products.image');
    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->middleware('permission:products.view')
        ->name('products.show');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.delete')
        ->name('products.destroy');
    Route::middleware('permission:categories.view')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    });
    Route::middleware('permission:categories.manage')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    Route::middleware('permission:brands.view')->group(function () {
        Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    });
    Route::middleware('permission:brands.manage')->group(function () {
        Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
        Route::put('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
    });

    Route::middleware('permission:units.view')->group(function () {
        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    });
    Route::middleware('permission:units.manage')->group(function () {
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });

    Route::get('/stock', [StockController::class, 'manager'])
        ->middleware('permission:inventory.view')
        ->name('stock.manager');
    Route::get('/stock/alert', [StockController::class, 'alert'])
        ->middleware('permission:inventory.view')
        ->name('stock.alert');
    Route::get('/stock/issued/create', [StockController::class, 'createIssued'])
        ->middleware('permission:inventory.adjust')
        ->name('stock.issued.create');
    Route::get('/stock/issued', [StockController::class, 'issued'])
        ->middleware('permission:inventory.adjust')
        ->name('stock.issued');
    Route::post('/stock/issued', [StockController::class, 'storeIssued'])
        ->middleware('permission:inventory.adjust')
        ->name('stock.issued.store');
    Route::delete('/stock/issued/{movement}', [StockController::class, 'destroyIssued'])
        ->middleware('permission:inventory.adjust')
        ->name('stock.issued.destroy');
    Route::get('/stock/conversion', [StockController::class, 'conversion'])
        ->middleware('permission:inventory.adjust')
        ->name('stock.conversion');
    Route::post('/stock/conversion', [StockController::class, 'storeConversion'])
        ->middleware('permission:inventory.adjust')
        ->name('stock.conversion.store');
    Route::get('/stock/transfers/create', [StockTransferController::class, 'create'])
        ->middleware('permission:inventory.transfer')
        ->name('stock.transfers.create');
    Route::get('/stock/transfers', [StockTransferController::class, 'index'])
        ->middleware('permission:inventory.view')
        ->name('stock.transfers.index');
    Route::post('/stock/transfers', [StockTransferController::class, 'store'])
        ->middleware('permission:inventory.transfer')
        ->name('stock.transfers.store');
    Route::post('/stock/transfers/{transfer}/complete', [StockTransferController::class, 'complete'])
        ->middleware('permission:inventory.transfer')
        ->name('stock.transfers.complete');
    Route::post('/stock/transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])
        ->middleware('permission:inventory.transfer')
        ->name('stock.transfers.cancel');
    Route::get('/stock/transfers/{transfer}', [StockTransferController::class, 'show'])
        ->middleware('permission:inventory.view')
        ->name('stock.transfers.show');
    Route::post('/stock/{product}/adjust', [StockController::class, 'adjust'])
        ->middleware('permission:inventory.adjust')
        ->name('stock.adjust');
    Route::post('/stock/{product}/price', [StockController::class, 'updatePrice'])
        ->middleware('permission:inventory.adjust')
        ->name('stock.price');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])
        ->middleware('permission:purchases.create')
        ->name('purchases.create');
    Route::get('/purchases', [PurchaseController::class, 'index'])
        ->middleware('permission:purchases.view')
        ->name('purchases.index');
    Route::post('/purchases', [PurchaseController::class, 'store'])
        ->middleware('permission:purchases.create')
        ->name('purchases.store');
    Route::get('/purchases/{purchase}/edit', [PurchaseController::class, 'edit'])
        ->middleware('permission:purchases.update')
        ->name('purchases.edit');
    Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])
        ->middleware('permission:purchases.update')
        ->name('purchases.update');
    Route::put('/purchases/{purchase}/details', [PurchaseController::class, 'updateDetails'])
        ->middleware('permission:purchases.update')
        ->name('purchases.details.update');
    Route::put('/purchases/{purchase}/status', [PurchaseController::class, 'updateStatus'])
        ->middleware('permission:purchases.update')
        ->name('purchases.status.update');
    Route::get('/purchases/{purchase}/payments', [PurchaseController::class, 'payments'])
        ->middleware('permission:purchases.view')
        ->name('purchases.payments');
    Route::post('/purchases/{purchase}/payments', [PurchaseController::class, 'storePayment'])
        ->middleware('permission:purchases.update')
        ->name('purchases.payments.store');
    Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'print'])
        ->middleware('permission:purchases.view')
        ->name('purchases.print');
    Route::get('/purchases/{purchase}/grn/{receipt}/print', [PurchaseController::class, 'printGrn'])
        ->middleware('permission:purchases.view')
        ->name('purchases.grn.print');
    Route::get('/purchases/{purchase}/returnable', [PurchaseReturnController::class, 'returnable'])
        ->middleware('permission:purchases.return')
        ->name('purchases.returns.returnable');
    Route::post('/purchases/{purchase}/returns', [PurchaseReturnController::class, 'store'])
        ->middleware('permission:purchases.return')
        ->name('purchases.returns.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])
        ->middleware('permission:purchases.view')
        ->name('purchases.show');
    Route::get('/purchase-returns', [PurchaseReturnController::class, 'index'])
        ->middleware('permission:purchases.return')
        ->name('purchase-returns.index');
    Route::get('/debit-notes', [DebitNoteController::class, 'index'])
        ->middleware('permission:purchases.view')
        ->name('debit-notes.index');
    Route::get('/debit-notes/create', [DebitNoteController::class, 'create'])
        ->middleware('permission:purchases.return')
        ->name('debit-notes.create');
    Route::post('/debit-notes', [DebitNoteController::class, 'store'])
        ->middleware('permission:purchases.return')
        ->name('debit-notes.store');
    Route::get('/debit-notes/purchases/{purchase}/eligible', [DebitNoteController::class, 'eligible'])
        ->middleware('permission:purchases.return')
        ->name('debit-notes.eligible');
    Route::get('/debit-notes/pdf', [DebitNoteController::class, 'pdf'])
        ->middleware('permission:purchases.view')
        ->name('debit-notes.pdf');
    Route::middleware('permission:pos.view')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/catalog', [PosController::class, 'catalog'])->name('pos.catalog');
        Route::get('/pos/search', [PosController::class, 'search'])->name('pos.search');
        Route::get('/pos/holds', [PosController::class, 'holds'])->name('pos.holds');
        Route::get('/pos/holds/{sale}', [PosController::class, 'showHold'])->name('pos.holds.show');
        Route::get('/pos/sales/{sale}/receipt', [PosController::class, 'receipt'])->name('pos.receipt');
    });

    Route::middleware('permission:pos.operate')->group(function () {
        Route::post('/pos/customers', [PosController::class, 'storeCustomer'])->name('pos.customers.store');
        Route::post('/pos/order', [PosController::class, 'order'])->name('pos.order');
    });

    Route::post('/pos/hold', [PosController::class, 'hold'])
        ->middleware('permission:pos.hold')
        ->name('pos.hold');

    // Static sales paths before /sales/{sale}
    Route::get('/sales', [SaleController::class, 'index'])
        ->middleware('permission:sales.view')
        ->name('sales.index');
    Route::get('/sales/returns', [SaleReturnController::class, 'index'])
        ->middleware('permission:sales.return')
        ->name('sales.returns');
    Route::get('/sales/voids', [SaleController::class, 'voids'])->name('sales.voids'); // OR: controller auth
    Route::get('/sales/orders', [OrderScreenController::class, 'index'])->name('sales.orders'); // OR: controller auth

    Route::get('/sales/credit-notes', [CreditNoteController::class, 'index'])
        ->middleware('permission:sales.view')
        ->name('sales.credit-notes');
    Route::get('/sales/credit-notes/create', [CreditNoteController::class, 'create'])
        ->middleware('permission:sales.return')
        ->name('sales.credit-notes.create');
    Route::get('/sales/credit-notes/eligible/{sale}', [CreditNoteController::class, 'eligibleSaleJson'])
        ->middleware('permission:sales.return')
        ->name('sales.credit-notes.eligible');
    Route::post('/sales/credit-notes', [CreditNoteController::class, 'store'])
        ->middleware('permission:sales.return')
        ->name('sales.credit-notes.store');
    Route::get('/sales/credit-notes/{creditNote}/print', [CreditNoteController::class, 'print'])
        ->middleware('permission:sales.view')
        ->name('sales.credit-notes.print');
    Route::post('/sales/credit-notes/{creditNote}/post', [CreditNoteController::class, 'post'])
        ->middleware('permission:sales.return')
        ->name('sales.credit-notes.post');
    Route::post('/sales/credit-notes/{creditNote}/void', [CreditNoteController::class, 'void'])
        ->middleware('permission:sales.void')
        ->name('sales.credit-notes.void');
    Route::get('/sales/credit-notes/{creditNote}', [CreditNoteController::class, 'show'])
        ->middleware('permission:sales.view')
        ->name('sales.credit-notes.show');

    Route::get('/sales/{sale}/return', [SaleReturnController::class, 'form'])
        ->middleware('permission:sales.return')
        ->name('sales.returns.form');
    Route::post('/sales/{sale}/return', [SaleReturnController::class, 'store'])
        ->middleware('permission:sales.return')
        ->name('sales.returns.store');
    Route::get('/sales/{sale}/payments', [SaleController::class, 'payments'])
        ->middleware('permission:sales.view')
        ->name('sales.payments');
    Route::get('/sales/{sale}/payments/{payment}/print', [SaleController::class, 'printPayment'])
        ->middleware('permission:sales.view')
        ->name('sales.payments.print');
    Route::get('/sales/{sale}/print', [SaleController::class, 'print'])
        ->middleware('permission:sales.view')
        ->name('sales.print');
    Route::get('/sales/{sale}/pdf', [SaleController::class, 'pdf'])
        ->middleware('permission:sales.view')
        ->name('sales.pdf');
    Route::get('/sales/{sale}/pos-pdf', [SaleController::class, 'posPdf'])
        ->middleware('permission:sales.view')
        ->name('sales.pos-pdf');
    Route::get('/sales/{sale}/dispatch-pdf', [SaleController::class, 'dispatchPdf'])
        ->middleware('permission:sales.view')
        ->name('sales.dispatch-pdf');
    Route::get('/sales/{sale}/delivery-pdf', [SaleController::class, 'deliveryPdf'])
        ->middleware('permission:sales.view')
        ->name('sales.delivery-pdf');
    Route::post('/sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void'); // OR: controller auth
    Route::get('/sales/{sale}', [SaleController::class, 'show'])
        ->middleware('permission:sales.view')
        ->name('sales.show');
    Route::middleware('permission:payments.create')->group(function () {
        Route::post('/sales/{sale}/payments', [SaleController::class, 'storePayment'])->name('sales.payments.store');
        Route::post('/sales/{sale}/payment-plan', [SaleController::class, 'storePaymentPlan'])->name('sales.payment-plan.store');
        Route::post('/sales/{sale}/payment-plan/{item}', [SaleController::class, 'storePlanPayment'])->name('sales.payment-plan.pay');
    });

    Route::middleware('permission:payments.update')->group(function () {
        Route::put('/sales/{sale}/payments/{payment}', [SaleController::class, 'updatePayment'])->name('sales.payments.update');
    });

    Route::middleware('permission:payments.delete')->group(function () {
        Route::delete('/sales/{sale}/payments/{payment}', [SaleController::class, 'destroyPayment'])->name('sales.payments.destroy');
    });

    Route::middleware('permission:sales.update')->group(function () {
        Route::put('/sales/{sale}/details', [SaleController::class, 'updateDetails'])->name('sales.details.update');
        Route::put('/sales/{sale}/discount', [SaleController::class, 'applyDiscount'])->name('sales.discount');
        Route::post('/sales/{sale}/remove-vat', [SaleController::class, 'removeVat'])->name('sales.remove-vat');
    });

    Route::get('/quotations/create', [QuotationController::class, 'create'])
        ->middleware('permission:quotations.create')
        ->name('quotations.create');
    Route::get('/quotations', [QuotationController::class, 'index'])
        ->middleware('permission:quotations.view')
        ->name('quotations.index');
    Route::post('/quotations', [QuotationController::class, 'store'])
        ->middleware('permission:quotations.create')
        ->name('quotations.store');
    Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])
        ->middleware('permission:quotations.update')
        ->name('quotations.edit');
    Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])
        ->middleware('permission:quotations.view')
        ->name('quotations.print');
    Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])
        ->middleware('permission:quotations.update')
        ->name('quotations.update');
    Route::post('/quotations/{quotation}/send', [QuotationController::class, 'send'])
        ->middleware('permission:quotations.update')
        ->name('quotations.send');
    Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])
        ->middleware('permission:quotations.convert')
        ->name('quotations.convert');
    Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])
        ->middleware('permission:quotations.delete')
        ->name('quotations.destroy');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])
        ->middleware('permission:quotations.view')
        ->name('quotations.show');
    Route::middleware('permission:suppliers.view')->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/template', [SupplierController::class, 'template'])->name('suppliers.template');
    });

    Route::middleware('permission:suppliers.create')->group(function () {
        Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers/import', [SupplierController::class, 'import'])->name('suppliers.import');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    });

    Route::middleware('permission:suppliers.update')->group(function () {
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    });

    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->middleware('permission:suppliers.delete')
        ->name('suppliers.destroy');

    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/customers/categories', [CustomerCategoryController::class, 'index'])->name('customers.categories');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/archived', [CustomerController::class, 'archived'])->name('customers.archived');
        Route::get('/customers/template', [CustomerController::class, 'template'])->name('customers.template');
        Route::get('/customers/download', [CustomerController::class, 'download'])->name('customers.download');
        Route::get('/customers/statement', [CustomerController::class, 'statement'])->name('customers.statement');
        Route::get('/customers/{customer}/payments', [CustomerController::class, 'payments'])->name('customers.payments');
    });

    Route::middleware('permission:customers.create')->group(function () {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers/import', [CustomerController::class, 'import'])->name('customers.import');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::post('/customers/categories', [CustomerCategoryController::class, 'store'])->name('customers.categories.store');
    });

    Route::middleware('permission:customers.update')->group(function () {
        Route::put('/customers/categories/{category}', [CustomerCategoryController::class, 'update'])->name('customers.categories.update');
        Route::post('/customers/write-off', [CustomerController::class, 'writeOff'])->name('customers.write-off');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
    });

    Route::middleware('permission:payments.create')->group(function () {
        Route::post('/customers/{customer}/payments', [CustomerController::class, 'storePayment'])->name('customers.payments.store');
    });

    Route::middleware('permission:customers.delete')->group(function () {
        Route::delete('/customers/categories/{category}', [CustomerCategoryController::class, 'destroy'])->name('customers.categories.destroy');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    Route::middleware('permission:expenses.view')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    });

    Route::middleware('permission:expenses.create')->group(function () {
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory'])->name('expenses.categories.store');
    });

    Route::middleware('permission:expenses.update')->group(function () {
        Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
    });

    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])
        ->middleware('permission:expenses.delete')
        ->name('expenses.destroy');

    Route::middleware('permission:accounting.view')->group(function () {
        Route::get('/accounting/types', [AccountingController::class, 'types'])->name('accounting.types');
        Route::get('/accounting/sub-types', [AccountingController::class, 'subTypes'])->name('accounting.sub-types');
        Route::get('/accounting/chart', [AccountingController::class, 'chart'])->name('accounting.chart');
        Route::get('/accounting/balances', [AccountingController::class, 'balances'])->name('accounting.balances');
        Route::get('/accounting/statement', [AccountingController::class, 'statement'])->name('accounting.statement');
        Route::get('/accounting/money', [AccountingController::class, 'money'])->name('accounting.money');
        Route::get('/accounting/journal', [AccountingController::class, 'journal'])->name('accounting.journal');
        Route::get('/accounting/profit-loss', [AccountingController::class, 'profitLoss'])->name('accounting.profit-loss');
        Route::get('/accounting/profit-loss/pdf', [AccountingController::class, 'profitLossPdf'])->name('accounting.profit-loss.pdf');
        Route::get('/accounting/balance-sheet', [AccountingController::class, 'balanceSheet'])->name('accounting.balance-sheet');
        Route::get('/accounting/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.trial-balance');
        Route::get('/accounting/combined-gl', [AccountingController::class, 'combinedGl'])->name('accounting.combined-gl');
        Route::get('/accounting/customers', [AccountingController::class, 'customers'])->name('accounting.customers');
        Route::get('/accounting/suppliers', [AccountingController::class, 'suppliers'])->name('accounting.suppliers');
    });

    Route::middleware('permission:accounting.manage')->group(function () {
        Route::post('/accounting/sub-types', [AccountingController::class, 'storeSubType'])->name('accounting.sub-types.store');
        Route::put('/accounting/sub-types/{subType}', [AccountingController::class, 'updateSubType'])->name('accounting.sub-types.update');
        Route::delete('/accounting/sub-types/{subType}', [AccountingController::class, 'destroySubType'])->name('accounting.sub-types.destroy');
        Route::post('/accounting/chart', [AccountingController::class, 'storeChart'])->name('accounting.chart.store');
        Route::put('/accounting/chart/{account}', [AccountingController::class, 'updateChart'])->name('accounting.chart.update');
        Route::delete('/accounting/chart/{account}', [AccountingController::class, 'destroyChart'])->name('accounting.chart.destroy');
        Route::post('/accounting/money', [AccountingController::class, 'storeMoney'])->name('accounting.money.store');
        Route::get('/accounting/journal/create', [AccountingController::class, 'createJournal'])->name('accounting.journal.create');
        Route::post('/accounting/journal', [AccountingController::class, 'storeJournal'])->name('accounting.journal.store');
        Route::post('/accounting/journal/multiple', [AccountingController::class, 'storeMultipleJournal'])->name('accounting.journal.multiple');
        Route::delete('/accounting/journal/{journal}', [AccountingController::class, 'destroyJournal'])->name('accounting.journal.destroy');
    });

    Route::get('/documents/categories', [DocumentFileController::class, 'categories'])
        ->middleware('permission:documents.view')
        ->name('documents.categories');
    Route::post('/documents/categories', [DocumentFileController::class, 'storeCategory'])
        ->middleware('permission:documents.manage')
        ->name('documents.categories.store');
    Route::put('/documents/categories/{category}', [DocumentFileController::class, 'updateCategory'])
        ->middleware('permission:documents.manage')
        ->name('documents.categories.update');
    Route::delete('/documents/categories/{category}', [DocumentFileController::class, 'destroyCategory'])
        ->middleware('permission:documents.manage')
        ->name('documents.categories.destroy');
    Route::get('/documents/create', [DocumentFileController::class, 'create'])
        ->middleware('permission:documents.manage')
        ->name('documents.create');
    Route::get('/documents', [DocumentFileController::class, 'index'])
        ->middleware('permission:documents.view')
        ->name('documents.index');
    Route::post('/documents', [DocumentFileController::class, 'store'])
        ->middleware('permission:documents.manage')
        ->name('documents.store');
    Route::get('/documents/{file}/download', [DocumentFileController::class, 'download'])
        ->middleware('permission:documents.view')
        ->name('documents.download');
    Route::get('/documents/{file}/edit', [DocumentFileController::class, 'edit'])
        ->middleware('permission:documents.manage')
        ->name('documents.edit');
    Route::put('/documents/{file}', [DocumentFileController::class, 'update'])
        ->middleware('permission:documents.manage')
        ->name('documents.update');
    Route::delete('/documents/{file}', [DocumentFileController::class, 'destroy'])
        ->middleware('permission:documents.manage')
        ->name('documents.destroy');
    Route::middleware('permission:products.view')->group(function () {
        Route::get('/manufacturing/bom', [ManufacturingController::class, 'bomIndex'])->name('manufacturing.bom.index');
        Route::get('/manufacturing/bom/create', [ManufacturingController::class, 'bomCreate'])->name('manufacturing.bom.create');
        Route::post('/manufacturing/bom', [ManufacturingController::class, 'bomStore'])->name('manufacturing.bom.store');
        Route::get('/manufacturing/bom/{bom}/edit', [ManufacturingController::class, 'bomEdit'])->name('manufacturing.bom.edit');
        Route::put('/manufacturing/bom/{bom}', [ManufacturingController::class, 'bomUpdate'])->name('manufacturing.bom.update');
        Route::delete('/manufacturing/bom/{bom}', [ManufacturingController::class, 'bomDestroy'])->name('manufacturing.bom.destroy');
        Route::get('/manufacturing/bom/{bom}/json', [ManufacturingController::class, 'bomJson'])->name('manufacturing.bom.json');
        Route::get('/manufacturing/packaging/setups', [ManufacturingController::class, 'packagingSetupIndex'])->name('manufacturing.packaging.setups');
        Route::post('/manufacturing/packaging/setups', [ManufacturingController::class, 'packagingSetupStore'])->name('manufacturing.packaging.setups.store');
        Route::delete('/manufacturing/packaging/setups/{setup}', [ManufacturingController::class, 'packagingSetupDestroy'])->name('manufacturing.packaging.setups.destroy');
        Route::get('/manufacturing/packaging', [ManufacturingController::class, 'packagingIndex'])->name('manufacturing.packaging.index');
        Route::get('/manufacturing/packaging/create', [ManufacturingController::class, 'packagingCreate'])->name('manufacturing.packaging.create');
        Route::post('/manufacturing/packaging', [ManufacturingController::class, 'packagingStore'])->name('manufacturing.packaging.store');
    });

    Route::middleware('permission:inventory.adjust')->group(function () {
        Route::get('/manufacturing/production', [ManufacturingController::class, 'productionIndex'])->name('manufacturing.production.index');
        Route::get('/manufacturing/production/create', [ManufacturingController::class, 'productionCreate'])->name('manufacturing.production.create');
        Route::post('/manufacturing/production', [ManufacturingController::class, 'productionStore'])->name('manufacturing.production.store');
        Route::delete('/manufacturing/production/{production}', [ManufacturingController::class, 'productionDestroy'])->name('manufacturing.production.destroy');
    });

    Route::middleware('permission:hr.view')->prefix('hr')->group(function () {
        Route::get('/dashboard', [HrDashboardController::class, 'index'])->name('hr.dashboard');

        Route::get('/departments', [HrDepartmentController::class, 'index'])->name('hr.departments.index');
        Route::get('/departments/create', [HrDepartmentController::class, 'create'])->name('hr.departments.create');
        Route::post('/departments', [HrDepartmentController::class, 'store'])->name('hr.departments.store');
        Route::get('/departments/{department}/edit', [HrDepartmentController::class, 'edit'])->name('hr.departments.edit');
        Route::put('/departments/{department}', [HrDepartmentController::class, 'update'])->name('hr.departments.update');
        Route::delete('/departments/{department}', [HrDepartmentController::class, 'destroy'])->name('hr.departments.destroy');

        Route::get('/designations', [HrDesignationController::class, 'index'])->name('hr.designations.index');
        Route::get('/designations/create', [HrDesignationController::class, 'create'])->name('hr.designations.create');
        Route::post('/designations', [HrDesignationController::class, 'store'])->name('hr.designations.store');
        Route::get('/designations/{designation}/edit', [HrDesignationController::class, 'edit'])->name('hr.designations.edit');
        Route::put('/designations/{designation}', [HrDesignationController::class, 'update'])->name('hr.designations.update');
        Route::delete('/designations/{designation}', [HrDesignationController::class, 'destroy'])->name('hr.designations.destroy');

        Route::get('/employee-categories', [HrEmployeeCategoryController::class, 'index'])->name('hr.employee-categories.index');
        Route::get('/employee-categories/create', [HrEmployeeCategoryController::class, 'create'])->name('hr.employee-categories.create');
        Route::post('/employee-categories', [HrEmployeeCategoryController::class, 'store'])->name('hr.employee-categories.store');
        Route::get('/employee-categories/{category}/edit', [HrEmployeeCategoryController::class, 'edit'])->name('hr.employee-categories.edit');
        Route::put('/employee-categories/{category}', [HrEmployeeCategoryController::class, 'update'])->name('hr.employee-categories.update');
        Route::delete('/employee-categories/{category}', [HrEmployeeCategoryController::class, 'destroy'])->name('hr.employee-categories.destroy');

        Route::get('/employees', [HrEmployeeController::class, 'index'])->name('hr.employees.index');
        Route::get('/employees/archived', [HrEmployeeController::class, 'archived'])->name('hr.employees.archived');
        Route::get('/employees/create', [HrEmployeeController::class, 'create'])->name('hr.employees.create');
        Route::post('/employees', [HrEmployeeController::class, 'store'])->name('hr.employees.store');
        Route::get('/employees/template', [HrEmployeeController::class, 'downloadTemplate'])->name('hr.employees.template');
        Route::post('/employees/upload', [HrEmployeeController::class, 'bulkUpload'])->name('hr.employees.upload');
        Route::post('/employees/{id}/restore', [HrEmployeeController::class, 'restore'])->name('hr.employees.restore');
        Route::get('/employees/{employee}/edit', [HrEmployeeController::class, 'edit'])->name('hr.employees.edit');
        Route::put('/employees/{employee}', [HrEmployeeController::class, 'update'])->name('hr.employees.update');
        Route::delete('/employees/{employee}', [HrEmployeeController::class, 'destroy'])->name('hr.employees.destroy');

        Route::get('/advance-salary', [HrAdvanceSalaryController::class, 'index'])->name('hr.advance-salary.index');
        Route::get('/advance-salary/create', [HrAdvanceSalaryController::class, 'create'])->name('hr.advance-salary.create');
        Route::post('/advance-salary', [HrAdvanceSalaryController::class, 'store'])->name('hr.advance-salary.store');
        Route::get('/advance-salary/{advance}/edit', [HrAdvanceSalaryController::class, 'edit'])->name('hr.advance-salary.edit');
        Route::put('/advance-salary/{advance}', [HrAdvanceSalaryController::class, 'update'])->name('hr.advance-salary.update');
        Route::delete('/advance-salary/{advance}', [HrAdvanceSalaryController::class, 'destroy'])->name('hr.advance-salary.destroy');

        Route::get('/allowances/employee', [HrAllowanceDeductionController::class, 'employeeChargesIndex'])->name('hr.allowances.employee');
        Route::get('/allowances/employee/create', [HrAllowanceDeductionController::class, 'employeeChargesCreate'])->name('hr.allowances.employee.create');
        Route::post('/allowances/employee', [HrAllowanceDeductionController::class, 'employeeChargesStore'])->name('hr.allowances.employee.store');
        Route::get('/allowances/employee/{record}/edit', [HrAllowanceDeductionController::class, 'employeeChargesEdit'])->name('hr.allowances.employee.edit');
        Route::put('/allowances/employee/{record}', [HrAllowanceDeductionController::class, 'employeeChargesUpdate'])->name('hr.allowances.employee.update');
        Route::delete('/allowances/employee/{record}', [HrAllowanceDeductionController::class, 'employeeChargesDestroy'])->name('hr.allowances.employee.destroy');

        Route::get('/allowances', [HrAllowanceDeductionController::class, 'chargesIndex'])->name('hr.allowances.index');
        Route::get('/allowances/create', [HrAllowanceDeductionController::class, 'chargesCreate'])->name('hr.allowances.create');
        Route::post('/allowances', [HrAllowanceDeductionController::class, 'chargesStore'])->name('hr.allowances.store');
        Route::get('/allowances/{charge}/edit', [HrAllowanceDeductionController::class, 'chargesEdit'])->name('hr.allowances.edit');
        Route::put('/allowances/{charge}', [HrAllowanceDeductionController::class, 'chargesUpdate'])->name('hr.allowances.update');
        Route::delete('/allowances/{charge}', [HrAllowanceDeductionController::class, 'chargesDestroy'])->name('hr.allowances.destroy');

        Route::get('/leave/holidays', [HrLeaveController::class, 'holidaysIndex'])->name('hr.leave.holidays');
        Route::post('/leave/holidays', [HrLeaveController::class, 'holidaysStore'])->name('hr.leave.holidays.store');
        Route::put('/leave/holidays/{holiday}', [HrLeaveController::class, 'holidaysUpdate'])->name('hr.leave.holidays.update');
        Route::delete('/leave/holidays/{holiday}', [HrLeaveController::class, 'holidaysDestroy'])->name('hr.leave.holidays.destroy');

        Route::get('/leave/types', [HrLeaveController::class, 'typesIndex'])->name('hr.leave.types');
        Route::get('/leave/types/create', [HrLeaveController::class, 'typesCreate'])->name('hr.leave.types.create');
        Route::post('/leave/types', [HrLeaveController::class, 'typesStore'])->name('hr.leave.types.store');
        Route::get('/leave/types/{type}/edit', [HrLeaveController::class, 'typesEdit'])->name('hr.leave.types.edit');
        Route::put('/leave/types/{type}', [HrLeaveController::class, 'typesUpdate'])->name('hr.leave.types.update');
        Route::delete('/leave/types/{type}', [HrLeaveController::class, 'typesDestroy'])->name('hr.leave.types.destroy');

        Route::get('/leave/assign', [HrLeaveController::class, 'assignmentsIndex'])->name('hr.leave.assign');
        Route::post('/leave/assign', [HrLeaveController::class, 'assignmentsStore'])->name('hr.leave.assign.store');
        Route::delete('/leave/assign/{assignment}', [HrLeaveController::class, 'assignmentsDestroy'])->name('hr.leave.assign.destroy');

        Route::get('/leave/manage', [HrLeaveController::class, 'leavesIndex'])->name('hr.leave.manage');
        Route::post('/leave/manage', [HrLeaveController::class, 'leavesStore'])->name('hr.leave.manage.store');
        Route::put('/leave/manage/{leave}/status', [HrLeaveController::class, 'leavesUpdateStatus'])->name('hr.leave.manage.status');
        Route::delete('/leave/manage/{leave}', [HrLeaveController::class, 'leavesDestroy'])->name('hr.leave.manage.destroy');

        Route::get('/attendance', [HrAttendanceController::class, 'index'])->name('hr.attendance.index');
        Route::get('/attendance/create', [HrAttendanceController::class, 'create'])->name('hr.attendance.create');
        Route::post('/attendance', [HrAttendanceController::class, 'store'])->name('hr.attendance.store');
        Route::get('/attendance/{attendance}/edit', [HrAttendanceController::class, 'edit'])->name('hr.attendance.edit');
        Route::put('/attendance/{attendance}', [HrAttendanceController::class, 'update'])->name('hr.attendance.update');
        Route::delete('/attendance/{attendance}', [HrAttendanceController::class, 'destroy'])->name('hr.attendance.destroy');

        Route::get('/payroll/create', [HrPayrollController::class, 'create'])->name('hr.payroll.create');
        Route::get('/payroll/payslip/{item}', [HrPayrollController::class, 'payslip'])->name('hr.payroll.payslip');
        Route::get('/payroll', [HrPayrollController::class, 'index'])->name('hr.payroll.index');
        Route::post('/payroll', [HrPayrollController::class, 'store'])->name('hr.payroll.store');
        Route::post('/payroll/{payroll}/process', [HrPayrollController::class, 'process'])->name('hr.payroll.process');
        Route::post('/payroll/{payroll}/approve', [HrPayrollController::class, 'approve'])->name('hr.payroll.approve');
        Route::delete('/payroll/{payroll}', [HrPayrollController::class, 'destroy'])->name('hr.payroll.destroy');
        Route::get('/payroll/{payroll}', [HrPayrollController::class, 'show'])->name('hr.payroll.show');

        Route::get('/payments/create', [HrSalaryPaymentController::class, 'create'])->name('hr.payments.create');
        Route::get('/payments', [HrSalaryPaymentController::class, 'index'])->name('hr.payments.index');
        Route::post('/payments', [HrSalaryPaymentController::class, 'store'])->name('hr.payments.store');
        Route::get('/payments/{payment}', [HrSalaryPaymentController::class, 'show'])->name('hr.payments.show');
        Route::get('/reports', [HrReportController::class, 'index'])->name('hr.reports.index');
        Route::get('/reports/employees', [HrReportController::class, 'employees'])->name('hr.reports.employees');
        Route::get('/reports/attendance', [HrReportController::class, 'attendance'])->name('hr.reports.attendance');
        Route::get('/reports/leave', [HrReportController::class, 'leave'])->name('hr.reports.leave');
        Route::get('/reports/payroll', [HrReportController::class, 'payroll'])->name('hr.reports.payroll');
        Route::get('/reports/payroll-summary', [HrReportController::class, 'payrollSummary'])->name('hr.reports.payroll-summary');
        Route::get('/reports/payment-history', [HrReportController::class, 'paymentHistory'])->name('hr.reports.payment-history');
    });

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports/summary/daily', [SummaryReportController::class, 'daily'])->name('reports.summary.daily');
        Route::get('/reports/summary/employee-branch', [SummaryReportController::class, 'employeeBranch'])->name('reports.summary.employee-branch');
        Route::get('/reports/summary/branch', [SummaryReportController::class, 'branch'])->name('reports.summary.branch');
        Route::get('/reports/summary/z', [SummaryReportController::class, 'zReport'])->name('reports.summary.z');
        Route::get('/reports/summary/debtors-creditors', [SummaryReportController::class, 'debtorsCreditors'])->name('reports.summary.debtors-creditors');
        Route::get('/reports/suppliers', [SupplierReportController::class, 'index'])->name('reports.suppliers');
        Route::get('/reports/loyalty', [LoyaltyReportController::class, 'index'])->name('reports.loyalty');
    });

    Route::middleware('permission:reports.tax')->group(function () {
        Route::get('/reports/tax', [TaxReportController::class, 'tax'])->name('reports.tax.index');
        Route::get('/reports/tax/vat', [TaxReportController::class, 'vat'])->name('reports.tax.vat');
        Route::get('/reports/tax/vat/pdf', [TaxReportController::class, 'vatPdf'])->name('reports.tax.vat.pdf');
        Route::get('/reports/tax/sales-vat', [TaxReportController::class, 'salesVat'])->name('reports.tax.sales-vat');
        Route::get('/reports/tax/monthly-vat', [TaxReportController::class, 'monthlyVat'])->name('reports.tax.monthly-vat');
        Route::get('/reports/tax/monthly-vat/pdf', [TaxReportController::class, 'monthlyVatPdf'])->name('reports.tax.monthly-vat.pdf');
    });

    Route::middleware('permission:reports.sales')->group(function () {
        Route::get('/reports/sales/employee-clearance', [SalesReportsController::class, 'employeeClearance'])->name('reports.sales.employee-clearance');
        Route::get('/reports/sales/cashier-clearance', [SalesReportsController::class, 'cashierClearance'])->name('reports.sales.cashier-clearance');
        Route::get('/reports/sales/sales', [SalesReportsController::class, 'sales'])->name('reports.sales.sales');
        Route::get('/reports/sales/sales-custom', [SalesReportsController::class, 'salesCustom'])->name('reports.sales.sales-custom');
        Route::get('/reports/sales/sales-employees', [SalesReportsController::class, 'salesEmployees'])->name('reports.sales.sales-employees');
        Route::get('/reports/sales/sales-summary', [SalesReportsController::class, 'salesSummary'])->name('reports.sales.sales-summary');
        Route::get('/reports/sales/item-sales', [SalesReportsController::class, 'itemSales'])->name('reports.sales.item-sales');
        Route::get('/reports/sales/items-category-summary', [SalesReportsController::class, 'itemsCategorySummary'])->name('reports.sales.items-category-summary');
        Route::get('/reports/sales/item-sales-summary', [SalesReportsController::class, 'itemSalesSummary'])->name('reports.sales.item-sales-summary');
        Route::get('/reports/sales/payments', [SalesReportsController::class, 'salesPayments'])->name('reports.sales.payments');
        Route::get('/reports/sales/commission', [SalesReportsController::class, 'salesCommission'])->name('reports.sales.commission');
        Route::get('/reports/sales/returns', [SalesReportsController::class, 'salesReturn'])->name('reports.sales.returns');
        Route::get('/reports/sales/cancelled', [SalesReportsController::class, 'salesCancel'])->name('reports.sales.cancelled');
        Route::get('/reports/sales/complementary', [SalesReportsController::class, 'complementary'])->name('reports.sales.complementary');
        Route::get('/reports/sales/credit-aging', [SalesReportsController::class, 'creditAging'])->name('reports.sales.credit-aging');
    });

    Route::middleware('permission:reports.purchases')->group(function () {
        Route::get('/reports/purchases/purchase', [PurchaseReportsController::class, 'purchase'])->name('reports.purchases.purchase');
        Route::get('/reports/purchases/items', [PurchaseReportsController::class, 'items'])->name('reports.purchases.items');
        Route::get('/reports/purchases/payments', [PurchaseReportsController::class, 'payments'])->name('reports.purchases.payments');
    });

    Route::middleware('permission:reports.stock')->group(function () {
        Route::get('/reports/stock/price-list', [StockReportsController::class, 'priceList'])->name('reports.stock.price-list');
        Route::get('/reports/stock/stock', [StockReportsController::class, 'stock'])->name('reports.stock.stock');
        Route::get('/reports/stock/stock-as-at', [StockReportsController::class, 'stockAsAt'])->name('reports.stock.stock-as-at');
        Route::get('/reports/stock/stock-as-at-detailed', [StockReportsController::class, 'stockAsAtDetailed'])->name('reports.stock.stock-as-at-detailed');
        Route::get('/reports/stock/template', [StockReportsController::class, 'template'])->name('reports.stock.template');
        Route::get('/reports/stock/ledger', [StockReportsController::class, 'ledger'])->name('reports.stock.ledger');
        Route::get('/reports/stock/transfer', [StockReportsController::class, 'transfer'])->name('reports.stock.transfer');
        Route::get('/reports/stock/adjust', [StockReportsController::class, 'adjust'])->name('reports.stock.adjust');
        Route::get('/reports/stock/alert', [StockReportsController::class, 'alert'])->name('reports.stock.alert');
        Route::get('/reports/stock/valuation', [StockReportsController::class, 'valuation'])->name('reports.stock.valuation');
        Route::get('/reports/stock/damaged', [StockReportsController::class, 'damaged'])->name('reports.stock.damaged');
        Route::get('/reports/stock/issued', [StockReportsController::class, 'issued'])->name('reports.stock.issued');
        Route::get('/reports/stock/consumption', [StockReportsController::class, 'consumption'])->name('reports.stock.consumption');
        Route::get('/reports/stock/production', [StockReportsController::class, 'production'])->name('reports.stock.production');
        Route::get('/reports/expired', [ExpiredItemsReportController::class, 'index'])->name('reports.expired');
    });

    Route::middleware('permission:reports.expenses')->group(function () {
        Route::get('/reports/expenses', [ExpenseReportController::class, 'index'])->name('reports.expenses');
        Route::get('/reports/expenses/pdf', [ExpenseReportController::class, 'pdf'])->name('reports.expenses.pdf');
    });

    Route::middleware('permission:reports.customers')->group(function () {
        Route::get('/reports/customers/customers', [CustomerReportsController::class, 'customers'])->name('reports.customers.customers');
        Route::get('/reports/customers/statement', [CustomerReportsController::class, 'statement'])->name('reports.customers.statement');
        Route::get('/reports/customers/statement/pdf', [CustomerReportsController::class, 'statementPdf'])->name('reports.customers.statement.pdf');
        Route::get('/reports/customers/purchases', [CustomerReportsController::class, 'purchases'])->name('reports.customers.purchases');
    });

    Route::middleware('permission:reports.audit')->group(function () {
        Route::get('/reports/audit', [AuditTrailReportController::class, 'index'])->name('reports.audit');
        Route::get('/reports/user-logs', [UserLogsReportController::class, 'index'])->name('reports.user-logs');
    });

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/logs', [UserAuthLogsController::class, 'index'])->name('users.logs');
    });

    Route::middleware('permission:users.create')->group(function () {
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    });

    Route::middleware('permission:users.update')->group(function () {
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    });

    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
        ->middleware('permission:users.delete')
        ->name('users.destroy');

    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/roles', [RoleManagementController::class, 'index'])->name('roles.index');
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/roles/create', [RoleManagementController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleManagementController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleManagementController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleManagementController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy'])->name('roles.destroy');
    });

    Route::middleware('permission:settings.view')->group(function () {
        Route::get('/settings/general', [CompanySettingsController::class, 'general'])->name('settings.general');
        Route::get('/settings/company', [CompanySettingsController::class, 'company'])->name('settings.company');
        Route::get('/settings/tax', [TaxSettingsController::class, 'index'])->name('settings.tax');
        Route::get('/settings/lookups/{kind}', [LookupSettingsController::class, 'index'])->name('settings.lookups');
        Route::get('/settings/audit', [SettingsAuditController::class, 'index'])->name('settings.audit');
        Route::get('/settings/backup', [SettingsBackupController::class, 'index'])->name('settings.backup');
    });

    Route::middleware('permission:settings.company')->group(function () {
        Route::put('/settings/general', [CompanySettingsController::class, 'updateGeneral'])->name('settings.general.update');
        Route::put('/settings/company', [CompanySettingsController::class, 'updateCompany'])->name('settings.company.update');
        Route::get('/settings/tax/create', [TaxSettingsController::class, 'create'])->name('settings.tax.create');
        Route::post('/settings/tax', [TaxSettingsController::class, 'store'])->name('settings.tax.store');
        Route::get('/settings/tax/{tax}/edit', [TaxSettingsController::class, 'edit'])->name('settings.tax.edit');
        Route::put('/settings/tax/{tax}', [TaxSettingsController::class, 'update'])->name('settings.tax.update');
        Route::delete('/settings/tax/{tax}', [TaxSettingsController::class, 'destroy'])->name('settings.tax.destroy');
        Route::get('/settings/lookups/{kind}/create', [LookupSettingsController::class, 'create'])->name('settings.lookups.create');
        Route::post('/settings/lookups/{kind}', [LookupSettingsController::class, 'store'])->name('settings.lookups.store');
        Route::get('/settings/lookups/{kind}/{lookup}/edit', [LookupSettingsController::class, 'edit'])->name('settings.lookups.edit');
        Route::put('/settings/lookups/{kind}/{lookup}', [LookupSettingsController::class, 'update'])->name('settings.lookups.update');
        Route::delete('/settings/lookups/{kind}/{lookup}', [LookupSettingsController::class, 'destroy'])->name('settings.lookups.destroy');
        Route::post('/settings/backup/run', [SettingsBackupController::class, 'run'])->name('settings.backup.run');
        Route::get('/settings/backup/download/{filename}', [SettingsBackupController::class, 'download'])->name('settings.backup.download')->where('filename', '.*');
        Route::delete('/settings/backup/{filename}', [SettingsBackupController::class, 'destroy'])->name('settings.backup.destroy')->where('filename', '.*');
    });

    Route::middleware('permission:settings.branches')->group(function () {
        Route::get('/settings/branches', [BranchSettingsController::class, 'index'])->name('settings.branches');
        Route::get('/settings/branches/create', [BranchSettingsController::class, 'create'])->name('settings.branches.create');
        Route::post('/settings/branches', [BranchSettingsController::class, 'store'])->name('settings.branches.store');
        Route::get('/settings/branches/{branch}/edit', [BranchSettingsController::class, 'edit'])->name('settings.branches.edit');
        Route::put('/settings/branches/{branch}', [BranchSettingsController::class, 'update'])->name('settings.branches.update');
        Route::delete('/settings/branches/{branch}', [BranchSettingsController::class, 'destroy'])->name('settings.branches.destroy');
    });

    Route::get('/settings/password', [ChangePasswordController::class, 'edit'])->name('settings.password');
    Route::put('/settings/password', [ChangePasswordController::class, 'update'])->name('settings.password.update');
});
