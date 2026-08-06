<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAuditLog extends Model
{
    protected $table = 'supplier_audit_logs';

    protected $fillable = [
        'id_supplier',
        'user_id',
        'action',
        'changes',
        'source_file',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public const ACTION_EXCEL_SYNC = 'excel_sync_apply';

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'id_supplier', 'id_supplier');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
