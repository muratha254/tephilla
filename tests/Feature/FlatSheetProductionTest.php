<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Colour;
use App\Models\Folding;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class FlatSheetProductionTest extends TestCase
{
    use CreatesSellixWorld;

    private Colour $black;

    private Colour $coffee;

    private Product $sheet;

    private Product $valley;

    private Product $bbc;

    private ProductVariant $blackSheet;

    private ProductVariant $coffeeSheet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();

        $this->black = $this->colour('Black');
        $this->coffee = $this->colour('Coffee Brown');
        $this->sheet = $this->item('Flat Sheet', false);
        $this->valley = $this->item('Valley', true);
        $this->bbc = $this->item('BBC', true);
        $this->blackSheet = $this->variant($this->sheet, $this->black);
        $this->coffeeSheet = $this->variant($this->sheet, $this->coffee);
        $this->variant($this->valley, $this->black);
        $this->variant($this->bbc, $this->black);
        $this->variant($this->valley, $this->coffee);
        $this->variant($this->bbc, $this->coffee);
    }

    public function test_item_profile_shows_flat_sheet_used_and_accessories_produced(): void
    {
        $this->receive($this->sheet, $this->blackSheet, 40, 'Received 40');

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => '2024-07-01',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 12,
            'notes' => 'profile fold',
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 8],
                ['product_id' => $this->bbc->id, 'colour_id' => $this->black->id, 'quantity' => 4],
            ],
        ])->assertRedirect();

        $this->actingAsAdmin()
            ->get(route('products.show', $this->sheet))
            ->assertOk()
            ->assertSee('Used on folding')
            ->assertSeeInOrder(['Black', '12', 'Valley Black', '8', 'profile fold', 'BBC Black', '4']);

        $this->actingAsAdmin()
            ->get(route('products.show', $this->valley))
            ->assertOk()
            ->assertSee('Produced on folding')
            ->assertSeeInOrder(['Flat Sheet', 'Black', '12', 'Black', '8', 'profile fold']);
    }

    public function test_production_form_carries_raw_material_colour_and_available_quantity(): void
    {
        $this->receive($this->sheet, $this->blackSheet, 40, 'Received 40');

        $this->actingAsAdmin()
            ->get(route('folding.create'))
            ->assertOk()
            ->assertSee('Available')
            ->assertSee('Balance')
            ->assertViewHas('materialStock', function ($stock) {
                $rows = collect($stock[(string) $this->sheet->id] ?? []);
                $black = $rows->firstWhere('id', (string) $this->black->id);
                $coffee = $rows->firstWhere('id', (string) $this->coffee->id);

                return $black
                    && $coffee
                    && $black['name'] === 'Black'
                    && (float) $black['qty'] === 40.0
                    && (float) $coffee['qty'] === 0.0;
            });
    }

    public function test_flat_sheet_receipts_and_production_stay_on_the_selected_colour(): void
    {
        $this->receive($this->sheet, $this->blackSheet, 55, 'Opening 55');
        $this->receive($this->sheet, $this->blackSheet, 400, 'Received 400 pcs');
        $this->assertStock($this->sheet, $this->blackSheet->id, 455);

        $this->receive($this->sheet, $this->coffeeSheet, 200, 'received 200 pcs');
        $this->assertStock($this->sheet, $this->blackSheet->id, 455);
        $this->assertStock($this->sheet, $this->coffeeSheet->id, 200);

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => '2024-05-30',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 20,
            'notes' => 'folded: 20 valleys, 20 BBCs',
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 20],
                ['product_id' => $this->bbc->id, 'colour_id' => $this->black->id, 'quantity' => 20],
            ],
        ])->assertRedirect(route('folding.index'))->assertSessionHas('success');

        $this->assertStock($this->sheet, $this->blackSheet->id, 435);
        $this->assertStock($this->sheet, $this->coffeeSheet->id, 200);
        $this->assertStock($this->valley, $this->variant($this->valley, $this->black)->id, 20);
        $this->assertStock($this->bbc, $this->variant($this->bbc, $this->black)->id, 20);
        $this->assertSame(0, Product::query()->where('name', 'Ridges')->count());

        $folding = Folding::query()->first();
        $this->assertSame('folded: 20 valleys, 20 BBCs', $folding->notes);
        $this->assertSame(2, $folding->outputs()->count());

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => '2024-06-10',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->coffee->id,
            'quantity' => 5,
            'notes' => 'coffee only',
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->coffee->id, 'quantity' => 10],
            ],
        ])->assertRedirect(route('folding.index'));

        $this->assertStock($this->sheet, $this->blackSheet->id, 435);
        $this->assertStock($this->sheet, $this->coffeeSheet->id, 195);
        $this->assertStock($this->valley, $this->variant($this->valley, $this->black)->id, 20);
    }

    public function test_production_rejects_more_flat_sheet_than_is_available(): void
    {
        $this->receive($this->sheet, $this->blackSheet, 10, 'Opening 10');

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => '2024-05-30',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 20,
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 20],
            ],
        ])->assertRedirect()->assertSessionHas('error', 'Insufficient Black Flat Sheet stock. Available: 10.');

        $this->assertStock($this->sheet, $this->blackSheet->id, 10);
        $this->assertStock($this->valley, $this->variant($this->valley, $this->black)->id, 0);
        $this->assertSame(0, Folding::query()->count());
    }

    public function test_reversing_production_restores_both_sides_once(): void
    {
        $this->receive($this->sheet, $this->blackSheet, 455, 'Received');

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => '2024-05-30',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 20,
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 20],
                ['product_id' => $this->bbc->id, 'colour_id' => $this->black->id, 'quantity' => 20],
            ],
        ])->assertRedirect();

        $folding = Folding::query()->first();
        $this->actingAsAdmin()->post(route('folding.void', $folding), [
            'void_reason' => 'Entered twice',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertStock($this->sheet, $this->blackSheet->id, 455);
        $this->assertStock($this->valley, $this->variant($this->valley, $this->black)->id, 0);
        $this->assertStock($this->bbc, $this->variant($this->bbc, $this->black)->id, 0);
        $this->assertSame('voided', $folding->fresh()->status);
        $this->assertSame(3, StockMovement::query()->where('type', StockMovement::FOLDING_REVERSAL)->count());

        $this->actingAsAdmin()->post(route('folding.void', $folding), [
            'void_reason' => 'Again',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertStock($this->sheet, $this->blackSheet->id, 455);
        $this->assertSame(3, StockMovement::query()->where('type', StockMovement::FOLDING_REVERSAL)->count());
    }

    public function test_historical_register_keeps_written_values_and_does_not_import_twice(): void
    {
        $book = new Spreadsheet();
        $flats = $book->getActiveSheet();
        $flats->setTitle('FLATSHEETS');
        $flats->setCellValue('A1', 'BLACK');
        $flats->setCellValue('F1', 'COFFEE BROWN');
        $flats->fromArray([
            ['Date', 'O.S', 'Folded', 'C.S', 'Narration'],
            ['29/05/2024', 55, null, 455, 'Received 400 pcs'],
            ['30/05/2024', 455, 20, 435, 'folded:20valleys,20bbcs'],
            ['16/03/2024', 435, 5, 430, 'Folded: 15 side wall flashings'],
        ], null, 'A2');
        $flats->fromArray([
            ['Date', 'O.S', 'Folded', 'C.S', 'Narration'],
            ['29/05/2024', 0, null, 200, 'received 200 pcs'],
            ['10/06/2024', 100, 20, 90, 'nickson'],
        ], null, 'F2');

        $path = storage_path('framework/testing-flat-' . uniqid('', true) . '.xlsx');
        (new Xlsx($book))->save($path);
        $upload = new UploadedFile($path, 'register.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $previewResponse = $this->actingAsAdmin()->post(route('stock.register.preview'), ['register' => $upload]);
        $previewResponse->assertRedirect(route('stock.opening'))
            ->assertSessionHas('register_preview');

        $preview = $previewResponse->getSession()->get('register_preview');
        $this->assertSame(3, $preview['colours']['Black']);
        $this->assertSame(2, $preview['colours']['Coffee Brown']);
        $this->assertSame(5, $preview['total']);
        $this->assertNotEmpty($preview['warnings']);

        $this->actingAsAdmin()->post(route('stock.register.confirm'))
            ->assertRedirect(route('stock.opening'))
            ->assertSessionHas('success');

        $this->assertSame(1, Product::query()->whereIn('name', ['Flat Sheet', 'Flatsheets'])->count());
        $blackVariant = ProductVariant::query()->where('product_id', $this->sheet->id)->where('colour_id', $this->black->id)->first();
        $this->assertNotNull($blackVariant);

        $fold = StockMovement::query()
            ->where('product_id', $this->sheet->id)
            ->where('notes', 'folded:20valleys,20bbcs')
            ->first();
        $this->assertNotNull($fold);
        $this->assertEquals(20, (float) $fold->quantity_out);
        $this->assertSame(0, Product::query()->where('name', 'Valley')->where('id', '!=', $this->valley->id)->count());
        $this->assertSame(0, Folding::query()->where('product_id', $this->valley->id)->count());

        $oddDate = StockMovement::query()->where('product_id', $this->sheet->id)->where('notes', 'Folded: 15 side wall flashings')->first();
        $this->assertSame('2024-03-16', substr((string) $oddDate->occurred_at, 0, 10));

        $again = new UploadedFile($path, 'register.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->actingAsAdmin()->post(route('stock.register.store'), ['register' => $again])
            ->assertRedirect(route('stock.opening'));
        $this->assertSame(1, StockMovement::query()->where('notes', 'folded:20valleys,20bbcs')->count());

        $unbalanced = StockMovement::query()->where('notes', 'nickson')->first();
        $this->assertNotNull($unbalanced);
        $this->assertEquals(20, (float) $unbalanced->quantity_out);
        $this->assertSame('nickson', $unbalanced->notes);
    }

    public function test_production_posts_every_output_and_ignores_the_narration(): void
    {
        $ridge = $this->item('Ridge', true);
        $flash = $this->item('Side Wall Flashing', true);
        $this->variant($ridge, $this->black);
        $this->variant($flash, $this->black);
        $this->receive($this->sheet, $this->blackSheet, 100, 'Received 100');

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => '2024-06-01',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 20,
            'notes' => 'folded: 20 valleys, 20 BBCs',
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 20],
                ['product_id' => $this->bbc->id, 'colour_id' => $this->black->id, 'quantity' => 20],
                ['product_id' => $ridge->id, 'colour_id' => $this->black->id, 'quantity' => 10],
                ['product_id' => $flash->id, 'colour_id' => $this->black->id, 'quantity' => 5],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertStock($this->sheet, $this->blackSheet->id, 80);
        $this->assertStock($this->valley, $this->variant($this->valley, $this->black)->id, 20);
        $this->assertStock($this->bbc, $this->variant($this->bbc, $this->black)->id, 20);
        $this->assertStock($ridge, $this->variant($ridge, $this->black)->id, 10);
        $this->assertStock($flash, $this->variant($flash, $this->black)->id, 5);
        $this->assertSame(1, Folding::query()->count());
        $this->assertSame(5, StockMovement::query()->where('type', StockMovement::FOLDING)->count());

        $audit = AuditLog::query()->where('module', 'folding')->where('action', 'create')->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->black->id, $audit->after_json['colour_id']);
        $this->assertCount(4, $audit->after_json['outputs']);
    }

    public function test_a_failed_output_does_not_leave_a_raw_material_deduction(): void
    {
        $this->receive($this->sheet, $this->blackSheet, 100, 'Received 100');
        $this->app->bind(\App\Services\InventoryService::class, function () {
            return new class extends \App\Services\InventoryService {
                public int $applies = 0;

                public function apply(array $payload): StockMovement
                {
                    $this->applies++;
                    if ($this->applies >= 2) {
                        throw new \RuntimeException('Finished output failed.');
                    }

                    return parent::apply($payload);
                }
            };
        });

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => '2024-06-01',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 20,
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 20],
                ['product_id' => $this->bbc->id, 'colour_id' => $this->black->id, 'quantity' => 20],
            ],
        ])->assertStatus(500);

        $this->assertStock($this->sheet, $this->blackSheet->id, 100);
        $this->assertStock($this->valley, $this->variant($this->valley, $this->black)->id, 0);
        $this->assertSame(0, Folding::query()->count());
    }

    public function test_the_same_production_request_is_recorded_once(): void
    {
        $this->receive($this->sheet, $this->blackSheet, 100, 'Received 100');
        $payload = [
            'idempotency_key' => 'fold-once',
            'folded_on' => '2024-06-01',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 20,
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 7],
            ],
        ];

        $this->actingAsAdmin()->post(route('folding.store'), $payload)->assertRedirect();
        $this->actingAsAdmin()->post(route('folding.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success', 'This folding was already recorded.');

        $this->assertSame(1, Folding::query()->count());
        $this->assertStock($this->sheet, $this->blackSheet->id, 80);
        $this->assertStock($this->valley, $this->variant($this->valley, $this->black)->id, 7);
    }

    public function test_production_uses_only_the_selected_branch(): void
    {
        $this->receive($this->sheet, $this->blackSheet, 100, 'Main receipt');
        $this->actingAs($this->admin->fresh(['role.permissions']))
            ->withSession(['current_branch_id' => $this->secondaryBranch->id])
            ->post(route('stock.adjust', $this->sheet), [
                'branch_id' => $this->secondaryBranch->id,
                'occurred_at' => '2024-05-29',
                'status' => 'addition',
                'control_account' => 'migration_control',
                'quantity' => 50,
                'product_variant_id' => $this->blackSheet->id,
                'notes' => 'Branch receipt',
            ])->assertRedirect()->assertSessionHas('success');

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => '2024-06-01',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 20,
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 4],
            ],
        ])->assertRedirect();

        $this->assertStock($this->sheet, $this->blackSheet->id, 80);
        $this->assertStock($this->sheet, $this->blackSheet->id, 50, $this->secondaryBranch->id);

        $this->actingAs($this->admin->fresh(['role.permissions']))
            ->withSession(['current_branch_id' => $this->secondaryBranch->id])
            ->post(route('folding.store'), [
                'folded_on' => '2024-06-02',
                'product_id' => $this->sheet->id,
                'colour_id' => $this->black->id,
                'quantity' => 30,
                'outputs' => [
                    ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 2],
                ],
            ])->assertRedirect();

        $this->assertStock($this->sheet, $this->blackSheet->id, 80);
        $this->assertStock($this->sheet, $this->blackSheet->id, 20, $this->secondaryBranch->id);
        $this->assertStock($this->valley, $this->variant($this->valley, $this->black)->id, 4);
        $this->assertStock($this->valley, $this->variant($this->valley, $this->black)->id, 2, $this->secondaryBranch->id);
    }

    public function test_negative_stock_permission_does_not_allow_production_to_overdraw(): void
    {
        $this->sheet->update(['allow_negative_stock' => true]);
        $this->receive($this->sheet, $this->blackSheet, 15, 'Fifteen');
        $this->receive($this->sheet, $this->coffeeSheet, 100, 'Coffee');

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => '2024-06-01',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 20,
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 20],
            ],
        ])->assertRedirect()->assertSessionHas('error', 'Insufficient Black Flat Sheet stock. Available: 15.');

        $this->assertStock($this->sheet, $this->blackSheet->id, 15);
        $this->assertStock($this->sheet, $this->coffeeSheet->id, 100);
        $this->assertSame(0, Folding::query()->count());
    }

    public function test_cashier_cannot_record_reverse_or_import_production(): void
    {
        $this->actingAsCashier()->post(route('folding.store'), [
            'folded_on' => '2024-06-01',
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 1,
        ])->assertStatus(403);

        $this->actingAsCashier()->get(route('folding.index'))->assertStatus(403);
        $this->actingAsCashier()->get(route('folding.flat-sheet'))->assertStatus(403);
        $this->actingAsCashier()->post(route('stock.register.preview'))->assertStatus(403);
        $this->assertSame(0, Folding::query()->count());
    }

    public function test_flat_sheet_ledger_shows_opening_receipt_and_fold(): void
    {
        $this->receive($this->sheet, $this->blackSheet, 55, 'Opening 55');
        $this->receive($this->sheet, $this->blackSheet, 400, 'Received 400 pcs');
        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => now()->toDateString(),
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 20,
            'notes' => 'folded: 20 valleys, 20 BBCs',
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 20],
            ],
        ])->assertRedirect();

        $this->actingAsAdmin()->get(route('reports.stock.ledger', [
            'show' => 1,
            'from' => '2020-01-01',
            'to' => now()->toDateString(),
            'product_id' => $this->sheet->id,
            'product_variant_id' => $this->blackSheet->id,
        ]))->assertOk()
            ->assertSee('Received 400 pcs')
            ->assertSee('folded: 20 valleys, 20 BBCs')
            ->assertSee('455.00');

        $this->actingAsAdmin()->get(route('folding.flat-sheet'))
            ->assertOk()
            ->assertSee('Black')
            ->assertSee('435');
    }

    public function test_accessory_buying_price_is_the_sheet_cost_shared_across_metres_used(): void
    {
        $this->sheet->update(['purchase_price' => 5000, 'selling_price' => 800]);
        $this->valley->update(['purchase_price' => 10, 'selling_price' => 900]);
        $this->bbc->update(['purchase_price' => 10, 'selling_price' => 700]);
        $ridge = $this->item('Ridge', true);
        $ridge->update(['purchase_price' => 10, 'selling_price' => 650]);
        $this->variant($ridge, $this->black);
        $this->receive($this->sheet, $this->blackSheet, 20, 'Opening 20 metres');

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => now()->toDateString(),
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 5,
            'idempotency_key' => 'sheet-cost-first',
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 1],
                ['product_id' => $this->bbc->id, 'colour_id' => $this->black->id, 'quantity' => 1],
                ['product_id' => $ridge->id, 'colour_id' => $this->black->id, 'quantity' => 1],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertStock($this->sheet, $this->blackSheet->id, 15);
        $this->assertStock($this->valley, $this->valley->variants()->where('colour_id', $this->black->id)->value('id'), 1);
        $this->valley->refresh();
        $this->bbc->refresh();
        $ridge->refresh();
        $this->assertSame('416.67', number_format((float) $this->valley->purchase_price, 2, '.', ''));
        $this->assertSame('416.67', number_format((float) $this->bbc->purchase_price, 2, '.', ''));
        $this->assertSame('416.67', number_format((float) $ridge->purchase_price, 2, '.', ''));
        $this->assertSame('900.00', number_format((float) $this->valley->selling_price, 2, '.', ''));
        $this->assertSame('700.00', number_format((float) $this->bbc->selling_price, 2, '.', ''));
        $this->assertSame('650.00', number_format((float) $ridge->selling_price, 2, '.', ''));

        $first = Folding::query()->latest('id')->first();
        $this->assertCount(3, $first->outputs);
        foreach ($first->outputs as $output) {
            $this->assertSame('416.67', number_format((float) $output->unit_cost, 2, '.', ''));
        }

        $this->sheet->update(['purchase_price' => 6000]);
        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => now()->toDateString(),
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 5,
            'idempotency_key' => 'sheet-cost-second',
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 1],
                ['product_id' => $this->bbc->id, 'colour_id' => $this->black->id, 'quantity' => 1],
                ['product_id' => $ridge->id, 'colour_id' => $this->black->id, 'quantity' => 1],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $first->refresh();
        foreach ($first->outputs as $output) {
            $this->assertSame('416.67', number_format((float) $output->unit_cost, 2, '.', ''));
        }
        $second = Folding::query()->latest('id')->first();
        foreach ($second->outputs as $output) {
            $this->assertSame('500.00', number_format((float) $output->unit_cost, 2, '.', ''));
        }
        $this->valley->refresh();
        $this->assertSame('500.00', number_format((float) $this->valley->purchase_price, 2, '.', ''));
        $this->assertSame('900.00', number_format((float) $this->valley->selling_price, 2, '.', ''));
        $this->assertStock($this->sheet, $this->blackSheet->id, 10);

        $this->sheet->update(['purchase_price' => 0]);
        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => now()->toDateString(),
            'product_id' => $this->sheet->id,
            'colour_id' => $this->black->id,
            'quantity' => 1,
            'idempotency_key' => 'sheet-cost-zero-price',
            'outputs' => [
                ['product_id' => $this->valley->id, 'colour_id' => $this->black->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('product_id');
        $this->assertStock($this->sheet, $this->blackSheet->id, 10);
    }

    private function colour(string $name): Colour
    {
        return Colour::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function item(string $name, bool $forSale): Product
    {
        return Product::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'sku' => strtoupper(str_replace(' ', '-', $name)),
            'purchase_price' => 100,
            'selling_price' => 150,
            'manage_stock' => true,
            'allow_negative_stock' => false,
            'is_active' => true,
            'for_sale' => $forSale,
        ]);
    }

    private function variant(Product $product, Colour $colour): ProductVariant
    {
        $variant = ProductVariant::query()->firstOrCreate(
            ['product_id' => $product->id, 'colour_id' => $colour->id],
            [
                'company_id' => $this->company->id,
                'color' => $colour->name,
                'is_active' => true,
            ]
        );
        $product->update(['has_variants' => true]);

        return $variant;
    }

    private function receive(Product $product, ProductVariant $variant, float $quantity, string $notes): void
    {
        $this->actingAsAdmin()->post(route('stock.adjust', $product), [
            'branch_id' => $this->branch->id,
            'occurred_at' => '2024-05-29',
            'status' => 'addition',
            'control_account' => 'migration_control',
            'quantity' => $quantity,
            'product_variant_id' => $variant->id,
            'notes' => $notes,
        ])->assertRedirect()->assertSessionHas('success');
    }

    private function assertStock(Product $product, int $variantId, float $expected, ?int $branchId = null): void
    {
        $row = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId ?: $this->branch->id)
            ->where('product_variant_id', $variantId)
            ->first();

        $this->assertEquals($expected, $row ? (float) $row->quantity : 0.0);
    }
}
