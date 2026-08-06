<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SupplierExcelSyncService
{
    /**
     * Normalize MOP for comparison (Consignment / Cash).
     */
    public function normalizeMop(?string $mop): string
    {
        $s = strtoupper(trim((string) $mop));

        return str_contains($s, 'CASH') ? 'CASH' : (str_contains($s, 'CONS') ? 'CONSIGNMENT' : $s);
    }

    /**
     * Canonical stored/display value — Cash or Consignment (input is case-insensitive).
     */
    public function canonicalMop(?string $mop, string $default = 'Consignment'): string
    {
        $trimmed = trim((string) $mop);
        if ($trimmed === '') {
            return $default;
        }

        $norm = $this->normalizeMop($trimmed);
        if ($norm === 'CASH') {
            return 'Cash';
        }
        if ($norm === 'CONSIGNMENT') {
            return 'Consignment';
        }

        return $trimmed;
    }

    public function normalizePhoneDigits(?string $telepon): string
    {
        return preg_replace('/\D+/', '', (string) $telepon) ?? '';
    }

    /**
     * Strip trailing parenthetical tags (ASCII or full-width parens), optional
     * " - Cash" style suffixes, then collapse whitespace for fuzzy name matching.
     */
    public function normalizeSupplierBaseName(?string $nama): string
    {
        $s = trim((string) $nama);
        $prev = null;
        while ($prev !== $s) {
            $prev = $s;
            $s = preg_replace('/\s*[\(（][^)）]{1,120}[\)）]\s*$/u', '', $s) ?? '';
            $s = trim($s);
        }
        $s = preg_replace('/\s*[-–—]\s*(cash|consignment|cons\.?)\s*$/iu', '', $s) ?? '';
        // Treat "A & B", "A+B", "A/B" the same as "A and B"
        $s = preg_replace('/\s*[&+\/]\s*/u', ' and ', $s) ?? $s;
        $s = preg_replace('/&/u', ' and ', $s) ?? $s;
        $s = preg_replace('/\s+and\s+/iu', ' and ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', trim($s)) ?? '';

        return mb_strtolower($s);
    }

    /**
     * Read MOP from a dedicated column, else from trailing "(Cash)" / "(Consignment)" in the name.
     */
    public function inferMopFromNameSuffix(string $nama): string
    {
        if (! preg_match('/[\(（]\s*([^)）]{1,60})\s*[\)）]\s*$/u', trim($nama), $m)) {
            return '';
        }
        $norm = $this->normalizeMop(trim($m[1]));
        if ($norm === 'CASH') {
            return 'Cash';
        }
        if ($norm === 'CONSIGNMENT') {
            return 'Consignment';
        }

        return '';
    }

    /**
     * Effective MOP for one Excel row (column value, else suffix in name, else Consignment).
     */
    public function effectiveMopForExcelRow(array $row, array $cols, string $nama): string
    {
        $m = '';
        if (($cols['mop'] ?? null) !== null) {
            $m = $this->cell($row, $cols['mop']);
        }
        if ($m !== '') {
            return $this->canonicalMop($m);
        }
        $fromName = $this->inferMopFromNameSuffix($nama);

        return $fromName !== '' ? $fromName : 'Consignment';
    }

    public function normalizeHeaderCell(mixed $cell): string
    {
        $s = is_string($cell) ? trim($cell) : trim((string) $cell);
        $s = preg_replace('/^\x{FEFF}+/u', '', $s) ?? $s;
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s) ?? $s;

        return mb_strtolower(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    /**
     * @return array{name: ?int, mop: ?int, address: ?int, phone: ?int, id: ?int}
     */
    public function resolveColumnsFromHeaderRow(array $headerRow): array
    {
        $normalized = [];
        foreach ($headerRow as $i => $cell) {
            $normalized[$i] = $this->normalizeHeaderCell($cell);
        }

        $findExact = static function (array $needles) use ($normalized): ?int {
            foreach ($needles as $needle) {
                $n = strtolower(trim((string) $needle));
                foreach ($normalized as $idx => $h) {
                    if ($h === $n) {
                        return (int) $idx;
                    }
                }
            }

            return null;
        };

        $nameCol = $findExact([
            'supplier name', 'suppliername', 'suplier name', 'name', 'supplier', 'nama supplier',
            'nama', 'vendor', 'vendor name', 'business name', 'company name', 'dealer name',
            'supplier / name', 'supplier_name',
        ]);

        if ($nameCol === null) {
            foreach ($normalized as $idx => $h) {
                if ($h === '' || str_contains($h, 'member') || str_contains($h, 'customer')) {
                    continue;
                }
                if ($h === 'supplier' || $h === 'nama' || $h === 'vendor' || $h === 'dealer') {
                    $nameCol = (int) $idx;
                    break;
                }
                if (str_contains($h, 'supplier') && str_contains($h, 'name')) {
                    $nameCol = (int) $idx;
                    break;
                }
                if (str_contains($h, 'vendor') && str_contains($h, 'name')) {
                    $nameCol = (int) $idx;
                    break;
                }
            }
        }

        $mopCol = $findExact([
            'means of payment', 'mode of payment', 'mop', 'payment mode', 'payment type', 'pay mode',
            'pay type', 'supplier mop', 'cons/cash', 'cash/cons', 'cash / consignment', 'consignment/cash',
            'means of pay', 'payment method',
        ]);

        if ($mopCol === null) {
            foreach ($normalized as $idx => $h) {
                if ($h === '' || ($nameCol !== null && (int) $idx === (int) $nameCol)) {
                    continue;
                }
                if ($h === 'mop' || $h === 'terms' || $h === 'pay type' || $h === 'payment type') {
                    $mopCol = (int) $idx;
                    break;
                }
                if (str_contains($h, 'mop')) {
                    $mopCol = (int) $idx;
                    break;
                }
                if (str_contains($h, 'payment') && (str_contains($h, 'mode') || str_contains($h, 'means') || str_contains($h, 'method'))) {
                    $mopCol = (int) $idx;
                    break;
                }
            }
        }

        $addressCol = $findExact(['address', 'alamat', 'location']);
        $phoneCol = $findExact(['telephone', 'phone', 'telepon', 'contact', 'mobile', 'tel', 'cell']);
        $idCol = $findExact(['supplier id', 'supplierid', 'id supplier', 'system id', 'database id', 'supplier_id']);
        if ($idCol === null) {
            foreach ($normalized as $idx => $h) {
                if ($h === 'id' || str_ends_with($h, ' supplier id')) {
                    $idCol = (int) $idx;
                    break;
                }
            }
        }

        $codeCol = $findExact([
            'supplier code', 'suppliercode', 'supplier_code', 'code', 'kode supplier', 'kode',
            'vendor code', 'vendorcode', 'dealer code',
        ]);

        return [
            'id' => $idCol,
            'code' => $codeCol,
            'name' => $nameCol,
            'mop' => $mopCol,
            'address' => $addressCol,
            'phone' => $phoneCol,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function sampleRowsSuggestMopInName(array $rows, int $startRow, int $nameCol): bool
    {
        $limit = min($startRow + 35, count($rows));
        for ($i = $startRow; $i < $limit; $i++) {
            $cell = isset($rows[$i][$nameCol]) ? trim((string) $rows[$i][$nameCol]) : '';
            if ($cell === '') {
                continue;
            }
            if (preg_match('/[\(（]\s*(cash|cons|consignment|cod)\b/iu', $cell)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{header: array<int, string>, header_index: int, data_start: int, cols: array<string, int|null>}
     */
    public function detectHeaderAndDataStart(array $rows): array
    {
        if (count($rows) < 2) {
            throw new \InvalidArgumentException('No data rows found under the header row.');
        }
        $maxR = min(25, count($rows) - 2);
        $best = null;
        $bestScore = -1;
        for ($r = 0; $r <= $maxR; $r++) {
            $header = array_map(static function ($c) {
                return is_string($c) ? trim($c) : trim((string) $c);
            }, $rows[$r]);
            foreach ($header as $k => $v) {
                if (is_string($v)) {
                    $header[$k] = preg_replace('/^\x{FEFF}/u', '', $v) ?? $v;
                }
            }
            $cols = $this->resolveColumnsFromHeaderRow($header);
            $score = 0;
            if ($cols['name'] !== null) {
                $score += 10;
            }
            if ($cols['mop'] !== null) {
                $score += 10;
            } elseif ($cols['name'] !== null && $this->sampleRowsSuggestMopInName($rows, $r + 1, (int) $cols['name'])) {
                $score += 6;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['header' => $header, 'header_index' => $r, 'cols' => $cols];
            }
        }
        if ($best === null || $best['cols']['name'] === null) {
            throw new \InvalidArgumentException(
                'Could not detect a supplier name column. Use a header like "Supplier Name", "Name", or "Nama" on row 1 (or move the table so the header is within the first 25 rows).'
            );
        }
        if ($best['cols']['mop'] === null && ! $this->sampleRowsSuggestMopInName($rows, $best['header_index'] + 1, (int) $best['cols']['name'])) {
            throw new \InvalidArgumentException(
                'Could not detect a MOP column (e.g. "Means of Payment", "MOP"). Add one, or put Cash/Consignment in parentheses in the supplier name, e.g. PETER ZIMBABWE (Cash).'
            );
        }

        return [
            'header' => $best['header'],
            'header_index' => $best['header_index'],
            'data_start' => $best['header_index'] + 1,
            'cols' => $best['cols'],
        ];
    }

    /**
     * @return array{
     *     by_id: array<int, Supplier>,
     *     by_exact: array<string, list<Supplier>>,
     *     by_base: array<string, list<Supplier>>,
     *     by_phone: array<string, list<Supplier>>
     * }
     */
    public function buildSupplierMatchIndexes(Collection $suppliers): array
    {
        $byId = [];
        $byExact = [];
        $byBase = [];
        $byPhone = [];
        foreach ($suppliers as $s) {
            $byId[(int) $s->id_supplier] = $s;
            $e = mb_strtolower(trim((string) $s->nama));
            if ($e !== '') {
                $byExact[$e][] = $s;
            }
            $b = $this->normalizeSupplierBaseName($s->nama);
            if ($b !== '') {
                $byBase[$b][] = $s;
            }
            $digits = $this->normalizePhoneDigits($s->telepon);
            if (strlen($digits) >= 6) {
                $byPhone[$digits][] = $s;
            }
        }

        return [
            'by_id' => $byId,
            'by_exact' => $byExact,
            'by_base' => $byBase,
            'by_phone' => $byPhone,
        ];
    }

    /**
     * Load sheet rows; strips UTF-8 BOM from the first row when present.
     *
     * @return array{rows: array<int, array<int, mixed>>}
     */
    public function parseExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        if (! empty($rows[0])) {
            foreach ($rows[0] as $k => $v) {
                if (is_string($v)) {
                    $rows[0][$k] = preg_replace('/^\x{FEFF}/u', '', $v) ?? $v;
                }
            }
        }

        return ['rows' => $rows];
    }

    public function cell(array $row, ?int $idx): string
    {
        if ($idx === null || ! isset($row[$idx])) {
            return '';
        }

        return trim((string) $row[$idx]);
    }

    /**
     * Match one Excel row to a system supplier (by ID, then unique phone, then exact name,
     * then relaxed base name — e.g. Excel "PETER ZIMBABWE" matches system "PETER ZIMBABWE (Cash)").
     *
     * @param  array<string, list<Supplier>>  $matchIndexes  from {@see buildSupplierMatchIndexes()}
     */
    public function matchSupplier(array $row, array $cols, array $matchIndexes): ?Supplier
    {
        $resolvedId = $this->resolveSupplierIdFromRow($row, $cols);
        if ($resolvedId !== null) {
            return $matchIndexes['by_id'][$resolvedId] ?? null;
        }
        if ($cols['phone'] !== null) {
            $digits = $this->normalizePhoneDigits($this->cell($row, $cols['phone']));
            if (strlen($digits) >= 6) {
                $matches = $matchIndexes['by_phone'][$digits] ?? [];
                if (count($matches) === 1) {
                    return $matches[0];
                }
            }
        }
        if ($cols['name'] !== null) {
            $name = $this->cell($row, $cols['name']);
            if ($name !== '') {
                $n = mb_strtolower(trim($name));
                $byExact = $matchIndexes['by_exact'] ?? [];
                if ($n !== '' && isset($byExact[$n]) && count($byExact[$n]) >= 1) {
                    return $this->pickFirstSupplierById($byExact[$n]);
                }

                $baseKey = $this->normalizeSupplierBaseName($name);
                $byBase = $matchIndexes['by_base'] ?? [];
                if ($baseKey !== '' && isset($byBase[$baseKey])) {
                    $cands = $byBase[$baseKey];
                    if (count($cands) === 1) {
                        return $cands[0];
                    }
                    $mopCell = $this->effectiveMopForExcelRow($row, $cols, $name);
                    $mopNorm = $this->normalizeMop($mopCell);
                    $filtered = array_values(array_filter(
                        $cands,
                        fn (Supplier $s) => $this->normalizeMop($s->mop) === $mopNorm
                    ));
                    if (count($filtered) === 1) {
                        return $filtered[0];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Resolve system supplier ID from optional Supplier ID or Supplier Code columns.
     */
    public function resolveSupplierIdFromRow(array $row, array $cols): ?int
    {
        foreach (['id', 'code'] as $key) {
            if (($cols[$key] ?? null) === null) {
                continue;
            }
            $raw = preg_replace('/\s+/', '', $this->cell($row, $cols[$key]));
            if ($raw !== '' && ctype_digit($raw)) {
                return (int) $raw;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $suppliers
     * @return array{name_variants: list<string>, payment_methods: list<string>, different_name: bool, different_mop: bool, match_hint: string}
     */
    public function buildMergeOptionsForGroup(array $suppliers): array
    {
        $names = [];
        $mops = [];
        foreach ($suppliers as $s) {
            $n = trim((string) ($s['nama'] ?? ''));
            if ($n !== '' && ! in_array($n, $names, true)) {
                $names[] = $n;
            }
            $m = $this->canonicalMop($s['mop'] ?? '');
            if (! in_array($m, $mops, true)) {
                $mops[] = $m;
            }
        }

        $normMops = array_values(array_unique(array_map(fn ($m) => $this->normalizeMop($m), $mops)));
        $differentName = count($names) > 1;
        $differentMop = count($normMops) > 1;

        $hints = [];
        if ($differentName) {
            $hints[] = 'Different name spelling (e.g. "A & B" vs "A and B")';
        }
        if ($differentMop) {
            $hints[] = 'Different payment method (Cash vs Consignment)';
        }
        if ($hints === []) {
            $hints[] = 'Same supplier entered more than once';
        }

        return [
            'name_variants' => $names,
            'payment_methods' => $mops,
            'different_name' => $differentName,
            'different_mop' => $differentMop,
            'match_hint' => implode('; ', $hints),
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    public function enrichDuplicateGroup(array $group): array
    {
        $suppliers = $group['suppliers'] ?? [];
        $mergeOptions = $this->buildMergeOptionsForGroup($suppliers);

        return array_merge($group, $mergeOptions);
    }

    /**
     * Duplicate supplier groups in the system (same base name, often different MOP).
     *
     * @return list<array<string, mixed>>
     */
    public function findReconciliationDuplicateGroups(): array
    {
        $groups = $this->findPossibleDuplicateGroups();
        $result = [];

        foreach ($groups['name_groups'] as $g) {
            $result[] = $this->enrichDuplicateGroup([
                'match_type' => $g['match_type'],
                'label' => $g['label'],
                'suppliers' => $g['suppliers'],
            ]);
        }

        foreach ($groups['phone_groups'] as $g) {
            $result[] = $this->enrichDuplicateGroup([
                'match_type' => $g['match_type'],
                'label' => $g['label'],
                'phone_digits' => $g['phone_digits'] ?? null,
                'suppliers' => $g['suppliers'],
            ]);
        }

        return $result;
    }

    /**
     * Persist audit log entries for Excel sync updates.
     *
     * @param  list<array{id_supplier: int, changes: array<string, array{old: string, new: string}>}>  $entries
     */
    public function logExcelSyncChanges(array $entries, ?int $userId, ?string $sourceFile): void
    {
        if ($entries === [] || ! Schema::hasTable('supplier_audit_logs')) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                'id_supplier' => (int) $entry['id_supplier'],
                'user_id' => $userId,
                'action' => \App\Models\SupplierAuditLog::ACTION_EXCEL_SYNC,
                'changes' => json_encode($entry['changes']),
                'source_file' => $sourceFile,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('supplier_audit_logs')->insert($rows);
    }

    /**
     * @param  list<Supplier>  $list
     */
    protected function pickFirstSupplierById(array $list): Supplier
    {
        usort($list, static fn (Supplier $a, Supplier $b) => $a->id_supplier <=> $b->id_supplier);

        return $list[0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDuplicatePhoneGroups(): array
    {
        $groups = [];
        $byPhone = [];
        foreach (Supplier::orderBy('id_supplier')->get(['id_supplier', 'nama', 'telepon']) as $s) {
            $d = $this->normalizePhoneDigits($s->telepon);
            if (strlen($d) < 6) {
                continue;
            }
            $byPhone[$d][] = $s;
        }
        foreach ($byPhone as $digits => $list) {
            if (count($list) < 2) {
                continue;
            }
            $groups[] = [
                'phone_digits' => $digits,
                'suppliers' => array_map(static function ($x) {
                    return [
                        'id_supplier' => (int) $x->id_supplier,
                        'nama' => (string) $x->nama,
                        'telepon' => (string) $x->telepon,
                    ];
                }, $list),
            ];
        }

        return $groups;
    }

    /**
     * Suppliers that look like duplicates (same normalized name and/or same phone).
     *
     * @return array{name_groups: array, phone_groups: array}
     */
    public function findPossibleDuplicateGroups(): array
    {
        $all = Supplier::query()->orderBy('nama')->get(['id_supplier', 'nama', 'telepon', 'mop', 'alamat']);
        $productCounts = DB::table('produk')
            ->select('id_supplier', DB::raw('COUNT(*) as c'))
            ->whereNotNull('id_supplier')
            ->where('id_supplier', '>', 0)
            ->groupBy('id_supplier')
            ->pluck('c', 'id_supplier');

        $mapSupplier = static function ($x) use ($productCounts) {
            $id = (int) $x->id_supplier;

            return [
                'id_supplier' => $id,
                'nama' => (string) $x->nama,
                'telepon' => (string) $x->telepon,
                'mop' => (string) ($x->mop ?? ''),
                'alamat' => (string) ($x->alamat ?? ''),
                'product_count' => (int) ($productCounts[$id] ?? 0),
            ];
        };

        $byBaseName = [];
        $byExactName = [];
        foreach ($all as $s) {
            $base = $this->normalizeSupplierBaseName($s->nama);
            if ($base !== '') {
                $byBaseName[$base][] = $s;
            }
            $exact = mb_strtolower(trim((string) $s->nama));
            if ($exact !== '') {
                $byExactName[$exact][] = $s;
            }
        }

        $nameGroups = [];
        $seenKeys = [];
        foreach ($byBaseName as $base => $list) {
            if (count($list) < 2) {
                continue;
            }
            $key = 'base:'.$base;
            $seenKeys[$key] = true;
            $nameGroups[] = [
                'match_type' => 'similar_name',
                'label' => $list[0]->nama,
                'suppliers' => array_map($mapSupplier, $list),
            ];
        }
        foreach ($byExactName as $exact => $list) {
            if (count($list) < 2) {
                continue;
            }
            $base = $this->normalizeSupplierBaseName($list[0]->nama);
            $key = 'base:'.$base;
            if ($base !== '' && isset($seenKeys[$key])) {
                continue;
            }
            $nameGroups[] = [
                'match_type' => 'exact_name',
                'label' => $list[0]->nama,
                'suppliers' => array_map($mapSupplier, $list),
            ];
        }

        $phoneGroups = [];
        foreach ($this->findDuplicatePhoneGroups() as $g) {
            $suppliers = $g['suppliers'];
            foreach ($suppliers as $i => $row) {
                $id = (int) $row['id_supplier'];
                $full = $all->firstWhere('id_supplier', $id);
                if ($full) {
                    $suppliers[$i] = $mapSupplier($full);
                } else {
                    $suppliers[$i]['product_count'] = (int) ($productCounts[$id] ?? 0);
                }
            }
            $phoneGroups[] = [
                'match_type' => 'same_phone',
                'phone_digits' => $g['phone_digits'],
                'label' => 'Phone '.$g['phone_digits'],
                'suppliers' => $suppliers,
            ];
        }

        return [
            'name_groups' => $nameGroups,
            'phone_groups' => $phoneGroups,
        ];
    }

    /**
     * @param  array<int>  $mergeIds
     * @return array{keep: array, merge: array, products_moving: int}
     */
    public function mergePreview(int $keepId, array $mergeIds): array
    {
        $keep = Supplier::find($keepId);
        $mergeIds = array_values(array_unique(array_filter(array_map('intval', $mergeIds))));
        $mergeIds = array_values(array_filter($mergeIds, static fn ($id) => $id > 0 && $id !== $keepId));

        $productsMoving = 0;
        $mergeRows = [];
        foreach ($mergeIds as $mid) {
            $src = Supplier::find($mid);
            if (! $src) {
                continue;
            }
            $pc = (int) DB::table('produk')->where('id_supplier', $mid)->count();
            $productsMoving += $pc;
            $mergeRows[] = [
                'id_supplier' => (int) $src->id_supplier,
                'nama' => (string) $src->nama,
                'mop' => (string) ($src->mop ?? ''),
                'product_count' => $pc,
            ];
        }

        return [
            'keep' => $keep ? [
                'id_supplier' => (int) $keep->id_supplier,
                'nama' => (string) $keep->nama,
                'mop' => (string) ($keep->mop ?? ''),
                'product_count' => (int) DB::table('produk')->where('id_supplier', $keepId)->count(),
            ] : null,
            'merge' => $mergeRows,
            'products_moving' => $productsMoving,
        ];
    }

    /**
     * Build reconciliation preview: compare Excel suppliers to the database.
     *
     * @return array<string, mixed>
     */
    public function buildPreview(string $path): array
    {
        $parsed = $this->parseExcel($path);
        $rows = $parsed['rows'];
        $det = $this->detectHeaderAndDataStart($rows);
        $cols = $det['cols'];
        $dataStart = $det['data_start'];
        $header = $det['header'];

        $allSuppliers = Supplier::query()->get();
        $matchIndexes = $this->buildSupplierMatchIndexes($allSuppliers);

        $matched = [];
        $unchanged = [];
        $unmatched = [];
        $missingFromSystem = [];
        $matchedSupplierIds = [];
        $excelRowsProcessed = 0;
        $excelRowsForMopScan = [];

        for ($i = $dataStart; $i < count($rows); $i++) {
            $row = $rows[$i];
            $nama = $this->cell($row, $cols['name']);
            $mopColVal = ($cols['mop'] ?? null) !== null ? $this->cell($row, $cols['mop']) : '';
            if ($nama === '' && $mopColVal === '') {
                continue;
            }

            $excelRowsForMopScan[] = [
                'row' => $i + 1,
                'nama' => $nama,
                'mop' => $nama !== '' ? $this->effectiveMopForExcelRow($row, $cols, $nama) : '',
            ];

            $excelRowsProcessed++;
            $rowCode = $this->resolveSupplierIdFromRow($row, $cols);

            if ($nama === '') {
                $entry = [
                    'row' => $i + 1,
                    'reason' => 'Missing supplier name',
                    'supplier_code' => $rowCode,
                ];
                $unmatched[] = $entry;
                $missingFromSystem[] = $entry;

                continue;
            }

            $mopVal = $this->effectiveMopForExcelRow($row, $cols, $nama);
            $sup = $this->matchSupplier($row, $cols, $matchIndexes);
            if (! $sup) {
                $entry = [
                    'row' => $i + 1,
                    'reason' => 'Supplier not found in system. Add Supplier Code/ID, a unique phone, or a matching name.',
                    'nama' => $nama,
                    'payment_method' => $mopVal,
                    'supplier_code' => $rowCode,
                ];
                $unmatched[] = $entry;
                $missingFromSystem[] = $entry;

                continue;
            }

            $matchedSupplierIds[(int) $sup->id_supplier] = true;
            $alamat = $cols['address'] !== null ? $this->cell($row, $cols['address']) : '';
            $telepon = $cols['phone'] !== null ? $this->cell($row, $cols['phone']) : (string) $sup->telepon;
            if ($telepon === '') {
                $telepon = (string) $sup->telepon;
            }

            $diff = $this->diffFields($sup, $nama, $mopVal, $alamat, $telepon);
            $rowPayload = [
                'row' => $i + 1,
                'id_supplier' => (int) $sup->id_supplier,
                'supplier_code' => $rowCode ?? (int) $sup->id_supplier,
                'current' => [
                    'nama' => (string) $sup->nama,
                    'mop' => (string) $sup->mop,
                    'alamat' => (string) ($sup->alamat ?? ''),
                    'telepon' => (string) ($sup->telepon ?? ''),
                ],
                'proposed' => [
                    'nama' => $nama,
                    'mop' => $mopVal,
                    'alamat' => $alamat,
                    'telepon' => $telepon,
                ],
                'changes' => $diff,
            ];

            if ($diff === []) {
                $unchanged[] = $rowPayload;

                continue;
            }

            $matched[] = $rowPayload;
        }

        $extraInSystem = [];
        foreach ($allSuppliers as $sup) {
            $id = (int) $sup->id_supplier;
            if (! isset($matchedSupplierIds[$id])) {
                $extraInSystem[] = [
                    'id_supplier' => $id,
                    'nama' => (string) $sup->nama,
                    'mop' => (string) ($sup->mop ?? ''),
                    'telepon' => (string) ($sup->telepon ?? ''),
                ];
            }
        }

        $productCounts = DB::table('produk')
            ->select('id_supplier', DB::raw('COUNT(*) as c'))
            ->whereNotNull('id_supplier')
            ->where('id_supplier', '>', 0)
            ->groupBy('id_supplier')
            ->pluck('c', 'id_supplier');

        $mopMismatches = $this->collectMopMismatches($matched, $excelRowsForMopScan, $matchIndexes, $productCounts);

        $duplicateGroupData = $this->findPossibleDuplicateGroups();
        $duplicateSuppliers = [];
        foreach ($duplicateGroupData['name_groups'] as $g) {
            $duplicateSuppliers[] = $this->enrichDuplicateGroup([
                'match_type' => $g['match_type'],
                'label' => $g['label'],
                'suppliers' => $g['suppliers'],
            ]);
        }
        foreach ($duplicateGroupData['phone_groups'] as $g) {
            $duplicateSuppliers[] = $this->enrichDuplicateGroup([
                'match_type' => $g['match_type'],
                'label' => $g['label'],
                'phone_digits' => $g['phone_digits'] ?? null,
                'suppliers' => $g['suppliers'],
            ]);
        }
        $phoneDuplicateGroups = array_map(static function (array $g) {
            return [
                'phone_digits' => $g['phone_digits'] ?? null,
                'suppliers' => array_map(static function (array $s) {
                    return [
                        'id_supplier' => (int) $s['id_supplier'],
                        'nama' => (string) $s['nama'],
                        'telepon' => (string) ($s['telepon'] ?? ''),
                    ];
                }, $g['suppliers'] ?? []),
            ];
        }, $duplicateGroupData['phone_groups']);

        $codeHeader = ($cols['code'] ?? null) !== null ? (string) ($header[$cols['code']] ?? '') : '';
        $idHeader = ($cols['id'] ?? null) !== null ? (string) ($header[$cols['id']] ?? '') : '';
        $nameHeader = $cols['name'] !== null ? (string) ($header[$cols['name']] ?? '') : '';
        $mopHeader = ($cols['mop'] ?? null) !== null ? (string) ($header[$cols['mop']] ?? '') : '';

        $summary = [
            'excel_rows_processed' => $excelRowsProcessed,
            'to_update' => count($matched),
            'unchanged' => count($unchanged),
            'mop_mismatches' => count($mopMismatches),
            'missing_from_system' => count($missingFromSystem),
            'extra_in_system' => count($extraInSystem),
            'duplicate_groups' => count($duplicateSuppliers),
        ];

        return [
            'summary' => $summary,
            'to_update' => $matched,
            'matched' => $matched,
            'unchanged' => $unchanged,
            'mop_mismatches' => $mopMismatches,
            'missing_from_system' => $missingFromSystem,
            'extra_in_system' => $extraInSystem,
            'duplicate_suppliers' => $duplicateSuppliers,
            'unmatched' => $unmatched,
            'phone_duplicate_groups' => $phoneDuplicateGroups,
            'meta' => [
                'header_sheet_row' => $det['header_index'] + 1,
                'data_starts_sheet_row' => $dataStart + 1,
                'columns_detected' => [
                    'supplier_code' => $codeHeader !== '' ? $codeHeader : ($idHeader !== '' ? $idHeader : '(not detected — optional)'),
                    'name' => $nameHeader,
                    'payment_method' => $mopHeader !== '' ? $mopHeader : '(from name suffix or default Consignment)',
                ],
            ],
        ];
    }

    public function diffFields(Supplier $db, string $nama, string $mop, string $alamat, string $telepon): array
    {
        $changes = [];
        $tDb = trim((string) $db->nama);
        if ($tDb !== $nama) {
            $changes['nama'] = ['old' => $tDb, 'new' => $nama];
        }
        $mDb = $this->normalizeMop($db->mop);
        $canonicalMop = $this->canonicalMop($mop);
        $mEx = $this->normalizeMop($canonicalMop);
        if ($mDb !== $mEx) {
            $changes['mop'] = ['old' => (string) $db->mop, 'new' => $canonicalMop];
        }
        $aDb = trim((string) $db->alamat);
        if ($aDb !== $alamat) {
            $changes['alamat'] = ['old' => $aDb, 'new' => $alamat];
        }
        $pDb = trim((string) $db->telepon);
        if ($pDb !== $telepon) {
            $changes['telepon'] = ['old' => $pDb, 'new' => $telepon];
        }

        return $changes;
    }

    /**
     * Payment-method conflicts on duplicate system suppliers that share the same base name
     * as an Excel row but were not the matched row (e.g. Excel "Daniel …" Consignment matches
     * id 197 while id 4520 "Daniel … (Cash)" appears here). Rows already in $matched are excluded.
     *
     * @param  list<array<string, mixed>>  $matched
     * @param  list<array{row: int, nama: string, mop: string}>  $excelRowsForMopScan
     * @param  array<string, mixed>  $matchIndexes
     * @param  \Illuminate\Support\Collection<int, int|string>  $productCounts
     * @return list<array<string, mixed>>
     */
    protected function collectMopMismatches(array $matched, array $excelRowsForMopScan, array $matchIndexes, $productCounts): array
    {
        $matchedIds = [];
        foreach ($matched as $m) {
            $id = (int) ($m['id_supplier'] ?? 0);
            if ($id > 0) {
                $matchedIds[$id] = true;
            }
        }

        $bySupplierId = [];

        foreach ($excelRowsForMopScan as $excel) {
            $nama = trim((string) ($excel['nama'] ?? ''));
            $excelMop = $this->canonicalMop((string) ($excel['mop'] ?? ''));
            if ($nama === '') {
                continue;
            }

            $baseKey = $this->normalizeSupplierBaseName($nama);
            if ($baseKey === '') {
                continue;
            }

            $excelMopNorm = $this->normalizeMop($excelMop);
            $candidates = $matchIndexes['by_base'][$baseKey] ?? [];

            foreach ($candidates as $sup) {
                $id = (int) $sup->id_supplier;
                if ($id <= 0 || isset($matchedIds[$id]) || isset($bySupplierId[$id])) {
                    continue;
                }

                if ($this->normalizeMop($sup->mop) === $excelMopNorm) {
                    continue;
                }

                $entry = [
                    'row' => (int) ($excel['row'] ?? 0),
                    'id_supplier' => $id,
                    'supplier_code' => $id,
                    'current' => [
                        'nama' => (string) $sup->nama,
                        'mop' => (string) ($sup->mop ?? ''),
                        'alamat' => (string) ($sup->alamat ?? ''),
                        'telepon' => (string) ($sup->telepon ?? ''),
                    ],
                    'proposed' => [
                        'nama' => $nama,
                        'mop' => $excelMop,
                        'alamat' => (string) ($sup->alamat ?? ''),
                        'telepon' => (string) ($sup->telepon ?? ''),
                    ],
                    'changes' => [
                        'mop' => [
                            'old' => (string) ($sup->mop ?? ''),
                            'new' => $excelMop,
                        ],
                    ],
                ];

                $mergeSuppliers = array_map(
                    fn (Supplier $candidate) => $this->supplierRowForMerge($candidate, $productCounts),
                    $candidates
                );
                if (count($mergeSuppliers) >= 2) {
                    $entry['merge_group'] = $this->enrichDuplicateGroup([
                        'match_type' => 'excel_mop_mismatch',
                        'label' => $nama,
                        'match_hint' => 'Excel row '.((int) ($excel['row'] ?? 0)).' expects '.$excelMop
                            .'. Merge duplicates or apply the Excel payment method to the mismatched record.',
                        'suppliers' => $mergeSuppliers,
                    ]);
                }

                $bySupplierId[$id] = $entry;
            }
        }

        $rows = array_values($bySupplierId);
        usort($rows, static function (array $a, array $b) {
            $idCmp = ((int) ($a['id_supplier'] ?? 0)) <=> ((int) ($b['id_supplier'] ?? 0));
            if ($idCmp !== 0) {
                return $idCmp;
            }

            return strcmp((string) ($a['current']['nama'] ?? ''), (string) ($b['current']['nama'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|string>|array<int, int|string>  $productCounts
     * @return array<string, mixed>
     */
    protected function supplierRowForMerge(Supplier $supplier, $productCounts): array
    {
        $id = (int) $supplier->id_supplier;

        return [
            'id_supplier' => $id,
            'nama' => (string) $supplier->nama,
            'telepon' => (string) ($supplier->telepon ?? ''),
            'mop' => (string) ($supplier->mop ?? ''),
            'alamat' => (string) ($supplier->alamat ?? ''),
            'product_count' => (int) ($productCounts[$id] ?? 0),
        ];
    }

    /**
     * Repoint every known FK from $fromId to $toId (same DB), excluding supplier table.
     */
    public function repointSupplierForeignKeys(int $fromId, int $toId): void
    {
        if ($fromId === $toId) {
            return;
        }
        $database = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT TABLE_NAME AS tbl, COLUMN_NAME AS col FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND COLUMN_NAME IN (\'id_supplier\', \'supplier_id\') AND TABLE_NAME <> ?',
            [$database, 'supplier']
        );
        foreach ($rows as $r) {
            $tbl = $r->tbl;
            $col = $r->col;
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            try {
                DB::table($tbl)->where($col, $fromId)->update([$col => $toId]);
            } catch (\Throwable $e) {
                // Log and continue — some tables may have composite unique keys
                \Log::warning("Supplier merge: could not repoint {$tbl}.{$col}: ".$e->getMessage());
            }
        }
    }

    /**
     * Merge suppliers into $keepId: move FKs, sum opening balances, delete merged rows.
     *
     * @param  array<int>  $mergeIds
     */
    public function mergeSuppliers(int $keepId, array $mergeIds, ?string $finalNama = null, ?string $finalMop = null, ?string $finalAlamat = null, ?string $finalTelepon = null): Supplier
    {
        $mergeIds = array_values(array_unique(array_filter(array_map('intval', $mergeIds))));
        $mergeIds = array_values(array_filter($mergeIds, static function ($id) use ($keepId) {
            return $id > 0 && $id !== $keepId;
        }));

        $keep = Supplier::query()->findOrFail($keepId);
        $sumOb = (float) ($keep->opening_balance ?? 0);
        $sumPaid = (float) ($keep->opening_balance_paid ?? 0);

        DB::beginTransaction();
        try {
            foreach ($mergeIds as $mid) {
                $src = Supplier::query()->find($mid);
                if (! $src) {
                    continue;
                }
                $sumOb += (float) ($src->opening_balance ?? 0);
                $sumPaid += (float) ($src->opening_balance_paid ?? 0);
                $this->repointSupplierForeignKeys($mid, $keepId);
                $src->delete();
            }

            $keep->opening_balance = $sumOb;
            $keep->opening_balance_paid = $sumPaid;
            if ($finalNama !== null && $finalNama !== '') {
                $keep->nama = $finalNama;
            }
            if ($finalMop !== null && $finalMop !== '') {
                $keep->mop = $this->canonicalMop($finalMop);
            }
            if ($finalAlamat !== null) {
                $keep->alamat = $finalAlamat;
            }
            if ($finalTelepon !== null) {
                $keep->telepon = $finalTelepon;
            }
            $keep->save();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $keep->fresh();
    }
}
