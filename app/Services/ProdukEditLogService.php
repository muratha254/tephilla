<?php

namespace App\Services;

use App\Models\Produk;
use App\Models\ProdukEditLog;
use App\Models\Shop;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ProdukEditLogService
{
    private const FIELD_LABELS = [
        'nama_produk' => 'Product name',
        'item_code' => 'Item code',
        'harga_beli' => 'Buy price',
        'harga_jual' => 'Selling price',
        'stok' => 'Stock (remaining)',
        'reorder_level' => 'Re-order level',
        'shop_id' => 'Shop',
        'id_supplier' => 'Supplier',
        'diskon' => 'Discount %',
    ];

    public function tableExists(): bool
    {
        return Schema::hasTable('produk_edit_log');
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Produk $produk): array
    {
        return [
            'nama_produk' => trim((string) ($produk->nama_produk ?? '')),
            'item_code' => trim((string) ($produk->item_code ?? $produk->kode_produk ?? '')),
            'harga_beli' => (float) ($produk->harga_beli ?? 0),
            'harga_jual' => (float) ($produk->harga_jual ?? 0),
            'stok' => (int) ($produk->stok ?? 0),
            'reorder_level' => (int) ($produk->reorder_level ?? 0),
            'shop_id' => (int) ($produk->shop_id ?? 0),
            'id_supplier' => (int) ($produk->id_supplier ?? 0),
            'diskon' => (int) ($produk->diskon ?? 0),
        ];
    }

    public function logDiff(int $idProduk, array $before, Produk $after, Request $request): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $changes = $this->buildChanges($before, $this->snapshot($after));
        if ($changes === []) {
            return;
        }

        $this->persist($idProduk, $changes, $this->resolveSource($request));
    }

    /**
     * @param  array<int, array{field: string, label: string, old: string, new: string}>  $changes
     */
    public function logExplicit(int $idProduk, array $changes, string $source): void
    {
        if (! $this->tableExists() || $changes === []) {
            return;
        }

        $this->persist($idProduk, $changes, $source);
    }

    /**
     * @param  array<int, array{field: string, label: string, old: string, new: string}>  $changes
     */
    public function formatChangesDetail(array $changes, ?string $userName = null): string
    {
        $parts = [];
        foreach ($changes as $c) {
            $old = (string) ($c['old'] ?? '—');
            $new = (string) ($c['new'] ?? '—');
            $label = (string) ($c['label'] ?? $c['field'] ?? 'Field');
            $parts[] = $label.': '.$old.' → '.$new;
        }
        $detail = implode(' · ', $parts);
        if ($userName !== null && trim($userName) !== '') {
            $detail .= ' · By: '.trim($userName);
        }

        return $detail;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, array{field: string, label: string, old: string, new: string}>
     */
    private function buildChanges(array $before, array $after): array
    {
        $changes = [];
        foreach (self::FIELD_LABELS as $field => $label) {
            $oldRaw = $before[$field] ?? null;
            $newRaw = $after[$field] ?? null;
            if ($this->valuesEqual($field, $oldRaw, $newRaw)) {
                continue;
            }
            $changes[] = [
                'field' => $field,
                'label' => $label,
                'old' => $this->formatValue($field, $oldRaw),
                'new' => $this->formatValue($field, $newRaw),
            ];
        }

        return $changes;
    }

    private function valuesEqual(string $field, $old, $new): bool
    {
        if (in_array($field, ['harga_beli', 'harga_jual'], true)) {
            return abs((float) $old - (float) $new) < 0.0001;
        }
        if (in_array($field, ['stok', 'reorder_level', 'shop_id', 'id_supplier', 'diskon'], true)) {
            return (int) $old === (int) $new;
        }

        return (string) $old === (string) $new;
    }

    private function formatValue(string $field, $value): string
    {
        if ($field === 'shop_id') {
            $shop = Shop::find((int) $value);

            return $shop ? (string) $shop->shop_name : ('#'.$value);
        }
        if ($field === 'id_supplier') {
            $sup = Supplier::find((int) $value);

            return $sup ? (string) $sup->nama : ('#'.$value);
        }
        if (in_array($field, ['harga_beli', 'harga_jual'], true)) {
            return format_uang((float) $value);
        }

        return (string) $value;
    }

    /**
     * @param  array<int, array{field: string, label: string, old: string, new: string}>  $changes
     */
    private function persist(int $idProduk, array $changes, string $source): void
    {
        $user = Auth::user();

        ProdukEditLog::create([
            'id_produk' => $idProduk,
            'user_id' => $user ? (int) $user->id : null,
            'user_name' => $user ? (string) ($user->name ?? $user->email ?? 'User') : 'System',
            'changes' => $changes,
            'source' => $source,
            'ip_address' => request()->ip(),
        ]);
    }

    private function resolveSource(Request $request): string
    {
        if ($request->boolean('inline_stock_only')) {
            return 'stock_list_stock';
        }
        if ($request->boolean('inline_item_in')) {
            return 'stock_list_item_in';
        }
        if ($request->boolean('inline_fields_only')) {
            return 'stock_list_inline';
        }

        return 'product_edit';
    }
}
