<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'parent_id',
        'conversion_rate',
        'category_id',
        'brand_id',
        'unit_id',
        'tax_id',
        'name',
        'sku',
        'barcode',
        'description',
        'image_path',
        'purchase_price',
        'selling_price',
        'wholesale_price',
        'reorder_level',
        'size',
        'color',
        'has_variants',
        'manage_stock',
        'allow_negative_stock',
        'is_active',
        'for_sale',
        'tax_inclusive',
        'expiry_date',
        'profit_margin',
        'promo_price',
        'sales_commission',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'promo_price' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'sales_commission' => 'decimal:2',
        'conversion_rate' => 'decimal:4',
        'reorder_level' => 'decimal:4',
        'has_variants' => 'boolean',
        'manage_stock' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'is_active' => 'boolean',
        'for_sale' => 'boolean',
        'tax_inclusive' => 'boolean',
        'expiry_date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function branchStock()
    {
        return $this->hasMany(ProductBranchStock::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Products visible for a branch:
     * - non-stock items (services) are company-wide
     * - stocked items must have a product_branch_stock row for that branch
     * - legacy items with no stock rows anywhere remain visible (until first stocked)
     */
    public function scopeAvailableAtBranch($query, ?int $branchId)
    {
        if (! $branchId) {
            return $query;
        }

        return $query->where(function ($builder) use ($branchId) {
            $builder->where('manage_stock', false)
                ->orWhereDoesntHave('branchStock', function ($stock) {
                    $stock->withoutGlobalScope('branch');
                })
                ->orWhereHas('branchStock', function ($stock) use ($branchId) {
                    $stock->withoutGlobalScope('branch')->where('branch_id', $branchId);
                });
        });
    }

    public function isAvailableAtBranch(?int $branchId): bool
    {
        if (! $branchId) {
            return true;
        }

        if (! $this->manage_stock) {
            return true;
        }

        $hasAny = $this->branchStock()->withoutGlobalScope('branch')->exists();
        if (! $hasAny) {
            return true;
        }

        return $this->branchStock()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->exists();
    }

    public function quantityAtBranch(?int $branchId, ?int $variantId = null): float
    {
        if (! $branchId) {
            return 0.0;
        }

        $query = $this->branchStock()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId);

        if ($variantId !== null) {
            $query->where('product_variant_id', $variantId);
        }

        return round((float) $query->sum('quantity'), 4);
    }

    public function getItemCodeAttribute(): string
    {
        return str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Products with active variants must name one. Products without variants stay on variant 0.
     */
    public function resolveVariantId($variantId): int
    {
        $variantId = (int) ($variantId ?? 0);
        $hasVariants = $this->variants()->where('is_active', true)->exists();

        if ($hasVariants && $variantId <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'product_variant_id' => "Select a colour for {$this->name}.",
            ]);
        }

        if ($variantId > 0 && ! $this->variants()->whereKey($variantId)->where('is_active', true)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'product_variant_id' => "That colour is not available for {$this->name}.",
            ]);
        }

        return $variantId;
    }

    public function taxLabel(): string
    {
        $rate = (float) optional($this->tax)->rate;
        if ($rate <= 0) {
            return '0%';
        }

        $display = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
        $type = $this->tax_inclusive ? 'Inc.' : 'Exc.';

        return $display . '% ' . $type;
    }
}
