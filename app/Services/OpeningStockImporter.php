<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Colour;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OpeningStockImporter
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Import every row, or none. Invalid rows are reported and stock is left unchanged.
     *
     * @return array{imported:int, errors:array<int, string>}
     */
    public function import(UploadedFile $file, int $companyId, int $defaultBranchId, int $userId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            return ['imported' => 0, 'errors' => ['Upload a CSV, XLS, or XLSX file.']];
        }

        $rows = $this->readRows($file, $extension);
        if (count($rows) === 0) {
            return ['imported' => 0, 'errors' => ['The file is empty.']];
        }

        $header = array_shift($rows);
        $map = $this->headerMap($header);
        foreach (['product', 'quantity'] as $required) {
            if (! isset($map[$required])) {
                return ['imported' => 0, 'errors' => ["Missing column: {$required}. Use product, colour, quantity, unit, branch."]];
            }
        }

        $prepared = [];
        $errors = [];
        $seen = [];
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $parsed = $this->parseRow($row, $map, $companyId, $defaultBranchId, $rowNumber);
            if ($parsed['errors']) {
                $errors = array_merge($errors, $parsed['errors']);
                continue;
            }

            $key = strtolower($parsed['product']->name . '|' . ($parsed['colour_name'] ?? '') . '|' . $parsed['branch_id']);
            if (isset($seen[$key])) {
                $errors[] = 'Row ' . $rowNumber . ': duplicates row ' . $seen[$key] . ' for the same product, colour, and branch.';
                continue;
            }
            $seen[$key] = $rowNumber;
            $prepared[] = $parsed;
        }

        if ($errors) {
            return ['imported' => 0, 'errors' => $errors];
        }

        if (count($prepared) === 0) {
            return ['imported' => 0, 'errors' => ['The file has no stock rows.']];
        }

        DB::transaction(function () use ($prepared, $companyId, $userId) {
            foreach ($prepared as $row) {
                $variantId = 0;
                $colour = $row['colour'];
                $product = $row['product'];
                if ($colour) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->where('colour_id', $colour->id)
                        ->first();
                    if (! $variant) {
                        $variant = ProductVariant::query()->create([
                            'company_id' => $companyId,
                            'product_id' => $product->id,
                            'colour_id' => $colour->id,
                            'color' => $colour->name,
                            'purchase_price' => $product->purchase_price,
                            'selling_price' => $product->selling_price,
                            'is_active' => true,
                        ]);
                        $product->update(['has_variants' => true]);
                    }
                    $variantId = (int) $variant->id;
                }

                $this->inventory->apply([
                    'company_id' => $companyId,
                    'branch_id' => $row['branch_id'],
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'type' => StockMovement::OPENING,
                    'quantity_in' => $row['quantity'],
                    'unit_cost' => $product->purchase_price,
                    'user_id' => $userId,
                    'notes' => 'Opening stock import',
                    'occurred_at' => now(),
                ]);
            }
        });

        return ['imported' => count($prepared), 'errors' => []];
    }

    private function readRows(UploadedFile $file, string $extension): array
    {
        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
            $rows = [];
            foreach ($sheet->toArray(null, true, true, false) as $row) {
                $rows[] = array_map(function ($cell) {
                    return $cell === null ? '' : (string) $cell;
                }, $row);
            }

            return $rows;
        }

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return [];
        }
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return array{errors:array<int,string>, product:?Product, colour:?Colour, colour_name:string, quantity:float, branch_id:int}
     */
    private function parseRow(array $row, array $map, int $companyId, int $defaultBranchId, int $rowNumber): array
    {
        $productName = trim((string) ($row[$map['product']] ?? ''));
        $colourName = isset($map['colour']) ? trim((string) ($row[$map['colour']] ?? '')) : '';
        $quantityRaw = trim((string) ($row[$map['quantity']] ?? ''));
        $branchName = isset($map['branch']) ? trim((string) ($row[$map['branch']] ?? '')) : '';
        $unitName = isset($map['unit']) ? trim((string) ($row[$map['unit']] ?? '')) : '';

        $problems = [];
        if ($productName === '') {
            $problems[] = 'product is blank';
        }
        if ($quantityRaw === '' || ! is_numeric($quantityRaw)) {
            $problems[] = 'quantity must be a number';
        }
        $quantity = is_numeric($quantityRaw) ? round((float) $quantityRaw, 4) : 0;
        if ($quantity <= 0 && is_numeric($quantityRaw)) {
            $problems[] = 'quantity must be greater than zero';
        }

        $product = $productName === '' ? null : Product::query()
            ->with('unit')
            ->where('company_id', $companyId)
            ->where('name', $productName)
            ->first();
        if ($productName !== '' && ! $product) {
            $problems[] = "product \"{$productName}\" was not found";
        }

        if ($unitName !== '' && $product) {
            $unit = $product->unit;
            $matches = $unit && (
                strcasecmp((string) $unit->name, $unitName) === 0
                || strcasecmp((string) $unit->short_name, $unitName) === 0
            );
            if (! $matches) {
                $problems[] = "unit \"{$unitName}\" does not match {$product->name}";
            }
        }

        $branchId = $defaultBranchId;
        if ($branchName !== '') {
            $branch = Branch::query()
                ->where('company_id', $companyId)
                ->where(function ($query) use ($branchName) {
                    $query->where('name', $branchName)->orWhere('code', $branchName);
                })
                ->first();
            if (! $branch) {
                $problems[] = "branch \"{$branchName}\" was not found";
            } else {
                $branchId = (int) $branch->id;
            }
        }

        $colour = null;
        if ($colourName !== '') {
            $colour = Colour::query()
                ->where('company_id', $companyId)
                ->where('name', $colourName)
                ->where('is_active', true)
                ->first();
            if (! $colour) {
                $problems[] = "colour \"{$colourName}\" was not found or is inactive";
            }
        } elseif ($product && $product->variants()->where('is_active', true)->exists()) {
            $problems[] = "colour is required for {$product->name}";
        }

        return [
            'errors' => $problems ? ['Row ' . $rowNumber . ': ' . implode('; ', $problems) . '.'] : [],
            'product' => $product,
            'colour' => $colour,
            'colour_name' => $colourName,
            'quantity' => $quantity,
            'branch_id' => $branchId,
        ];
    }

    private function headerMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $label) {
            $key = strtolower(trim((string) $label));
            $key = str_replace([' ', '-'], '_', $key);
            if (in_array($key, ['product', 'item', 'product_name'], true)) {
                $map['product'] = $index;
            } elseif (in_array($key, ['colour', 'color'], true)) {
                $map['colour'] = $index;
            } elseif (in_array($key, ['quantity', 'qty'], true)) {
                $map['quantity'] = $index;
            } elseif ($key === 'unit') {
                $map['unit'] = $index;
            } elseif ($key === 'branch') {
                $map['branch'] = $index;
            }
        }

        return $map;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
