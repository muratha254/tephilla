<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Penjualan extends Model
{
    use HasFactory;

    protected $table = 'penjualan';
    protected $primaryKey = 'id_penjualan';
    //protected $table = 'shops';
   // protected $primaryKey = 'id';
    protected $guarded = [];
    
    protected $casts = [
        'sale_type' => 'string',
        'edit_initiated_at' => 'datetime',
        'confirmation_status' => 'string',
        'was_edited_from_defect' => 'boolean',
    ];
    

    public function member()
    {
        return $this->hasOne(Member::class, 'id_member', 'id_member');
    }
   

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }
    
    public function details()
    {
        return $this->hasMany(PenjualanDetail::class, 'id_penjualan', 'id_penjualan');
    }

    /**
     * Get the sale-level discount amount (for reports).
     * Uses discount_amount when fixed; for percentage, result is rounded to whole number.
     */
    public function getSaleDiscountAmount(): float
    {
        $type = $this->discount_type ?? 'percentage';
        if ($type === 'fixed' && ($this->discount_amount ?? 0) > 0) {
            return (float) $this->discount_amount;
        }
        $amount = (float) ($this->total_harga ?? 0) * ((float) ($this->diskon ?? 0) / 100);
        return (float) round($amount, 0);
    }

    /**
     * Get discount display label for reports: "Ksh X.XX" or "Ksh X" when percentage (rounded).
     */
    public function getDiscountDisplayLabel(): string
    {
        $amount = $this->getSaleDiscountAmount();
        if ($amount <= 0) {
            return '-';
        }
        $type = $this->discount_type ?? 'percentage';
        if ($type === 'fixed') {
            return 'Ksh ' . number_format($amount, 2);
        }
        $pct = (int) round($this->diskon ?? 0);
        return 'Ksh ' . number_format($amount, 0) . ' (' . $pct . '%)';
    }

    /**
     * Sale date for printed receipts (day name + d-m-Y), e.g. "Friday, 22-05-2026".
     */
    public function getReceiptDateFormatted(): string
    {
        $dt = $this->saledate ?? $this->created_at;

        return $dt
            ? \Carbon\Carbon::parse($dt)->format('l, d-m-Y')
            : \Carbon\Carbon::now()->format('l, d-m-Y');
    }

    /**
     * Receipt totals after discount (matches POS checkout: subtotal ex-VAT, VAT, discount, total payable).
     *
     * @return array{subtotal: float, vat: float, discount_amount: float, discount_label: string, total: float}
     */
    public function getReceiptBreakdown(): array
    {
        $total = (float) ($this->bayar ?? $this->total_harga ?? 0);
        $discountAmount = $this->getSaleDiscountAmount();
        $discountType = $this->discount_type ?? 'percentage';
        $discountLabel = '';

        if ($discountAmount > 0) {
            if ($discountType === 'fixed') {
                $discountLabel = 'DISCOUNT';
            } else {
                $discountLabel = 'DISCOUNT (' . (int) round($this->diskon ?? 0) . '%)';
            }
        }

        $vat = ($this->tax !== null && $this->tax !== '')
            ? (float) $this->tax
            : round($total * (16 / 116), 2);

        return [
            'subtotal' => round($total - $vat, 2),
            'vat' => $vat,
            'discount_amount' => $discountAmount,
            'discount_label' => $discountLabel,
            'total' => $total,
        ];
    }

    /**
     * Get the discount percentage (returns 0 if fixed discount).
     */
    public function getDiscountPercentage(): float
    {
        $type = $this->discount_type ?? 'percentage';
        if ($type === 'percentage') {
            return (float) ($this->diskon ?? 0);
        }
        return 0;
    }

    /**
     * Sidebar / activities badge: pending line items on receipts that are not fully confirmed,
     * or count of those receipts when item-level confirmation is unavailable.
     */
    public static function receiptConfirmationBadgeCount(): int
    {
        if (! Schema::hasColumn('penjualan', 'confirmation_status')) {
            return 0;
        }

        if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            return (int) PenjualanDetail::query()
                ->whereHas('penjualan', function ($q) {
                    $q->where('status', 'completed')
                        ->where(function ($q2) {
                            $q2->whereNull('confirmation_status')
                                ->orWhereIn('confirmation_status', ['pending', 'review', 'defect']);
                        });
                })
                ->where(function ($q) {
                    $q->whereNull('item_confirmation_status')
                        ->orWhere('item_confirmation_status', 'pending');
                })
                ->count();
        }

        return (int) static::query()
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNull('confirmation_status')
                    ->orWhereIn('confirmation_status', ['pending', 'review', 'defect']);
            })
            ->count();
    }
}
