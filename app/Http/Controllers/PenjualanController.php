<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\Account;
use App\Models\DailyCash;
use App\Models\Supplier;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\Shop;
use Illuminate\Http\Request;
use PDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\Facades\DataTables;
use App\Jobs\ProcessSalesImportJob;
use App\Jobs\ReadSalesImportExcelJob;
use Illuminate\Support\Facades\Storage;
use App\Services\EnsureSaleSupplierLedgerService;
use App\Services\SaleSupplierLedgerRemovalService;


class PenjualanController extends Controller
{
    /**
     * Normalize date string from dd/mm/yyyy or yyyy-mm-dd to Y-m-d for DB queries.
     */
    protected function normalizeFilterDate($dateString)
    {
        if (empty($dateString) || !is_string($dateString)) {
            return null;
        }
        $trimmed = trim($dateString);
        if ($trimmed === '') {
            return null;
        }
        // Already Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return $trimmed;
        }
        // dd/mm/yyyy or d/m/yyyy
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $trimmed, $m)) {
            try {
                return \Carbon\Carbon::createFromFormat('d/m/Y', $trimmed)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        try {
            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Apply optional sales-date range unless the user is searching by receipt or item code (open search, all dates).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    protected function applySalesListDateFilters($query, Request $request): void
    {
        $receipt = trim((string) $request->input('receipt_number', ''));
        $itemCode = trim((string) $request->input('item_code', ''));

        if ($receipt !== '' || $itemCode !== '') {
            return;
        }

        $startDate = $this->normalizeFilterDate($request->input('start_date'));
        if ($startDate) {
            $query->whereDate('saledate', '>=', $startDate);
        }

        $endDate = $this->normalizeFilterDate($request->input('end_date'));
        if ($endDate) {
            $query->whereDate('saledate', '<=', $endDate);
        }
    }

    /**
     * Sales list / normal-sale exports: completed, plus active rows that already have payment
     * (e.g. reopened for edit and not finalized again — still real receipts).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  bool  $qualifyTable  Use penjualan.status / penjualan.bayar when the query joins other tables
     */
    protected function applyNormalSalesListStatusScope($query, bool $qualifyTable = false): void
    {
        if (! Schema::hasColumn('penjualan', 'status')) {
            return;
        }
        $s = $qualifyTable ? 'penjualan.status' : 'status';
        if (! Schema::hasColumn('penjualan', 'bayar')) {
            $query->where($s, 'completed');

            return;
        }
        $b = $qualifyTable ? 'penjualan.bayar' : 'bayar';
        $query->where(function ($q) use ($s, $b) {
            $q->where($s, 'completed')
                ->orWhere(function ($q2) use ($s, $b) {
                    $q2->where($s, 'active')->where($b, '>', 0);
                });
        });
    }

    /**
     * Final thermal receipts (nota kecil) only after the sale is completed in POS.
     * Open carts and suspended sales use withheld_receipt instead.
     */
    protected function assertSaleReceiptAllowed(Penjualan $penjualan): void
    {
        if (! Schema::hasColumn('penjualan', 'status')) {
            return;
        }

        if (($penjualan->status ?? '') === 'completed') {
            return;
        }

        abort(403, 'This receipt is only available after you complete the transaction in POS.');
    }

    /**
     * DataTables HTML for currency_type (comma-separated values, e.g. KSH,USD).
     */
    protected function formatCurrencyTypeBadgesHtml(?string $currency): string
    {
        $raw = $currency ?? 'KSH';
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if (empty($parts)) {
            $parts = ['KSH'];
        }
        $out = '';
        foreach ($parts as $c) {
            $out .= '<span class="label label-warning" style="margin-right:3px;">' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return $out;
    }

    /**
     * Highest numeric suffix among receipts that strictly match PREFIX+digits only (e.g. UTAM301 → 301, U004 → 4).
     */
    protected function maxNumericReceiptSuffixForPrefix(string $prefix): int
    {
        $driver = DB::getDriverName();
        $startPos = strlen($prefix) + 1;

        if ($driver === 'mysql') {
            $regex = '^'.preg_quote($prefix, '/').'[0-9]+$';

            return (int) DB::table('penjualan')
                ->where('receiptno', 'like', $prefix.'%')
                ->whereRaw('receiptno REGEXP ?', [$regex])
                ->selectRaw('COALESCE(MAX(CAST(SUBSTRING(receiptno, '.$startPos.') AS UNSIGNED)), 0) as m')
                ->value('m');
        }

        // SQLite / testing: no REGEXP — scan matching rows (small N for tests)
        $max = 0;
        $pattern = '/^'.preg_quote($prefix, '/').'(\d+)$/';
        $rows = DB::table('penjualan')->where('receiptno', 'like', $prefix.'%')->pluck('receiptno');
        foreach ($rows as $r) {
            if (preg_match($pattern, (string) $r, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    /**
     * Next sequential receipt number for a prefix (U for standard POS / M for management), including active open sales.
     * Pass $continueAfterPrefixes so a new prefix continues the counter from legacy receipts (e.g. U after UTAM).
     * Uses MySQL GET_LOCK so multiple cashiers cannot get the same number at the same time.
     *
     * @param  array<int, string>  $continueAfterPrefixes  Extra strict prefixes whose numeric max is merged (not used in the returned string).
     */
    protected function computeNextSequentialReceiptNo(string $prefix, int $padLength = 3, array $continueAfterPrefixes = []): string
    {
        $max = $this->maxNumericReceiptSuffixForPrefix($prefix);
        foreach ($continueAfterPrefixes as $legacyPrefix) {
            $max = max($max, $this->maxNumericReceiptSuffixForPrefix((string) $legacyPrefix));
        }

        $next = $max + 1;

        return $prefix.str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
    }

    /**
     * Run callback while holding a global lock for receipt allocation (MySQL only).
     */
    protected function withReceiptAllocationLock(string $lockName, callable $callback)
    {
        if (DB::getDriverName() === 'mysql') {
            DB::selectOne('SELECT GET_LOCK(?, 10)', [$lockName]);
            try {
                return $callback();
            } finally {
                DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }

        return $callback();
    }

    public function index(Request $request)
    {
        // Only admins can access the Sales List
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access the Sales List.');
        }

        $query = Penjualan::query();

        $this->applySalesListDateFilters($query, $request);

        $penjualans = $query->orderByDesc('saledate')->orderByDesc('id_penjualan')->paginate(50);

        return view('penjualan.index', compact('penjualans'));
    }

    /**
     * Display management sales list
     */
    public function managementSales(Request $request)
    {
        // Allow any authenticated sales user (route already protected by sales.read middleware).
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized. Please log in to access the Management Sales List.');
        }

        return view('penjualan.management_index');
    }
        
   
public function data(Request $request)
{
    try {
        $receiptFilter = $request->filled('receipt_number')
            ? trim((string) $request->input('receipt_number'))
            : '';

        $query = Penjualan::with(['member', 'user', 'details.produk'])
            ->orderByDesc('saledate')
            ->orderByDesc('id_penjualan');

        // Completed + paid-but-active (reopened edit not finalized)
        $this->applyNormalSalesListStatusScope($query);

        // Exclude management sales from regular sales list
        if (Schema::hasColumn('penjualan', 'sale_type')) {
            $query->where(function ($q) {
                $q->where('sale_type', 'normal')->orWhereNull('sale_type');
            });
        }

        // Date filtering — skipped when searching by receipt or item code (all dates)
        $this->applySalesListDateFilters($query, $request);

        // Payment mode filter
        if ($request->filled('payment_mode')) {
            $query->where('payment_method', $request->input('payment_mode'));
        }

        // Receipt number filter (partial match)
        if ($request->filled('receipt_number')) {
            $query->where('receiptno', 'like', '%' . $request->input('receipt_number') . '%');
        }

        // Item code filter (matches produk.item_code or produk.kode_produk on sale lines)
        if ($request->filled('item_code')) {
            $itemCode = trim((string) $request->input('item_code'));
            $query->whereIn('id_penjualan', function ($q) use ($itemCode) {
                $q->select('penjualan_detail.id_penjualan')
                    ->from('penjualan_detail')
                    ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                    ->where(function ($w) use ($itemCode) {
                        $w->where('produk.item_code', 'like', '%' . $itemCode . '%')
                            ->orWhere('produk.kode_produk', 'like', '%' . $itemCode . '%');
                    })
                    ->distinct();
            });
        }

        // Receipt status filter (confirmation_status)
        if ($request->filled('receipt_status') && Schema::hasColumn('penjualan', 'confirmation_status')) {
            $query->where('confirmation_status', $request->input('receipt_status'));
        }

        // Server-side: DataTables applies limit/offset and search from request
        $datatable = datatables()->eloquent($query)
            ->addIndexColumn()
            ->addColumn('checkbox', function ($penjualan) {
                return '<input type="checkbox" class="row-checkbox" value="' . $penjualan->id_penjualan . '">';
            })
            ->addColumn('total_item', function ($penjualan) {
                return format_uang($penjualan->total_item);
            })
            ->addColumn('total_harga', function ($penjualan) {
                return 'Ksh ' . format_uang($penjualan->total_harga);
            })
            ->addColumn('bayar', function ($penjualan) {
                return 'Ksh ' . format_uang($penjualan->bayar);
            })
            ->addColumn('saledate', function ($penjualan) {
                if ($penjualan->saledate) {
                    return date('d/m/Y', strtotime($penjualan->saledate));
                }
                $date = $penjualan->created_at;
                return $date ? date('d/m/Y', strtotime($date)) : '';
            })
            ->addColumn('tanggal', function ($penjualan) {
                return tanggal_indonesia($penjualan->created_at, false);
            })
            ->addColumn('kode_member', function ($penjualan) {
                $member = $penjualan->member->kode_member ?? '';
                return '<span class="label label-success">' . $member . '</span>';
            })
            ->editColumn('diskon', function ($penjualan) {
                return $penjualan->getDiscountDisplayLabel();
            })
            ->editColumn('kasir', function ($penjualan) {
                return $penjualan->user->name ?? '';
            })
            ->addColumn('payment_method', function ($penjualan) {
                $method = $penjualan->payment_method ?? 'Cash';
                $badgeClass = 'label-default';
                if ($method === 'Mpesa') {
                    $badgeClass = 'label-success';
                } elseif ($method === 'Card') {
                    $badgeClass = 'label-info';
                } elseif ($method === 'Cash') {
                    $badgeClass = 'label-primary';
                }
                return '<span class="label ' . $badgeClass . '">' . $method . '</span>';
            })
            ->addColumn('currency_type', function ($penjualan) {
                return $this->formatCurrencyTypeBadgesHtml($penjualan->currency_type ?? null);
            })
            ->addColumn('receipt_status', function ($penjualan) {
                if (Schema::hasColumn('penjualan', 'confirmation_status')) {
                    $status = $penjualan->confirmation_status ?? 'pending';
                    if ($status === 'confirmed') {
                        return '<span class="label label-success">Confirmed</span>';
                    } elseif ($status === 'defect') {
                        return '<span class="label label-danger">Defect</span>';
                    } elseif ($status === 'review') {
                        return '<span class="label label-info">Review</span>';
                    } else {
                        return '<span class="label label-warning">Pending</span>';
                    }
                }
                return '<span class="label label-warning">Pending</span>';
            })
            ->editColumn('receiptno', function ($penjualan) {
                $r = e($penjualan->receiptno ?? '');
                if (Schema::hasColumn('penjualan', 'status')
                    && ($penjualan->status ?? '') === 'active'
                    && (float) ($penjualan->bayar ?? 0) > 0) {
                    return $r.' <span class="label label-danger" title="Reopened or not finalized — complete again in POS to restore accounts/stock">Open</span>';
                }

                return $r;
            })
            ->addColumn('shop_codes', function ($penjualan) {
            try {
                // Use a direct query to get shop codes to avoid relationship issues
                $shopCodes = DB::table('penjualan_detail')
                    ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                    ->leftJoin('shops', 'produk.shop_id', '=', 'shops.id')
                    ->where('penjualan_detail.id_penjualan', $penjualan->id_penjualan)
                    ->whereNotNull('shops.shop_code')
                    ->where('shops.shop_code', '!=', '')
                    ->distinct()
                    ->pluck('shops.shop_code')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
                
                if (empty($shopCodes)) {
                    return '<span class="text-muted">N/A</span>';
                }
                
                $badges = array_map(function($code) {
                    return '<span class="label label-info" style="margin-right: 3px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</span>';
                }, $shopCodes);
                return implode(' ', $badges);
            } catch (\Exception $e) {
                \Log::error('Error getting shop codes for penjualan ' . ($penjualan->id_penjualan ?? 'unknown') . ': ' . $e->getMessage());
                return '<span class="text-muted">N/A</span>';
            }
            })
            ->addColumn('aksi', function ($penjualan) {
                $hasQuickAdd = false;
                if (Schema::hasColumn('produk', 'is_incomplete')) {
                    $hasQuickAdd = $penjualan->details->contains(function ($detail) {
                        return $detail->produk && (int) ($detail->produk->is_incomplete ?? 0) === 1;
                    });
                }

                $confirmBtn = $hasQuickAdd
                    ? '<button onclick="confirmReceipt(' . $penjualan->id_penjualan . ')" class="btn btn-xs btn-warning btn-flat" title="Complete Quick Add items first"><i class="fa fa-clock-o"></i> Quick Add</button>'
                    : '<button onclick="confirmReceipt(' . $penjualan->id_penjualan . ')" class="btn btn-xs btn-success btn-flat" title="Confirm Receipt"><i class="fa fa-check"></i> Confirm</button>';

                $buttons = '<div class="btn-group">'
                    . '<button onclick="showDetail(`' . route('penjualan.show', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-primary btn-flat" title="View Details"><i class="fa fa-eye"></i></button>'
                    . '<button onclick="reprintReceipt(`' . route('penjualan.reprint', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-info btn-flat" title="Reprint Receipt"><i class="fa fa-print"></i></button>'
                    . $confirmBtn;
                if (auth()->user()->hasRole('admin')) {
                    $buttons .= '<a href="' . route('penjualan.edit_sale', $penjualan->id_penjualan) . '" class="btn btn-xs btn-warning btn-flat" title="Edit Sale (you)"><i class="fa fa-edit"></i></a>';
                    $buttons .= '<a href="' . route('penjualan.initiate_edit', $penjualan->id_penjualan) . '" class="btn btn-xs btn-success btn-flat" title="Initiate edit (cashier continues)"><i class="fa fa-share"></i></a>';
                    $buttons .= '<button onclick="deleteData(`' . route('penjualan.destroy', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-danger btn-flat" title="Delete"><i class="fa fa-trash"></i></button>';
                }
                $buttons .= '</div>';
                return $buttons;
            })
            ->orderColumn('saledate', 'penjualan.saledate $1')
            ->rawColumns(['aksi', 'kode_member', 'payment_method', 'shop_codes', 'currency_type', 'receipt_status', 'receiptno', 'checkbox']);

        $response = $datatable->make(true);
        $payload = $response->getData(true);

        if ($receiptFilter !== '' && (int) ($payload['recordsFiltered'] ?? 0) === 0) {
            $hint = $this->findEditInitiatedReceiptSearchHint($receiptFilter);
            if ($hint !== null) {
                $payload['edit_initiated_hint'] = $hint;
            }
        }

        return response()->json($payload);
    } catch (\Throwable $e) {
        \Log::error('Penjualan data() error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Failed to load sales list. Please try again.',
        ], 200);
    }
}

    /**
     * Base query for management sales list / exports.
     */
    protected function managementSalesQuery(Request $request)
    {
        $query = Penjualan::query()
            ->where('sale_type', 'management')
            ->orderByDesc('saledate')
            ->orderByDesc('id_penjualan');

        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }

        $this->applySalesListDateFilters($query, $request);

        if ($request->filled('receipt_number')) {
            $query->where('receiptno', 'like', '%' . trim((string) $request->input('receipt_number')) . '%');
        }

        if ($request->filled('item_code')) {
            $itemCode = trim((string) $request->input('item_code'));
            $query->whereIn('id_penjualan', function ($q) use ($itemCode) {
                $q->select('penjualan_detail.id_penjualan')
                    ->from('penjualan_detail')
                    ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                    ->where(function ($w) use ($itemCode) {
                        $w->where('produk.item_code', 'like', '%' . $itemCode . '%')
                            ->orWhere('produk.kode_produk', 'like', '%' . $itemCode . '%');
                    })
                    ->distinct();
            });
        }

        return $query;
    }

    /**
     * Get management sales data for DataTables
     */
    public function managementSalesData(Request $request)
    {
        try {
            $query = $this->managementSalesQuery($request)
                ->with(['member', 'user', 'details.produk.shop']);

            return datatables()
                ->eloquent($query)
                ->addIndexColumn()
                ->addColumn('select_row', function ($penjualan) {
                    return '<input type="checkbox" class="management-sale-check" value="' . (int) $penjualan->id_penjualan . '">';
                })
                ->addColumn('total_item', function ($penjualan) {
                    return format_uang($penjualan->total_item);
                })
                ->addColumn('total_harga', function ($penjualan) {
                    return 'Ksh ' . format_uang($penjualan->total_harga);
                })
                ->addColumn('bayar', function ($penjualan) {
                    return 'Ksh ' . format_uang($penjualan->bayar);
                })
                ->addColumn('saledate', function ($penjualan) {
                    if ($penjualan->saledate) {
                        return date('d/m/Y', strtotime($penjualan->saledate));
                    }
                    $date = $penjualan->created_at;

                    return $date ? date('d/m/Y', strtotime($date)) : '';
                })
                ->editColumn('diskon', function ($penjualan) {
                    return $penjualan->getDiscountDisplayLabel();
                })
                ->editColumn('kasir', function ($penjualan) {
                    return $penjualan->user->name ?? '';
                })
                ->addColumn('payment_method', function ($penjualan) {
                    $method = $penjualan->payment_method ?? 'Cash';
                    $badgeClass = 'label-default';
                    if ($method === 'Mpesa') {
                        $badgeClass = 'label-success';
                    } elseif ($method === 'Card') {
                        $badgeClass = 'label-info';
                    } elseif ($method === 'Cash') {
                        $badgeClass = 'label-primary';
                    }

                    return '<span class="label ' . $badgeClass . '">' . e($method) . '</span>';
                })
                ->addColumn('product_names', function ($penjualan) {
                    $names = collect($penjualan->details ?? [])
                        ->map(function ($detail) {
                            return optional($detail->produk)->nama_produk;
                        })
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    return count($names) ? e(implode(', ', $names)) : '-';
                })
                ->addColumn('shop_names', function ($penjualan) {
                    $shops = collect($penjualan->details ?? [])
                        ->map(function ($detail) {
                            return optional(optional($detail->produk)->shop)->shop_name;
                        })
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    return count($shops) ? e(implode(', ', $shops)) : '-';
                })
                ->addColumn('aksi', function ($penjualan) {
                    $buttons = '
                <div class="btn-group">
                    <button onclick="showDetail(`' . route('penjualan.show', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-primary btn-flat" title="View Details"><i class="fa fa-eye"></i></button>
                    <button onclick="reprintReceipt(`' . route('penjualan.reprint', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-info btn-flat" title="Reprint Receipt"><i class="fa fa-print"></i></button>
                    <a href="' . route('penjualan.edit_sale', $penjualan->id_penjualan) . '" class="btn btn-xs btn-warning btn-flat" title="Edit Sale"><i class="fa fa-edit"></i></a>';

                    $user = auth()->user();
                    if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                        $buttons .= '<button onclick="deleteData(`' . route('penjualan.destroy', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-danger btn-flat" title="Delete"><i class="fa fa-trash"></i></button>';
                    }

                    $buttons .= '</div>';

                    return $buttons;
                })
                ->filterColumn('product_names', function ($query, $keyword) {
                    $like = '%' . $keyword . '%';
                    $query->whereIn('id_penjualan', function ($sub) use ($like) {
                        $sub->select('penjualan_detail.id_penjualan')
                            ->from('penjualan_detail')
                            ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                            ->where(function ($w) use ($like) {
                                $w->where('produk.nama_produk', 'like', $like)
                                    ->orWhere('produk.item_code', 'like', $like)
                                    ->orWhere('produk.kode_produk', 'like', $like);
                            });
                    });
                })
                ->filterColumn('shop_names', function ($query, $keyword) {
                    $like = '%' . $keyword . '%';
                    $query->whereIn('id_penjualan', function ($sub) use ($like) {
                        $sub->select('penjualan_detail.id_penjualan')
                            ->from('penjualan_detail')
                            ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                            ->leftJoin('shops', 'produk.shop_id', '=', 'shops.id')
                            ->where(function ($w) use ($like) {
                                $w->where('shops.shop_name', 'like', $like)
                                    ->orWhere('shops.shop_code', 'like', $like);
                            });
                    });
                })
                ->filterColumn('kasir', function ($query, $keyword) {
                    $query->whereHas('user', function ($q) use ($keyword) {
                        $q->where('name', 'like', '%' . $keyword . '%');
                    });
                })
                ->filterColumn('payment_method', function ($query, $keyword) {
                    $query->where('payment_method', 'like', '%' . $keyword . '%');
                })
                ->orderColumn('saledate', 'penjualan.saledate $1')
                ->rawColumns(['aksi', 'payment_method', 'select_row'])
                ->make(true);
        } catch (\Throwable $e) {
            \Log::error('managementSalesData error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Failed to load management sales. Please try again.',
            ], 200);
        }
    }

    /**
     * Export management sales report as PDF
     */
    public function exportManagementSalesPdf(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized. Please log in to access the Management Sales List.');
        }

        $startDateRaw = $request->input('start_date');
        $endDateRaw = $request->input('end_date');
        $startDate = $this->normalizeFilterDate($startDateRaw);
        $endDate = $this->normalizeFilterDate($endDateRaw);

        $query = $this->managementSalesQuery($request)
            ->with(['member', 'user'])
            ->reorder()
            ->orderBy('saledate', 'asc')
            ->orderBy('created_at', 'asc');

        $selectedIds = collect(explode(',', (string) $request->input('selected_ids', '')))
            ->map(function ($id) {
                return (int) trim($id);
            })
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values();
        if ($selectedIds->isNotEmpty()) {
            $query->whereIn('id_penjualan', $selectedIds->all());
        }

        $sales = $query->get();

        // Calculate totals
        $totalQuantity = $sales->sum('total_item');
        $totalAmount = $sales->sum('bayar');
        $totalDiscount = $sales->sum(function($sale) {
            $type = $sale->discount_type ?? 'percentage';
            if ($type === 'fixed' && ($sale->discount_amount ?? 0) > 0) {
                return floatval($sale->discount_amount);
            }
            return floatval($sale->total_harga) * (floatval($sale->diskon ?? 0) / 100);
        });

        // Format dates for display
        $periodLabel = 'All Time';
        if ($startDate && $endDate) {
            $periodLabel = Carbon::parse($startDate)->format('d/m/Y') . ' - ' . Carbon::parse($endDate)->format('d/m/Y');
        } elseif ($startDate) {
            $periodLabel = 'From ' . Carbon::parse($startDate)->format('d/m/Y');
        } elseif ($endDate) {
            $periodLabel = 'Until ' . Carbon::parse($endDate)->format('d/m/Y');
        } elseif (!empty($startDateRaw) || !empty($endDateRaw)) {
            // Keep export alive even if user supplied unparseable dates.
            $periodLabel = 'All Time';
        }

        $setting = Setting::first();

        $pdf = PDF::loadView('penjualan.management_sales_pdf', compact(
            'sales',
            'totalQuantity',
            'totalAmount',
            'totalDiscount',
            'periodLabel',
            'startDate',
            'endDate',
            'setting'
        ));

        $pdf->setPaper('a4', 'landscape');
        
        $filename = 'management_sales_report_' . date('Y-m-d') . '.pdf';
        
        return $pdf->stream($filename);
    }

    /**
     * Export management sales as PDF using cost price (amount to pay).
     */
    public function exportManagementSalesCostPdf(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized. Please log in to access this export.');
        }

        $startDateRaw = $request->input('start_date');
        $endDateRaw = $request->input('end_date');
        $startDate = $this->normalizeFilterDate($startDateRaw);
        $endDate = $this->normalizeFilterDate($endDateRaw);

        $query = $this->managementSalesQuery($request)
            ->with(['user', 'details.produk'])
            ->reorder()
            ->orderBy('saledate', 'asc')
            ->orderBy('created_at', 'asc');

        $selectedIds = collect(explode(',', (string) $request->input('selected_ids', '')))
            ->map(function ($id) {
                return (int) trim($id);
            })
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values();
        if ($selectedIds->isNotEmpty()) {
            $query->whereIn('id_penjualan', $selectedIds->all());
        }

        $sales = $query->get();
        $rows = $sales->map(function ($sale, $idx) {
            $details = collect($sale->details ?? []);
            $totalQty = (float) $details->sum(function ($d) {
                return (float) ($d->jumlah ?? 0);
            });
            $totalCost = (float) $details->sum(function ($d) {
                $qty = (float) ($d->jumlah ?? 0);
                $cost = (float) (optional($d->produk)->harga_beli ?? 0);

                return $qty * $cost;
            });
            $products = $details->map(function ($d) {
                return optional($d->produk)->nama_produk;
            })->filter()->unique()->values()->all();

            return [
                'index' => $idx + 1,
                'date' => $sale->saledate ? Carbon::parse($sale->saledate)->format('d/m/Y') : ($sale->created_at ? Carbon::parse($sale->created_at)->format('d/m/Y') : ''),
                'receipt' => $sale->receiptno,
                'products' => count($products) ? implode(', ', $products) : '-',
                'quantity' => $totalQty,
                'cost_total' => $totalCost,
                'cashier' => optional($sale->user)->name ?? 'N/A',
            ];
        })->values();

        $totalQuantity = (float) $rows->sum('quantity');
        $totalCostAmount = (float) $rows->sum('cost_total');

        $periodLabel = 'All Time';
        if ($startDate && $endDate) {
            $periodLabel = Carbon::parse($startDate)->format('d/m/Y') . ' - ' . Carbon::parse($endDate)->format('d/m/Y');
        } elseif ($startDate) {
            $periodLabel = 'From ' . Carbon::parse($startDate)->format('d/m/Y');
        } elseif ($endDate) {
            $periodLabel = 'Until ' . Carbon::parse($endDate)->format('d/m/Y');
        }

        $setting = Setting::first();
        $pdf = PDF::loadView('penjualan.management_sales_cost_pdf', compact(
            'rows',
            'totalQuantity',
            'totalCostAmount',
            'periodLabel',
            'setting'
        ));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('management_sales_cost_report_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Export sales list (filtered) as PDF for printing.
     */
    public function exportSalesListPdf(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access the Sales List export.');
        }

        try {
        $query = Penjualan::with(['member', 'user'])
            ->orderBy('saledate', 'desc')
            ->orderBy('id_penjualan', 'desc');

        $this->applyNormalSalesListStatusScope($query);

        if (Schema::hasColumn('penjualan', 'sale_type')) {
            $query->where(function ($q) {
                $q->where('sale_type', 'normal')->orWhereNull('sale_type');
            });
        }

        $this->applySalesListDateFilters($query, $request);
        if ($request->filled('payment_mode')) {
            $query->where('payment_method', $request->input('payment_mode'));
        }
        if ($request->filled('receipt_number')) {
            $query->where('receiptno', 'like', '%' . $request->input('receipt_number') . '%');
        }
        if ($request->filled('item_code')) {
            $itemCode = trim((string) $request->input('item_code'));
            $query->whereIn('id_penjualan', function ($q) use ($itemCode) {
                $q->select('penjualan_detail.id_penjualan')
                    ->from('penjualan_detail')
                    ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                    ->where(function ($w) use ($itemCode) {
                        $w->where('produk.item_code', 'like', '%' . $itemCode . '%')
                            ->orWhere('produk.kode_produk', 'like', '%' . $itemCode . '%');
                    })
                    ->distinct();
            });
        }
        if ($request->filled('receipt_status') && Schema::hasColumn('penjualan', 'confirmation_status')) {
            $query->where('confirmation_status', $request->input('receipt_status'));
        }

        // Limit for PDF to avoid memory/timeout (max 2000 rows)
        $sales = $query->limit(2000)->get();

        // Attach shop codes text for each sale (for PDF)
        $saleIds = $sales->pluck('id_penjualan')->toArray();
        $shopCodesMap = [];
        if (!empty($saleIds) && Schema::hasTable('shops')) {
            try {
                $rows = DB::table('penjualan_detail')
                    ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                    ->leftJoin('shops', 'produk.shop_id', '=', 'shops.id')
                    ->whereIn('penjualan_detail.id_penjualan', $saleIds)
                    ->whereNotNull('shops.shop_code')
                    ->where('shops.shop_code', '!=', '')
                    ->select('penjualan_detail.id_penjualan', 'shops.shop_code')
                    ->distinct()
                    ->get();
                foreach ($rows as $row) {
                    $shopCodesMap[$row->id_penjualan][] = $row->shop_code;
                }
            } catch (\Throwable $e) {
                // ignore shop codes if table/column missing
            }
        }
        foreach ($sales as $sale) {
            $sale->shop_codes_text = isset($shopCodesMap[$sale->id_penjualan])
                ? implode(', ', array_unique($shopCodesMap[$sale->id_penjualan]))
                : 'N/A';
            $sale->receipt_status_text = $sale->confirmation_status ?? 'pending';
        }

        $totalQuantity = $sales->sum('total_item');
        $totalAmount = $sales->sum('bayar');
        $totalDiscount = $sales->sum(function ($sale) {
            $type = $sale->discount_type ?? 'percentage';
            if ($type === 'fixed' && ($sale->discount_amount ?? 0) > 0) {
                return floatval($sale->discount_amount);
            }
            return floatval($sale->total_harga) * (floatval($sale->diskon ?? 0) / 100);
        });

        $filterLines = [];
        if ($startDate) {
            $filterLines[] = 'From ' . Carbon::parse($startDate)->format('d/m/Y');
        }
        if ($endDate) {
            $filterLines[] = 'To ' . Carbon::parse($endDate)->format('d/m/Y');
        }
        if ($request->filled('payment_mode')) {
            $filterLines[] = 'Payment: ' . $request->input('payment_mode');
        }
        if ($request->filled('receipt_number')) {
            $filterLines[] = 'Receipt No: ' . $request->input('receipt_number');
        }
        if ($request->filled('item_code')) {
            $filterLines[] = 'Item Code: ' . $request->input('item_code');
        }
        if ($request->filled('receipt_status')) {
            $filterLines[] = 'Receipt Status: ' . $request->input('receipt_status');
        }
        $filterLabel = count($filterLines) > 0 ? implode(' | ', $filterLines) : 'All sales';

        $setting = Setting::first();
        if (!$setting) {
            $setting = (object) ['nama_perusahaan' => 'Company', 'alamat' => ''];
        }

        $pdf = PDF::loadView('penjualan.sales_list_pdf', compact(
            'sales',
            'totalQuantity',
            'totalAmount',
            'totalDiscount',
            'filterLabel',
            'setting'
        ));

        $pdf->setPaper('a4', 'landscape');
        $filename = 'sales_list_' . date('Y-m-d') . '.pdf';
        return $pdf->stream($filename);
        } catch (\Throwable $e) {
            \Log::error('exportSalesListPdf: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            if (request()->expectsJson()) {
                return response()->json(['error' => 'Unable to generate PDF: ' . $e->getMessage()], 500);
            }
            abort(500, 'Unable to generate PDF: ' . (config('app.debug') ? $e->getMessage() : 'Please check storage/logs/laravel.log'));
        }
    }

    /**
     * Export sales list (filtered) as Excel. Uses same filters as PDF export.
     */
    public function exportSalesListExcel(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access the Sales List export.');
        }

        $query = Penjualan::with(['member', 'user'])
            ->orderBy('saledate', 'desc')
            ->orderBy('id_penjualan', 'desc');

        $this->applyNormalSalesListStatusScope($query);
        if (Schema::hasColumn('penjualan', 'sale_type')) {
            $query->where(function ($q) {
                $q->where('sale_type', 'normal')->orWhereNull('sale_type');
            });
        }

        $this->applySalesListDateFilters($query, $request);
        if ($request->filled('payment_mode')) {
            $query->where('payment_method', $request->input('payment_mode'));
        }
        if ($request->filled('receipt_number')) {
            $query->where('receiptno', 'like', '%' . $request->input('receipt_number') . '%');
        }
        if ($request->filled('item_code')) {
            $itemCode = trim((string) $request->input('item_code'));
            $query->whereIn('id_penjualan', function ($q) use ($itemCode) {
                $q->select('penjualan_detail.id_penjualan')
                    ->from('penjualan_detail')
                    ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                    ->where(function ($w) use ($itemCode) {
                        $w->where('produk.item_code', 'like', '%' . $itemCode . '%')
                            ->orWhere('produk.kode_produk', 'like', '%' . $itemCode . '%');
                    })
                    ->distinct();
            });
        }
        if ($request->filled('receipt_status') && Schema::hasColumn('penjualan', 'confirmation_status')) {
            $query->where('confirmation_status', $request->input('receipt_status'));
        }

        $sales = $query->limit(50000)->get();

        $saleIds = $sales->pluck('id_penjualan')->toArray();
        $shopCodesMap = [];
        if (!empty($saleIds) && Schema::hasTable('shops')) {
            try {
                $rows = DB::table('penjualan_detail')
                    ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                    ->leftJoin('shops', 'produk.shop_id', '=', 'shops.id')
                    ->whereIn('penjualan_detail.id_penjualan', $saleIds)
                    ->whereNotNull('shops.shop_code')
                    ->where('shops.shop_code', '!=', '')
                    ->select('penjualan_detail.id_penjualan', 'shops.shop_code')
                    ->distinct()
                    ->get();
                foreach ($rows as $row) {
                    $shopCodesMap[$row->id_penjualan][] = $row->shop_code;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales List');

        $headers = ['#', 'Date', 'Shop Codes', 'Receipt No', 'Qty', 'Total Price', 'Discount %', 'Total Pay', 'Payment', 'Receipt Status', 'Cashier'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        $row = 2;
        foreach ($sales as $index => $sale) {
            $shopCodes = isset($shopCodesMap[$sale->id_penjualan])
                ? implode(', ', array_unique($shopCodesMap[$sale->id_penjualan]))
                : 'N/A';
            $dateStr = $sale->saledate
                ? Carbon::parse($sale->saledate)->format('d/m/Y')
                : ($sale->created_at ? Carbon::parse($sale->created_at)->format('d/m/Y') : '');
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $dateStr);
            $sheet->setCellValue('C' . $row, $shopCodes);
            $sheet->setCellValue('D' . $row, $sale->receiptno ?? '');
            $sheet->setCellValue('E' . $row, (int) $sale->total_item);
            $sheet->setCellValue('F' . $row, (float) $sale->total_harga);
            $sheet->setCellValue('G' . $row, (float) ($sale->diskon ?? 0));
            $sheet->setCellValue('H' . $row, (float) $sale->bayar);
            $sheet->setCellValue('I' . $row, $sale->payment_method ?? 'Cash');
            $sheet->setCellValue('J' . $row, ucfirst($sale->confirmation_status ?? 'pending'));
            $sheet->setCellValue('K' . $row, optional($sale->user)->name ?? 'N/A');
            $row++;
        }

        foreach (range('A', 'K') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'sales_list_' . date('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function create()
    {
        // Check if day is opened
        if (!DailyCash::isTodayOpened()) {
            return redirect()->route('daily-cash.open-form')
                ->withErrors(['error' => 'Cash on Hand must be entered before sales can be recorded.']);
        }

        // Check if day is closed
        if (DailyCash::isTodayClosed()) {
            return redirect()->route('dashboard')
                ->withErrors(['error' => 'Today has already been closed. Please open a new day.']);
        }

        // Clean up incomplete sales from previous sessions (sales with no items or total = 0)
        $this->cleanupIncompleteSales();

        // Allocate receipt under lock so multiple cashiers never get the same receipt number
        $penjualan = $this->withReceiptAllocationLock('penjualan_receipt_utam_lock', function () {
            $receiptNo = $this->computeNextSequentialReceiptNo('U', 3, ['UTAM']);

            $p = new Penjualan();
            $p->total_item = 0;
            $p->total_harga = 0;
            $p->diskon = 0;
            $p->bayar = 0;
            $p->diterima = 0;
            $p->receiptno = $receiptNo;
            $p->saledate = Carbon::now()->toDateString();
            $p->id_user = auth()->id();
            $p->sale_type = 'normal';

            if (Schema::hasColumn('penjualan', 'status')) {
                $p->status = 'active';
            }
            $p->save();

            return $p;
        });

        session(['id_penjualan' => $penjualan->id_penjualan]);
        Cookie::queue('pos_last_sale', (string) $penjualan->id_penjualan, 60 * 24 * 7);

        return redirect()->route('transaksi.index');
    }

    /**
     * Create a management sale (for gifts, employee purchases, etc.)
     */
    public function createManagement()
    {
        // Allow any authenticated POS user (route is already protected by level middleware).
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized. Please log in to create management sales.');
        }

        // Check if day is opened
        if (!DailyCash::isTodayOpened()) {
            return redirect()->route('daily-cash.open-form')
                ->withErrors(['error' => 'Cash on Hand must be entered before sales can be recorded.']);
        }

        // Check if day is closed
        if (DailyCash::isTodayClosed()) {
            return redirect()->route('dashboard')
                ->withErrors(['error' => 'Today has already been closed. Please open a new day.']);
        }

        // Clean up incomplete sales from previous sessions
        $this->cleanupIncompleteSales();

        $penjualan = $this->withReceiptAllocationLock('penjualan_receipt_mgmt_lock', function () {
            $receiptNo = $this->computeNextSequentialReceiptNo('M');

            $p = new Penjualan();
            $p->total_item = 0;
            $p->total_harga = 0;
            $p->diskon = 0;
            $p->bayar = 0;
            $p->diterima = 0;
            $p->receiptno = $receiptNo;
            $p->saledate = Carbon::now()->toDateString();
            $p->id_user = auth()->id();
            $p->sale_type = 'management';

            if (Schema::hasColumn('penjualan', 'status')) {
                $p->status = 'active';
            }
            $p->save();

            return $p;
        });

        session(['id_penjualan' => $penjualan->id_penjualan]);
        Cookie::queue('pos_last_sale', (string) $penjualan->id_penjualan, 60 * 24 * 7);

        return redirect()->route('transaksi.index');
    }

    /**
     * Create a new sale (helper method)
     */
    private function createNewSale()
    {
        $penjualan = $this->withReceiptAllocationLock('penjualan_receipt_utam_lock', function () {
            $receiptNo = $this->computeNextSequentialReceiptNo('U', 3, ['UTAM']);

            $p = new Penjualan();
            $p->total_item = 0;
            $p->total_harga = 0;
            $p->diskon = 0;
            $p->bayar = 0;
            $p->diterima = 0;
            $p->receiptno = $receiptNo;
            $p->saledate = Carbon::now()->toDateString();
            $p->id_user = auth()->id();
            $p->sale_type = 'normal';

            if (Schema::hasColumn('penjualan', 'status')) {
                $p->status = 'active';
            }
            $p->save();

            return $p;
        });

        session(['id_penjualan' => $penjualan->id_penjualan]);
        Cookie::queue('pos_last_sale', (string) $penjualan->id_penjualan, 60 * 24 * 7);

        return $penjualan;
    }

    /**
     * Clean up abandoned empty sales for this user only.
     *
     * Important: Do NOT use total_item/total_harga on the penjualan row — those are often still 0 until
     * checkout even when the cart has line items. Only remove sales that have no penjualan_detail rows.
     * Previously this deleted every "incomplete" sale in the whole DB, breaking other cashiers' sessions.
     */
    private function cleanupIncompleteSales()
    {
        $uid = auth()->id();
        if (! $uid) {
            return;
        }

        $emptySales = Penjualan::query()
            ->where('id_user', $uid)
            ->whereDoesntHave('details')
            ->get();

        foreach ($emptySales as $sale) {
            $sale->delete();
        }
    }

    /**
     * Cleanup incomplete sale via AJAX
     */
    public function cleanupIncompleteSale(Request $request)
    {
        $idPenjualan = session('id_penjualan');
        
        if (!$idPenjualan) {
            return response()->json(['message' => 'No active sale found'], 200);
        }

        $penjualan = Penjualan::find($idPenjualan);
        
        if (!$penjualan) {
            return response()->json(['message' => 'Sale not found'], 200);
        }

        $lineCount = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->count();

        // Only remove if there are no cart lines (parent totals stay 0 until checkout)
        if ($lineCount === 0) {
            $penjualan->delete();
            session()->forget('id_penjualan');
            Cookie::queue(Cookie::forget('pos_last_sale'));

            return response()->json(['message' => 'Incomplete sale cleaned up'], 200);
        }

        return response()->json(['message' => 'Sale has items, not cleaned up'], 200);
    }

    public function store(Request $request)
    {
        // Check if there are any sale items first
        $detailCount = PenjualanDetail::where('id_penjualan', $request->id_penjualan)->count();
        
        // Get actual totals from the sale details if they weren't provided in the request
        $calculatedTotal = 0;
        $calculatedTotalItem = 0;
        
        if ($detailCount > 0) {
            $details = PenjualanDetail::where('id_penjualan', $request->id_penjualan)->get();
            foreach ($details as $detail) {
                $calculatedTotal += $detail->subtotal;
                $calculatedTotalItem += $detail->jumlah;
            }
        }
        
        // Use calculated values if request values are missing or zero
        $totalItem = $request->total_item ?? $calculatedTotalItem;
        $total = $request->total ?? $calculatedTotal;
        
        // Validate required fields (clear message if session sale was deleted — e.g. old bug or stale tab)
        try {
            $request->validate([
                'ReceiptNo' => 'required|string',
                'id_penjualan' => 'required|integer|exists:penjualan,id_penjualan',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'This sale is no longer valid (refresh the page or start a new sale from New Sale).',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        }
        
        if ($detailCount === 0 || $totalItem == 0 || $total <= 0) {
            if ($request->ajax()) {
                return response()->json([
                    'message' => 'Cannot save a sale without items. Please add at least one product.',
                    'errors' => ['items' => ['Please add at least one product to complete the sale.']]
                ], 422);
            }
            return redirect()->back()->withErrors(['items' => 'Please add at least one product to complete the sale.']);
        }

        // Use find() so a deleted/stale sale ID returns JSON 422 instead of ModelNotFound (500)
        $penjualan = Penjualan::find($request->id_penjualan);
        if (! $penjualan) {
            $msg = 'This sale is no longer valid (refresh the page or start a new sale from New Sale).';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => $msg,
                    'errors' => ['id_penjualan' => ['The selected id penjualan is invalid.']],
                ], 422);
            }

            return redirect()->back()->withErrors(['id_penjualan' => $msg]);
        }

        // When re-saving: if admin initiated edit for cashier, payment was already reversed at initiate time
        $wasEditInitiated = Schema::hasColumn('penjualan', 'edit_initiated_at') && !empty($penjualan->edit_initiated_at);
        $wasCompleted = !$wasEditInitiated && Schema::hasColumn('penjualan', 'status') && $penjualan->status === 'completed';
        $oldBayar = $penjualan->bayar;
        $oldPaymentMethod = $penjualan->payment_method ?? 'Cash';
        $oldPaymentSplitDetails = $penjualan->payment_split_details;

        $penjualan->id_member = $request->id_member ?? null;
        $penjualan->total_item = $totalItem;

        // Calculate and store discount
        // Discount percentage is applied to total payable (subtotal + VAT), not subtotal only.
        $diskonValue = 0;
        $discountType = 'percentage';
        $discountAmount = 0;
        $totalPayableBeforeDiscount = $total;

        if ($request->filled('diskon')) {
            $diskonInput = (float) str_replace(',', '', $request->diskon);
            $discountType = $request->discount_type ?? 'percentage';
            $bayar = (float) ($request->bayar ?? $total);

            if ($discountType === 'percentage') {
                $diskonValue = min(100, max(0, (int) round($diskonInput)));
                // Frontend sends total/bayar = amount after discount. Derive total payable: bayar = totalPayable * (1 - pct/100)
                $pctFactor = 1 - ($diskonValue / 100);
                if ($diskonValue > 0 && $pctFactor > 0 && $bayar > 0) {
                    $totalPayableBeforeDiscount = $bayar / $pctFactor;
                    $discountAmount = round($totalPayableBeforeDiscount * ($diskonValue / 100), 0);
                } elseif ($total > 0) {
                    $totalPayableBeforeDiscount = $total;
                    $discountAmount = round($totalPayableBeforeDiscount * ($diskonValue / 100), 0);
                }
            } else {
                $discountAmount = min($diskonInput, $bayar + $diskonInput);
                $totalPayableBeforeDiscount = $bayar + $discountAmount;
                $diskonValue = 0;
            }
        }

        $penjualan->total_harga = $totalPayableBeforeDiscount;
        $penjualan->diskon = $diskonValue;
        if (Schema::hasColumn('penjualan', 'discount_type')) {
            $penjualan->discount_type = $discountType;
        }
        if (Schema::hasColumn('penjualan', 'discount_amount')) {
            $penjualan->discount_amount = $discountAmount > 0 ? $discountAmount : null;
        }

        $penjualan->bayar = $request->bayar ?? $total;
        $penjualan->receiptno = $request->ReceiptNo;
        // Use the date the user chose (e.g. for backdated receipts); default to now
        $chosenDate = $this->parsePosSaleDateInput($request->input('saledate2'));
        $penjualan->created_at = $chosenDate;
        if (Schema::hasColumn('penjualan', 'saledate')) {
            $penjualan->saledate = $chosenDate->toDateString();
        }
        $penjualan->diterima = $request->diterima ?? 0;
        
        // Store payment method if column exists
        if (Schema::hasColumn('penjualan', 'payment_method')) {
            $paymentMethod = 'Cash'; // Default
            if ($request->filled('paymentMode')) {
                $paymentMethod = $request->paymentMode;
            } elseif ($request->filled('payment_method')) {
                $paymentMethod = $request->payment_method;
            }
            $penjualan->payment_method = $paymentMethod;
            
            // Handle split payment details
            if ($paymentMethod === 'Split' && Schema::hasColumn('penjualan', 'payment_split_details')) {
                $splitCash = floatval($request->input('splitCash', 0));
                $splitMpesa = floatval($request->input('splitMpesa', 0));
                $splitCard = floatval($request->input('splitCard', 0));
                
                // Validate split payment totals match amount due
                $splitTotal = $splitCash + $splitMpesa + $splitCard;
                $amountDue = floatval($penjualan->bayar);
                
                if (abs($splitTotal - $amountDue) >= 0.01) {
                    if ($request->ajax()) {
                        return response()->json([
                            'message' => 'Split payment total (Ksh ' . number_format($splitTotal, 2) . ') must equal the amount due (Ksh ' . number_format($amountDue, 2) . ').',
                            'errors' => ['split_payment' => ['Split payment total does not match amount due.']]
                        ], 422);
                    }
                    return redirect()->back()->withErrors(['split_payment' => 'Split payment total does not match amount due.']);
                }
                
                // Store split payment details as JSON
                $splitDetails = [
                    'cash' => $splitCash,
                    'mpesa' => $splitMpesa,
                    'card' => $splitCard
                ];
                $penjualan->payment_split_details = json_encode($splitDetails);
            }
            
            \Log::info('Payment method being saved:', [
                'paymentMode' => $request->input('paymentMode'),
                'payment_method' => $request->input('payment_method'),
                'final_value' => $paymentMethod,
                'penjualan_id' => $penjualan->id_penjualan,
                'split_details' => $paymentMethod === 'Split' ? $penjualan->payment_split_details : null
            ]);
        } else {
            \Log::warning('payment_method column does not exist in penjualan table');
        }

        // One atomic transaction: if anything fails after this point, no partial sale (avoids "printed receipt but not in sales list").
        $ledgerReconciliation = null;
        DB::beginTransaction();
        try {
        $ensureLedger = app(EnsureSaleSupplierLedgerService::class);

        // Store currency type if column exists
        if (Schema::hasColumn('penjualan', 'currency_type')) {
            $currencyType = $request->input('currency_type');
            if ($currencyType) {
                $penjualan->currency_type = $currencyType;
            }
        }
        
        // Calculate and store tax (16% VAT inclusive)
        $taxAmount = 0;
        if ($request->filled('tax')) {
            $taxAmount = floatval($request->tax);
        } else {
            // Calculate tax if not provided (16% inclusive)
            $taxAmount = round($penjualan->bayar * (16 / 116), 2);
        }
        
        // Store tax if column exists
        if (Schema::hasColumn('penjualan', 'tax')) {
            $penjualan->tax = $taxAmount;
        }
        
        // Calculate and store driver commission (from subtotal before VAT)
        // Commission is calculated from subtotal before VAT
        // Example: Total payable = 1600, VAT = 221, Subtotal = 1379, Commission = 10% of 1379 = 137.9
        $driverCommission = 0;
        
        // Calculate subtotal before VAT: bayar (total payable) - tax
        // bayar is the total payable amount (after discount if any), which includes VAT
        $subtotalBeforeVat = $penjualan->bayar - $taxAmount;
        $setting = Setting::first();
        $commissionRate = $setting->driver_commission_rate ?? 0;
        
        \Log::info('Calculating driver commission:', [
            'bayar' => $penjualan->bayar,
            'tax' => $taxAmount,
            'subtotal_before_vat' => $subtotalBeforeVat,
            'commission_rate' => $commissionRate
        ]);
        
        if ($commissionRate > 0 && $subtotalBeforeVat > 0) {
            $driverCommission = round($subtotalBeforeVat * ($commissionRate / 100), 2);
            \Log::info('Calculated driver commission: ' . $driverCommission);
        } else {
            \Log::warning('Commission not calculated. Rate: ' . $commissionRate . ', Subtotal: ' . $subtotalBeforeVat);
        }
        
        // Always try to save commission - use both Eloquent and direct DB update
        try {
            if (Schema::hasColumn('penjualan', 'driver_commission')) {
                $penjualan->driver_commission = $driverCommission;
            }
        } catch (\Exception $e) {
            \Log::warning('Could not set via Eloquent: ' . $e->getMessage());
        }
        
        // Also try direct DB update as fallback
        if (Schema::hasColumn('penjualan', 'driver_commission')) {
            try {
                DB::table('penjualan')
                    ->where('id_penjualan', $penjualan->id_penjualan)
                    ->update(['driver_commission' => $driverCommission]);
                \Log::info('Updated commission via direct DB query: ' . $driverCommission);
            } catch (\Exception $e) {
                \Log::error('Direct DB update failed: ' . $e->getMessage());
            }
        } else {
            \Log::error('driver_commission column does NOT exist in penjualan table!');
        }
        
        $penjualan->update();
        
        // Verify commission was saved by refreshing and checking
        $penjualan->refresh();
        $savedCommission = $penjualan->driver_commission ?? 0;
        \Log::info('Commission after save: ' . $savedCommission);
        
        if ($savedCommission == 0 && $driverCommission > 0) {
            \Log::error('Commission was calculated (' . $driverCommission . ') but not saved!');
        }
        
        // Stock validation disabled - allowing items to be sold even when out of stock
        // Validate stock availability before finalizing sale (skip stock check for quick-added/incomplete items; admin adjusts quantity later)
        // COMMENTED OUT: Stock check disabled to allow selling out-of-stock items
        /*
        $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)
                    ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                    ->get();
        
        $outOfStockItems = [];
        foreach ($details as $detail) {
            $produk = Produk::find($detail->id_produk);
            if (!$produk) {
                $outOfStockItems[] = "Product ID {$detail->id_produk} not found";
                continue;
            }
            // Skip stock check for quick-added (incomplete) products; admin will adjust quantity later
            if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
                continue;
            }
            if ($produk->stok < $detail->jumlah) {
                $outOfStockItems[] = "{$produk->nama_produk} (Code: {$produk->kode_produk}): Available: {$produk->stok}, Requested: {$detail->jumlah}";
            }
        }
        
        if (!empty($outOfStockItems)) {
            $errorMessage = "Cannot complete sale. Insufficient stock for the following products:\n" . implode("\n", $outOfStockItems);
            if ($request->ajax()) {
                return response()->json([
                    'message' => $errorMessage,
                    'errors' => ['stock' => $outOfStockItems]
                ], 422);
            }
            return redirect()->back()->withErrors(['stock' => $errorMessage]);
        }
        */
        
        // Get details for grouping by supplier (stock validation removed)
        $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)
                    ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
                    ->get();
        
        // Group by supplier
        $details = $details->groupBy('produk.id_supplier');
        
        // If this sale was previously completed (being re-completed after edit),
        // we need to handle existing invoice items intelligently (update instead of duplicate)
        $saleDate = $penjualan->saledate ?? $penjualan->created_at->toDateString();
        // Align consignment rows with business sale day (saledate), not the completion clock time,
        // so supplier consignment date filters match POS / penjualan reports.
        $saleDateTime = Carbon::parse($saleDate)->startOfDay();
        $deferLedgerUntilConfirmed = $ensureLedger->saleDefersLedgerUntilConfirmed($penjualan);

        // Get ALL current product IDs across all suppliers for orphaned items cleanup
        $allCurrentProductIds = $details->flatten()->pluck('id_produk')->unique()->toArray();
        
        // Also delete any Pembelian records created for cash suppliers from this sale
        // (these will be recreated if needed)
        if (($wasCompleted || $wasEditInitiated)) {
            $currentProductIds = $details->flatten()->pluck('id_produk')->unique()->toArray();
            $pembelians = Pembelian::whereDate('purchasedate2', $saleDate)
                ->whereHas('details', function($query) use ($currentProductIds) {
                    $query->whereIn('id_produk', $currentProductIds);
                })
                ->get();
            
            foreach ($pembelians as $pembelian) {
                // Only delete if it was auto-created from a sale (has matching products and date)
                $pembelian->details()->delete();
                $pembelian->delete();
            }
        }

        foreach ($details as $supplierId => $items) {
            $supplier = Supplier::find($supplierId);
            $isCashSupplier = $ensureLedger->isCashSupplier($supplier);

            // Do not create invoice or consignment (InvoiceItem) for Cash suppliers - they go to Pembelian only
            if (!$isCashSupplier) {
                $hasConsignmentLinesToPost = ! $deferLedgerUntilConfirmed || $items->contains(function ($line) {
                    return ($line->item_confirmation_status ?? 'pending') === 'confirmed';
                });

                if ($hasConsignmentLinesToPost) {
                // Find or create invoice for this supplier
                // If re-completing, try to find existing invoice from the same sale date
                $invoice = null;
                if ($wasCompleted || $wasEditInitiated) {
                    $invoice = Invoice::where('id_supplier', $supplierId)
                        ->whereDate('created_at', $saleDate)
                        ->first();
                }
                
                if (!$invoice) {
                    $invoice = new Invoice();
                    $invoice->total = 0;
                    $invoice->id_supplier = $supplierId;
                    $invoice->created_at = $saleDateTime;
                    $invoice->updated_at = $saleDateTime;
                    $invoice->save();
                } else {
                    // Reset total for existing invoice - we'll recalculate
                    $invoice->total = 0;
                    $invoice->save();
                }
                }
            }

            foreach ($items as $item) {
                // Update PenjualanDetail
                // Note: penjualan_detail.diskon is tinyInteger (0-100) for percentage only
                // Don't update item-level diskon from sale-level discount (which could be fixed amount)
                // Item-level diskon should remain as the product's original discount percentage
                // The sale-level discount is already applied in the penjualan table
                // $item->diskon should not be changed here as it's already set per product
                // $item->diskon = $request->diskon; // REMOVED: This was causing out of range errors
                // No need to update diskon at item level - it's already set correctly per product

                // Update Produk stok
                $produk = Produk::find($item->id_produk);
                if (! $produk) {
                    throw new \RuntimeException('Product ID '.$item->id_produk.' is missing; cannot complete sale.');
                }
                $produk->stok -= $item->jumlah;
                $produk->update();

                // Do not create consignment or cash entries for quick-added (incomplete) items until admin has updated the product
                if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
                    continue;
                }

                // Do not create consignment (InvoiceItem) for Cash suppliers - they appear in Pembelian only, not consignment list
                if ($isCashSupplier) {
                    continue;
                }

                if ($deferLedgerUntilConfirmed && ($item->item_confirmation_status ?? 'pending') !== 'confirmed') {
                    continue;
                }

                // Calculate amount for the invoice item
                $amount = $item->jumlah * $produk->harga_beli;
                
                // If re-completing, try to find and update existing invoice item
                $invoiceItem = null;
                if ($wasCompleted || $wasEditInitiated) {
                    // Find existing unpaid invoice item for this product/supplier from this sale
                    $saleCreatedAt = $penjualan->created_at ?? Carbon::parse($saleDate);
                    $timeWindowStart = $saleCreatedAt->copy()->subDays(1)->startOfDay();
                    $timeWindowEnd = Carbon::now()->endOfDay();

                    $existingItemBase = function () use ($item, $supplierId, $saleDate, $timeWindowStart, $timeWindowEnd) {
                        return InvoiceItem::where('produk_id', $item->id_produk)
                            ->where('supplier_id', $supplierId)
                            ->where(function ($query) use ($saleDate, $timeWindowStart, $timeWindowEnd) {
                                $query->whereDate('created_at', $saleDate)
                                    ->orWhereBetween('created_at', [$timeWindowStart, $timeWindowEnd]);
                            })
                            ->where(function ($query) {
                                $query->where('amount_paid', 0)
                                    ->orWhereNull('amount_paid')
                                    ->orWhereRaw('COALESCE(amount_paid, 0) = 0');
                            })
                            ->whereRaw('COALESCE(balance, amount) = amount');
                    };

                    $existingItem = null;
                    if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
                        $existingItem = $existingItemBase()
                            ->where('penjualan_id', $penjualan->id_penjualan)
                            ->orderBy('id')
                            ->first();
                    } else {
                        // Legacy DB: no sale linkage — keep narrow time/supplier match only.
                        $existingItem = $existingItemBase()->orderBy('id')->first();
                    }

                    if ($existingItem) {
                        // Update existing invoice item instead of creating new one
                        $invoiceItem = $existingItem;
                        $oldInvoiceId = $invoiceItem->invoice_id;
                        $invoiceItem->invoice_id = $invoice->id; // Update to current invoice
                        $invoiceItem->quantity = $item->jumlah;
                        $invoiceItem->amount = $amount;
                        $invoiceItem->balance = $amount; // Reset balance since it's unpaid
                        $invoiceItem->discount = $item->discount ?? 0;
                        $invoiceItem->status = 'Not paid';
                        if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
                            $invoiceItem->penjualan_id = $penjualan->id_penjualan;
                        }
                        $invoiceItem->save();
                        
                        // Delete any other duplicate unpaid invoice items for this product
                        // (in case there are multiple from previous completions)
                        $duplicateQuery = InvoiceItem::where('produk_id', $item->id_produk)
                            ->where('supplier_id', $supplierId)
                            ->where('id', '!=', $invoiceItem->id) // Exclude the one we just updated
                            ->where(function ($query) {
                                $query->where('amount_paid', 0)
                                    ->orWhereNull('amount_paid')
                                    ->orWhereRaw('COALESCE(amount_paid, 0) = 0');
                            })
                            ->whereRaw('COALESCE(balance, amount) = amount');
                        if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
                            $duplicateQuery->where('penjualan_id', $penjualan->id_penjualan);
                        } else {
                            $duplicateQuery->where(function ($query) use ($saleDate, $timeWindowStart, $timeWindowEnd) {
                                $query->whereDate('created_at', $saleDate)
                                    ->orWhereBetween('created_at', [$timeWindowStart, $timeWindowEnd]);
                            });
                        }
                        $duplicateItems = $duplicateQuery->get();
                        
                        foreach ($duplicateItems as $duplicate) {
                            $dupInvoice = Invoice::find($duplicate->invoice_id);
                            if ($dupInvoice) {
                                $dupInvoice->total = max(0, $dupInvoice->total - $duplicate->amount);
                                $dupInvoice->save();
                            }
                            $duplicate->delete();
                        }
                        
                        // Update old invoice total if it changed
                        if ($oldInvoiceId != $invoice->id) {
                            $oldInvoice = Invoice::find($oldInvoiceId);
                            if ($oldInvoice) {
                                $oldInvoice->total = max(0, $oldInvoice->total - $existingItem->amount);
                                $oldInvoice->save();
                            }
                        }
                        
                        \Log::info('Updated existing invoice item for re-completed sale', [
                            'penjualan_id' => $penjualan->id_penjualan,
                            'invoice_item_id' => $invoiceItem->id,
                            'produk_id' => $item->id_produk,
                            'old_quantity' => $existingItem->quantity,
                            'new_quantity' => $item->jumlah,
                            'old_amount' => $existingItem->amount,
                            'new_amount' => $amount,
                            'duplicates_deleted' => $duplicateItems->count()
                        ]);
                    }
                }
                
                // If no existing item found, create only when sale qty is not already on consignment ledger
                if (! $invoiceItem) {
                    $diskon = (int) ($item->diskon ?? $item->discount ?? 0);
                    if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
                        $remaining = $ensureLedger->remainingConsignmentQtyToPost(
                            (int) $penjualan->id_penjualan,
                            (int) $item->id_produk,
                            (int) $supplierId,
                            $penjualan
                        );
                        if ($remaining <= 0) {
                            $invoiceItem = InvoiceItem::query()
                                ->where('penjualan_id', $penjualan->id_penjualan)
                                ->where('produk_id', $item->id_produk)
                                ->where('supplier_id', $supplierId)
                                ->orderBy('id')
                                ->first();
                        } else {
                            $posted = $ensureLedger->createConsignmentInvoiceItemIfNeeded(
                                $penjualan,
                                $produk,
                                $supplier,
                                (int) $item->jumlah,
                                $diskon
                            );
                            if ($posted) {
                                $invoiceItem = $posted;
                                $amount = (float) $posted->amount;
                            }
                        }
                    } else {
                        $invoiceItem = new InvoiceItem();
                        $invoiceItem->uniqid = uniqid();
                        $invoiceItem->produk_id = $item->id_produk;
                        $invoiceItem->supplier_id = $supplierId;
                        $invoiceItem->invoice_id = $invoice->id;
                        $invoiceItem->status = 'Not paid';
                        $invoiceItem->discount = $diskon;
                        $invoiceItem->balance = $amount;
                        $invoiceItem->quantity = $item->jumlah;
                        $invoiceItem->amount = $amount;
                        $invoiceItem->created_at = $saleDateTime;
                        $invoiceItem->updated_at = $saleDateTime;
                        $invoiceItem->save();
                    }
                }

                // Update invoice total (guard path may have updated a different daily invoice)
                if ($invoiceItem && (int) $invoiceItem->invoice_id === (int) $invoice->id) {
                    $invoice->total += $amount;
                }
            }

            if (!$isCashSupplier && isset($invoice) && $invoice) {
                // Update invoice total
                $invoice->save();

                // Orphan cleanup runs once after all suppliers (penjualan_id scope). Per-supplier
                // cleanup missed rows whose supplier_id differs from produk.id_supplier (e.g. reference consignment).
            }

            // Check if supplier is Cash and create Pembelian record (same rules as EnsureSaleSupplierLedgerService)
            // Exclude quick-added (incomplete) items until admin has updated the product
            if ($supplier && $isCashSupplier) {
                // Calculate total for this supplier's items (only non-incomplete products)
                $totalHarga = 0;
                $totalItem = 0;
                $pembelianDetails = [];

                foreach ($items as $item) {
                    $produk = Produk::find($item->id_produk);
                    if (!$produk) {
                        continue;
                    }
                    if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
                        continue; // skip quick-add; will appear in cash after admin updates product
                    }
                    if ($deferLedgerUntilConfirmed && ($item->item_confirmation_status ?? 'pending') !== 'confirmed') {
                        continue;
                    }
                    $subtotal = $item->jumlah * $produk->harga_beli;
                    $totalHarga += $subtotal;
                    $totalItem += $item->jumlah;

                    $pembelianDetails[] = [
                        'produk' => $produk,
                        'jumlah' => $item->jumlah,
                        'harga_beli' => $produk->harga_beli,
                        'subtotal' => $subtotal,
                    ];
                }

                if ($totalHarga > 0) {
                    // Create Pembelian record (outstanding - bayar = 0)
                    $pembelian = Pembelian::create([
                        'id_supplier' => $supplierId,
                        'total_item' => $totalItem,
                        'total_harga' => $totalHarga,
                        'reorder' => 0,
                        'bayar' => 0, // Outstanding - will be paid later
                        'purchasedate2' => $penjualan->saledate ?? now()->toDateString(),
                        'created_at' => $saleDateTime,
                        'updated_at' => $saleDateTime,
                    ]);

                    // Create PembelianDetail records
                    foreach ($pembelianDetails as $detail) {
                        PembelianDetail::create([
                            'id_pembelian' => $pembelian->id_pembelian,
                            'id_produk' => $detail['produk']->id_produk,
                            'harga_beli' => $detail['harga_beli'],
                            'jumlah' => $detail['jumlah'],
                            'subtotal' => $detail['subtotal'],
                        ]);
                    }

                    \Log::info('Auto-created Pembelian for cash supplier from sale', [
                        'penjualan_id' => $penjualan->id_penjualan,
                        'supplier_id' => $supplierId,
                        'pembelian_id' => $pembelian->id_pembelian,
                        'total_harga' => $totalHarga,
                    ]);
                }
            }
        }
        
        // After processing all suppliers: remove unpaid consignment lines for this sale whose
        // product is no longer on the receipt. Scope by penjualan_id only (no supplier filter,
        // no created_at window) so rows stay aligned after edits/backfills even when supplier_id
        // differs from produk.id_supplier (e.g. reference consignment).
        if (($wasCompleted || $wasEditInitiated) && ! empty($allCurrentProductIds)) {
            if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
                $allOrphanedItems = InvoiceItem::where('penjualan_id', $penjualan->id_penjualan)
                    ->whereNotIn('produk_id', $allCurrentProductIds)
                    ->where(function ($query) {
                        $query->where('amount_paid', 0)
                            ->orWhereNull('amount_paid')
                            ->orWhereRaw('COALESCE(amount_paid, 0) = 0');
                    })
                    ->whereRaw('COALESCE(balance, amount) = amount')
                    ->get();
            } else {
                \Log::warning('Skipping orphan consignment cleanup: invoice_items.penjualan_id is missing', [
                    'penjualan_id' => $penjualan->id_penjualan,
                ]);
                $allOrphanedItems = collect();
            }

            \Log::info('Final cleanup: orphaned consignment invoice items for this sale', [
                'penjualan_id' => $penjualan->id_penjualan,
                'current_product_ids' => $allCurrentProductIds,
                'orphaned_items_found' => $allOrphanedItems->count(),
            ]);
            
            // Delete all orphaned items
            foreach ($allOrphanedItems as $orphanedItem) {
                // Double-check: Make sure this product is really not in current sale
                $isStillInSale = in_array($orphanedItem->produk_id, $allCurrentProductIds);
                if ($isStillInSale) {
                    \Log::warning('Skipping deletion in final cleanup - product still in sale', [
                        'penjualan_id' => $penjualan->id_penjualan,
                        'invoice_item_id' => $orphanedItem->id,
                        'produk_id' => $orphanedItem->produk_id
                    ]);
                    continue;
                }
                
                $orphanedInvoice = Invoice::find($orphanedItem->invoice_id);
                if ($orphanedInvoice) {
                    $orphanedInvoice->total = max(0, $orphanedInvoice->total - $orphanedItem->amount);
                    $orphanedInvoice->save();
                    
                    // If invoice becomes empty, delete it
                    $remainingItems = InvoiceItem::where('invoice_id', $orphanedInvoice->id)->count();
                    if ($orphanedInvoice->total == 0 && $remainingItems == 0) {
                        $orphanedInvoice->delete();
                        \Log::info('Deleted empty invoice in final cleanup', [
                            'invoice_id' => $orphanedInvoice->id
                        ]);
                    }
                }
                
                $orphanedItem->delete();
                \Log::info('Deleted orphaned invoice item in final cleanup (removed from sale)', [
                    'penjualan_id' => $penjualan->id_penjualan,
                    'invoice_item_id' => $orphanedItem->id,
                    'produk_id' => $orphanedItem->produk_id,
                    'supplier_id' => $orphanedItem->supplier_id,
                    'invoice_id' => $orphanedItem->invoice_id,
                    'amount' => $orphanedItem->amount,
                    'quantity' => $orphanedItem->quantity
                ]);
            }
            
            \Log::info('Final cleanup completed', [
                'penjualan_id' => $penjualan->id_penjualan,
                'items_deleted' => $allOrphanedItems->count()
            ]);
            
            // Removed broad "aggressive" cleanup: it could delete valid backfilled rows from other
            // sales when suppliers/products overlapped within the time window.
        }

        // Mark sale as completed; clear edit_initiated_at if this was an initiated edit
        if (Schema::hasColumn('penjualan', 'status')) {
            $penjualan->status = 'completed';
            if (Schema::hasColumn('penjualan', 'edit_initiated_at')) {
                $penjualan->edit_initiated_at = null;
            }
            $penjualan->update();
            
            // Set confirmation_status for new completed sales
            if (Schema::hasColumn('penjualan', 'confirmation_status') && !$wasCompleted) {
                // Check if receipt has multiple different items
                $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
                $uniqueProducts = $details->pluck('id_produk')->unique()->count();
                
                // If more than one different item, set to review status
                if ($uniqueProducts > 1) {
                    DB::statement("UPDATE penjualan SET confirmation_status = 'review' WHERE id_penjualan = ?", [$penjualan->id_penjualan]);
                } else {
                    DB::statement("UPDATE penjualan SET confirmation_status = 'pending' WHERE id_penjualan = ?", [$penjualan->id_penjualan]);
                }
            }
        }

        // Add sale amount to corresponding account based on payment method
        // Skip account update for management sales (gifts, employee purchases, etc.)
        $saleType = $penjualan->sale_type ?? 'normal';
        if ($saleType !== 'management') {
            try {
                // When re-saving an edited (previously completed) sale, reverse the old amount first
                if ($wasCompleted && $oldBayar > 0) {
                    if ($oldPaymentMethod === 'Split' && $oldPaymentSplitDetails) {
                        $oldSplit = json_decode($oldPaymentSplitDetails, true);
                        if ($oldSplit) {
                            foreach (['Cash' => $oldSplit['cash'] ?? 0, 'Mpesa' => $oldSplit['mpesa'] ?? 0, 'Card' => $oldSplit['card'] ?? 0] as $accountName => $amount) {
                                if ($amount > 0) {
                                    $account = Account::getByName($accountName);
                                    if ($account) {
                                        $account->deductAmount($amount);
                                        \Log::info('Edit sale: reversed split amount from account:', [
                                            'account' => $accountName, 'amount' => $amount, 'penjualan_id' => $penjualan->id_penjualan
                                        ]);
                                    }
                                }
                            }
                        }
                    } else {
                        $account = Account::getByName($oldPaymentMethod);
                        if ($account) {
                            $account->deductAmount($oldBayar);
                            \Log::info('Edit sale: reversed amount from account:', [
                                'account' => $oldPaymentMethod, 'amount' => $oldBayar, 'penjualan_id' => $penjualan->id_penjualan
                            ]);
                        }
                    }
                }

                $paymentMethod = $penjualan->payment_method ?? 'Cash';
                
                // Handle split payments
                if ($paymentMethod === 'Split' && Schema::hasColumn('penjualan', 'payment_split_details') && $penjualan->payment_split_details) {
                    $splitDetails = json_decode($penjualan->payment_split_details, true);
                    
                    if ($splitDetails) {
                        // Update each account for split payment
                        $accountsToUpdate = [
                            'Cash' => floatval($splitDetails['cash'] ?? 0),
                            'Mpesa' => floatval($splitDetails['mpesa'] ?? 0),
                            'Card' => floatval($splitDetails['card'] ?? 0)
                        ];
                        
                        foreach ($accountsToUpdate as $accountName => $amount) {
                            if ($amount > 0) {
                                $account = Account::getByName($accountName);
                                if ($account) {
                                    $account->addAmount($amount);
                                    \Log::info('Split payment amount added to account:', [
                                        'account' => $accountName,
                                        'amount' => $amount,
                                        'new_balance' => $account->balance,
                                        'penjualan_id' => $penjualan->id_penjualan
                                    ]);
                                } else {
                                    \Log::warning('Account not found for split payment:', [
                                        'account_name' => $accountName,
                                        'penjualan_id' => $penjualan->id_penjualan
                                    ]);
                                }
                            }
                        }
                    }
                } else {
                    // Single payment method
                    $account = Account::getByName($paymentMethod);
                    
                    if ($account) {
                        $account->addAmount($penjualan->bayar);
                        \Log::info('Sale amount added to account:', [
                            'account' => $paymentMethod,
                            'amount' => $penjualan->bayar,
                            'new_balance' => $account->balance,
                            'penjualan_id' => $penjualan->id_penjualan
                        ]);
                    } else {
                        \Log::warning('Account not found for payment method:', [
                            'payment_method' => $paymentMethod,
                            'penjualan_id' => $penjualan->id_penjualan
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error adding sale amount to account: ' . $e->getMessage());
                // Don't fail the sale if account update fails
            }
        } else {
            \Log::info('Management sale completed - skipping account update:', [
                'penjualan_id' => $penjualan->id_penjualan,
                'receiptno' => $penjualan->receiptno,
                'amount' => $penjualan->bayar
            ]);
        }

        // Verify consignment invoice_items vs penjualan_detail; multi-pass backfill + cash pembelian safety net; audit log.
        $ledgerReconciliation = $ensureLedger->ensureConsignmentLedgerCompleteOnSaleComplete($penjualan, false);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Penjualan store failed (rolled back): '.$e->getMessage(), [
                'id_penjualan' => $request->input('id_penjualan'),
                'trace' => $e->getTraceAsString(),
            ]);
            if ($request->ajax() || $request->wantsJson()) {
                $detailMsg = config('app.debug') ? $e->getMessage() : 'If this keeps happening, contact support.';

                return response()->json([
                    'message' => 'Sale could not be completed. Nothing was saved. Please try again.',
                    'errors' => ['sale' => [$detailMsg]],
                ], 500);
            }

            return redirect()->back()->withErrors(['error' => 'Sale could not be completed. Please try again.']);
        }

        if ($request->ajax()) {
            $json = [
                'success' => true,
                'id_penjualan' => $penjualan->id_penjualan,
                // POS cashiers use level:1,2 routes; auto_print requires sales.read
                'print_url' => route('transaksi.nota_kecil', $penjualan->id_penjualan),
            ];
            if (is_array($ledgerReconciliation)) {
                $json['ledger_reconciliation'] = $ledgerReconciliation;
                $backfilled = (int) ($ledgerReconciliation['invoice_items_created_total'] ?? 0);
                $cashHdr = (int) ($ledgerReconciliation['cash_pembelian_created'] ?? 0);
                if ($backfilled > 0 || $cashHdr > 0) {
                    $json['ledger_notice'] = 'Supplier ledger was updated automatically ('.$backfilled.' consignment line(s)'
                        .($cashHdr > 0 ? ', '.$cashHdr.' cash purchase header(s)' : '')
                        .').';
                }
                if (empty($ledgerReconciliation['coverage_ok'])) {
                    $json['ledger_warning'] = 'Some consignment lines could not be posted to the supplier ledger (incomplete product, missing buying price, or suppressed line). Ask admin to check this receipt: '.($penjualan->receiptno ?? '');
                }
            }

            return response()->json($json);
        }

        // Receipt view on POS route (same permission as completing a sale)
        return redirect()->route('transaksi.nota_kecil', $penjualan->id_penjualan);
    }

    public function suspend(Request $request)
    {
        try {
            // POS safety: if frontend didn't send id_penjualan, fallback to session cart id
            $idFromRequest = $request->input('id_penjualan');
            $idFromSession = session('id_penjualan');
            $idPenjualan = $idFromRequest ?: $idFromSession;

            if (! $idPenjualan) {
                return response()->json([
                    'message' => 'This sale is no longer valid (refresh the page or start a new sale from New Sale).',
                    'errors' => ['id_penjualan' => ['The selected id penjualan is invalid.']],
                ], 422);
            }

            $request->merge(['id_penjualan' => $idPenjualan]);

            $request->validate([
                'id_penjualan' => 'required|integer|exists:penjualan,id_penjualan',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'This sale is no longer valid (refresh the page or start a new sale from New Sale).',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        $penjualan = Penjualan::find($request->id_penjualan);
        if (! $penjualan) {
            $msg = 'This sale is no longer valid (refresh the page or start a new sale from New Sale).';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => $msg,
                    'errors' => ['id_penjualan' => ['The selected id penjualan is invalid.']],
                ], 422);
            }

            return redirect()->back()->withErrors(['id_penjualan' => $msg]);
        }
        
        // Check if sale has items
        $detailCount = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->count();
        
        if ($detailCount === 0) {
            if ($request->ajax()) {
                return response()->json([
                    'message' => 'Cannot suspend a sale without items.',
                    'errors' => ['items' => ['Please add at least one product to suspend the sale.']]
                ], 422);
            }
            return redirect()->back()->withErrors(['items' => 'Please add at least one product to suspend the sale.']);
        }

        // Save current sale state (use same discount logic as store for consistency)
        $penjualan->total_item = $request->total_item ?? 0;
        $penjualan->total_harga = $request->total ?? 0;
        $discountType = $request->discount_type ?? 'percentage';
        $diskonInput = (float) str_replace(',', '', $request->diskon ?? 0);
        $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        $totalPayable = $request->total ?? $penjualan->total_harga ?? 0;
        if ($totalPayable <= 0 && $details->isNotEmpty()) {
            $totalPayable = $details->sum('subtotal');
        }
        if ($discountType === 'percentage') {
            $penjualan->diskon = min(100, max(0, (int) round($diskonInput)));
            if (Schema::hasColumn('penjualan', 'discount_amount')) {
                $penjualan->discount_amount = $totalPayable > 0 ? round($totalPayable * ($penjualan->diskon / 100), 0) : null;
            }
        } else {
            $penjualan->diskon = 0;
            if (Schema::hasColumn('penjualan', 'discount_amount')) {
                $penjualan->discount_amount = min($diskonInput, $totalPayable);
            }
        }
        if (Schema::hasColumn('penjualan', 'discount_type')) {
            $penjualan->discount_type = $discountType;
        }
        $penjualan->bayar = $request->bayar ?? 0;
        $penjualan->diterima = $request->diterima ?? 0;
        
        // Store payment method if provided and column exists
        if (Schema::hasColumn('penjualan', 'payment_method')) {
            if ($request->filled('paymentMode')) {
                $penjualan->payment_method = $request->paymentMode;
            } elseif ($request->filled('payment_method')) {
                $penjualan->payment_method = $request->payment_method;
            }
        }
        
        // Mark as suspended so it appears in suspended sales list
        if (Schema::hasColumn('penjualan', 'status')) {
            $penjualan->status = 'suspended';
        }
        $penjualan->update();

        // Clear session to allow starting a new sale
        session()->forget('id_penjualan');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Sale suspended successfully. Starting a new sale...'
            ]);
        }
    
        return redirect()->route('transaksi.baru')->with('success', 'Sale suspended successfully.');
    }

    public function unsuspend($id)
    {
        $penjualan = Penjualan::findOrFail($id);
        
        if (!Schema::hasColumn('penjualan', 'status')) {
            return redirect()->back()->withErrors(['error' => 'Suspend feature is not available. Please run migration first.']);
        }
        
        if ($penjualan->status !== 'suspended') {
            return redirect()->back()->withErrors(['error' => 'This sale is not suspended.']);
        }

        // Activate the suspended sale
        $penjualan->status = 'active';
        $penjualan->update();

        // Set session to continue with this sale
        session(['id_penjualan' => $penjualan->id_penjualan]);

        // Pass resume ID in URL so POS loads even if session doesn't persist across redirect (e.g. LAN access)
        return redirect()->route('transaksi.index', ['resume' => $penjualan->id_penjualan])->with('success', 'Sale unsuspended. You can continue with the sale.');
    }

    /**
     * Re-open a completed sale for editing (admin only).
     * Sets sale to active, restores stock for current items, redirects to POS.
     */
    public function editSale($id)
    {
        if (!auth()->user()) {
            abort(403, 'Unauthorized. Please log in to edit sales.');
        }

        if (!Schema::hasColumn('penjualan', 'status')) {
            return redirect()->back()->withErrors(['error' => 'Edit sale is not available.']);
        }

        $penjualan = Penjualan::findOrFail($id);
        if ($this->saleIsAwaitingEditCompletion($penjualan)) {
            return redirect()->route('penjualan.initiated_edits')
                ->withErrors(['error' => 'This sale is already open for editing. Use Continue edit on that page, then complete in POS.']);
        }
        if ($penjualan->status !== 'completed') {
            return redirect()->back()->withErrors(['error' => 'Only completed sales can be edited.']);
        }

        try {
            $stripped = app(SaleSupplierLedgerRemovalService::class)->removeAllUnpaidConsignmentInvoiceItemsForPenjualan($penjualan);
            if ($stripped > 0) {
                \Log::info('Stripped unpaid consignment invoice_items when reopening sale for direct edit', [
                    'penjualan_id' => $penjualan->id_penjualan,
                    'receiptno' => $penjualan->receiptno,
                    'invoice_items_removed' => $stripped,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error stripping consignment when reopening sale for edit: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'Could not clear consignment lines for this edit. Please try again.']);
        }

        // Restore stock for all items in this sale so re-saving does not double-deduct
        $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        foreach ($details as $item) {
            $produk = Produk::find($item->id_produk);
            if ($produk) {
                $produk->stok += $item->jumlah;
                $produk->update();
            }
        }

        // Remove Pembelian records (cash purchases) created from this sale
        // These appear in the cash generated sales list and should be removed when editing
        try {
            $saleDate = Carbon::parse($penjualan->created_at)->format('Y-m-d');
            $saleDetails = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
            $saleProductIds = $saleDetails->pluck('id_produk')->unique()->toArray();
            
            if (!empty($saleProductIds)) {
                // Find Pembelian records that match this sale's date and products
                // These are the cash purchases auto-created from this sale
                $pembelians = Pembelian::whereDate('purchasedate2', $saleDate)
                    ->whereHas('details', function($query) use ($saleProductIds) {
                        $query->whereIn('id_produk', $saleProductIds);
                    })
                    ->get();
                
                foreach ($pembelians as $pembelian) {
                    // Check if supplier is a cash supplier (only cash suppliers appear in cash generated list)
                    $supplier = Supplier::find($pembelian->id_supplier);
                    $isCashSupplier = $supplier && strtoupper(trim($supplier->mop ?? '')) === 'CASH';
                    
                    if (!$isCashSupplier) {
                        continue; // Skip non-cash suppliers
                    }
                    
                    // Check if this Pembelian matches the sale by verifying products and quantities
                    $pembelianDetails = PembelianDetail::where('id_pembelian', $pembelian->id_pembelian)
                        ->whereIn('id_produk', $saleProductIds)
                        ->get();
                    
                    // Only delete if Pembelian has unpaid balance (bayar = 0 or less than total)
                    // This ensures we only delete auto-created purchases, not manually paid ones
                    $isUnpaid = floatval($pembelian->bayar ?? 0) == 0 || 
                                floatval($pembelian->bayar ?? 0) < floatval($pembelian->total_harga ?? 0);
                    
                    if ($isUnpaid && $pembelianDetails->isNotEmpty()) {
                        // Delete PembelianDetail records first
                        PembelianDetail::where('id_pembelian', $pembelian->id_pembelian)->delete();
                        
                        // Delete the Pembelian record
                        $pembelian->delete();
                        
                        \Log::info('Removed Pembelian (cash purchase) when directly editing sale', [
                            'penjualan_id' => $penjualan->id_penjualan,
                            'pembelian_id' => $pembelian->id_pembelian,
                            'supplier_id' => $pembelian->id_supplier,
                            'total_harga' => $pembelian->total_harga
                        ]);
                    }
                }
                
                if ($pembelians->count() > 0) {
                    \Log::info('Removed Pembelian records for direct edit', [
                        'penjualan_id' => $penjualan->id_penjualan,
                        'pembelians_removed' => $pembelians->count()
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error removing Pembelian records when directly editing sale: ' . $e->getMessage());
            // Don't fail the edit if Pembelian removal fails
        }

        // Update DailyCash totals to remove this sale from cash generated list
        // Recalculate totals for the sale date to exclude this sale
        try {
            $saleDate = Carbon::parse($penjualan->created_at)->format('Y-m-d');
            $dailyCash = DailyCash::whereDate('date', $saleDate)->first();
            
            if ($dailyCash) {
                // Recalculate total_sales for this date (only completed sales)
                $query = Penjualan::whereDate('created_at', $saleDate);
                if (Schema::hasColumn('penjualan', 'status')) {
                    $query->where('status', 'completed');
                }
                // Exclude this sale from the calculation since we're about to change its status
                $query->where('id_penjualan', '!=', $penjualan->id_penjualan);
                
                $totalSales = $query->sum('bayar');
                $netSales = $totalSales - $dailyCash->opening_cash;
                
                $dailyCash->total_sales = $totalSales;
                $dailyCash->net_sales = $netSales;
                $dailyCash->save();
                
                \Log::info('Updated DailyCash totals after direct sale edit', [
                    'penjualan_id' => $penjualan->id_penjualan,
                    'sale_date' => $saleDate,
                    'new_total_sales' => $totalSales,
                    'new_net_sales' => $netSales,
                    'sale_amount_removed' => $penjualan->bayar
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating DailyCash totals when directly editing sale: ' . $e->getMessage());
            // Don't fail the edit if DailyCash update fails
        }

        $penjualan->status = 'active';
        if (Schema::hasColumn('penjualan', 'edit_initiated_at')) {
            $penjualan->edit_initiated_at = now();
        }
        $penjualan->update();

        session(['id_penjualan' => $penjualan->id_penjualan]);
        return redirect()->route('transaksi.index', ['resume' => $penjualan->id_penjualan])
            ->with('success', 'Sale reopened for editing. Change items or amounts and complete again.');
    }

    /**
     * Sale is waiting for POS completion after "Initiate edit" or admin "Edit sale" (reopen).
     * Stays on Initiated Sale Edits until status becomes completed (edit_initiated_at cleared on save).
     */
    protected function saleIsAwaitingEditCompletion(Penjualan $penjualan): bool
    {
        if (! Schema::hasColumn('penjualan', 'status')) {
            return false;
        }
        $st = strtolower((string) ($penjualan->status ?? ''));
        if ($st === 'edit_initiated') {
            return true;
        }
        if ($st === 'active' && Schema::hasColumn('penjualan', 'edit_initiated_at') && $penjualan->edit_initiated_at !== null) {
            return (float) ($penjualan->bayar ?? 0) > 0;
        }

        return false;
    }

    /**
     * When sales list search finds no rows, detect if the receipt is on Initiated Sale Edits instead.
     *
     * @return array{receiptno: string, id_penjualan: int, resume_url: string, initiated_edits_url: string}|null
     */
    protected function findEditInitiatedReceiptSearchHint(string $receiptNumber): ?array
    {
        $receipt = trim($receiptNumber);
        if ($receipt === '' || ! Schema::hasColumn('penjualan', 'status')) {
            return null;
        }

        $candidates = Penjualan::query()
            ->where('receiptno', 'like', '%'.$receipt.'%');

        if (Schema::hasColumn('penjualan', 'sale_type')) {
            $candidates->where(function ($q) {
                $q->where('sale_type', 'normal')->orWhereNull('sale_type');
            });
        }

        $candidates = $candidates
            ->orderByRaw('CASE WHEN receiptno = ? THEN 0 ELSE 1 END', [$receipt])
            ->orderByDesc('id_penjualan')
            ->limit(10)
            ->get();

        foreach ($candidates as $penjualan) {
            if (! $this->saleIsAwaitingEditCompletion($penjualan)) {
                continue;
            }

            return [
                'receiptno' => (string) ($penjualan->receiptno ?? $receipt),
                'id_penjualan' => (int) $penjualan->id_penjualan,
                'resume_url' => route('transaksi.index', ['resume' => $penjualan->id_penjualan]),
                'initiated_edits_url' => route('penjualan.initiated_edits'),
            ];
        }

        return null;
    }

    /**
     * Core: strip unpaid consignment, reverse payment, restore stock, Pembelian cleanup, DailyCash, set status edit_initiated.
     *
     * @return array{ok: bool, error?: string}
     */
    protected function performInitiateEditSale(Penjualan $penjualan): array
    {
        // Remove unpaid consignment first so a never-completed edit cannot leave supplier balances posted.
        // POS completion recreates invoice_items (same as cash Pembelian being removed below).
        try {
            $stripped = app(SaleSupplierLedgerRemovalService::class)->removeAllUnpaidConsignmentInvoiceItemsForPenjualan($penjualan);
            if ($stripped > 0) {
                \Log::info('Stripped unpaid consignment invoice_items when initiating sale edit', [
                    'penjualan_id' => $penjualan->id_penjualan,
                    'receiptno' => $penjualan->receiptno,
                    'invoice_items_removed' => $stripped,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error stripping consignment when initiating sale edit: '.$e->getMessage());

            return ['ok' => false, 'error' => 'Could not clear consignment lines for this edit. Please try again.'];
        }

        // Reverse payment from account so cashier's completion will add the new amount only
        $saleType = $penjualan->sale_type ?? 'normal';
        if ($saleType !== 'management' && $penjualan->bayar > 0) {
            try {
                $paymentMethod = $penjualan->payment_method ?? 'Cash';
                if ($paymentMethod === 'Split' && Schema::hasColumn('penjualan', 'payment_split_details') && $penjualan->payment_split_details) {
                    $split = json_decode($penjualan->payment_split_details, true);
                    if ($split) {
                        foreach (['Cash' => $split['cash'] ?? 0, 'Mpesa' => $split['mpesa'] ?? 0, 'Card' => $split['card'] ?? 0] as $accountName => $amount) {
                            if ($amount > 0) {
                                $account = Account::getByName($accountName);
                                if ($account) {
                                    $account->deductAmount($amount);
                                }
                            }
                        }
                    }
                } else {
                    $account = Account::getByName($paymentMethod);
                    if ($account) {
                        $account->deductAmount($penjualan->bayar);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error reversing payment when initiating edit: ' . $e->getMessage());

                return ['ok' => false, 'error' => 'Could not reverse payment. Please try again.'];
            }
        }

        // Restore stock so cashier's completion does not double-deduct
        $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        foreach ($details as $item) {
            $produk = Produk::find($item->id_produk);
            if ($produk) {
                $produk->stok += $item->jumlah;
                $produk->update();
            }
        }

        // Remove Pembelian records (cash purchases) created from this sale
        // These appear in the cash generated sales list and should be removed when editing
        try {
            $saleDate = Carbon::parse($penjualan->created_at)->format('Y-m-d');
            $saleDetails = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
            $saleProductIds = $saleDetails->pluck('id_produk')->unique()->toArray();

            if (! empty($saleProductIds)) {
                // Find Pembelian records that match this sale's date and products
                // These are the cash purchases auto-created from this sale
                $pembelians = Pembelian::whereDate('purchasedate2', $saleDate)
                    ->whereHas('details', function ($query) use ($saleProductIds) {
                        $query->whereIn('id_produk', $saleProductIds);
                    })
                    ->get();

                foreach ($pembelians as $pembelian) {
                    // Check if supplier is a cash supplier (only cash suppliers appear in cash generated list)
                    $supplier = Supplier::find($pembelian->id_supplier);
                    $isCashSupplier = $supplier && strtoupper(trim($supplier->mop ?? '')) === 'CASH';

                    if (! $isCashSupplier) {
                        continue; // Skip non-cash suppliers
                    }

                    // Check if this Pembelian matches the sale by verifying products and quantities
                    $pembelianDetails = PembelianDetail::where('id_pembelian', $pembelian->id_pembelian)
                        ->whereIn('id_produk', $saleProductIds)
                        ->get();

                    // Only delete if Pembelian has unpaid balance (bayar = 0 or less than total)
                    // This ensures we only delete auto-created purchases, not manually paid ones
                    $isUnpaid = floatval($pembelian->bayar ?? 0) == 0 ||
                                floatval($pembelian->bayar ?? 0) < floatval($pembelian->total_harga ?? 0);

                    if ($isUnpaid && $pembelianDetails->isNotEmpty()) {
                        // Delete PembelianDetail records first
                        PembelianDetail::where('id_pembelian', $pembelian->id_pembelian)->delete();

                        // Delete the Pembelian record
                        $pembelian->delete();

                        \Log::info('Removed Pembelian (cash purchase) when initiating sale edit', [
                            'penjualan_id' => $penjualan->id_penjualan,
                            'pembelian_id' => $pembelian->id_pembelian,
                            'supplier_id' => $pembelian->id_supplier,
                            'total_harga' => $pembelian->total_harga,
                        ]);
                    }
                }

                if ($pembelians->count() > 0) {
                    \Log::info('Removed Pembelian records for initiated edit', [
                        'penjualan_id' => $penjualan->id_penjualan,
                        'pembelians_removed' => $pembelians->count(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error removing Pembelian records when initiating edit: ' . $e->getMessage());
            // Don't fail the edit if Pembelian removal fails
        }

        // Update DailyCash totals to remove this sale from cash generated list
        // Recalculate totals for the sale date to exclude this sale
        try {
            $saleDate = Carbon::parse($penjualan->created_at)->format('Y-m-d');
            $dailyCash = DailyCash::whereDate('date', $saleDate)->first();

            if ($dailyCash) {
                // Recalculate total_sales for this date (only completed sales)
                $query = Penjualan::whereDate('created_at', $saleDate);
                if (Schema::hasColumn('penjualan', 'status')) {
                    $query->where('status', 'completed');
                }
                // Exclude this sale from the calculation since we're about to change its status
                $query->where('id_penjualan', '!=', $penjualan->id_penjualan);

                $totalSales = $query->sum('bayar');
                $netSales = $totalSales - $dailyCash->opening_cash;

                $dailyCash->total_sales = $totalSales;
                $dailyCash->net_sales = $netSales;
                $dailyCash->save();

                \Log::info('Updated DailyCash totals after initiating sale edit', [
                    'penjualan_id' => $penjualan->id_penjualan,
                    'sale_date' => $saleDate,
                    'new_total_sales' => $totalSales,
                    'new_net_sales' => $netSales,
                    'sale_amount_removed' => $penjualan->bayar,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error updating DailyCash totals when initiating edit: ' . $e->getMessage());
            // Don't fail the edit if DailyCash update fails
        }

        $penjualan->status = 'edit_initiated';
        if (Schema::hasColumn('penjualan', 'edit_initiated_at')) {
            $penjualan->edit_initiated_at = now();
        }
        $penjualan->update();

        return ['ok' => true];
    }

    /**
     * Initiate a re-edit so the cashier can continue (admin only).
     * Reverses payment from account, restores stock, sets status to edit_initiated.
     */
    public function initiateEditSale($id)
    {
        if (! auth()->user()) {
            abort(403, 'Unauthorized. Please log in to initiate sale edits.');
        }

        if (! Schema::hasColumn('penjualan', 'status')) {
            return redirect()->back()->withErrors(['error' => 'Initiate edit is not available.']);
        }

        $penjualan = Penjualan::findOrFail($id);
        if ($this->saleIsAwaitingEditCompletion($penjualan)) {
            return redirect()->back()->withErrors(['error' => 'This sale is already open for editing. Use Initiated Sale Edits to continue in POS.']);
        }
        if ($penjualan->status !== 'completed') {
            return redirect()->back()->withErrors(['error' => 'Only completed sales can be sent for editing.']);
        }

        $result = $this->performInitiateEditSale($penjualan);
        if (! ($result['ok'] ?? false)) {
            return redirect()->back()->withErrors(['error' => $result['error'] ?? 'Could not initiate edit.']);
        }

        return redirect()->route('penjualan.index')
            ->with('success', 'Edit initiated. Cashier can continue from "Initiated Sale Edits" and complete the transaction.');
    }

    /**
     * POS page: enter receipt number, preview lines, then start edit (same logic as admin initiate).
     */
    public function initiateEditSearchForm()
    {
        if (! Schema::hasColumn('penjualan', 'status')) {
            return redirect()->route('transaksi.baru')
                ->withErrors(['error' => 'Initiate edit is not available.']);
        }

        return view('penjualan.initiate_edit_search');
    }

    /**
     * AJAX: lookup completed sale by receipt for current user (cashier = own sales only).
     */
    public function lookupInitiateEditByReceipt(Request $request)
    {
        $request->validate([
            'receiptno' => 'required|string|max:120',
        ]);

        if (! Schema::hasColumn('penjualan', 'status')) {
            return response()->json(['ok' => false, 'message' => 'Initiate edit is not available.'], 422);
        }

        $receiptNo = trim($request->receiptno);
        if ($receiptNo === '') {
            return response()->json(['ok' => false, 'message' => 'Enter a receipt number.'], 422);
        }

        $penjualan = Penjualan::query()
            ->where('receiptno', $receiptNo)
            ->orderByDesc('id_penjualan')
            ->first();

        if (! $penjualan) {
            return response()->json([
                'ok' => false,
                'message' => 'No sale found with receipt: '.substr($receiptNo, 0, 40),
            ]);
        }

        if (! $this->userCanSelfInitiateEditSale($penjualan)) {
            return response()->json([
                'ok' => false,
                'message' => 'You can only initiate an edit for receipts you created yourself.',
            ], 403);
        }

        $saleType = $penjualan->sale_type ?? 'normal';
        if ($saleType === 'management') {
            return response()->json([
                'ok' => false,
                'message' => 'Management sales cannot be edited from this screen.',
            ], 422);
        }

        if ($this->saleIsAwaitingEditCompletion($penjualan)) {
            return response()->json([
                'ok' => true,
                'already_initiated' => true,
                'id_penjualan' => (int) $penjualan->id_penjualan,
                'receiptno' => $penjualan->receiptno,
                'message' => 'This sale is already waiting for edit. Open POS to continue (see Initiated Sale Edits).',
                'resume_url' => url('/transaksi?resume='.$penjualan->id_penjualan),
            ]);
        }

        if ($penjualan->status !== 'completed') {
            return response()->json([
                'ok' => false,
                'message' => 'Only completed sales can be prepared for editing. Current status: '.$penjualan->status,
            ], 422);
        }

        $details = PenjualanDetail::with('produk.shop')
            ->where('id_penjualan', $penjualan->id_penjualan)
            ->get();

        if ($details->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'This receipt has no line items.',
            ], 422);
        }

        $items = $details->map(function ($d) {
            $p = $d->produk;

            return [
                'nama_produk' => $p->nama_produk ?? ('#'.$d->id_produk),
                'kode_produk' => $p->kode_produk ?? '',
                'qty' => (float) $d->jumlah,
                'subtotal' => (float) $d->subtotal,
                'shop' => $p && $p->shop ? ($p->shop->shop_code ?? $p->shop->shop_name ?? '') : '',
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'already_initiated' => false,
            'id_penjualan' => (int) $penjualan->id_penjualan,
            'receiptno' => $penjualan->receiptno,
            'saledate' => $penjualan->saledate ?? optional($penjualan->created_at)->format('Y-m-d'),
            'total_pay' => (float) $penjualan->bayar,
            'total_item' => (int) $penjualan->total_item,
            'items' => $items,
        ]);
    }

    /**
     * Start initiated edit for own sale (runs same reversal logic as admin initiate).
     */
    public function startInitiateEditBySale(Request $request)
    {
        $request->validate([
            'id_penjualan' => 'required|integer|exists:penjualan,id_penjualan',
        ]);

        if (! Schema::hasColumn('penjualan', 'status')) {
            return redirect()->route('transaksi.baru')
                ->withErrors(['error' => 'Initiate edit is not available.']);
        }

        $penjualan = Penjualan::findOrFail($request->id_penjualan);

        if (! $this->userCanSelfInitiateEditSale($penjualan)) {
            abort(403, 'You can only initiate an edit for receipts you created yourself.');
        }

        $saleType = $penjualan->sale_type ?? 'normal';
        if ($saleType === 'management') {
            return redirect()->route('transaksi.initiate_edit_form')
                ->withErrors(['error' => 'Management sales cannot be edited from this screen.']);
        }

        if ($this->saleIsAwaitingEditCompletion($penjualan)) {
            return redirect()->route('transaksi.index', ['resume' => $penjualan->id_penjualan])
                ->with('success', 'Continue editing this sale in POS.');
        }

        if ($penjualan->status !== 'completed') {
            return redirect()->route('transaksi.initiate_edit_form')
                ->withErrors(['error' => 'Only completed sales can be prepared for editing.']);
        }

        $result = $this->performInitiateEditSale($penjualan);
        if (! ($result['ok'] ?? false)) {
            return redirect()->route('transaksi.initiate_edit_form')
                ->withErrors(['error' => $result['error'] ?? 'Could not initiate edit.']);
        }

        return redirect()->route('transaksi.index', ['resume' => $penjualan->id_penjualan])
            ->with('success', 'Sale opened for editing. Update items or amounts, then complete the transaction.');
    }

    /**
     * Cashiers may only self-initiate edit for their own completed normal sales.
     */
    protected function userCanSelfInitiateEditSale(Penjualan $penjualan): bool
    {
        if (auth()->user()->hasRole('admin')) {
            return true;
        }

        return (int) $penjualan->id_user === (int) auth()->id();
    }

    /**
     * Page listing sales that admin has initiated for edit (cashier continues from here).
     */
    public function initiatedEdits()
    {
        if (!Schema::hasColumn('penjualan', 'status')) {
            return redirect()->back()->withErrors(['error' => 'Initiated edits are not available.']);
        }

        $initiatedSales = Penjualan::with(['member', 'user'])
            ->where(function ($q) {
                $q->where('status', 'edit_initiated');
                if (Schema::hasColumn('penjualan', 'edit_initiated_at')) {
                    $q->orWhere(function ($q2) {
                        $q2->where('status', 'active')
                            ->whereNotNull('edit_initiated_at')
                            ->where('bayar', '>', 0);
                    });
                }
            })
            ->where(function ($q) {
                if (Schema::hasColumn('penjualan', 'sale_type')) {
                    $q->where('sale_type', 'normal')->orWhereNull('sale_type');
                }
            })
            ->orderBy('updated_at', 'desc')
            ->orderBy('id_penjualan', 'desc')
            ->get();

        return view('penjualan.initiated_edits', compact('initiatedSales'));
    }

    public function suspendedSales()
    {
        if (!Schema::hasColumn('penjualan', 'status')) {
            return redirect()->route('transaksi.baru')
                ->withErrors(['error' => 'Suspend feature is not available. Please run migration: php artisan migrate']);
        }
        
        $suspendedSales = Penjualan::where('status', 'suspended')
            ->with(['member', 'user'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('penjualan.suspended', compact('suspendedSales'));
    }

    public function suspendedSalesData()
    {
        if (!Schema::hasColumn('penjualan', 'status')) {
            return response()->json(['suspendedSales' => []]);
        }
        
        $suspendedSales = Penjualan::where('status', 'suspended')
            ->with(['member', 'user'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $data = [];
        foreach ($suspendedSales as $index => $sale) {
            $data[] = [
                'index' => $index + 1,
                'id' => $sale->id_penjualan,
                'date' => $sale->saledate ?? $sale->created_at->format('Y-m-d'),
                'receiptno' => $sale->receiptno,
                'total_item' => $sale->total_item,
                'total_harga' => format_uang($sale->total_harga),
                'diskon' => $sale->getDiscountDisplayLabel(),
                'bayar' => format_uang($sale->bayar),
                'cashier' => $sale->user->name ?? 'N/A',
                'suspended_at' => $sale->updated_at->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json(['suspendedSales' => $data]);
    }
    

    public function show($id)
    {
        $detail = PenjualanDetail::with('produk.shop')->where('id_penjualan', $id)->get();

        return datatables()
            ->of($detail)
            ->addIndexColumn()
            ->addColumn('kode_produk', function ($detail) {
                return '<span class="label label-success">'. $detail->produk->kode_produk .'</span>';
            })
            ->addColumn('shop_code', function ($detail) {
                $shopCode = $detail->produk->shop->shop_code ?? $detail->produk->kode_produk ?? 'N/A';
                return '<span class="label label-info">'. $shopCode .'</span>';
            })
            ->addColumn('nama_produk', function ($detail) {
                return $detail->produk->nama_produk;
            })
            ->addColumn('harga_jual', function ($detail) {
                return 'ksh '. format_uang($detail->harga_jual);
            })
            ->addColumn('jumlah', function ($detail) {
                return format_uang($detail->jumlah);
            })
            ->addColumn('subtotal', function ($detail) {
                return 'ksh '. format_uang($detail->subtotal);
            })
            ->rawColumns(['kode_produk', 'shop_code'])
            ->make(true);
    }

    public function confirmationData($id)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $penjualan = Penjualan::with(['member', 'user', 'details.produk.shop'])
            ->findOrFail($id);

        if (Schema::hasColumn('penjualan', 'confirmation_status')) {
            $status = $penjualan->confirmation_status ?? 'pending';
        } else {
            $status = 'pending';
        }

        // Initialize item confirmation statuses if they don't exist
        if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            foreach ($penjualan->details as $detail) {
                if (empty($detail->item_confirmation_status)) {
                    DB::statement("UPDATE penjualan_detail SET item_confirmation_status = 'pending' WHERE id_penjualan_detail = ?", [$detail->id_penjualan_detail]);
                }
            }
            // Refresh to get updated statuses
            $penjualan->load('details');
        }

        // Get all shops for dropdown
        $shops = \App\Models\Shop::orderBy('shop_name')->get();

        // Check if any item is quick-added and not yet updated
        $waitingUpdate = false;
        if (Schema::hasColumn('produk', 'is_incomplete')) {
            $waitingUpdate = $penjualan->details->contains(function ($detail) {
                $produk = $detail->produk;
                return $produk && !empty($produk->is_incomplete);
            });
        }

        // Prepare items data
        $items = [];
        foreach ($penjualan->details as $detail) {
            $produk = $detail->produk;
            $isQuickAdd = $produk && Schema::hasColumn('produk', 'is_incomplete') && ! empty($produk->is_incomplete);
            $items[] = [
                'id' => $detail->id_penjualan_detail,
                'id_produk' => $detail->id_produk,
                'product_code' => trim((string) ($detail->produk->item_code ?? $detail->produk->kode_produk ?? '')) ?: 'N/A',
                'product_name' => $detail->produk->nama_produk ?? 'N/A',
                'shop_id' => $detail->produk->shop_id ?? null,
                'price' => $detail->harga_jual,
                'quantity' => $detail->jumlah,
                'discount' => $detail->diskon,
                'subtotal' => $detail->subtotal,
                'item_confirmation_status' => $detail->item_confirmation_status ?? 'pending',
                'is_quick_add' => $isQuickAdd,
            ];
        }

        return response()->json([
            'success' => true,
            'receipt' => [
                'receiptno' => $penjualan->receiptno,
                'saledate' => $penjualan->saledate ? \Carbon\Carbon::parse($penjualan->saledate)->format('d/m/Y') : ($penjualan->created_at ? $penjualan->created_at->format('d/m/Y') : 'N/A'),
                'created_at' => $penjualan->created_at ? $penjualan->created_at->format('d/m/Y') : 'N/A',
                'total_item' => $penjualan->total_item,
                'bayar' => $penjualan->bayar,
                'confirmation_status' => $status,
                'cashier' => $penjualan->user->name ?? 'N/A',
                'member' => $penjualan->member->nama ?? 'Walk-in Customer',
            ],
            'items' => $items,
            'shops' => $shops->map(function($shop) {
                return [
                    'id' => $shop->id,
                    'shop_name' => $shop->shop_name,
                ];
            }),
            'waitingUpdate' => $waitingUpdate,
        ]);
    }

    public function saleInfo($id)
    {
        $penjualan = Penjualan::findOrFail($id);
        
        $paymentMethod = $penjualan->payment_method ?? 'Cash';
        $splitDetails = null;
        
        if ($paymentMethod === 'Split' && Schema::hasColumn('penjualan', 'payment_split_details') && $penjualan->payment_split_details) {
            $splitDetails = json_decode($penjualan->payment_split_details, true);
        }
        
        return response()->json([
            'payment_method' => $paymentMethod,
            'split_details' => $splitDetails
        ]);
    }
    // visit "codeastro" for more projects!
    public function destroy(Request $request, $id)
    {
        // Only admins can delete sales
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only administrators can delete sales.'], 403);
        }

        $penjualan = Penjualan::find($id);
        if (! $penjualan) {
            return response()->json(['message' => 'Sale not found.'], 404);
        }

        $ledger = app(EnsureSaleSupplierLedgerService::class);
        $removal = app(SaleSupplierLedgerRemovalService::class);
        $preview = $removal->ledgerImpactForEntirePenjualan($penjualan, $ledger);

        if ($preview['consignment_paid'] || $preview['cash_purchase_paid']) {
            return response()->json([
                'message' => $preview['message'] ?? 'Cannot delete this sale while linked supplier records have payments recorded.',
            ], 422);
        }

        if (($preview['consignment'] || $preview['cash']) && ! $request->boolean('confirmed')) {
            return response()->json([
                'requires_confirmation' => true,
                'message' => 'Deleting this sale will also remove matching unpaid entries from supplier consignment and/or cash-generated supplier purchases.',
                'consignment' => $preview['consignment'],
                'cash' => $preview['cash'],
            ], 409);
        }

        // Deduct sale amount from corresponding account if sale was completed
        if (Schema::hasColumn('penjualan', 'status') && $penjualan->status === 'completed') {
            try {
                $paymentMethod = $penjualan->payment_method ?? 'Cash';
                $account = Account::getByName($paymentMethod);
                
                if ($account) {
                    $account->deductAmount($penjualan->bayar);
                    \Log::info('Sale amount deducted from account:', [
                        'account' => $paymentMethod,
                        'amount' => $penjualan->bayar,
                        'new_balance' => $account->balance,
                        'penjualan_id' => $penjualan->id_penjualan
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Error deducting sale amount from account: ' . $e->getMessage());
                // Continue with deletion even if account update fails
            }
        }

        $removal->removeAllUnpaidLedgerForPenjualan($penjualan);

        $detail    = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        foreach ($detail as $item) {
            $produk = Produk::find($item->id_produk);
            if ($produk) {
                $produk->stok += $item->jumlah;
                $produk->update();
            }

            $idProduk = (int) $item->id_produk;
            $item->delete();
            Produk::deleteOrphanPosQuickAddProduct($idProduk);
        }

        $penjualan->delete();

        return response(null, 204);
    }

    public function selesai()
    {
        $setting = Setting::first();

        return view('penjualan.selesai', compact('setting'));
    }

    public function notaKecil($id = null)
    {
        $setting = Setting::first();
        
        // Try to get sale ID from parameter first, then from session
        $penjualanId = $id ?? session('id_penjualan');
        
        if (!$penjualanId) {
            abort(404, 'Sale not found. Please try again.');
        }
        
        $penjualan = Penjualan::with('user')->find($penjualanId);
        if (! $penjualan) {
            abort(404, 'Sale not found.');
        }

        $this->assertSaleReceiptAllowed($penjualan);
        
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', $penjualanId)
            ->orderBy('id_penjualan_detail')
            ->get();
        
        if ($detail->isEmpty()) {
            abort(404, 'Sale details not found.');
        }
        
        return view('penjualan.nota_kecil', compact('setting', 'penjualan', 'detail'));
    }

    /**
     * Reprint receipt for a specific sale
     */
    public function reprintReceipt($id)
    {
        $setting = Setting::first();
        $penjualan = Penjualan::with('user')->find($id);
        
        if (!$penjualan) {
            abort(404, 'Sale not found');
        }

        $this->assertSaleReceiptAllowed($penjualan);
        
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', $id)
            ->orderBy('id_penjualan_detail')
            ->get();
        
        if ($detail->isEmpty()) {
            abort(404, 'Sale details not found');
        }
        
        return view('penjualan.nota_kecil', compact('setting', 'penjualan', 'detail'));
    }

    /**
     * AJAX: find a completed sale by receipt number (for POS reprint).
     */
    public function lookupReceiptForReprint(Request $request)
    {
        $receiptNo = trim((string) $request->query('receiptno', ''));
        if ($receiptNo === '') {
            return response()->json([
                'found' => false,
                'message' => 'Enter a receipt number (e.g. U001).',
            ], 422);
        }

        $penjualan = Penjualan::query()
            ->where('receiptno', $receiptNo)
            ->orderByDesc('id_penjualan')
            ->first();

        if (! $penjualan) {
            return response()->json([
                'found' => false,
                'message' => 'No sale found with receipt number: '.substr($receiptNo, 0, 40),
            ]);
        }

        if (Schema::hasColumn('penjualan', 'status') && ($penjualan->status ?? '') === 'active') {
            return response()->json([
                'found' => false,
                'message' => 'That receipt belongs to an open cart. Complete the sale on the POS first.',
            ]);
        }

        $hasDetails = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->exists();
        if (! $hasDetails) {
            return response()->json([
                'found' => false,
                'message' => 'This receipt has no items to print.',
            ]);
        }

        return response()->json([
            'found' => true,
            'id_penjualan' => (int) $penjualan->id_penjualan,
            'receiptno' => $penjualan->receiptno,
            // URL under POS middleware (cashiers may not have sales.read for penjualan.reprint)
            'print_url' => route('transaksi.reprint_sale', $penjualan->id_penjualan),
        ]);
    }

    /**
     * List recent sales for POS reprint picker (receipt + total).
     */
    public function recentSalesForReprint(Request $request)
    {
        $startDate = $this->normalizeFilterDate($request->query('start_date'));
        $endDate = $this->normalizeFilterDate($request->query('end_date'));

        if (! $startDate || ! $endDate) {
            $days = min(30, max(1, (int) $request->query('days', 7)));
            $endDate = Carbon::today()->toDateString();
            $startDate = Carbon::today()->subDays($days - 1)->toDateString();
        }

        if ($startDate > $endDate) {
            return response()->json([
                'message' => 'Start date must be on or before end date.',
                'sales' => [],
            ], 422);
        }

        $maxRangeDays = 366;
        if (Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) > $maxRangeDays) {
            return response()->json([
                'message' => 'Date range cannot exceed '.$maxRangeDays.' days.',
                'sales' => [],
            ], 422);
        }

        $q = Penjualan::query()
            ->whereHas('details')
            ->whereDate('saledate', '>=', $startDate)
            ->whereDate('saledate', '<=', $endDate);

        if (Schema::hasColumn('penjualan', 'status')) {
            $q->whereIn('status', ['completed', 'suspended']);
        }

        $sales = $q->orderByDesc('saledate')
            ->orderByDesc('id_penjualan')
            ->limit(250)
            ->get(['id_penjualan', 'receiptno', 'bayar', 'total_harga', 'total_item', 'saledate', 'created_at', 'sale_type']);

        $rows = $sales->map(function ($s) {
            $bayar = (float) ($s->bayar ?? 0);
            $totalHarga = (float) ($s->total_harga ?? 0);
            $amount = $bayar > 0 ? $bayar : $totalHarga;

            return [
                'id_penjualan' => (int) $s->id_penjualan,
                'receiptno' => (string) ($s->receiptno ?? ''),
                'amount' => round($amount, 2),
                'amount_formatted' => 'Ksh '.number_format($amount, 2),
                'total_item' => (int) ($s->total_item ?? 0),
                'saledate' => $s->saledate ? Carbon::parse($s->saledate)->format('Y-m-d') : '',
                'time' => $s->created_at ? $s->created_at->format('d/m/Y H:i') : '',
                'sale_type' => (string) ($s->sale_type ?? 'normal'),
                'print_url' => route('transaksi.reprint_sale', $s->id_penjualan),
            ];
        });

        return response()->json([
            'sales' => $rows,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Auto-print receipt after sale completion
     * Opens in new window and automatically triggers print
     */
    public function autoPrintReceipt($id)
    {
        $setting = Setting::first();
        $penjualan = Penjualan::with('user')->find($id);
        
        if (!$penjualan) {
            abort(404, 'Sale not found');
        }

        $this->assertSaleReceiptAllowed($penjualan);
        
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', $id)
            ->orderBy('id_penjualan_detail')
            ->get();
        
        if ($detail->isEmpty()) {
            abort(404, 'Sale details not found');
        }
        
        return view('penjualan.receipt_auto_print', compact('setting', 'penjualan', 'detail'));
    }

    /**
     * Generate withheld sale receipt (before completing transaction)
     */
    public function withheldReceipt($id)
    {
        $setting = Setting::first();
        $penjualan = Penjualan::with('user')->find($id);
        
        if (!$penjualan) {
            abort(404, 'Sale not found');
        }
        
        // Check if sale is still editable (not completed).
        // Allow printing after suspend as well, because a suspended sale can still be edited later.
        if (Schema::hasColumn('penjualan', 'status')) {
            if ($penjualan->status !== 'active' && $penjualan->status !== 'suspended') {
                abort(404, 'This sale cannot be printed (already completed or not available).');
            }
        }
        
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', $id)
            ->orderBy('id_penjualan_detail')
            ->get();
        
        if ($detail->isEmpty()) {
            abort(404, 'No items found in this sale. Please add items before printing receipt.');
        }
        
        // Calculate total from detail items (for active sales, total_harga might be 0)
        $calculatedTotal = $detail->sum('subtotal');
        
        // Use calculated total if penjualan total_harga is 0 or not set (total payable before discount)
        $totalHarga = ($penjualan->total_harga > 0) ? $penjualan->total_harga : $calculatedTotal;
        
        // Calculate VAT (16% inclusive) and subtotal
        $vatAmount = round($totalHarga * (16 / 116), 2);
        $subTotal = round($totalHarga - $vatAmount, 2);
        
        // Withheld receipt: never show sale-level discount. Discount is only final at completion,
        // and penjualan.diskon may be the form default (e.g. member discount) not yet applied by the user.
        $discountAmount = 0;
        $totalAfterDiscount = $totalHarga;
        
        return view('penjualan.withheld_receipt', compact('setting', 'penjualan', 'detail', 'totalHarga', 'vatAmount', 'subTotal', 'discountAmount', 'totalAfterDiscount'));
    }

    public function notaBesar()
    {
        $setting = Setting::first();
        $penjualan = Penjualan::find(session('id_penjualan'));
        if (! $penjualan) {
            abort(404);
        }
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', session('id_penjualan'))
            ->get();

        $pdf = PDF::loadView('penjualan.nota_besar', compact('setting', 'penjualan', 'detail'));
        $pdf->setPaper(0,0,609,440, 'potrait');
        return $pdf->stream('Transaction-'. date('Y-m-d-his') .'.pdf');
    }

    public function loadForm($diskon, $total, $diterima, $tax, $subtotal)
    {
        $bayar = $total; // Total after tax
        $kembali = ($diterima != 0) ? $diterima - $bayar : 0;

        $data = [
            'totalrp' => format_uang($total),
            'bayar' => $bayar,
            'bayarrp' => format_uang($bayar),
            'terbilang' => ucwords(terbilang($bayar). ' Shillings'),
            'kembalirp' => format_uang($kembali),
            'kembali_terbilang' => ucwords(terbilang($kembali). ' Shillings'),
            'taxrp' => format_uang($tax),
            'subtotalBeforeTaxrp' => format_uang($subtotal),
        ];

        return response()->json($data);
    }

    /**
     * Display detailed sales report page (Admin only)
     */
    public function detailedReport()
    {
        // Only admins can access the Detailed Sales Report
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access the Detailed Sales Report.');
        }

        return view('penjualan.detailed_report');
    }

    /**
     * Display sales lines whose sold products have no valid supplier.
     * Excludes quick-added (incomplete) products.
     */
    public function noSupplierSales()
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access No Supplier Sales.');
        }

        return view('penjualan.no_supplier_sales');
    }

    /**
     * Data for No Supplier Sales DataTable.
     */
    public function noSupplierSalesData(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access No Supplier Sales.');
        }

        $invoiceAllocBySaleSub = DB::table('invoice_items')
            ->select('penjualan_id', 'produk_id')
            ->whereNotNull('penjualan_id')
            ->groupBy('penjualan_id', 'produk_id');

        $cashAllocSub = DB::table('pembelian_detail')
            ->join('pembelian', 'pembelian.id_pembelian', '=', 'pembelian_detail.id_pembelian')
            ->select(
                'pembelian_detail.id_produk as cash_produk_id',
                DB::raw('DATE(pembelian.purchasedate2) as cash_sale_date')
            )
            ->groupBy('pembelian_detail.id_produk', DB::raw('DATE(pembelian.purchasedate2)'));

        $query = PenjualanDetail::query()
            ->join('penjualan', 'penjualan.id_penjualan', '=', 'penjualan_detail.id_penjualan')
            ->join('produk', 'produk.id_produk', '=', 'penjualan_detail.id_produk')
            ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->leftJoin('users', 'users.id', '=', 'penjualan.id_user')
            ->leftJoinSub($invoiceAllocBySaleSub, 'alloc_invoice_sale', function ($join) {
                $join->on('alloc_invoice_sale.penjualan_id', '=', 'penjualan.id_penjualan')
                    ->on('alloc_invoice_sale.produk_id', '=', 'penjualan_detail.id_produk');
            })
            ->leftJoinSub($cashAllocSub, 'alloc_cash', function ($join) {
                $join->on('alloc_cash.cash_produk_id', '=', 'penjualan_detail.id_produk')
                    ->whereRaw('alloc_cash.cash_sale_date = DATE(penjualan.saledate)');
            })
            ->select(
                'penjualan_detail.id_penjualan_detail',
                'penjualan.id_penjualan',
                'penjualan.receiptno',
                'penjualan.saledate',
                'penjualan.payment_method',
                'penjualan_detail.jumlah',
                'penjualan_detail.harga_jual',
                'penjualan_detail.diskon',
                'penjualan_detail.subtotal',
                'produk.item_code',
                'produk.kode_produk',
                'produk.nama_produk',
                'produk.harga_beli',
                'produk.id_supplier',
                'supplier.nama as supplier_name',
                'shops.shop_name',
                'users.name as cashier_name'
            );

        // Exclude quick-added items.
        if (Schema::hasColumn('produk', 'is_incomplete')) {
            $query->where(function ($q) {
                $q->whereNull('produk.is_incomplete')
                    ->orWhere('produk.is_incomplete', false);
            });
        }

        // Include completed + paid-but-active (reopened) normal sales.
        $this->applyNormalSalesListStatusScope($query, true);

        // Exclude management sales where sale_type exists.
        if (Schema::hasColumn('penjualan', 'sale_type')) {
            $query->where(function ($q) {
                $q->where('penjualan.sale_type', 'normal')
                    ->orWhereNull('penjualan.sale_type');
            });
        }

        // Historical missing-allocation detection:
        // show ONLY sale lines missing in BOTH consignment list and cash-generated list.
        $query->where(function ($q) {
            $q->whereNull('alloc_invoice_sale.penjualan_id')
                ->whereNull('alloc_cash.cash_produk_id');
        });

        if ($request->filled('start_date')) {
            $query->whereDate('penjualan.saledate', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('penjualan.saledate', '<=', $request->input('end_date'));
        }

        $summaryQuery = clone $query;
        $totalRows = (clone $summaryQuery)->count();
        $totalQty = (clone $summaryQuery)->sum('penjualan_detail.jumlah');
        $totalAmount = (clone $summaryQuery)->sum('penjualan_detail.subtotal');

        return datatables()
            ->of($query)
            ->addIndexColumn()
            ->editColumn('saledate', function ($row) {
                return $row->saledate ? Carbon::parse($row->saledate)->format('d/m/Y') : '-';
            })
            ->addColumn('product_code', function ($row) {
                return e($row->item_code ?: ($row->kode_produk ?: '-'));
            })
            ->addColumn('cashier_name', function ($row) {
                return e($row->cashier_name ?: 'Unknown');
            })
            ->addColumn('shop_name', function ($row) {
                return e($row->shop_name ?: '-');
            })
            ->addColumn('supplier_name', function ($row) {
                return e(trim((string) ($row->supplier_name ?? '')) ?: '-');
            })
            ->addColumn('action', function ($row) {
                $detailId = (int) ($row->id_penjualan_detail ?? 0);
                if ($detailId <= 0) {
                    return '<span class="text-muted">-</span>';
                }

                $currentSupplierId = (int) ($row->id_supplier ?? 0);
                $currentSupplierName = trim((string) ($row->supplier_name ?? ''));
                $label = $currentSupplierName !== '' ? e($currentSupplierName) : 'No supplier on product';
                $canAssignCurrent = ($currentSupplierId > 0 && $currentSupplierName !== '');
                $assignBtnClass = $canAssignCurrent ? 'btn-success' : 'btn-default';
                $assignBtnDisabled = $canAssignCurrent ? '' : ' disabled';
                $assignBtnTitle = $canAssignCurrent
                    ? 'Assign to current product supplier'
                    : 'Current supplier is missing. Use Assign Different Supplier.';

                $html = '<div class="btn-group btn-group-xs" role="group" style="white-space: nowrap;">';
                $html .= '<button type="button" class="btn '.$assignBtnClass.' js-no-supplier-assign-current"'
                    .' data-detail-id="'.$detailId.'"'
                    .' data-supplier-id="'.$currentSupplierId.'"'
                    .' title="'.e($assignBtnTitle).'"'
                    .$assignBtnDisabled
                    .'>'
                    .'<i class="fa fa-check"></i> Assign'
                    .'</button>';
                $html .= '<button type="button" class="btn btn-primary js-no-supplier-assign-different"'
                    .' data-detail-id="'.$detailId.'"'
                    .' title="Choose different supplier">'
                    .'<i class="fa fa-exchange"></i> Assign Different Supplier'
                    .'</button>';
                $html .= '</div>';
                $html .= '<div class="text-muted small" style="margin-top:4px;">Current: '.$label.'</div>';
                $html .= '<div class="js-no-supplier-assign-picker-wrap" data-detail-id="'.$detailId.'" style="display:none; margin-top:6px;">';
                $html .= '<select class="form-control input-sm js-no-supplier-sale-assign" data-detail-id="'.$detailId.'" style="min-width:220px;">'
                    .'<option value="">Assign to supplier...</option>'
                    .'</select>';
                $html .= '</div>';

                return $html;
            })
            ->with([
                'total_rows' => (int) $totalRows,
                'total_qty' => (float) $totalQty,
                'total_amount' => (float) $totalAmount,
            ])
            ->rawColumns(['action'])
            ->make(true);
    }

    /**
     * JSON for Select2 supplier search on No Supplier Sales page.
     */
    public function noSupplierSalesSuppliersSelect(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access No Supplier Sales.');
        }

        $q = trim((string) $request->query('q', ''));
        $query = Supplier::query()->orderBy('nama');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('nama', 'like', $like)
                    ->orWhere('telepon', 'like', $like);
            });
        }
        $rows = $query->limit(50)->get(['id_supplier', 'nama', 'mop']);
        $results = $rows->map(function ($s) {
            $mop = strtoupper(trim((string) ($s->mop ?? '')));
            $text = (string) ($s->nama ?? ('Supplier #'.$s->id_supplier));
            if ($mop !== '') {
                $text .= ' — '.$mop;
            }
            return ['id' => (int) $s->id_supplier, 'text' => $text];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Assign one no-supplier sale line to a selected supplier ledger.
     */
    public function assignNoSupplierSale(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access No Supplier Sales.');
        }

        $request->validate([
            'detail_id' => 'required|integer',
            'supplier_id' => 'nullable|integer|exists:supplier,id_supplier',
            'use_current_supplier' => 'nullable|boolean',
        ]);

        $detail = PenjualanDetail::query()
            ->join('penjualan', 'penjualan.id_penjualan', '=', 'penjualan_detail.id_penjualan')
            ->join('produk', 'produk.id_produk', '=', 'penjualan_detail.id_produk')
            ->select(
                'penjualan_detail.id_penjualan_detail',
                'penjualan_detail.id_penjualan',
                'penjualan_detail.id_produk',
                'penjualan_detail.jumlah',
                'penjualan_detail.diskon',
                'penjualan.saledate',
                'produk.harga_beli',
                'produk.nama_produk'
            )
            ->where('penjualan_detail.id_penjualan_detail', (int) $request->input('detail_id'))
            ->first();

        if (!$detail) {
            return response()->json(['message' => 'Sale line not found.'], 404);
        }

        $supplierId = (int) $request->input('supplier_id', 0);
        if ($request->boolean('use_current_supplier') && $supplierId <= 0) {
            $currentProductSupplierId = (int) DB::table('produk')
                ->where('id_produk', (int) $detail->id_produk)
                ->value('id_supplier');
            $supplierId = $currentProductSupplierId;
        }

        if ($supplierId <= 0) {
            return response()->json(['message' => 'This product has no supplier assigned. Use "Assign Different Supplier".'], 422);
        }

        $supplier = Supplier::find($supplierId);
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $produk = Produk::find((int) $detail->id_produk);
        if ($produk && Schema::hasColumn('produk', 'is_incomplete') && (bool) $produk->is_incomplete) {
            return response()->json(['message' => 'Quick-added items cannot be assigned from this page.'], 422);
        }

        $qty = (float) ($detail->jumlah ?? 0);
        $buyPrice = (float) ($detail->harga_beli ?? 0);
        if ($qty <= 0) {
            return response()->json(['message' => 'Invalid sale quantity for this line.'], 422);
        }
        if ($buyPrice <= 0) {
            return response()->json(['message' => 'Buying price is missing (0) for this product. Set a valid buying price first, then assign to supplier.'], 422);
        }

        $amount = $qty * $buyPrice;
        $saleDate = $detail->saledate ? Carbon::parse($detail->saledate)->toDateString() : Carbon::today()->toDateString();
        $saleDateTime = Carbon::parse($saleDate)->startOfDay();
        $supplierMop = strtoupper(trim((string) ($supplier->mop ?? '')));
        $isCashSupplier = ($supplierMop === 'CASH');

        if ($isCashSupplier) {
            $pembelian = Pembelian::where('id_supplier', (int) $supplierId)
                ->whereDate('purchasedate2', $saleDate)
                ->orderBy('id_pembelian', 'desc')
                ->first();

            if (!$pembelian) {
                $pembelian = Pembelian::create([
                    'id_supplier' => (int) $supplierId,
                    'total_item' => 0,
                    'total_harga' => 0,
                    'reorder' => 0,
                    'bayar' => 0,
                    'purchasedate2' => $saleDate,
                    'created_at' => $saleDateTime,
                    'updated_at' => $saleDateTime,
                ]);
            }

            $existingDetail = PembelianDetail::where('id_pembelian', (int) $pembelian->id_pembelian)
                ->where('id_produk', (int) $detail->id_produk)
                ->orderBy('id_pembelian_detail', 'desc')
                ->first();

            if ($existingDetail) {
                $existingDetail->harga_beli = $buyPrice;
                $existingDetail->jumlah = (float) ($existingDetail->jumlah ?? 0) + $qty;
                $existingDetail->subtotal = (float) ($existingDetail->subtotal ?? 0) + $amount;
                $existingDetail->updated_at = now();
                $existingDetail->save();
            } else {
                PembelianDetail::create([
                    'id_pembelian' => (int) $pembelian->id_pembelian,
                    'id_produk' => (int) $detail->id_produk,
                    'harga_beli' => $buyPrice,
                    'jumlah' => $qty,
                    'subtotal' => $amount,
                    'created_at' => $saleDateTime,
                    'updated_at' => $saleDateTime,
                ]);
            }

            $pembelian->total_item = (float) ($pembelian->total_item ?? 0) + $qty;
            $pembelian->total_harga = (float) ($pembelian->total_harga ?? 0) + $amount;
            $pembelian->save();

            return response()->json([
                'message' => 'Assigned to supplier under cash-generated sales successfully.',
            ]);
        }

        $invoice = Invoice::where('id_supplier', (int) $supplierId)
            ->whereDate('created_at', $saleDate)
            ->orderBy('id', 'desc')
            ->first();

        if (!$invoice) {
            $invoice = new Invoice();
            $invoice->id_supplier = (int) $supplierId;
            $invoice->total = 0;
            $invoice->created_at = $saleDateTime;
            $invoice->updated_at = $saleDateTime;
            $invoice->save();
        }

        $ledger = app(EnsureSaleSupplierLedgerService::class);
        $existingInvoiceItem = null;
        if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
            $existingInvoiceItem = InvoiceItem::query()
                ->where('penjualan_id', (int) $detail->id_penjualan)
                ->where('supplier_id', (int) $supplierId)
                ->where('produk_id', (int) $detail->id_produk)
                ->orderBy('id', 'desc')
                ->first();
        }
        if (! $existingInvoiceItem) {
            $existingInvoiceItem = InvoiceItem::query()
                ->where('invoice_id', (int) $invoice->id)
                ->where('supplier_id', (int) $supplierId)
                ->where('produk_id', (int) $detail->id_produk)
                ->where(function ($q) {
                    $q->whereNull('amount_paid')
                        ->orWhereRaw('COALESCE(amount_paid,0) = 0');
                })
                ->orderBy('id', 'desc')
                ->first();
        }

        if ($existingInvoiceItem) {
            $existingInvoiceItem->quantity = (float) ($existingInvoiceItem->quantity ?? 0) + $qty;
            $existingInvoiceItem->amount = (float) ($existingInvoiceItem->amount ?? 0) + $amount;
            $existingInvoiceItem->balance = (float) ($existingInvoiceItem->balance ?? 0) + $amount;
            $existingInvoiceItem->status = 'Not paid';
            if (Schema::hasColumn('invoice_items', 'penjualan_id') && empty($existingInvoiceItem->penjualan_id)) {
                $existingInvoiceItem->penjualan_id = (int) $detail->id_penjualan;
            }
            $existingInvoiceItem->updated_at = now();
            $existingInvoiceItem->save();
        } else {
            $penjualan = Penjualan::find((int) $detail->id_penjualan);
            $produk = Produk::find((int) $detail->id_produk);
            $supplier = Supplier::find((int) $supplierId);
            $posted = ($penjualan && $produk && $supplier)
                ? $ledger->createConsignmentInvoiceItemIfNeeded(
                    $penjualan,
                    $produk,
                    $supplier,
                    (int) $qty,
                    (int) ($detail->diskon ?? 0)
                )
                : null;
            if (! $posted) {
                return response()->json([
                    'message' => 'Consignment ledger already has this sale line posted.',
                ], 422);
            }
        }

        if (! $existingInvoiceItem) {
            $invoice->refresh();
            $invoice->total = (float) InvoiceItem::where('invoice_id', $invoice->id)->sum('amount');
            $invoice->save();
        } else {
            $invoice->total = (float) ($invoice->total ?? 0) + $amount;
            $invoice->save();
        }

        return response()->json([
            'message' => 'Assigned to supplier under consignment sales successfully.',
        ]);
    }

    /**
     * Export detailed sales report as PDF
     */
    public function exportDetailedReportPdf(Request $request)
    {
        // Only admins can export reports
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can export reports.');
        }

        $period = $request->input('period', 'today'); // today, week, month, annual, custom
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $supplierType = $request->input('supplier_type', 'all');

        // Determine date range based on period
        switch ($period) {
            case 'today':
                $startDate = Carbon::today()->format('Y-m-d');
                $endDate = Carbon::today()->format('Y-m-d');
                break;
            case 'week':
                $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
                $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
                $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            case 'annual':
                $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
                $endDate = Carbon::now()->endOfYear()->format('Y-m-d');
                break;
            case 'custom':
                if (!$startDate || !$endDate) {
                    return redirect()->back()->withErrors(['error' => 'Please select start date and end date for custom period.']);
                }
                break;
            default:
                $startDate = Carbon::today()->format('Y-m-d');
                $endDate = Carbon::today()->format('Y-m-d');
        }

        // Query sales with details
        $query = Penjualan::with(['member', 'user'])
            ->whereDate('saledate', '>=', $startDate)
            ->whereDate('saledate', '<=', $endDate)
            ->orderBy('saledate', 'asc')
            ->orderBy('created_at', 'asc');

        $this->applyNormalSalesListStatusScope($query);

        // Exclude management sales
        if (Schema::hasColumn('penjualan', 'sale_type')) {
            $query->where(function($q) {
                $q->where('sale_type', 'normal')->orWhereNull('sale_type');
            });
        }

        $sales = $query->get();

        // Get sales details for each sale (filtered by supplier type)
        $reportData = [];
        $totalSales = 0;
        $totalItems = 0;
        $totalDiscount = 0;
        $totalTax = 0;

        foreach ($sales as $sale) {
            $details = PenjualanDetail::with(['produk', 'produk.supplier'])
                ->where('id_penjualan', $sale->id_penjualan)
                ->get();

            // Filter by supplier type (consignment / cash)
            if ($supplierType !== 'all') {
                $details = $details->filter(function ($detail) use ($supplierType) {
                    $supplier = $detail->produk->supplier ?? null;
                    $supplierMop = $supplier ? strtoupper(trim($supplier->mop ?? '')) : '';
                    if ($supplierType === 'consignment') {
                        return $supplierMop === 'CONSIGNMENT';
                    }
                    if ($supplierType === 'cash') {
                        return $supplierMop === 'CASH';
                    }
                    return true;
                })->values();
            }

            // Skip sales with no matching details when filtered
            if ($details->isEmpty()) {
                continue;
            }

            $saleTotal = floatval($sale->bayar ?? $sale->total_harga ?? 0);
            $saleTax = 0;
            if (Schema::hasColumn('penjualan', 'tax') && $sale->tax) {
                $saleTax = floatval($sale->tax);
            } else {
                $saleTax = round($saleTotal * (16 / 116), 2);
            }

            $reportData[] = [
                'sale' => $sale,
                'details' => $details,
                'tax' => $saleTax,
            ];

            // Recalculate totals from filtered details
            $filteredSubtotal = $details->sum('subtotal');
            $totalSales += $filteredSubtotal;
            $totalItems += $details->sum('jumlah');
            $totalDiscount += floatval($sale->diskon ?? 0);
            $totalTax += round($filteredSubtotal * (16 / 116), 2);
        }

        $setting = Setting::first();

        // Generate period label
        $periodLabel = ucfirst($period);
        if ($period === 'custom') {
            $periodLabel = Carbon::parse($startDate)->format('d/m/Y') . ' - ' . Carbon::parse($endDate)->format('d/m/Y');
        } elseif ($period === 'today') {
            $periodLabel = Carbon::parse($startDate)->format('d F Y');
        } elseif ($period === 'week') {
            $periodLabel = Carbon::parse($startDate)->format('d/m/Y') . ' - ' . Carbon::parse($endDate)->format('d/m/Y');
        } elseif ($period === 'month') {
            $periodLabel = Carbon::parse($startDate)->format('F Y');
        } elseif ($period === 'annual') {
            $periodLabel = Carbon::parse($startDate)->format('Y');
        }

        $supplierTypeLabel = $supplierType === 'all' ? '' : ($supplierType === 'consignment' ? ' (Consignment Only)' : ' (Cash Only)');

        $pdf = PDF::loadView('penjualan.detailed_report_pdf', compact(
            'reportData',
            'startDate',
            'endDate',
            'periodLabel',
            'supplierTypeLabel',
            'totalSales',
            'totalItems',
            'totalDiscount',
            'totalTax',
            'setting'
        ));

        $pdf->setPaper('a4', 'portrait');
        
        $filename = 'detailed_sales_report_' . $period . '_' . date('Y-m-d', strtotime($startDate)) . '.pdf';
        
        return $pdf->stream($filename);
    }

    /**
     * Get detailed sales report data for DataTables
     */
    public function detailedReportData(Request $request)
    {
        // Only admins can access the Detailed Sales Report
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access the Detailed Sales Report.');
        }

        $period = $request->input('period', 'today');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Determine date range based on period
        switch ($period) {
            case 'today':
                $startDate = Carbon::today()->format('Y-m-d');
                $endDate = Carbon::today()->format('Y-m-d');
                break;
            case 'week':
                $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
                $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
                $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            case 'annual':
                $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
                $endDate = Carbon::now()->endOfYear()->format('Y-m-d');
                break;
            case 'custom':
                if (!$startDate || !$endDate) {
                    return response()->json(['error' => 'Please select start date and end date for custom period.'], 400);
                }
                break;
            default:
                $startDate = Carbon::today()->format('Y-m-d');
                $endDate = Carbon::today()->format('Y-m-d');
        }

        // Query sales with details
        $query = Penjualan::with(['member', 'user'])
            ->whereDate('saledate', '>=', $startDate)
            ->whereDate('saledate', '<=', $endDate)
            ->orderBy('saledate', 'asc')
            ->orderBy('created_at', 'asc');

        $this->applyNormalSalesListStatusScope($query);

        // Exclude management sales
        if (Schema::hasColumn('penjualan', 'sale_type')) {
            $query->where(function($q) {
                $q->where('sale_type', 'normal')->orWhereNull('sale_type');
            });
        }

        $sales = $query->get();

        $supplierType = $request->input('supplier_type', 'all'); // all, consignment, cash

        // Calculate payment totals by method (Cash, Mpesa, Card)
        $paymentTotals = ['Cash' => 0, 'Mpesa' => 0, 'Card' => 0];
        foreach ($sales as $sale) {
            $details = PenjualanDetail::with(['produk', 'produk.supplier'])
                ->where('id_penjualan', $sale->id_penjualan)
                ->get();

            if ($supplierType !== 'all') {
                $details = $details->filter(function ($detail) use ($supplierType) {
                    $supplier = $detail->produk->supplier ?? null;
                    $supplierMop = $supplier ? strtoupper(trim($supplier->mop ?? '')) : '';
                    if ($supplierType === 'consignment') return $supplierMop === 'CONSIGNMENT';
                    if ($supplierType === 'cash') return $supplierMop === 'CASH';
                    return true;
                })->values();
            }
            if ($details->isEmpty()) continue;

            $amount = floatval($sale->bayar ?? $sale->total_harga ?? 0);
            $paymentMethod = $sale->payment_method ?? 'Cash';

            if ($paymentMethod === 'Split' && Schema::hasColumn('penjualan', 'payment_split_details') && $sale->payment_split_details) {
                $split = json_decode($sale->payment_split_details, true);
                if (is_array($split)) {
                    $paymentTotals['Cash'] += floatval($split['cash'] ?? 0);
                    $paymentTotals['Mpesa'] += floatval($split['mpesa'] ?? 0);
                    $paymentTotals['Card'] += floatval($split['card'] ?? 0);
                }
            } else {
                $methodKey = in_array($paymentMethod, ['Cash', 'Mpesa', 'Card']) ? $paymentMethod : 'Cash';
                $paymentTotals[$methodKey] += $amount;
            }
        }

        // Flatten all items from all sales into a single array
        // Allocate sale-level discount to line items proportionally so subtotals reflect discounts
        $allItems = [];
        foreach ($sales as $sale) {
            $details = PenjualanDetail::with(['produk', 'produk.supplier'])
                ->where('id_penjualan', $sale->id_penjualan)
                ->get();

            // Filter by supplier type (consignment / cash)
            if ($supplierType !== 'all') {
                $details = $details->filter(function ($detail) use ($supplierType) {
                    $supplier = $detail->produk->supplier ?? null;
                    $supplierMop = $supplier ? strtoupper(trim($supplier->mop ?? '')) : '';
                    if ($supplierType === 'consignment') {
                        return $supplierMop === 'CONSIGNMENT';
                    }
                    if ($supplierType === 'cash') {
                        return $supplierMop === 'CASH';
                    }
                    return true;
                })->values();
            }

            $saleDiscountAmount = $sale->getSaleDiscountAmount();
            $detailSubtotalSum = $details->sum('subtotal');

            foreach ($details as $detail) {
                $detailSubtotal = floatval($detail->subtotal);
                $allocatedSaleDiscount = $detailSubtotalSum > 0 && $saleDiscountAmount > 0
                    ? round($detailSubtotal / $detailSubtotalSum * $saleDiscountAmount, 2)
                    : 0;
                $itemDiscountAmount = $detail->harga_jual * $detail->jumlah * (floatval($detail->diskon ?? 0) / 100);
                $totalDiscountAmount = $itemDiscountAmount + $allocatedSaleDiscount;
                $adjustedSubtotal = $detailSubtotal - $allocatedSaleDiscount;

                $saleDiscountType = $sale->discount_type ?? 'percentage';
                $saleDiskon = (int) round($sale->diskon ?? 0);
                $itemDiskon = (int) round($detail->diskon ?? 0);

                // Discount(%): show percentage when applicable
                $discountPctDisplay = ($saleDiscountType === 'percentage' && $saleDiskon > 0)
                    ? $saleDiskon . '%'
                    : ($itemDiskon > 0 ? $itemDiskon . '%' : '-');

                $allItems[] = [
                    'date' => $sale->saledate ? date('d/m/Y', strtotime($sale->saledate)) : date('d/m/Y', strtotime($sale->created_at)),
                    'receiptno' => $sale->receiptno,
                    'product_code' => $detail->produk->kode_produk ?? 'N/A',
                    'product_name' => $detail->produk->nama_produk ?? 'N/A',
                    'quantity' => $detail->jumlah,
                    'unit_price' => floatval($detail->harga_jual),
                    'discount' => floatval($detail->diskon),
                    'discount_amount' => $totalDiscountAmount,
                    'discount_percentage_display' => $discountPctDisplay,
                    'subtotal' => $adjustedSubtotal,
                ];
            }
        }

        return DataTables::of(collect($allItems))
            ->addIndexColumn()
            ->addColumn('date', function ($item) {
                return $item['date'];
            })
            ->addColumn('receiptno', function ($item) {
                return $item['receiptno'];
            })
            ->addColumn('product_code', function ($item) {
                return $item['product_code'];
            })
            ->addColumn('product_name', function ($item) {
                return $item['product_name'];
            })
            ->addColumn('quantity', function ($item) {
                return number_format($item['quantity'], 0);
            })
            ->addColumn('unit_price', function ($item) {
                return 'Ksh ' . number_format($item['unit_price'], 2);
            })
            ->addColumn('discount_percentage', function ($item) {
                return $item['discount_percentage_display'] ?? '-';
            })
            ->addColumn('discount', function ($item) {
                $amt = $item['discount_amount'] ?? 0;
                if ($amt <= 0) return '-';
                $isPct = ($item['discount_percentage_display'] ?? '-') !== '-';
                return 'Ksh ' . number_format($isPct ? round($amt, 0) : $amt, $isPct ? 0 : 2);
            })
            ->addColumn('subtotal', function ($item) {
                return 'Ksh ' . number_format($item['subtotal'], 2);
            })
            ->rawColumns([])
            ->with('payment_totals', $paymentTotals)
            ->make(true);
    }

    /**
     * Show import form for sales
     */
    public function importForm()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                if (request()->expectsJson() || request()->ajax()) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                abort(403, 'Unauthorized. Please log in to access this page.');
            }
            
            if (!$user->hasRole('admin')) {
                if (request()->expectsJson() || request()->ajax()) {
                    return response()->json(['error' => 'Unauthorized. Only administrators can import sales.'], 403);
                }
                abort(403, 'Unauthorized. Only administrators can import sales.');
            }

            // Get session data safely
            $errors = session('errors', []);
            $warnings = session('warnings', []);
            $success = session('success', null);
            
            return view('penjualan.import', [
                'sessionErrors' => is_array($errors) ? $errors : [],
                'sessionWarnings' => is_array($warnings) ? $warnings : [],
                'sessionSuccess' => $success
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error loading import form: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json(['error' => 'Error loading import page: ' . $e->getMessage()], 500);
            }
            
            abort(500, 'Error loading import page: ' . $e->getMessage());
        }
    }

    /**
     * Process sales import from Excel file
     */
    public function import(Request $request)
    {
        \Log::info('Import method called', [
            'has_file' => $request->hasFile('file'),
            'is_ajax' => $request->ajax(),
            'expects_json' => $request->expectsJson(),
            'x_requested_with' => $request->header('X-Requested-With')
        ]);
        
        // Increase memory limit and execution time for large files (100k+ rows)
        ini_set('memory_limit', '1024M');
        set_time_limit(900); // 15 minutes for reading file
        
        $user = auth()->user();
        
        if (!$user) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Please log in to access this page.'
                ], 403)->header('Content-Type', 'application/json');
            }
            abort(403, 'Unauthorized. Please log in to access this page.');
        }
        
        if (!$user->hasRole('admin')) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized. Only administrators can import sales.'
                ], 403)->header('Content-Type', 'application/json');
            }
            abort(403, 'Unauthorized. Only administrators can import sales.');
        }

        // Initialize progress key early (before any processing)
        $progressKey = 'import_progress_' . auth()->id() . '_' . time();

        $importMode = $request->input('import_mode', 'standard') === 'reimport' ? 'reimport' : 'standard';
        $yearFrom = (int) $request->input('reimport_year_from', 0);
        $yearTo = (int) $request->input('reimport_year_to', 0);
        if ($importMode === 'reimport') {
            if ($yearFrom < 1) {
                $yearFrom = 2007;
            }
            if ($yearTo < 1) {
                $yearTo = (int) date('Y');
            }
            if ($yearFrom > $yearTo) {
                [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
            }
        }

        Cache::put("import_mode_{$progressKey}", $importMode, 900);
        Cache::put("import_year_from_{$progressKey}", $yearFrom, 900);
        Cache::put("import_year_to_{$progressKey}", $yearTo, 900);
        
        // Initialize progress - file validation
        Cache::put($progressKey, [
            'status' => 'processing',
            'current' => 0,
            'total' => 0,
            'percentage' => 1,
            'message' => $importMode === 'reimport'
                ? "Validating file (reimport {$yearFrom}–{$yearTo})..."
                : 'Validating file...',
            'import_mode' => $importMode,
        ], 600);

        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls|max:204800', // 200MB max (value in KB)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'progress_key' => $progressKey
                ], 422)->header('Content-Type', 'application/json');
            }
            return redirect()->route('penjualan.import')
                ->withErrors($e->errors());
        }

        try {
            $file = $request->file('file');
            
            if (!$file || !$file->isValid()) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid file uploaded. Please select a valid Excel file.',
                        'progress_key' => $progressKey
                    ], 422)->header('Content-Type', 'application/json');
                }
                return redirect()->route('penjualan.import')
                    ->withErrors(['error' => 'Invalid file uploaded. Please select a valid Excel file.']);
            }
            
            try {
                DB::connection()->getPdo();
            } catch (\Exception $dbError) {
                $errorMsg = 'Database connection failed. Please make sure MySQL/XAMPP is running. Error: ' . $dbError->getMessage();
                \Log::error($errorMsg);
                Cache::put($progressKey, [
                    'status' => 'error',
                    'current' => 0,
                    'total' => 0,
                    'percentage' => 0,
                    'message' => $errorMsg,
                ], 600);

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'error' => $errorMsg,
                        'progress_key' => $progressKey,
                    ], 500)->header('Content-Type', 'application/json');
                }

                return redirect()->route('penjualan.import')
                    ->withErrors(['error' => $errorMsg]);
            }

            Storage::disk('local')->makeDirectory('imports');
            $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
            $storedPath = $file->storeAs('imports', $progressKey . '.' . $extension);

            if (! $storedPath) {
                $errorMsg = 'Could not save uploaded file on server.';
                Cache::put($progressKey, [
                    'status' => 'error',
                    'message' => $errorMsg,
                ], 600);

                return response()->json([
                    'success' => false,
                    'error' => $errorMsg,
                    'progress_key' => $progressKey,
                ], 500)->header('Content-Type', 'application/json');
            }

            Cache::put($progressKey, [
                'status' => 'processing',
                'current' => 0,
                'total' => 0,
                'percentage' => 2,
                'message' => 'File uploaded. Waiting for import worker — reading Excel will start shortly...',
                'needs_worker' => true,
            ], 900);

            ReadSalesImportExcelJob::dispatch($progressKey, $storedPath, (int) auth()->id())
                ->onQueue('imports');

            $modeLabel = $importMode === 'reimport'
                ? "reimport (years {$yearFrom}–{$yearTo})"
                : 'standard import';

            return response()->json([
                'success' => true,
                'message' => "File uploaded. {$modeLabel} queued — ensure the import worker is running (see instructions on this page).",
                'progress_key' => $progressKey,
                'import_mode' => $importMode,
                'needs_worker' => true,
            ])->header('Content-Type', 'application/json');

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            $errorMessage = 'Failed to import sales: ' . $e->getMessage();
            \Log::error('Error importing sales: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Mark progress as error
            if (isset($progressKey)) {
                Cache::put($progressKey, [
                    'status' => 'error',
                    'current' => isset($currentIndex) ? $currentIndex : 0,
                    'total' => isset($totalReceipts) ? $totalReceipts : 0,
                    'percentage' => isset($currentIndex) && isset($totalReceipts) && $totalReceipts > 0 
                        ? round(($currentIndex / $totalReceipts) * 100, 2) : 0,
                    'message' => 'Error: ' . $e->getMessage()
                ], 600);
            }
            
            // Always return JSON for AJAX requests
            if ($request->expectsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                    'progress_key' => $progressKey ?? null
                ], 500)->header('Content-Type', 'application/json');
            }
            
            return redirect()->route('penjualan.import')
                ->withErrors(['error' => $errorMessage]);
        } catch (\Throwable $e) {
            // Catch any fatal errors or exceptions not caught above
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            \Log::error('Fatal error in import method: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->expectsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'error' => 'An unexpected error occurred: ' . $e->getMessage(),
                    'progress_key' => $progressKey ?? null
                ], 500)->header('Content-Type', 'application/json');
            }
            
            return redirect()->route('penjualan.import')
                ->withErrors(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
        }
    }

    /**
     * Check import progress
     */
    public function importProgress(Request $request)
    {
        $progressKey = $request->input('progress_key');
        
        if (!$progressKey) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Progress key not provided'
            ], 404);
        }
        
        $progress = Cache::get($progressKey);
        
        if (!$progress) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Progress not found or expired'
            ], 404);
        }

        if (Schema::hasTable('jobs')) {
            $pendingImports = (int) DB::table('jobs')->where('queue', 'imports')->count();
            $reservedImports = (int) DB::table('jobs')->where('queue', 'imports')->whereNotNull('reserved_at')->count();
            $progress['pending_import_jobs'] = $pendingImports;
            $progress['processing_import_jobs'] = $reservedImports;

            $message = strtolower((string) ($progress['message'] ?? ''));
            $activelyProcessing = $reservedImports > 0
                || str_contains($message, 'reading excel')
                || str_contains($message, 'grouped')
                || str_contains($message, 'importing sales')
                || str_contains($message, 'processing receipt');

            // Only warn when jobs are waiting and nothing is actively running
            if ($pendingImports > 0 && ! $activelyProcessing && ($progress['percentage'] ?? 0) < 40) {
                $progress['needs_worker'] = true;
                $progress['worker_hint'] = 'Import worker required: run php artisan queue:work --queue=imports --timeout=7200 in a terminal, then refresh or wait.';
            } else {
                $progress['needs_worker'] = false;
            }
        }
        
        return response()->json($progress);
    }

    /**
     * Parse POS sale date from dd/mm/yyyy or yyyy-mm-dd.
     */
    private function parsePosSaleDateInput(?string $raw): Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return Carbon::now();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
            return Carbon::createFromFormat(
                'd/m/Y',
                sprintf('%d/%d/%d', (int) $m[1], (int) $m[2], (int) $m[3])
            )->startOfDay();
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Exception $e) {
            return Carbon::now();
        }
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return Carbon::today()->toDateString();
        }

        // Try to parse as Excel date (numeric)
        if (is_numeric($dateString)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateString);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Not an Excel date
            }
        }

        // Try Carbon parse
        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return Carbon::today()->toDateString();
        }
    }
}
// visit "codeastro" for more projects!