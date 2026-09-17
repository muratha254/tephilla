<?php

/**
 * Complete end-to-end business transaction test via real controllers/services.
 * Creates tagged TEST DATA only. Does not wipe the database.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountingCatalog;
use App\Services\AccountingPoster;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\LoyaltyService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

$results = [];
$errors = [];
$pass = $fail = 0;

function mark(string $key, bool $ok, string $note = ''): void
{
    global $results, $pass, $fail, $errors;
    $results[$key] = ['ok' => $ok, 'note' => $note];
    if ($ok) {
        $pass++;
        echo "PASS  {$key}" . ($note ? " — {$note}" : '') . "\n";
    } else {
        $fail++;
        $errors[] = "{$key}: {$note}";
        echo "FAIL  {$key} — {$note}\n";
    }
}

function stockQty(int $productId, int $branchId): float
{
    return (float) (ProductBranchStock::query()
        ->where('product_id', $productId)
        ->where('branch_id', $branchId)
        ->value('quantity') ?? 0);
}

function journalFor($model, string $event): ?JournalEntry
{
    return JournalEntry::query()
        ->where('source_type', get_class($model))
        ->where('source_id', $model->id)
        ->where('source_event', $event)
        ->with('lines')
        ->first();
}

function journalBalance(?JournalEntry $je): array
{
    if (! $je) {
        return [0.0, 0.0, false];
    }
    $d = round((float) $je->lines->sum('debit'), 2);
    $c = round((float) $je->lines->sum('credit'), 2);

    return [$d, $c, abs($d - $c) < 0.011 && $d > 0];
}

echo "=== NEWPOS COMPLETE E2E BUSINESS TRANSACTION TEST ===\n\n";

$admin = User::query()->where('email', 'test.superadmin@newpos.local')->first();
if (! $admin) {
    fwrite(STDERR, "Missing test.superadmin@newpos.local — run TestUsersSeeder\n");
    exit(1);
}

Auth::login($admin);
Session::put('current_branch_id', $admin->branch_id);
app()->instance('currentCompanyId', (int) $admin->company_id);
app()->instance('currentBranchId', (int) $admin->branch_id);

$companyId = (int) $admin->company_id;
$branchId = (int) $admin->branch_id;

app(AccountingCatalog::class)->ensure($companyId);
app(AccountingCatalog::class)->ensureOperationalAccounts($companyId);
app(SettingsService::class)->set($companyId, 'loyalty_points_rate', '0.01', 'company_profile');

$tag = 'E2E-TEST-DATA';

// ---------- STEP 1: SUPPLIER ----------
echo "-- Step 1: Supplier --\n";
$supplier = Supplier::query()
    ->where('company_id', $companyId)
    ->where('email', 'supplier@test.newpos.local')
    ->first();

if (! $supplier) {
    $supplier = Supplier::query()->create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'name' => 'NewPOS Test Supplier',
        'phone' => '0700000000',
        'mobile' => '0700000000',
        'email' => 'supplier@test.newpos.local',
        'address' => 'Nairobi, Kenya',
        'notes' => $tag . ' | Contact: Test Supplier Contact',
        'opening_balance' => 0,
        'is_active' => true,
    ]);
    app(AuditLogger::class)->record('create', 'suppliers', $supplier, null, ['name' => $supplier->name, 'tag' => $tag]);
} else {
    $supplier->update([
        'name' => 'NewPOS Test Supplier',
        'phone' => '0700000000',
        'address' => 'Nairobi, Kenya',
        'notes' => $tag . ' | Contact: Test Supplier Contact',
        'is_active' => true,
    ]);
}

$supplierOk = Supplier::query()->whereKey($supplier->id)->where('name', 'NewPOS Test Supplier')->exists();
mark('Supplier', $supplierOk, $supplierOk ? "id={$supplier->id}" : 'not saved');

// ---------- STEP 2: PRODUCT ----------
echo "\n-- Step 2: Product --\n";
$category = ProductCategory::query()->firstOrCreate(
    ['company_id' => $companyId, 'name' => 'Test Category'],
    ['is_active' => true]
);
$unit = Unit::query()->firstOrCreate(
    ['company_id' => $companyId, 'name' => 'Piece'],
    ['short_name' => 'pc', 'is_active' => true]
);

$product = Product::query()
    ->where('company_id', $companyId)
    ->where('sku', 'TEST-PROD-001')
    ->first();

if (! $product) {
    $product = Product::query()->create([
        'company_id' => $companyId,
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'name' => 'NewPOS Test Product',
        'sku' => 'TEST-PROD-001',
        'purchase_price' => 500,
        'selling_price' => 800,
        'manage_stock' => true,
        'allow_negative_stock' => false,
        'is_active' => true,
        'for_sale' => true,
        'tax_inclusive' => false,
        'description' => $tag . ' | Preferred supplier: NewPOS Test Supplier',
    ]);
} else {
    $product->update([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'name' => 'NewPOS Test Product',
        'purchase_price' => 500,
        'selling_price' => 800,
        'manage_stock' => true,
        'is_active' => true,
        'for_sale' => true,
        'tax_inclusive' => false,
        'description' => $tag . ' | Preferred supplier: NewPOS Test Supplier',
    ]);
}

// Ensure stock row at 0 if missing / reset only if leftover from prior incomplete test and no open movements we care about
$stockRow = ProductBranchStock::query()->firstOrCreate(
    ['company_id' => $companyId, 'branch_id' => $branchId, 'product_id' => $product->id],
    ['quantity' => 0, 'average_cost' => 500]
);

// If leftover stock from prior E2E, adjust down to 0 via inventory service so baseline is clean
$startQty = stockQty($product->id, $branchId);
if ($startQty > 0) {
    app(InventoryService::class)->apply([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'product_id' => $product->id,
        'type' => StockMovement::ADJUSTMENT,
        'quantity_out' => $startQty,
        'unit_cost' => 500,
        'user_id' => $admin->id,
        'notes' => $tag . ' baseline reset to 0 before E2E',
        'occurred_at' => now(),
    ]);
}
$initialStock = stockQty($product->id, $branchId);

$productOk = $product->sku === 'TEST-PROD-001'
    && (float) $product->purchase_price == 500.0
    && (float) $product->selling_price == 800.0
    && $initialStock == 0.0;
mark('Product', $productOk, "sku={$product->sku} cost={$product->purchase_price} price={$product->selling_price} stock={$initialStock} (supplier linked via purchase, no product.supplier_id column)");

// ---------- STEP 3: PURCHASE + RECEIVE ----------
echo "\n-- Step 3: Purchase + receive --\n";
$purchaseReq = Request::create('/purchases', 'POST', [
    'supplier_id' => $supplier->id,
    'order_date' => now()->toDateString(),
    'status' => 'received',
    'notes' => $tag . ' purchase of 10 units @ 500',
    'items' => [[
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_cost' => 500,
        'selling_price' => 800,
        'tax_rate' => 0,
        'discount_percent' => 0,
    ]],
    'payment_amount' => 0,
]);
$purchaseReq->setUserResolver(fn () => $admin);
$purchaseReq->setLaravelSession(app('session.store'));

try {
    $purchaseResponse = app(PurchaseController::class)->store(
        $purchaseReq,
        app(DocumentNumberService::class),
        app(InventoryService::class),
        app(AccountingPoster::class),
        app(AuditLogger::class)
    );
    $purchaseOk = true;
    $purchaseNote = 'controller store returned';
} catch (Throwable $e) {
    $purchaseOk = false;
    $purchaseNote = $e->getMessage();
    mark('Purchase', false, $purchaseNote);
    mark('Purchase Receiving', false, 'blocked by purchase failure');
    mark('Inventory Increase', false, 'blocked');
    echo "\nFATAL purchase failure — aborting remaining steps that depend on stock.\n";
    echo $e->getTraceAsString() . "\n";
    goto REPORT;
}

$purchase = PurchaseOrder::query()
    ->where('supplier_id', $supplier->id)
    ->where('notes', 'like', $tag . '%')
    ->orderByDesc('id')
    ->first();

$purchaseValid = $purchase
    && (float) $purchase->total == 5000.0
    && $purchase->status === 'received'
    && (int) $purchase->supplier_id === (int) $supplier->id;
mark('Purchase', (bool) $purchaseValid, $purchase
    ? "number={$purchase->number} total={$purchase->total} status={$purchase->status}"
    : 'purchase not found');

$item = $purchase ? $purchase->items()->where('product_id', $product->id)->first() : null;
$receiveOk = $item && (float) $item->quantity == 10.0 && (float) $item->quantity_received == 10.0 && (float) $item->unit_cost == 500.0;
mark('Purchase Receiving', (bool) $receiveOk, $item
    ? "qty={$item->quantity} received={$item->quantity_received} cost={$item->unit_cost}"
    : 'item missing');

$afterPurchaseStock = stockQty($product->id, $branchId);
mark('Inventory Increase', $afterPurchaseStock == 10.0, "stock after receive={$afterPurchaseStock} (expected 10)");

$poMovements = StockMovement::query()
    ->where('product_id', $product->id)
    ->where('type', StockMovement::PURCHASE_RECEIPT)
    ->where('reference_type', PurchaseOrder::class)
    ->where('reference_id', $purchase->id)
    ->count();
if ($poMovements < 1) {
    // also check GRN reference
    $poMovements = StockMovement::query()
        ->where('product_id', $product->id)
        ->where('type', StockMovement::PURCHASE_RECEIPT)
        ->where('notes', 'like', '%' . $purchase->number . '%')
        ->count();
}

$supplierBalance = method_exists($purchase, 'balance') ? $purchase->balance() : ((float) $purchase->total - (float) $purchase->paid_amount);
mark('Supplier Balance', abs($supplierBalance - 5000.0) < 0.011, "PO unpaid balance={$supplierBalance} (credit purchase)");

// ---------- STEP 4: PURCHASE GL ----------
echo "\n-- Step 4: Purchase GL --\n";
$grn = $purchase->receipts()->latest('id')->first();
$poster = app(AccountingPoster::class);
$purchaseJe = $grn ? journalFor($grn, 'purchase_receipt') : null;
if (! $purchaseJe && $purchase) {
    // fallback: find by description
    $purchaseJe = JournalEntry::query()
        ->where('description', 'like', '%' . ($grn->number ?? $purchase->number) . '%')
        ->orWhere(function ($q) use ($purchase) {
            $q->where('source_type', PurchaseOrder::class)->where('source_id', $purchase->id);
        })
        ->with('lines')
        ->latest('id')
        ->first();
}
[$pd, $pc, $pBal] = journalBalance($purchaseJe);
$purchaseJeCount = $grn
    ? JournalEntry::query()->where('source_type', get_class($grn))->where('source_id', $grn->id)->where('source_event', 'purchase_receipt')->count()
    : 0;
mark('Purchase GL', $pBal && $purchaseJeCount === 1, "debits={$pd} credits={$pc} count={$purchaseJeCount} je=" . ($purchaseJe->number ?? 'none'));

$purchaseAudit = AuditLog::query()
    ->where('module', 'purchases')
    ->where(function ($q) use ($purchase, $grn) {
        $q->where('auditable_id', $purchase->id);
        if ($grn) {
            $q->orWhere('auditable_id', $grn->id);
        }
    })
    ->exists();

// ---------- STEP 5: CUSTOMER ----------
echo "\n-- Step 5: Customer --\n";
$customer = Customer::query()
    ->where('company_id', $companyId)
    ->where('email', 'customer@test.newpos.local')
    ->first();
if (! $customer) {
    $customer = Customer::query()->create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'name' => 'NewPOS Test Customer',
        'phone' => '0711111111',
        'mobile' => '0711111111',
        'email' => 'customer@test.newpos.local',
        'loyalty_points' => 0,
        'opening_balance' => 0,
        'is_active' => true,
        'is_walk_in' => false,
        'notes' => $tag,
    ]);
} else {
    $customer->update([
        'name' => 'NewPOS Test Customer',
        'phone' => '0711111111',
        'loyalty_points' => 0,
        'opening_balance' => 0,
        'is_active' => true,
        'notes' => $tag,
    ]);
}
mark('Customer', Customer::query()->whereKey($customer->id)->exists(), "id={$customer->id}");

// ---------- STEP 6-10: POS SALE ----------
echo "\n-- Steps 6-10: POS cash sale --\n";
$stockBeforeSale = stockQty($product->id, $branchId);
$posReq = Request::create('/pos/order', 'POST', [
    'customer_id' => $customer->id,
    'sale_date' => now()->toDateTimeString(),
    'document_type' => 'pos',
    'discount_amount' => 0,
    'payment_amount' => 1600,
    'payment_method' => 'cash',
    'notes' => $tag . ' cash sale qty 2',
    'items' => [['product_id' => $product->id, 'quantity' => 2]],
    'payments' => [['method' => 'cash', 'amount' => 1600]],
]);
$posReq->setUserResolver(fn () => $admin);
$posReq->headers->set('Accept', 'application/json');

try {
    $posResponse = app(PosController::class)->order(
        $posReq,
        app(DocumentNumberService::class),
        app(InventoryService::class),
        app(AccountingPoster::class),
        app(LoyaltyService::class),
        app(AuditLogger::class)
    );
    $posData = json_decode($posResponse->getContent(), true);
    $sale = Sale::query()->find($posData['id'] ?? 0);
} catch (Throwable $e) {
    mark('POS Sale', false, $e->getMessage());
    $sale = null;
    echo $e->getTraceAsString() . "\n";
}

$saleOk = $sale
    && (int) $sale->customer_id === (int) $customer->id
    && (float) $sale->total == 1600.0
    && (float) $sale->paid_amount == 1600.0
    && $sale->status === Sale::STATUS_COMPLETED
    && $sale->payment_status === Sale::PAYMENT_PAID;
mark('POS Sale', (bool) $saleOk, $sale
    ? "number={$sale->number} total={$sale->total} paid={$sale->paid_amount} receipt={$sale->receipt_number}"
    : 'sale missing');

$payment = $sale ? $sale->payments()->first() : null;
$paymentOk = $payment && (float) $payment->amount == 1600.0 && $payment->method === 'cash';
$paymentCount = $sale ? $sale->payments()->count() : 0;
mark('Cash Payment', $paymentOk && $paymentCount === 1, $payment
    ? "method={$payment->method} amount={$payment->amount} count={$paymentCount}"
    : 'payment missing');

$stockAfterSale = stockQty($product->id, $branchId);
mark('Inventory Deduction', $stockAfterSale == 8.0, "before={$stockBeforeSale} after={$stockAfterSale} expected=8");

$saleMoves = $sale ? StockMovement::query()
    ->where('reference_type', Sale::class)
    ->where('reference_id', $sale->id)
    ->where('type', StockMovement::POS_SALE)
    ->get() : collect();
$saleOut = round((float) $saleMoves->sum('quantity_out'), 4);
mark('Stock Movement', $saleOut == 2.0 && $saleMoves->count() === 1, "movements={$saleMoves->count()} qty_out={$saleOut}");

$saleJe = $sale ? journalFor($sale, 'sale_complete') : null;
[$sd, $sc, $sBal] = journalBalance($saleJe);
$saleJeCount = $sale
    ? JournalEntry::query()->where('source_type', Sale::class)->where('source_id', $sale->id)->where('source_event', 'sale_complete')->count()
    : 0;
mark('Sales GL', $sBal && $saleJeCount === 1, "debits={$sd} credits={$sc} count={$saleJeCount} je=" . ($saleJe->number ?? 'none'));

// Loyalty
$loyaltyRate = (float) app(SettingsService::class)->get($companyId, 'loyalty_points_rate', 0);
$earn = $sale ? LoyaltyTransaction::query()
    ->where('source_type', Sale::class)
    ->where('source_id', $sale->id)
    ->where('type', 'earn')
    ->get() : collect();
if ($loyaltyRate > 0 && $sale) {
    app(LoyaltyService::class)->earnForSale($sale->fresh()); // idempotent retry
    $earn2 = LoyaltyTransaction::query()
        ->where('source_type', Sale::class)
        ->where('source_id', $sale->id)
        ->where('type', 'earn')
        ->count();
    $expectedPts = round(1600 * $loyaltyRate, 2);
    $pts = (float) $customer->fresh()->loyalty_points;
    mark('Loyalty', $earn->count() === 1 && $earn2 === 1 && abs($pts - $expectedPts) < 0.011,
        "rate={$loyaltyRate} txs={$earn2} points={$pts} expected={$expectedPts}");
} else {
    mark('Loyalty', true, 'NOT APPLICABLE or rate=0 — rate=' . $loyaltyRate);
}

$custBalance = method_exists($customer, 'creditAmount')
    ? (float) $customer->fresh()->creditAmount()
    : (float) Sale::query()->where('customer_id', $customer->id)->where('status', Sale::STATUS_COMPLETED)->sum('balance');
// creditAmount includes opening + all unpaid — for our cash sale balance should be 0 on this sale
$saleBalance = $sale ? (float) $sale->fresh()->balance : -1;
mark('Customer Balance', $saleBalance == 0.0, "sale.balance={$saleBalance}");

// ---------- AUDIT ----------
echo "\n-- Step 12: Audit --\n";
$saleAudit = $sale ? AuditLog::query()
    ->where('module', 'sales')
    ->where('auditable_type', Sale::class)
    ->where('auditable_id', $sale->id)
    ->exists() : false;
mark('Audit Logging', $purchaseAudit && $saleAudit, 'purchase_audit=' . ($purchaseAudit ? 'Y' : 'N') . ' sale_audit=' . ($saleAudit ? 'Y' : 'N'));

// ---------- REPORTS (existence / queryable) ----------
echo "\n-- Step 15: Reports data presence --\n";
$inSalesReport = $sale && Sale::query()->whereKey($sale->id)->where('status', Sale::STATUS_COMPLETED)->exists();
$inPurchaseReport = $purchase && PurchaseOrder::query()->whereKey($purchase->id)->exists();
$inStockReport = StockMovement::query()->where('product_id', $product->id)->exists();
$inGlReport = $saleJe && $purchaseJe;
$inLoyaltyReport = LoyaltyTransaction::query()->where('customer_id', $customer->id)->exists() || $loyaltyRate <= 0;
mark('Reports', $inSalesReport && $inPurchaseReport && $inStockReport && $inGlReport,
    'sales/purchase/stock/GL rows present for test docs');

// ---------- STEP 16: VOID on SEPARATE sale ----------
echo "\n-- Step 16: Void (separate sale) --\n";
$voidSale = null;
try {
    $voidReq = Request::create('/pos/order', 'POST', [
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateTimeString(),
        'document_type' => 'pos',
        'discount_amount' => 0,
        'notes' => $tag . ' void-candidate sale qty 1',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 800]],
    ]);
    $voidReq->setUserResolver(fn () => $admin);
    $voidReq->headers->set('Accept', 'application/json');
    $voidResp = app(PosController::class)->order(
        $voidReq,
        app(DocumentNumberService::class),
        app(InventoryService::class),
        app(AccountingPoster::class),
        app(LoyaltyService::class),
        app(AuditLogger::class)
    );
    $voidData = json_decode($voidResp->getContent(), true);
    $voidSale = Sale::query()->find($voidData['id'] ?? 0);
    $stockBeforeVoid = stockQty($product->id, $branchId);

    $voidForm = Request::create('/sales/' . $voidSale->id . '/void', 'POST', [
        'invoice_date' => now()->toDateString(),
        'receipt_ref' => $voidSale->receipt_number ?: $voidSale->number,
        'want_refund' => 1,
        'void_reason' => $tag . ' controlled void test',
        'flagged' => 0,
    ]);
    $voidForm->setUserResolver(fn () => $admin);
    app(SaleController::class)->void(
        $voidForm,
        $voidSale,
        app(InventoryService::class),
        app(AccountingPoster::class),
        app(LoyaltyService::class),
        app(AuditLogger::class)
    );
    $voidSale->refresh();
    $stockAfterVoid = stockQty($product->id, $branchId);
    $voidJe = journalFor($voidSale, 'sale_void');
    [$vd, $vc, $vBal] = journalBalance($voidJe);
    $voidOk = $voidSale->status === Sale::STATUS_VOIDED
        && $stockAfterVoid == $stockBeforeVoid + 1
        && $vBal;
    mark('Void/Cancel', $voidOk, "status={$voidSale->status} stock {$stockBeforeVoid}->{$stockAfterVoid} voidJE balanced=" . ($vBal ? 'Y' : 'N'));
} catch (Throwable $e) {
    mark('Void/Cancel', false, $e->getMessage());
}

// ---------- STEP 17: CREDIT NOTE on original sale (1 unit) ----------
echo "\n-- Step 17: Credit note --\n";
$stockBeforeCn = stockQty($product->id, $branchId);
$saleItem = $sale ? $sale->items()->where('product_id', $product->id)->first() : null;
$cn = null;
try {
    if (! $sale || ! $saleItem) {
        throw new RuntimeException('Original sale/item missing for credit note');
    }
    $cnReq = Request::create('/sales/credit-notes', 'POST', [
        'sale_id' => $sale->id,
        'credit_date' => now()->toDateString(),
        'reason' => $tag . ' partial credit 1 unit',
        'notes' => $tag,
        'restore_stock' => 1,
        'action' => 'posted',
        'items' => [[
            'sale_item_id' => $saleItem->id,
            'quantity' => 1,
        ]],
    ]);
    $cnReq->setUserResolver(fn () => $admin);
    app(CreditNoteController::class)->store(
        $cnReq,
        app(DocumentNumberService::class),
        app(InventoryService::class),
        app(AccountingPoster::class),
        app(LoyaltyService::class),
        app(AuditLogger::class)
    );
    $cn = CreditNote::query()
        ->where('sale_id', $sale->id)
        ->orderByDesc('id')
        ->first();
} catch (Throwable $e) {
    mark('Credit Note', false, $e->getMessage());
    mark('Credit Note Stock Restoration', false, 'blocked');
    mark('Credit Note GL', false, 'blocked');
}

$stockAfterCn = stockQty($product->id, $branchId);
if ($cn) {
    $cnOk = $cn->isPosted()
        && (int) $cn->sale_id === (int) $sale->id
        && abs((float) $cn->total - 800.0) < 0.011;
    mark('Credit Note', $cnOk, "number={$cn->number} total={$cn->total} status={$cn->status}");
    mark('Credit Note Stock Restoration', $cn->stock_restored && $stockAfterCn == 9.0,
        "stock_restored=" . ($cn->stock_restored ? 'Y' : 'N') . " stock={$stockAfterCn} expected=9");
    $cnJe = journalFor($cn, 'credit_note');
    [$cd, $cc, $cBal] = journalBalance($cnJe);
    $cnJeCount = JournalEntry::query()
        ->where('source_type', CreditNote::class)
        ->where('source_id', $cn->id)
        ->where('source_event', 'credit_note')
        ->count();
    // idempotent re-post
    app(AccountingPoster::class)->postCreditNote($cn->fresh(['items.saleItem.product']));
    $cnJeCount2 = JournalEntry::query()
        ->where('source_type', CreditNote::class)
        ->where('source_id', $cn->id)
        ->where('source_event', 'credit_note')
        ->count();
    mark('Credit Note GL', $cBal && $cnJeCount === 1 && $cnJeCount2 === 1,
        "debits={$cd} credits={$cc} count={$cnJeCount2}");
} elseif (! isset($results['Credit Note'])) {
    mark('Credit Note', false, 'not created');
}

// ---------- FINAL RECONCILIATION ----------
echo "\n-- Step 18: Final reconciliation --\n";
$finalStock = stockQty($product->id, $branchId);
// After void restored +1 and CN +1 from path: 10 - 2 (sale) - 1 (void sale) + 1 (void restore) + 1 (CN) = 9
// Wait: void sale deducted 1 then restored 1, so void is net 0 on the original path.
// Path: reset0 → +10 purchase → -2 sale → (-1 void sale +1 void restore) → +1 CN = 9
mark('Final Stock Reconciliation', $finalStock == 9.0, "final_stock={$finalStock} expected=9");

$dupPurchases = PurchaseOrder::query()->where('notes', 'like', $tag . ' purchase%')->count();
$dupSales = Sale::query()->where('notes', 'like', $tag . ' cash sale%')->count();
$dupPayments = $sale ? Payment::query()->where('payable_type', Sale::class)->where('payable_id', $sale->id)->count() : 0;
$dupSaleJe = $sale ? JournalEntry::query()->where('source_type', Sale::class)->where('source_id', $sale->id)->where('source_event', 'sale_complete')->count() : 0;
$dupCnJe = $cn ? JournalEntry::query()->where('source_type', CreditNote::class)->where('source_id', $cn->id)->where('source_event', 'credit_note')->count() : 0;
$dupLoyalty = $sale ? LoyaltyTransaction::query()->where('source_type', Sale::class)->where('source_id', $sale->id)->where('type', 'earn')->count() : 0;

$noDup = $dupPurchases === 1 && $dupSales === 1 && $dupPayments === 1 && $dupSaleJe === 1 && ($cn ? $dupCnJe === 1 : true) && ($loyaltyRate > 0 ? $dupLoyalty === 1 : true);
if (! $noDup) {
    $errors[] = "Duplicates check: purchases={$dupPurchases} sales={$dupSales} payments={$dupPayments} saleJE={$dupSaleJe} cnJE={$dupCnJe} loyalty={$dupLoyalty}";
}

// Totals for report moved to REPORT label
$purchaseTotal = $purchase ? (float) $purchase->total : 0;
$saleTotal = $sale ? (float) $sale->total : 0;
$paymentTotal = $sale ? (float) $sale->payments()->sum('amount') : 0;
$glDebits = round(($pd ?? 0) + ($sd ?? 0) + ($cd ?? 0) + ($vd ?? 0), 2);
$glCredits = round(($pc ?? 0) + ($sc ?? 0) + ($cc ?? 0) + ($vc ?? 0), 2);

REPORT:
$purchaseTotal = isset($purchase) && $purchase ? (float) $purchase->total : 0;
$saleTotal = isset($sale) && $sale ? (float) $sale->total : 0;
$paymentTotal = isset($sale) && $sale ? (float) $sale->payments()->sum('amount') : 0;
$glDebits = round(($pd ?? 0) + ($sd ?? 0) + ($cd ?? 0) + ($vd ?? 0), 2);
$glCredits = round(($pc ?? 0) + ($sc ?? 0) + ($cc ?? 0) + ($vc ?? 0), 2);

echo "\n==================== END-TO-END TEST RESULT ====================\n";
$keys = [
    'Supplier', 'Product', 'Purchase', 'Purchase Receiving', 'Inventory Increase', 'Supplier Balance', 'Purchase GL',
    'Customer', 'POS Sale', 'Cash Payment', 'Inventory Deduction', 'Sales GL', 'Loyalty', 'Audit Logging', 'Reports',
    'Void/Cancel', 'Credit Note', 'Credit Note Stock Restoration', 'Credit Note GL', 'Final Stock Reconciliation',
];
foreach ($keys as $k) {
    if (! isset($results[$k])) {
        mark($k, false, 'not executed');
    }
    $r = $results[$k];
    $label = $r['ok'] ? 'PASS' : 'FAIL';
    if (stripos($r['note'], 'NOT APPLICABLE') !== false) {
        $label = 'NOT APPLICABLE';
    }
    echo str_pad($k . ':', 34) . " {$label}" . ($r['note'] ? " ({$r['note']})" : '') . "\n";
}

echo "\n--- ACTUAL VALUES ---\n";
echo "Initial Stock: {$initialStock}\n";
echo "Purchased: 10\n";
echo "Sold (main sale): 2\n";
echo "Void sale net: 0 (sold 1 then restored)\n";
echo "Credited/Returned: 1\n";
echo "Final Stock: " . (isset($finalStock) ? $finalStock : stockQty($product->id ?? 0, $branchId)) . "\n";
echo "Purchase Total: {$purchaseTotal}\n";
echo "Sale Total: {$saleTotal}\n";
echo "Payment Total: {$paymentTotal}\n";
echo "GL Debits (sum tested JEs): {$glDebits}\n";
echo "GL Credits (sum tested JEs): {$glCredits}\n";

echo "\n--- DOCUMENT REFS ---\n";
echo "Supplier ID: {$supplier->id}\n";
echo "Product ID: {$product->id} SKU=TEST-PROD-001\n";
echo "Purchase: " . ($purchase->number ?? 'n/a') . "\n";
echo "Sale: " . ($sale->number ?? 'n/a') . "\n";
echo "Credit Note: " . ($cn->number ?? 'n/a') . "\n";
echo "Void Sale: " . ($voidSale->number ?? 'n/a') . "\n";

if ($errors) {
    echo "\n--- ERRORS ---\n";
    foreach ($errors as $err) {
        echo "- {$err}\n";
    }
}

$blocking = array_filter($results, function ($r, $k) {
    if (stripos($r['note'] ?? '', 'NOT APPLICABLE') !== false) {
        return false;
    }

    return ! $r['ok'];
}, ARRAY_FILTER_USE_BOTH);

echo "\n=== OVERALL: " . (count($blocking) === 0 ? 'PASSED' : 'FAILED') . " (pass={$pass} fail={$fail}) ===\n";
exit(count($blocking) === 0 ? 0 : 1);
