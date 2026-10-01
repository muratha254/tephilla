<?php

namespace App\Services;

use App\Models\Colour;
use App\Models\Folding;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ColourRegisterImporter
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Read the old colour register workbook.
     * Finished-product sheets increase stock when folded and decrease it when sold.
     * The flatsheet sheet increases stock when received and decreases it when folded.
     * Narrations are stored as written and are not converted into other products.
     *
     * @return array{imported:int, skipped:int, errors:array<int, string>}
     */

    /**
     * Read the workbook without writing stock. Warnings do not change the rows.
     *
     * @return array{colours:array<string, int>, total:int, warnings:array<int, string>, errors:array<int, string>}
     */
    public function preview($file): array
    {
        $parsed = $this->read($file);
        $counts = [];
        $warnings = [];
        $previousDate = [];

        foreach ($parsed['lines'] as $line) {
            $counts[$line['colour']] = ($counts[$line['colour']] ?? 0) + 1;
            $sheetQty = (float) ($line['sheet_quantity'] ?? 0);
            $opening = (float) $line['opening'];
            $closing = (float) ($line['closing'] ?? 0);
            if ($sheetQty > 0
                && abs(($opening - $sheetQty) - $closing) >= 0.001
                && abs(($opening + $sheetQty) - $closing) >= 0.001) {
                $warnings[] = $line['colour'] . ' ' . $line['date'] . ': opening, folded, and closing do not reconcile. The written values will be kept.';
            }
            $prior = $previousDate[$line['colour']] ?? null;
            if ($prior && $line['date'] < $prior) {
                $warnings[] = $line['colour'] . ' ' . $line['date'] . ' is earlier than the previous line. The date will be kept.';
            }
            $previousDate[$line['colour']] = $line['date'];
        }

        return [
            'colours' => $counts,
            'total' => count($parsed['lines']),
            'warnings' => $warnings,
            'errors' => $parsed['errors'],
        ];
    }

    public function import($file, int $companyId, int $branchId, int $userId): array
    {
        $parsed = $this->read($file);
        $lines = $parsed['lines'];
        $errors = $parsed['errors'];

        if ($errors) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => $errors];
        }
        if (count($lines) === 0) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['No colour-register rows were found.']];
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($lines, $companyId, $branchId, $userId, &$imported, &$skipped) {
            foreach ($lines as $line) {
                $product = $this->product($companyId, $line['product'], $line['for_sale']);
                $colour = $this->colour($companyId, $line['colour']);
                $variantId = $this->variant($companyId, $product, $colour);
                $reference = 'REG-' . substr(sha1($line['key']), 0, 24);

                if ($this->alreadyImported($reference)) {
                    $skipped++;
                    continue;
                }

                $onHand = $this->inventory->quantityOnHand($companyId, $branchId, $product->id, $variantId);
                if (abs($onHand - $line['opening']) > 0.0001) {
                    $this->bringToOpening($companyId, $branchId, $product, $variantId, $userId, $line, $onHand);
                }

                $this->postEvent($companyId, $branchId, $product, $colour, $variantId, $userId, $line, $reference);
                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => []];
    }

    /**
     * @return array{lines:array<int, array>, errors:array<int, string>}
     */
    private function sheetLines(Worksheet $sheet): array
    {
        $highest = (int) $sheet->getHighestRow();
        $rows = $sheet->rangeToArray('A1:T' . $highest, null, true, false, false);
        $flatsheet = stripos($sheet->getTitle(), 'flat') !== false;
        $sheetColour = $flatsheet ? null : $this->colourName($sheet->getTitle());
        $blocks = [
            [0, 1, 2, 3, 4],
            [5, 6, 7, 8, 9],
            [10, 11, 12, 13, 14],
            [15, 16, 17, 18, 19],
        ];
        $lines = [];
        $errors = [];

        if (! $flatsheet && $sheetColour === null) {
            $errors[] = 'Sheet "' . $sheet->getTitle() . '" is not a known colour.';
        }

        foreach ($blocks as $block) {
            $heading = $this->blockHeading($rows[0] ?? [], $block);
            if ($heading === '') {
                continue;
            }
            $productName = $flatsheet ? 'Flatsheets' : $this->productName($heading);
            $colourName = $flatsheet ? $this->colourName($heading) : $sheetColour;
            if ($colourName === null) {
                $errors[] = 'Sheet "' . $sheet->getTitle() . '" has an unknown colour "' . $heading . '".';
                continue;
            }

            $carryYear = null;
            for ($index = 2; $index < count($rows); $index++) {
                $row = $rows[$index];
                $dateText = trim((string) ($row[$block[0]] ?? ''));
                $opening = $this->number($row[$block[1]] ?? null);
                $quantity = $this->number($row[$block[2]] ?? null);
                $closing = $this->number($row[$block[3]] ?? null);
                $note = trim((string) ($row[$block[4]] ?? ''));
                if ($dateText === '' && $quantity == 0.0 && $note === '') {
                    continue;
                }
                if ($dateText === '') {
                    $errors[] = $sheet->getTitle() . ' row ' . ($index + 1) . ': a stock line has no date.';
                    continue;
                }

                $date = $this->parseDate($sheet, $index + 1, $block[0] + 1, $dateText, $carryYear);
                if ($date === null) {
                    $errors[] = $sheet->getTitle() . ' row ' . ($index + 1) . ': date "' . $dateText . '" could not be read.';
                    continue;
                }
                $carryYear = substr($date, 0, 4);

                $movement = $this->classify($opening, $quantity, $closing);
                if ($movement === null) {
                    continue;
                }

                $lines[] = [
                    'key' => $sheet->getTitle() . '|' . $productName . '|' . $colourName . '|' . ($index + 1) . '|' . $date . '|' . $note,
                    'product' => $productName,
                    'colour' => $colourName,
                    'for_sale' => ! $flatsheet,
                    'flatsheet' => $flatsheet,
                    'date' => $date,
                    'opening' => $opening,
                    'quantity' => $movement['quantity'],
                    'direction' => $movement['direction'],
                    'narration' => $note,
                    'closing' => $closing,
                    'sheet_quantity' => $quantity,
                ];
            }
        }

        return ['lines' => $lines, 'errors' => $errors];
    }

    /**
     * @return array{lines:array<int, array>, errors:array<int, string>}
     */
    private function read($file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : (string) $file;
        $book = IOFactory::load($path);
        $lines = [];
        $errors = [];

        foreach ($book->getAllSheets() as $sheet) {
            $parsed = $this->sheetLines($sheet);
            if ($parsed['errors']) {
                $errors = array_merge($errors, $parsed['errors']);
            }
            $lines = array_merge($lines, $parsed['lines']);
        }

        return ['lines' => $lines, 'errors' => $errors];
    }

    private function classify(float $opening, float $quantity, float $closing): ?array
    {
        if ($quantity > 0 && abs(($opening + $quantity) - $closing) < 0.001) {
            return ['direction' => 'in', 'quantity' => $quantity];
        }
        if ($quantity > 0) {
            return ['direction' => 'out', 'quantity' => $quantity];
        }
        if ($closing > $opening) {
            return ['direction' => 'in', 'quantity' => round($closing - $opening, 4)];
        }
        if ($opening > $closing) {
            return ['direction' => 'out', 'quantity' => round($opening - $closing, 4)];
        }

        return null;
    }

    private function postEvent(int $companyId, int $branchId, Product $product, Colour $colour, int $variantId, int $userId, array $line, string $reference): void
    {
        $in = $line['direction'] === 'in';
        $received = $in && preg_match('/receiv/i', $line['narration']) === 1;
        $type = $line['direction'] === 'out' && ! $line['flatsheet']
            ? StockMovement::SALE
            : ($received ? StockMovement::PURCHASE_RECEIPT : StockMovement::FOLDING);

        $folding = null;
        if ($type === StockMovement::FOLDING) {
            $folding = Folding::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'colour_id' => $colour->id,
                'user_id' => $userId,
                'employee_name' => 'Old register',
                'folded_on' => $line['date'],
                'quantity' => $line['quantity'],
                'notes' => $line['narration'] !== '' ? $line['narration'] : null,
            ]);
        }

        $this->inventory->apply([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'type' => $type,
            'quantity_in' => $in ? $line['quantity'] : 0,
            'quantity_out' => $in ? 0 : $line['quantity'],
            'unit_cost' => $product->purchase_price,
            'user_id' => $userId,
            'notes' => $line['narration'] !== '' ? $line['narration'] : null,
            'reference_type' => $folding ? Folding::class : 'colour_register',
            'reference_id' => $folding ? $folding->id : null,
            'reference_number' => $reference,
            'occurred_at' => $line['date'] . ' 00:00:00',
        ]);
    }

    private function bringToOpening(int $companyId, int $branchId, Product $product, int $variantId, int $userId, array $line, float $onHand): void
    {
        $delta = round($line['opening'] - $onHand, 4);
        $this->inventory->apply([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'type' => StockMovement::OPENING,
            'quantity_in' => $delta > 0 ? $delta : 0,
            'quantity_out' => $delta < 0 ? abs($delta) : 0,
            'unit_cost' => $product->purchase_price,
            'user_id' => $userId,
            'notes' => 'Opening balance from the old register',
            'reference_type' => 'colour_register',
            'reference_number' => 'REG-OS-' . substr(sha1($line['key']), 0, 20),
            'occurred_at' => $line['date'] . ' 00:00:00',
        ]);
    }

    private function alreadyImported(string $reference): bool
    {
        return StockMovement::query()
            ->withoutGlobalScope('company')
            ->withoutGlobalScope('branch')
            ->where('reference_number', $reference)
            ->exists();
    }

    private function product(int $companyId, string $name, bool $forSale): Product
    {
        $names = $name === 'Flatsheets' ? ['Flat Sheet', 'Flatsheets'] : [$name];
        $existing = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('name', $names)
            ->orderBy('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        return Product::query()->create(array_merge(
            ['company_id' => $companyId, 'name' => $name],
            [
                'sku' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '-', $name), 0, 40)),
                'purchase_price' => 0,
                'selling_price' => 0,
                'manage_stock' => true,
                'allow_negative_stock' => false,
                'is_active' => true,
                'for_sale' => $forSale,
            ]
        ));
    }

    private function colour(int $companyId, string $name): Colour
    {
        return Colour::query()->firstOrCreate(
            ['company_id' => $companyId, 'name' => $name],
            ['is_active' => true]
        );
    }

    private function variant(int $companyId, Product $product, Colour $colour): int
    {
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
                'is_active' => true,
            ]);
        }
        $product->update(['has_variants' => true]);

        return (int) $variant->id;
    }

    private function blockHeading(array $header, array $block): string
    {
        foreach ($block as $column) {
            $text = trim((string) ($header[$column] ?? ''));
            if ($text !== '' && ! in_array(strtoupper($text), ['DATE', 'O.S', 'SALES', 'FOLDED', 'C.S', 'NARRATION'], true)) {
                return $text;
            }
        }

        return '';
    }

    private function productName(string $heading): string
    {
        $key = strtoupper(trim($heading));
        $names = [
            'RIDGES' => 'Ridges',
            'BARDGE BOX' => 'Bardge Box',
            'VALLEYS' => 'Valleys',
            'SIDE FLASH' => 'Side Flash',
        ];

        return $names[$key] ?? trim($heading);
    }

    private function colourName(string $heading): ?string
    {
        $key = strtoupper(preg_replace('/[^A-Z]/', '', $heading));
        $names = [
            'BLACK' => 'Black',
            'COFFEBROWN' => 'Coffee Brown',
            'COFFEEBROWN' => 'Coffee Brown',
            'COFFE BROWN' => 'Coffee Brown',
            'MAROON' => 'Maroon Red',
            'MAROONRED' => 'Maroon Red',
            'MARRONRED' => 'Maroon Red',
            'BLACKRED' => 'Black Red',
        ];

        return $names[$key] ?? null;
    }

    private function number($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return is_numeric($value) ? round((float) $value, 4) : 0.0;
    }

    private function parseDate(Worksheet $sheet, int $row, int $column, string $text, ?string $carryYear): ?string
    {
        $cell = $sheet->getCellByColumnAndRow($column, $row);
        $raw = $cell->getValue();
        if (is_numeric($raw) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
        }

        $patterns = [
            '/^\d{2}\/\d{2}\/\d{4}$/' => 'd/m/Y',
            '/^\d{1,2}\/\d{1,2}\/\d{4}$/' => 'j/n/Y',
            '/^\d{2}-\d{2}-\d{4}$/' => 'd-m-Y',
            '/^\d{1,2}-\d{1,2}-\d{4}$/' => 'j-n-Y',
        ];
        foreach ($patterns as $pattern => $format) {
            if (preg_match($pattern, $text) !== 1) {
                continue;
            }
            try {
                $parsed = Carbon::createFromFormat('!' . $format, $text);
            } catch (\Throwable $e) {
                continue;
            }
            if ($parsed instanceof Carbon) {
                $errors = Carbon::getLastErrors();
                if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                    return $parsed->format('Y-m-d');
                }
            }
        }

        if (preg_match('/^(\d{1,2})[-\s]([A-Za-z]{3,})(?:[-\s](\d{4}))?$/', $text, $match) === 1) {
            $year = $match[3] ?? $carryYear;
            if ($year) {
                try {
                    $parsed = Carbon::createFromFormat('!j-M-Y', $match[1] . '-' . substr($match[2], 0, 3) . '-' . $year);
                } catch (\Throwable $e) {
                    $parsed = null;
                }
                if ($parsed instanceof Carbon) {
                    return $parsed->format('Y-m-d');
                }
            }
        }

        return null;
    }
}
