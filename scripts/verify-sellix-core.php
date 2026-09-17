<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Exceptions\NegativeStockException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Models\Unit;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

$company = Company::query()->first();
$branch = Branch::withoutGlobalScopes()->first();
$category = ProductCategory::withoutGlobalScopes()->first();
$unit = Unit::withoutGlobalScopes()->first();
$tax = Tax::withoutGlobalScopes()->first();

$product = Product::withoutGlobalScopes()->create([
    'company_id' => $company->id,
    'category_id' => $category->id,
    'unit_id' => $unit->id,
    'tax_id' => $tax->id,
    'name' => 'Test Charger',
    'sku' => 'TEST-001',
    'selling_price' => 100,
    'purchase_price' => 50,
    'manage_stock' => true,
    'allow_negative_stock' => false,
    'is_active' => true,
]);

$inventory = app(InventoryService::class);
$movement = $inventory->apply([
    'company_id' => $company->id,
    'branch_id' => $branch->id,
    'product_id' => $product->id,
    'type' => 'opening',
    'quantity_in' => 20,
    'unit_cost' => 50,
    'reference_number' => 'OPEN-1',
    'occurred_at' => now(),
]);

echo 'opening_after=' . $movement->quantity_after . PHP_EOL;
echo 'on_hand=' . $inventory->quantityOnHand($company->id, $branch->id, $product->id) . PHP_EOL;

$blocked = false;
try {
    $inventory->apply([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'type' => 'pos_sale',
        'quantity_out' => 25,
    ]);
} catch (NegativeStockException $e) {
    $blocked = true;
}
echo 'negative_blocked=' . ($blocked ? 'yes' : 'no') . PHP_EOL;

$numbers = app(DocumentNumberService::class);
echo 'invoice=' . $numbers->next($company->id, 'invoice') . PHP_EOL;
echo 'invoice_peek=' . $numbers->peek($company->id, 'invoice') . PHP_EOL;

App\Models\NumberSequence::withoutGlobalScopes()
    ->where('company_id', $company->id)
    ->where('document_type', 'invoice')
    ->update(['next_number' => 1]);

$adminOk = Hash::check('1234', User::where('email', 'admin@mail.com')->value('password'));
echo 'admin_password_ok=' . ($adminOk ? 'yes' : 'no') . PHP_EOL;

StockMovement::withoutGlobalScopes()->where('product_id', $product->id)->delete();
ProductBranchStock::withoutGlobalScopes()->where('product_id', $product->id)->delete();
$product->forceDelete();

echo 'cleaned=yes' . PHP_EOL;
