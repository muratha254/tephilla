<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pembelian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PembelianDetail;
use App\Models\Produk;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\Supplier;
use App\Http\Resources\PaymentResource;
use PDF;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\InvoicePayment;
use App\Models\Setting;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Services\EnsureSaleSupplierLedgerService;
use App\Services\MissingBuyingPriceLedgerService;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PaymentController extends Controller
{
    public function exportPaymentsPdf(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));

        $payments = Payment::with('supplier')
            ->when($startDate, function ($q) use ($startDate) {
                $q->whereDate('date', '>=', $startDate);
            })
            ->when($endDate, function ($q) use ($endDate) {
                $q->whereDate('date', '<=', $endDate);
            })
            ->orderBy('date', 'desc')
            ->get();

        $totalAmount = $payments->sum('amount');
        $setting = Setting::first();

        $pdf = PDF::loadView('payment.payments_pdf', [
            'payments' => $payments,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalAmount' => $totalAmount,
            'setting' => $setting,
        ]);

        return $pdf->download('payments_'. $startDate .'_to_'. $endDate .'.pdf');
    }
    public function index(Request $request)
    {
        $query = Pembelian::query();

        if ($request->filled('start_date')) {
            $query->whereDate('purchasedate2', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('purchasedate2', '<=', $request->input('end_date'));
        }

        // Get all Consignment suppliers for the filter dropdown
        $suppliers = Supplier::where('mop', 'Consignment')->orderBy('nama')->get();

        return view('payment.index', compact('suppliers'));
    }
    
    public function suppliersWithPendingPayments(Request $request)
    {
        // Get suppliers with their pending payment amounts for AJAX
        $suppliers = Supplier::orderBy('nama')->get()->map(function($supplier) {
            // Calculate total pending consignment payment
            $pendingResult = InvoiceItem::where('supplier_id', $supplier->id_supplier)
                ->select(DB::raw('COALESCE(SUM(amount - COALESCE(amount_paid, 0)), 0) as pending'))
                ->first();
            
            $pendingAmount = $pendingResult && isset($pendingResult->pending) ? floatval($pendingResult->pending) : 0;
            
            return [
                'id_supplier' => $supplier->id_supplier,
                'nama' => $supplier->nama,
                'telepon' => $supplier->telepon,
                'alamat' => $supplier->alamat,
                'pending_amount' => $pendingAmount,
            ];
        })->sortByDesc(function($supplier) {
            return $supplier['pending_amount'];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $suppliers
        ]);
    }

    public function data(Request $request)
    {
        $query = Payment::join('supplier', 'payments.supplier_id', '=', 'supplier.id_supplier')
                    ->select('payments.*', 'supplier.nama', 'supplier.telepon')
                    ->orderBy('payments.id', 'desc');

        if ($request->filled('reference_number')) {
            $query->where('payments.reference_number', 'like', '%' . $request->input('reference_number') . '%');
        }

        if ($request->filled('supplier_id')) {
            $query->where('payments.supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('payments.date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('payments.date', '<=', $request->input('end_date'));
        }

        $payments = $query->get();

        return datatables()
            ->of($payments)
            ->addIndexColumn()
            // Expose explicit payment_method column for DataTables
            ->addColumn('payment_method', function ($payment) {
                $method = $payment->payment_method ?? null;
                if (!$method || strtolower($method) === 'supplier_payment') {
                    return 'Cash';
                }
                return $method;
            })
            ->addColumn('total_harga', function ($payment) {
                return $payment->amount;
            })
            ->addColumn('date', function ($payment) {
                return $payment->date
                    ? Carbon::parse($payment->date)->format('Y-m-d')
                    : '';
            })
            ->addColumn('telepon', function ($payment) {
                return $payment->telepon;
            })
            ->addColumn('reference_number', function ($payment) {
                return $payment->reference_number;
            })
            ->addColumn('uniqid', function ($payment) {
                return $payment->uniqid;
            })
            ->addColumn('supplier', function ($payment) {
                return $payment->nama;
            })
            ->addColumn('tanggal', function ($payment) {
                if (! $payment->date) {
                    return '';
                }
                $c = Carbon::parse($payment->date);

                return '<span data-order="'.e($c->format('Y-m-d')).'">'.$c->format('d/m/Y').'</span>';
            })
            ->addColumn('aksi', function ($payment) {
                $u = auth()->user();
                $buttons = '<div class="btn-group">';
                if (!$u || $u->can_read || $u->hasModulePermission('consignment', 'read') || $u->hasRole('admin')) {
                    $buttons .= '<button onclick="viewPaymentDetails('.$payment->id.')" class="btn btn-xs btn-info btn-flat"><i class="fa fa-eye"></i> View</button>';
                }
                if ($u && $u->can_update) {
                    $buttons .= '<button onclick="deletePayment(`'. route('payment.undo', $payment->id) .'`)" class="btn btn-xs btn-warning btn-flat"><i class="fa fa-undo"></i> Undo</button>';
                }
                if ($u && $u->can_delete) {
                    $buttons .= '<button onclick="deleteData(`'. route('payment.destroy', $payment->id) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>';
                }
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['aksi', 'tanggal'])
            ->make(true);
    }

    public function create(Request $request, $id)
    {
        // Optional: allow preselecting payment mode via query param and persist in session
        // Example: /payment/{id}/create?mode=Mpesa
        $modeFromQuery = $request->query('mode');
        if (!empty($modeFromQuery)) {
            session(['forced_payment_mode' => $modeFromQuery]);
        }

        $id_pembelian = session('id_pembelian');
        $produk = Produk::orderBy('nama_produk')->get();
        $supplier = Supplier::where('id_supplier', '=', $id)->first();
        if (!$supplier) {
            abort(404, 'Supplier not found.');
        }
        $pembelian = $id_pembelian ? Pembelian::find($id_pembelian) : null;
        $diskon = $pembelian ? ($pembelian->diskon ?? 0) : 0;

        // Calculate total outstanding balance
        // Get all invoice items with outstanding balance (don't group - show all items separately)
        // Filter to only show items with outstanding balance > 0
        $invoiceItemsQuery = InvoiceItem::where('invoice_items.supplier_id', $id)
            ->whereRaw('invoice_items.amount - COALESCE(invoice_items.amount_paid, 0) > 0')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('produk', 'invoice_items.produk_id', '=', 'produk.id_produk')
            ->leftJoin('shops', 'produk.shop_id', '=', 'shops.id')
            ->leftJoin('penjualan', 'invoice_items.penjualan_id', '=', 'penjualan.id_penjualan')
            ->select(
                'produk.*', 
                'invoices.*', 
                'invoice_items.*',
                'shops.shop_name',
                DB::raw('COALESCE(invoice_items.amount_paid, 0) as paid_amount'),
                DB::raw('invoice_items.amount - COALESCE(invoice_items.amount_paid, 0) as outstanding_balance')
            );
        
        // Apply date range filter if provided - filter by sale date (when receipt was sold), not invoice_item created_at
        if ($request->filled('start_date')) {
            $invoiceItemsQuery->where(function($q) use ($request) {
                // If penjualan exists, use saledate or created_at from penjualan
                $q->where(function($subQ) use ($request) {
                    $subQ->whereNotNull('penjualan.id_penjualan')
                         ->where(function($dateQ) use ($request) {
                             $dateQ->whereDate('penjualan.saledate', '>=', $request->input('start_date'))
                                   ->orWhere(function($dateQ2) use ($request) {
                                       $dateQ2->whereNull('penjualan.saledate')
                                              ->whereDate('penjualan.created_at', '>=', $request->input('start_date'));
                                   });
                         });
                })
                // Fallback to invoice_items.created_at if no penjualan link
                ->orWhere(function($subQ) use ($request) {
                    $subQ->whereNull('penjualan.id_penjualan')
                         ->whereDate('invoice_items.created_at', '>=', $request->input('start_date'));
                });
            });
        }
        if ($request->filled('end_date')) {
            $invoiceItemsQuery->where(function($q) use ($request) {
                // If penjualan exists, use saledate or created_at from penjualan
                $q->where(function($subQ) use ($request) {
                    $subQ->whereNotNull('penjualan.id_penjualan')
                         ->where(function($dateQ) use ($request) {
                             $dateQ->whereDate('penjualan.saledate', '<=', $request->input('end_date'))
                                   ->orWhere(function($dateQ2) use ($request) {
                                       $dateQ2->whereNull('penjualan.saledate')
                                              ->whereDate('penjualan.created_at', '<=', $request->input('end_date'));
                                   });
                         });
                })
                // Fallback to invoice_items.created_at if no penjualan link
                ->orWhere(function($subQ) use ($request) {
                    $subQ->whereNull('penjualan.id_penjualan')
                         ->whereDate('invoice_items.created_at', '<=', $request->input('end_date'));
                });
            });
        }
        
        $invoiceItems = $invoiceItemsQuery->orderByRaw('COALESCE(penjualan.saledate, penjualan.created_at, invoice_items.created_at) DESC')->get();

        // Calculate total outstanding balance from invoice items
        $totalOutstanding = $invoiceItems->sum('outstanding_balance');
        
        // Get supplier opening balance (if not already paid)
        $openingBalance = floatval($supplier->opening_balance ?? 0);
        $openingBalancePaid = floatval($supplier->opening_balance_paid ?? 0);
        $openingBalanceOutstanding = max(0, $openingBalance - $openingBalancePaid);
        
        // Total outstanding including opening balance
        $totalOutstandingWithOpening = $totalOutstanding + $openingBalanceOutstanding;

        // Get date range from request to pass to view
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Get date values from request for the view
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        return view('payment_detail.index', compact(
            'id_pembelian', 
            'produk', 
            'supplier', 
            'diskon', 
            'invoiceItems',
            'startDate',
            'endDate',
            'totalOutstanding',
            'openingBalance',
            'openingBalancePaid',
            'openingBalanceOutstanding',
            'totalOutstandingWithOpening',
            'startDate',
            'endDate'
        ));
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            \Log::info('Raw request data:', $request->all());

            // Decode payment data
            $paymentData = json_decode($request->invoice_data);
            \Log::info('Decoded payment data:', ['data' => $paymentData]);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON data received: ' . json_last_error_msg());
            }

            // Validate required data
            if (empty($paymentData->supplier_id)) {
                \Log::error('Missing supplier_id in payment data', ['data' => $paymentData]);
                throw new \Exception('Supplier ID is required');
            }

            if (empty($paymentData->totalPay) || $paymentData->totalPay <= 0) {
                throw new \Exception('Invalid payment amount');
            }

            // Allow payment even if only opening balance is being paid
            $openingBalancePayment = floatval($paymentData->opening_balance_payment ?? 0);
            if ((empty($paymentData->invoices) || !is_array($paymentData->invoices) || count($paymentData->invoices) === 0) && $openingBalancePayment <= 0) {
                throw new \Exception('No invoice items or opening balance payment provided');
            }

            $supplierId = intval($paymentData->supplier_id);
            \Log::info('Parsed supplier ID:', ['id' => $supplierId]);
            
            // Debug: Log all payment method sources
            \Log::info('Payment method sources:', [
                'request_payment_method' => $request->input('payment_method'),
                'request_paymentMode' => $request->input('paymentMode'),
                'paymentData_payment_method' => $paymentData->payment_method ?? 'not set',
                'all_request_data' => $request->all()
            ]);

            // Validate date range (required)
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            
            if (!$startDate || !$endDate) {
                throw new \Exception('Start Date and End Date are required to filter items for payment.');
            }
            
            if ($startDate > $endDate) {
                throw new \Exception('Start Date cannot be after End Date.');
            }

            // Create payment record
            $payment = new Payment();
            $payment->type = 'supplier_payment';
            $payment->amount = $paymentData->totalPay;
            $payment->date = $request->payment_date; // Use payment_date from request
            $payment->reference_number = 'PAY-' . date('Ymd') . '-' . rand(1000, 9999);
            $payment->uniqid = uniqid();
            $payment->supplier_id = $supplierId;
            
            // Store date range in notes as JSON for later use in PDF export
            $payment->notes = json_encode([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            // Resolve payment method - check multiple sources
            $resolvedMethod = null;
            
            // First check direct request parameter (most reliable)
            if ($request->has('payment_method') && !empty($request->input('payment_method'))) {
                $resolvedMethod = $request->input('payment_method');
            }
            // Then check paymentMode (from form field name)
            elseif ($request->has('paymentMode') && !empty($request->input('paymentMode'))) {
                $resolvedMethod = $request->input('paymentMode');
            }
            // Then check in JSON data
            elseif (isset($paymentData->payment_method) && !empty($paymentData->payment_method)) {
                $resolvedMethod = $paymentData->payment_method;
            }
            // Finally check session value set on create() via query param (?mode=Mpesa)
            elseif (session()->has('forced_payment_mode')) {
                $resolvedMethod = session('forced_payment_mode');
            }
            
            // Normalize the payment method
            if ($resolvedMethod) {
                $normalized = trim($resolvedMethod);
                // Keep the original case but ensure it matches allowed values
                // The dropdown sends: "Cash", "Mpesa", "Cheque" (exact case)
                $allowed = ['Cash', 'Mpesa', 'Cheque'];
                // If it doesn't match exactly, try case-insensitive match
                $found = false;
                foreach ($allowed as $allowedValue) {
                    if (strcasecmp($normalized, $allowedValue) === 0) {
                        $normalized = $allowedValue; // Use the exact allowed value
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $normalized = 'Cash'; // Default if not found
                }
            } else {
                $normalized = 'Cash'; // Default if nothing found
            }
            
            \Log::info('Final payment method:', ['method' => $normalized]);
            $payment->payment_method = $normalized;
            $payment->save();

            $totalPaid = 0;
            
            // Process opening balance payment if any
            if ($openingBalancePayment > 0) {
                $supplier = Supplier::findOrFail($supplierId);
                $currentPaid = floatval($supplier->opening_balance_paid ?? 0);
                $newPaid = $currentPaid + $openingBalancePayment;
                $openingBalance = floatval($supplier->opening_balance ?? 0);
                
                // Don't allow paying more than outstanding opening balance
                if ($newPaid > $openingBalance) {
                    throw new \Exception('Opening balance payment exceeds outstanding amount');
                }
                
                $supplier->opening_balance_paid = $newPaid;
                $supplier->save();
                
                $totalPaid += $openingBalancePayment;
                
                \Log::info('Opening balance payment processed', [
                    'supplier_id' => $supplierId,
                    'amount' => $openingBalancePayment,
                    'total_paid' => $newPaid,
                    'opening_balance' => $openingBalance
                ]);
            }

            foreach ($paymentData->invoices as $item) {
                if (empty($item->item_id)) {
                    throw new \Exception('Invalid invoice item ID');
                }

                $invoiceItem = InvoiceItem::findOrFail($item->item_id);
                
                // Verify invoice belongs to supplier
                if ($invoiceItem->supplier_id != $supplierId) {
                    throw new \Exception('Invoice does not belong to the specified supplier');
                }

                $amountToPay = floatval($item->amount_to_pay);
                if ($amountToPay <= 0) {
                    continue;
                }

                // Create payment item record
                $paymentItem = new PaymentItem();
                $paymentItem->payment_id = $payment->id;
                $paymentItem->invoice_id = $invoiceItem->invoice_id;
                $paymentItem->amount = $amountToPay;
                $paymentItem->narrative = "Payment for invoice item {$invoiceItem->uniqid}";
                $paymentItem->save();

                // Update invoice item balance and amount_paid
                $invoiceItem->amount_paid = floatval($invoiceItem->amount_paid) + $amountToPay;
                $invoiceItem->balance = floatval($invoiceItem->amount) - floatval($invoiceItem->amount_paid);
                $invoiceItem->save();

                // Create invoice payment record
                $invoicePayment = new InvoicePayment();
                $invoicePayment->invoice_id = $invoiceItem->invoice_id;
                $invoicePayment->payment_id = $payment->id;
                $invoicePayment->supplier_id = $supplierId;
                $invoicePayment->amount_paid = $amountToPay;
                $invoicePayment->remaining_balance = $invoiceItem->balance;
                $invoicePayment->payment_reference = $payment->reference_number;
                $invoicePayment->payment_status = 'Completed';
                $invoicePayment->payment_date = $request->payment_date;
                $invoicePayment->save();

                $totalPaid += $amountToPay;
            }

            // Verify total amount matches sum of payment items (including opening balance)
            if (abs($totalPaid - floatval($paymentData->totalPay)) > 0.01) {
                throw new \Exception('Total payment amount does not match sum of payment items. Expected: ' . $paymentData->totalPay . ', Calculated: ' . $totalPaid);
            }

            DB::commit();
            return response()->json([
                'success' => true, 
                'message' => 'Payment processed successfully',
                'payment_id' => $payment->id,
                'redirect' => route('payment.view', $payment->id)
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Payment processing failed: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Payment processing failed: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $detail = PaymentItem::select(
            'payment_items.id',
            'payment_items.payment_id as rId',
            'invoice_items.*',
            'invoice_items.amount as credit',
            'payment_items.amount as paid',
            'invoice_items.balance as debit',
            'invoice_items.quantity as quantity'
        )
        ->join('invoice_items', 'payment_items.invoice_id', '=', 'invoice_items.invoice_id')
        ->where('payment_items.payment_id', $id)
        ->get();

        return datatables()
            ->of($detail)
            ->addIndexColumn()
            ->addColumn('kode_produk', function ($detail) {
                $name = Produk::where('id_produk', '=', $detail->produk_id)->first();
                return $name->kode_produk;
            })
            ->addColumn('price', function ($detail) {
                return $detail->credit;
            })
            ->addColumn('nama_produk', function ($detail) {
                $name = Produk::where('id_produk', '=', $detail->produk_id)->first();
                return $name->nama_produk;
            })
            ->addColumn('nama', function ($detail) {
                return $detail->amount;
            })
            ->addColumn('total', function ($detail) {
                return 'ksh ' . format_uang($detail->paid);
            })
            ->addColumn('balance', function ($detail) {
                return $detail->credit - $detail->paid;
            })
            ->rawColumns(['kode_produk'])
            ->make(true);
    }

    public function paymentDetails($id)
    {
        $payment = Payment::with(['supplier'])->findOrFail($id);
        
        // Get payment items - need to handle the relationship properly
        // Payment items link to invoices, but we need the specific invoice items that were paid
        // Let's get payment items first, then fetch invoice items for each invoice
        $paymentItems = PaymentItem::where('payment_id', $id)->get();
        
        // For each payment item, get the invoice items for that invoice
        $allItems = collect();
        foreach ($paymentItems as $paymentItem) {
            // Get invoice items for this invoice
            $invoiceItems = InvoiceItem::with(['produk', 'invoice'])
                ->where('invoice_id', $paymentItem->invoice_id)
                ->get();
            
            // For each invoice item, create a payment detail item
            foreach ($invoiceItems as $invoiceItem) {
                // Check if this invoice item was actually paid (amount_paid > 0)
                if (floatval($invoiceItem->amount_paid ?? 0) > 0) {
                    $allItems->push([
                        'payment_item_id' => $paymentItem->id,
                        'invoice_item_id' => $invoiceItem->id,
                        'invoice_id' => $invoiceItem->invoice_id,
                        'produk_id' => $invoiceItem->produk_id,
                        'quantity' => $invoiceItem->quantity,
                        'invoice_amount' => floatval($invoiceItem->amount ?? 0),
                        'amount_paid' => floatval($invoiceItem->amount_paid ?? 0),
                        'paid_amount' => floatval($paymentItem->amount ?? 0),
                        'balance' => floatval($invoiceItem->amount ?? 0) - floatval($invoiceItem->amount_paid ?? 0),
                        'produk' => $invoiceItem->produk,
                        'invoice' => $invoiceItem->invoice,
                        'payment_date' => $paymentItem->created_at,
                    ]);
                }
            }
        }
        
        $paymentItems = $allItems;
        
        // Get all invoice items for this supplier to count total consignment items
        $totalConsignmentItems = InvoiceItem::where('supplier_id', $payment->supplier_id)->count();
        
        // Format payment items with product and invoice details
        $items = $paymentItems->map(function($item) {
            $produk = $item['produk'] ?? null;
            $invoice = $item['invoice'] ?? null;
            
            return [
                'id' => $item['payment_item_id'],
                'product_code' => $produk ? $produk->kode_produk : 'N/A',
                'product_name' => $produk ? $produk->nama_produk : 'N/A',
                'quantity' => $item['quantity'] ?? 0,
                'invoice_amount' => $item['invoice_amount'] ?? 0,
                'amount_paid' => $item['amount_paid'] ?? 0,
                'balance' => $item['balance'] ?? 0,
                'invoice_number' => $invoice ? $invoice->invoice_number : 'N/A',
                'payment_date' => $item['payment_date'] ? date('Y-m-d', strtotime($item['payment_date'])) : null,
            ];
        });
        
        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'reference_number' => $payment->reference_number,
                'amount' => floatval($payment->amount),
                'date' => $payment->date ? date('Y-m-d', strtotime($payment->date)) : null,
                'created_at' => $payment->created_at ? $payment->created_at->format('Y-m-d H:i:s') : null,
                'type' => $payment->type ?? 'Cash',
                'payment_method' => $payment->payment_method ?? 'Cash',
            ],
            'supplier' => [
                'id' => $payment->supplier->id_supplier ?? null,
                'name' => $payment->supplier->nama ?? 'N/A',
                'phone' => $payment->supplier->telepon ?? 'N/A',
                'address' => $payment->supplier->alamat ?? 'N/A',
            ],
            'items' => $items,
            'total_items_paid' => $paymentItems->count(),
            'total_consignment_items' => $totalConsignmentItems,
        ]);
    }

    public function viewPayment($id, Request $request)
    {
        $payment = Payment::with(['supplier'])->findOrFail($id);
        
        // Get date range from request, or from payment notes (so view and PDF use same range)
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        if (!$startDate || !$endDate) {
            $notes = $payment->notes ? json_decode($payment->notes, true) : null;
            if ($notes && isset($notes['start_date']) && isset($notes['end_date'])) {
                $startDate = $startDate ?: $notes['start_date'];
                $endDate = $endDate ?: $notes['end_date'];
            }
        }
        if (!$startDate || !$endDate) {
            $startDate = $startDate ?: ($payment->date ? date('Y-m-d', strtotime($payment->date)) : date('Y-m-d'));
            $endDate = $endDate ?: ($payment->date ? date('Y-m-d', strtotime($payment->date)) : date('Y-m-d'));
        }
        
        // Get payment items
        $paymentItems = PaymentItem::where('payment_id', $id)->get();
        
        // For each payment item, get the invoice items for that invoice
        $allItems = collect();
        foreach ($paymentItems as $paymentItem) {
            // Get invoice items for this invoice
            $invoiceItemsQuery = InvoiceItem::with(['produk', 'invoice'])
                ->where('invoice_id', $paymentItem->invoice_id);
            
            // Apply date filters if provided
            if ($startDate) {
                $invoiceItemsQuery->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $invoiceItemsQuery->whereDate('created_at', '<=', $endDate);
            }
            
            $invoiceItems = $invoiceItemsQuery->get();
            
            // If date filter returned no items, show all paid items for this invoice (so payment view is never empty)
            if ($invoiceItems->isEmpty()) {
                $invoiceItems = InvoiceItem::with(['produk', 'invoice'])
                    ->where('invoice_id', $paymentItem->invoice_id)
                    ->get();
            }
            
            // For each invoice item, create a payment detail item
            foreach ($invoiceItems as $invoiceItem) {
                // Check if this invoice item was actually paid (amount_paid > 0)
                if (floatval($invoiceItem->amount_paid ?? 0) > 0) {
                    $allItems->push([
                        'payment_item_id' => $paymentItem->id,
                        'invoice_item_id' => $invoiceItem->id,
                        'invoice_id' => $invoiceItem->invoice_id,
                        'produk_id' => $invoiceItem->produk_id,
                        'quantity' => $invoiceItem->quantity,
                        'invoice_amount' => floatval($invoiceItem->amount ?? 0),
                        'amount_paid' => floatval($invoiceItem->amount_paid ?? 0),
                        'paid_amount' => floatval($paymentItem->amount ?? 0),
                        'balance' => floatval($invoiceItem->amount ?? 0) - floatval($invoiceItem->amount_paid ?? 0),
                        'produk' => $invoiceItem->produk,
                        'invoice' => $invoiceItem->invoice,
                        'payment_date' => $paymentItem->created_at,
                        'invoice_date' => $invoiceItem->created_at,
                    ]);
                }
            }
        }
        
        // Get all invoice items for this supplier to count total consignment items
        $totalConsignmentItems = InvoiceItem::where('supplier_id', $payment->supplier_id)->count();
        
        // Format payment items with product and invoice details
        $items = $allItems->map(function($item) {
            $produk = $item['produk'] ?? null;
            $invoice = $item['invoice'] ?? null;
            
            // Calculate unit amount (cost) as buying price
            $quantity = floatval($item['quantity'] ?? 0);
            $buyingPrice = 0;
            
            if ($produk && isset($produk->harga_beli) && $produk->harga_beli > 0) {
                $buyingPrice = floatval($produk->harga_beli);
            } else {
                // Fallback: calculate unit price from invoice amount if product buying price not available
                $invoiceAmount = floatval($item['invoice_amount'] ?? 0);
                $buyingPrice = $quantity > 0 ? ($invoiceAmount / $quantity) : 0;
            }
            
            return [
                'id' => $item['payment_item_id'],
                'product_code' => $produk ? $produk->kode_produk : 'N/A',
                'product_name' => $produk ? $produk->nama_produk : 'N/A',
                'quantity' => $item['quantity'] ?? 0,
                'unit_amount' => $buyingPrice,
                'invoice_amount' => $item['invoice_amount'] ?? 0,
                'amount_paid' => $item['amount_paid'] ?? 0,
                'balance' => $item['balance'] ?? 0,
                'invoice_number' => $invoice ? $invoice->invoice_number : 'N/A',
                'payment_date' => $item['payment_date'] ? date('Y-m-d', strtotime($item['payment_date'])) : null,
            ];
        });
        
        return view('payment.view', compact('payment', 'items', 'totalConsignmentItems', 'startDate', 'endDate'));
    }

    public function exportPaymentPdf($id, Request $request)
    {
        $payment = Payment::with(['supplier'])->findOrFail($id);
        
        // Get start and end dates from request, or from payment notes (stored during payment creation)
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        // If not in request, try to get from payment notes
        if (!$startDate || !$endDate) {
            try {
                $notes = $payment->notes ? json_decode($payment->notes, true) : null;
                if ($notes && isset($notes['start_date']) && isset($notes['end_date'])) {
                    $startDate = $startDate ?: $notes['start_date'];
                    $endDate = $endDate ?: $notes['end_date'];
                }
            } catch (\Exception $e) {
                // If notes don't contain date range, use payment date as fallback
            }
        }
        
        // Final fallback to payment date
        if (!$startDate || !$endDate) {
            $startDate = $startDate ?: ($payment->date ? date('Y-m-d', strtotime($payment->date)) : date('Y-m-d'));
            $endDate = $endDate ?: ($payment->date ? date('Y-m-d', strtotime($payment->date)) : date('Y-m-d'));
        }
        
        $paymentItems = PaymentItem::where('payment_id', $id)->get();
        
        $totalConsignmentItems = InvoiceItem::where('supplier_id', $payment->supplier_id)->count();
        
        // Map of how much was paid per invoice_id in THIS payment
        $amountPaidByInvoiceId = $paymentItems
            ->groupBy('invoice_id')
            ->map(function ($group) {
                return $group->sum('amount');
            });
        
        $invoiceIds = $paymentItems->pluck('invoice_id')->unique()->toArray();
        $allItems = collect();
        
        if (!empty($invoiceIds)) {
            // Base query: all invoice items for this payment's invoices
            $baseQuery = InvoiceItem::with(['produk.shop', 'invoice', 'penjualan'])
                ->whereIn('invoice_items.invoice_id', $invoiceIds)
                ->select('invoice_items.*');
            
            // Use same date filter as payment view: invoice_items.created_at (so PDF matches "Items Paid For" on screen)
            $invoiceItems = (clone $baseQuery)
                ->whereRaw('DATE(invoice_items.created_at) >= ?', [$startDate])
                ->whereRaw('DATE(invoice_items.created_at) <= ?', [$endDate])
                ->get();
            
            // If date filter returns no items, show all items so the list is never empty
            if ($invoiceItems->isEmpty()) {
                $invoiceItems = $baseQuery->get();
            }
            
            // Per-invoice totals (all items) for proportional amount_paid
            $invoiceTotalAmount = [];
            foreach ($invoiceItems as $invoiceItem) {
                $invId = $invoiceItem->invoice_id;
                if (!isset($invoiceTotalAmount[$invId])) {
                    $invoiceTotalAmount[$invId] = 0;
                }
                $invoiceTotalAmount[$invId] += floatval($invoiceItem->amount ?? 0);
            }
            
            foreach ($invoiceItems as $invoiceItem) {
                // Only include items that have been paid (match payment view "Items Paid For")
                if (floatval($invoiceItem->amount_paid ?? 0) <= 0) {
                    continue;
                }
                $produk = $invoiceItem->produk;
                $penjualan = $invoiceItem->penjualan;
                
                $quantity = floatval($invoiceItem->quantity ?? 0);
                $buyingPrice = 0;
                if ($produk && isset($produk->harga_beli) && $produk->harga_beli > 0) {
                    $buyingPrice = floatval($produk->harga_beli);
                } else {
                    $invoiceAmount = floatval($invoiceItem->amount ?? 0);
                    $buyingPrice = $quantity > 0 ? ($invoiceAmount / $quantity) : 0;
                }
                
                $invoiceAmount = floatval($invoiceItem->amount ?? 0);
                $invoiceTotal = $invoiceTotalAmount[$invoiceItem->invoice_id] ?? $invoiceAmount;
                $paymentForInvoice = floatval($amountPaidByInvoiceId[$invoiceItem->invoice_id] ?? 0);
                $amountPaid = $invoiceTotal > 0 ? ($paymentForInvoice * ($invoiceAmount / $invoiceTotal)) : 0;
                
                $saleDate = null;
                if ($penjualan) {
                    $saleDate = $penjualan->saledate ?? $penjualan->created_at;
                }
                $dateSold = $saleDate ? \Carbon\Carbon::parse($saleDate)->format('d/m/y') : (isset($invoiceItem->created_at) ? $invoiceItem->created_at->format('d/m/y') : 'N/A');
                $receiptRaw = $penjualan ? (string) ($penjualan->receiptno ?? '') : '';
                $receiptNo = $receiptRaw !== '' ? trim(Str::before($receiptRaw, '|')) : 'N/A';
                
                $allItems->push([
                    'product_name' => $produk ? $produk->nama_produk : 'N/A',
                    'shop_name' => optional($produk)->shop ? optional($produk->shop)->shop_name : 'N/A',
                    'quantity' => $invoiceItem->quantity ?? 0,
                    'unit_amount' => $buyingPrice,
                    'invoice_amount' => $invoiceAmount,
                    'amount_paid' => $amountPaid,
                    'balance' => $invoiceAmount - $amountPaid,
                    'invoice_number' => optional($invoiceItem->invoice)->invoice_number ?? 'N/A',
                    'date_sold' => $dateSold,
                    'receipt_no' => $receiptNo,
                ]);
            }
        }
        
        $items = $allItems->sort(function ($a, $b) {
            return strcasecmp((string) ($a['shop_name'] ?? ''), (string) ($b['shop_name'] ?? ''));
        })->values();
        $totalPaid = $items->sum('amount_paid');

        $setting = Setting::first();
        $pdf = PDF::loadView('payment.export-pdf', compact('payment', 'items', 'totalPaid', 'totalConsignmentItems', 'setting', 'startDate', 'endDate'));
        $pdf->setPaper('a4', 'landscape');
        $pdf->getDomPDF()->set_option('isPhpEnabled', true);

        return $pdf->download('payment-' . $payment->reference_number . '.pdf');
    }

    /**
     * PDF preview of line items that would be paid — same layout as payment/{id}/view export, before processing payment.
     */
    public function exportPaymentPreviewPdf(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $paymentData = json_decode($request->input('invoice_data', ''));
        if (! $paymentData || json_last_error() !== JSON_ERROR_NONE) {
            abort(422, 'Invalid payment preview data.');
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if (! $startDate || ! $endDate) {
            abort(422, 'Start date and end date are required.');
        }

        $invoices = $paymentData->invoices ?? [];
        if (! is_array($invoices)) {
            $invoices = [];
        }

        $openingBalancePayment = floatval($paymentData->opening_balance_payment ?? 0);
        $totalPay = floatval($paymentData->totalPay ?? 0);

        $allItems = collect();

        foreach ($invoices as $inv) {
            $itemId = (int) ($inv->item_id ?? 0);
            $amountToPay = round(floatval($inv->amount_to_pay ?? 0), 2);
            if ($itemId <= 0 || $amountToPay <= 0) {
                continue;
            }

            $invoiceItem = InvoiceItem::with(['produk.shop', 'invoice', 'penjualan'])
                ->where('id', $itemId)
                ->where('supplier_id', $supplier->id_supplier)
                ->first();

            if (! $invoiceItem) {
                continue;
            }

            $produk = $invoiceItem->produk;
            $penjualan = $invoiceItem->penjualan;
            $quantity = floatval($invoiceItem->quantity ?? 0);

            $buyingPrice = 0;
            if ($produk && isset($produk->harga_beli) && $produk->harga_beli > 0) {
                $buyingPrice = floatval($produk->harga_beli);
            } else {
                $invoiceAmount = floatval($invoiceItem->amount ?? 0);
                $buyingPrice = $quantity > 0 ? ($invoiceAmount / $quantity) : 0;
            }

            $saleDate = null;
            if ($penjualan) {
                $saleDate = $penjualan->saledate ?? $penjualan->created_at;
            }
            try {
                $dateSold = $saleDate ? Carbon::parse($saleDate)->format('d/m/y') : (isset($invoiceItem->created_at) ? $invoiceItem->created_at->format('d/m/y') : 'N/A');
            } catch (\Throwable $e) {
                $dateSold = isset($invoiceItem->created_at) ? $invoiceItem->created_at->format('d/m/y') : 'N/A';
            }
            $receiptRaw = $penjualan ? (string) ($penjualan->receiptno ?? '') : '';
            $receiptNo = $receiptRaw !== '' ? trim(Str::before($receiptRaw, '|')) : 'N/A';

            $invoiceAmount = floatval($invoiceItem->amount ?? 0);

            $shopName = 'N/A';
            if ($produk) {
                $shopName = optional($produk->shop)->shop_name ?? 'N/A';
            }

            $allItems->push([
                'product_name' => $produk ? $produk->nama_produk : 'N/A',
                'shop_name' => $shopName,
                'quantity' => $invoiceItem->quantity ?? 0,
                'unit_amount' => $buyingPrice,
                'invoice_amount' => $invoiceAmount,
                'amount_paid' => $amountToPay,
                'balance' => max(0, $invoiceAmount - $amountToPay),
                'invoice_number' => optional($invoiceItem->invoice)->invoice_number ?? 'N/A',
                'date_sold' => $dateSold,
                'receipt_no' => $receiptNo,
            ]);
        }

        if ($openingBalancePayment > 0) {
            $allItems->push([
                'product_name' => 'Opening balance',
                'shop_name' => '—',
                'quantity' => 1,
                'unit_amount' => $openingBalancePayment,
                'invoice_amount' => $openingBalancePayment,
                'amount_paid' => $openingBalancePayment,
                'balance' => 0,
                'invoice_number' => '—',
                'date_sold' => '—',
                'receipt_no' => '—',
            ]);
        }

        if ($allItems->isEmpty()) {
            abort(422, 'No invoice lines or opening balance to include in the preview.');
        }

        $totalPaid = round($allItems->sum('amount_paid'), 2);
        if ($totalPay > 0 && abs($totalPaid - $totalPay) > 0.05) {
            Log::warning('Payment preview: totalPay vs summed lines mismatch', [
                'supplier_id' => $supplier->id_supplier,
                'totalPay' => $totalPay,
                'summed' => $totalPaid,
            ]);
        }

        // Preview PDF: order lines by Shop (A→Z); keep opening balance row last
        $items = $allItems->sort(function ($a, $b) {
            $aOpen = ($a['product_name'] ?? '') === 'Opening balance';
            $bOpen = ($b['product_name'] ?? '') === 'Opening balance';
            if ($aOpen !== $bOpen) {
                return $aOpen ? 1 : -1;
            }

            return strcasecmp((string) ($a['shop_name'] ?? ''), (string) ($b['shop_name'] ?? ''));
        })->values();

        $paymentDateInput = $request->input('payment_date');
        try {
            $paymentDateForDisplay = $paymentDateInput ? Carbon::parse($paymentDateInput)->toDateString() : Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            $paymentDateForDisplay = Carbon::today()->toDateString();
        }

        $payment = (object) [
            'reference_number' => 'PREVIEW-' . date('Ymd-His'),
            'supplier' => $supplier,
            'date' => $paymentDateForDisplay,
        ];

        $totalConsignmentItems = InvoiceItem::where('supplier_id', $supplier->id_supplier)->count();
        $setting = Setting::first();
        $isPreview = true;

        try {
            $pdf = PDF::loadView('payment.export-pdf', compact(
                'payment',
                'items',
                'totalPaid',
                'totalConsignmentItems',
                'setting',
                'startDate',
                'endDate',
                'isPreview'
            ));
            $pdf->setPaper('a4', 'landscape');
            $pdf->getDomPDF()->set_option('isPhpEnabled', true);

            return $pdf->download('payment-preview-' . $payment->reference_number . '.pdf');
        } catch (\Throwable $e) {
            Log::error('exportPaymentPreviewPdf PDF failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Could not generate preview PDF.');
        }
    }

    public function exportPaymentExcel($id)
    {
        $payment = Payment::with(['supplier'])->findOrFail($id);
        
        $paymentItems = PaymentItem::where('payment_id', $id)->get();
        
        $totalConsignmentItems = InvoiceItem::where('supplier_id', $payment->supplier_id)->count();
        
        $items = $paymentItems->map(function($paymentItem) {
            $invoiceItem = InvoiceItem::with(['produk', 'invoice'])
                ->where('invoice_id', $paymentItem->invoice_id)
                ->first();
            $produk = $invoiceItem->produk ?? null;
            
            return [
                'product_code' => $produk->kode_produk ?? 'N/A',
                'product_name' => $produk->nama_produk ?? 'N/A',
                'quantity' => $invoiceItem->quantity ?? 0,
                'invoice_amount' => floatval($invoiceItem->amount ?? 0),
                'amount_paid' => floatval($paymentItem->amount ?? 0),
                'balance' => floatval($invoiceItem->amount ?? 0) - floatval($invoiceItem->amount_paid ?? 0),
            ];
        });
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header
        $sheet->setCellValue('A1', 'Payment Details');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Payment Information
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Reference Number:');
        $sheet->setCellValue('B' . $row, $payment->reference_number);
        $row++;
        $sheet->setCellValue('A' . $row, 'Supplier:');
        $sheet->setCellValue('B' . $row, $payment->supplier->nama ?? 'N/A');
        $row++;
        $sheet->setCellValue('A' . $row, 'Payment Date:');
        $sheet->setCellValue('B' . $row, $payment->date ? date('Y-m-d', strtotime($payment->date)) : 'N/A');
        $row++;
        $sheet->setCellValue('A' . $row, 'Payment Amount:');
        $sheet->setCellValue('B' . $row, 'Ksh ' . number_format($payment->amount, 2));
        $row++;
        $sheet->setCellValue('A' . $row, 'Items Paid:');
        $sheet->setCellValue('B' . $row, $paymentItems->count());
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Consignment Items:');
        $sheet->setCellValue('B' . $row, $totalConsignmentItems);
        
        // Items Table
        $row += 2;
        $sheet->setCellValue('A' . $row, '#');
        $sheet->setCellValue('B' . $row, 'Product Code');
        $sheet->setCellValue('C' . $row, 'Product Name');
        $sheet->setCellValue('D' . $row, 'Quantity');
        $sheet->setCellValue('E' . $row, 'Invoice Amount');
        $sheet->setCellValue('F' . $row, 'Amount Paid');
        $sheet->setCellValue('G' . $row, 'Balance');
        
        $headerStyle = $sheet->getStyle('A' . $row . ':G' . $row);
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');
        
        $row++;
        foreach ($items as $index => $item) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $item['product_code']);
            $sheet->setCellValue('C' . $row, $item['product_name']);
            $sheet->setCellValue('D' . $row, $item['quantity']);
            $sheet->setCellValue('E' . $row, 'Ksh ' . number_format($item['invoice_amount'], 2));
            $sheet->setCellValue('F' . $row, 'Ksh ' . number_format($item['amount_paid'], 2));
            $sheet->setCellValue('G' . $row, 'Ksh ' . number_format($item['balance'], 2));
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'payment-' . $payment->reference_number . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $payment = Payment::findOrFail($id);
            
            // Reverse the payment items
            $paymentItems = PaymentItem::where('payment_id', $payment->id)->get();
            
            foreach ($paymentItems as $item) {
                $invoiceItem = InvoiceItem::where('invoice_id', $item->invoice_id)->first();
                
                if ($invoiceItem) {
                    // Reverse the balance and payment
                    $newBalance = ($invoiceItem->balance ?? 0) - $item->amount;
                    $newAmountPaid = ($invoiceItem->amount_paid ?? 0) - $item->amount;
                    
                    $invoiceItem->update([
                        'balance' => $newBalance,
                        'amount_paid' => $newAmountPaid,
                        'status' => $newBalance >= $invoiceItem->amount ? 'Full paid' : 'Partially paid'
                    ]);
                }
                
                $item->delete();
            }

            $payment->delete();
            
            DB::commit();
            return response(null, 204);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Payment deletion failed'], 500);
        }
    }

    public function redo($id)
    {
        try {
            DB::beginTransaction();

            // Find the original payment with all related data
            $originalPayment = Payment::with(['paymentItems.invoice'])->findOrFail($id);
            \Log::info('Original payment found:', ['id' => $id, 'amount' => $originalPayment->amount]);
            
            // Create a new payment record
            $newPayment = new Payment();
            $newPayment->type = $originalPayment->type;
            $newPayment->amount = $originalPayment->amount;
            $newPayment->date = now()->format('Y-m-d');
            $newPayment->reference_number = 'REDO-' . date('Ymd') . '-' . rand(1000, 9999);
            $newPayment->uniqid = uniqid();
            $newPayment->supplier_id = $originalPayment->supplier_id;
            $newPayment->save();
            \Log::info('New payment created:', ['id' => $newPayment->id, 'amount' => $newPayment->amount]);

            // For each payment item in the original payment
            foreach ($originalPayment->paymentItems as $item) {
                \Log::info('Processing payment item:', ['invoice_id' => $item->invoice_id, 'amount' => $item->amount]);
                
                // Create new payment item
                $newPaymentItem = new PaymentItem();
                $newPaymentItem->payment_id = $newPayment->id;
                $newPaymentItem->invoice_id = $item->invoice_id;
                $newPaymentItem->amount = $item->amount;
                $newPaymentItem->narrative = "Redo payment for invoice {$item->invoice_id}";
                $newPaymentItem->save();
                \Log::info('New payment item created:', ['id' => $newPaymentItem->id]);

                // Find all invoice items for this invoice
                $invoiceItems = InvoiceItem::where('invoice_id', $item->invoice_id)->get();
                foreach ($invoiceItems as $invoiceItem) {
                    $oldAmountPaid = $invoiceItem->amount_paid ?? 0;
                    $invoiceItem->amount_paid = floatval($oldAmountPaid) + (floatval($item->amount) / count($invoiceItems)); // Distribute payment equally
                    $invoiceItem->balance = floatval($invoiceItem->amount) - floatval($invoiceItem->amount_paid);
                    $invoiceItem->save();
                    \Log::info('Invoice item updated:', [
                        'id' => $invoiceItem->id,
                        'old_amount_paid' => $oldAmountPaid,
                        'new_amount_paid' => $invoiceItem->amount_paid,
                        'new_balance' => $invoiceItem->balance
                    ]);
                }
                
                if ($invoiceItems->isEmpty()) {
                    \Log::warning('No invoice items found for invoice_id: ' . $item->invoice_id);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Payment redone successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error redoing payment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error redoing payment: ' . $e->getMessage()], 500);
        }
    }

    public function undo($id)
    {
        try {
            DB::beginTransaction();

            // Find the payment to undo with all related items
            $payment = Payment::with(['paymentItems.invoiceItem'])->findOrFail($id);
            \Log::info('Undoing payment:', ['id' => $id, 'amount' => $payment->amount]);
            
            // For each payment item, restore the original state
            foreach ($payment->paymentItems as $paymentItem) {
                \Log::info('Processing payment item:', [
                    'invoice_id' => $paymentItem->invoice_id, 
                    'amount' => $paymentItem->amount
                ]);
                
                // Find the invoice item and update its balance
                $invoiceItem = InvoiceItem::where('invoice_id', $paymentItem->invoice_id)->first();
                
                if ($invoiceItem) {
                    // Store old values for logging
                    $oldAmountPaid = $invoiceItem->amount_paid;
                    $oldBalance = $invoiceItem->balance;
                    
                    // Calculate new values using BC Math for precision
                    $newAmountPaid = bcsub($invoiceItem->amount_paid, $paymentItem->amount, 2);
                    $newBalance = bcsub($invoiceItem->amount, $newAmountPaid, 2);
                    
                    // Update invoice item
                    $invoiceItem->amount_paid = $newAmountPaid;
                    $invoiceItem->balance = $newBalance;
                    
                    // Update status based on new balance
                    if (bccomp($newBalance, '0.00', 2) == 0) {
                        $invoiceItem->status = 'Paid';
                    } elseif (bccomp($newBalance, $invoiceItem->amount, 2) == 0) {
                        $invoiceItem->status = 'Unpaid';
                    } else {
                        $invoiceItem->status = 'Partially paid';
                    }
                    
                    $invoiceItem->save();
                    
                    \Log::info('Invoice item updated:', [
                        'id' => $invoiceItem->id,
                        'old_amount_paid' => $oldAmountPaid,
                        'new_amount_paid' => $newAmountPaid,
                        'old_balance' => $oldBalance,
                        'new_balance' => $newBalance,
                        'new_status' => $invoiceItem->status
                    ]);
                } else {
                    \Log::warning('No invoice item found for invoice_id: ' . $paymentItem->invoice_id);
                }
                
                // Delete the payment item
                $paymentItem->delete();
            }
            
            // Delete the payment record
            $payment->delete();
            
            // Delete related invoice payments
            InvoicePayment::where('payment_id', $id)->delete();
            
            DB::commit();
            return response()->json([
                'success' => true, 
                'message' => 'Payment undone successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error undoing payment: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false, 
                'message' => 'Error undoing payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function paymentHistory($invoice_id)
    {
        $payments = PaymentItem::with(['payment'])
            ->where('invoice_id', $invoice_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'payment_date' => $item->created_at,
                    'payment_reference' => $item->payment->reference_number,
                    'amount_paid' => $item->amount,
                    'remaining_balance' => $item->invoice->real_balance - $item->invoice->amount_paid,
                    'payment_status' => $item->invoice->status
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    public function pendingConsignments()
    {
        $suppliers = Supplier::where('mop', 'Consignment')->orderBy('nama')->get(['id_supplier', 'nama']);
        $missingBuyingPriceCount = app(MissingBuyingPriceLedgerService::class)->countForContext('consignment');
        $consignmentSuppliers = Supplier::whereRaw('UPPER(TRIM(mop)) = ?', ['CONSIGNMENT'])->orderBy('nama')->get(['id_supplier', 'nama']);
        $cashSuppliersForFix = Supplier::where(function ($q) {
            $q->whereRaw('UPPER(TRIM(mop)) = ?', ['CASH'])
                ->orWhereRaw("nama LIKE '% (Cash)'");
        })->orderBy('nama')->get(['id_supplier', 'nama']);
        $missingBuyContext = 'consignment';

        return view('payment.pending', compact('suppliers', 'missingBuyingPriceCount', 'consignmentSuppliers', 'cashSuppliersForFix', 'missingBuyContext'));
    }

    protected function buildConsignmentQuery(Request $request)
    {
        $query = InvoiceItem::with(['supplier', 'produk.shop', 'invoice', 'penjualan'])
            ->join('supplier', 'invoice_items.supplier_id', '=', 'supplier.id_supplier')
            ->where(function($q) {
                // Include invoice items from Consignment suppliers (case-insensitive)
                $q->whereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CONSIGNMENT'])
                  // OR invoice items from Cash suppliers that have been converted
                  // IMPORTANT: For Cash suppliers, we ONLY show invoice items that were definitely converted from a purchase
                  // This prevents showing invoice items that might exist for other reasons
                  ->orWhere(function($subQ) {
                      // Case-insensitive check for Cash suppliers
                      $subQ->whereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CASH'])
                           // CRITICAL: Only show invoice items that were created from a conversion
                           // We require ALL of these conditions to be true:
                           ->whereExists(function($existsQuery) {
                               $existsQuery->select(\DB::raw(1))
                                   ->from('pembelian_detail')
                                   ->join('pembelian', 'pembelian_detail.id_pembelian', '=', 'pembelian.id_pembelian')
                                   ->join('invoices', function($join) {
                                       $join->on('invoices.id', '=', 'invoice_items.invoice_id')
                                            ->on('invoices.id_supplier', '=', 'pembelian.id_supplier');
                                   })
                                   // Match product, quantity, and supplier using explicit where clauses
                                   ->whereRaw('pembelian_detail.id_produk = invoice_items.produk_id')
                                   ->whereRaw('pembelian_detail.jumlah = invoice_items.quantity')
                                   ->whereRaw('pembelian.id_supplier = invoice_items.supplier_id')
                                   // Match the amount: invoice item amount must exactly match pembelian_detail's total
                                   // When converting: amount = jumlah * harga_beli
                                   ->whereRaw('ABS(invoice_items.amount - (pembelian_detail.harga_beli * pembelian_detail.jumlah)) < 0.01')
                                   // CRITICAL: Invoice must be created AFTER pembelian (proves it's a conversion)
                                   ->whereRaw('invoices.created_at > pembelian.created_at')
                                   // Time window: within 7 days (prevents matching old conversions)
                                   ->whereRaw('invoices.created_at <= DATE_ADD(pembelian.created_at, INTERVAL 7 DAY)')
                                   // Invoice item must also be created after pembelian
                                   ->whereRaw('invoice_items.created_at > pembelian.created_at')
                                   ->whereRaw('invoice_items.created_at <= DATE_ADD(pembelian.created_at, INTERVAL 7 DAY)');
                           });
                  });
            })
            // NOTE: Do not auto-exclude "converted" rows by heuristic matching.
            // The previous whereNotExists block could hide valid consignment items
            // (same product/qty/amount happened in purchases), which made suppliers
            // disappear from Consignment Overview and blocked payment.
            ->leftJoin('penjualan', 'invoice_items.penjualan_id', '=', 'penjualan.id_penjualan')
            ->leftJoin('produk', 'invoice_items.produk_id', '=', 'produk.id_produk')
            ->leftJoin('shops', 'produk.shop_id', '=', 'shops.id')
            ->leftJoin('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->select('invoice_items.*');
            // No DISTINCT / base ORDER BY here: joins are many-to-one from invoice_items, and
            // DISTINCT + ORDER BY makes Yajra use a subquery count (very slow on large tables).
            // DataTables applies ordering via orderColumn; exports add orderBy explicitly.

        return $this->applyConsignmentFilters($request, $query);
    }

    /** Normalize date string to Y-m-d (accepts Y-m-d or d/m/Y). */
    protected function normalizeDateToYmd($dateStr)
    {
        if (empty($dateStr)) {
            return null;
        }
        $dateStr = trim($dateStr);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $dateStr)) {
            return $dateStr;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        try {
            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $dateStr;
        }
    }

    protected function applyConsignmentFilters(Request $request, $query)
    {
        if ($request->filled('supplier_id')) {
            $query->where('invoice_items.supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('status')) {
            $status = strtolower($request->input('status'));
            if ($status === 'paid') {
                $query->whereRaw('COALESCE(invoice_items.amount_paid, 0) >= invoice_items.amount');
            } elseif ($status === 'pending') {
                $query->whereRaw('COALESCE(invoice_items.amount_paid, 0) < invoice_items.amount');
            }
        }

        // `invoices` + `penjualan` must already be joined when using COALESCE below
        // (buildConsignmentQuery joins them; totals queries add joins before calling this)
        $start = $request->filled('start_date') ? $this->normalizeDateToYmd($request->input('start_date')) : null;
        $end = $request->filled('end_date') ? $this->normalizeDateToYmd($request->input('end_date')) : null;
        if ($start) {
            $query->whereRaw(
                'DATE(COALESCE(penjualan.saledate, invoices.created_at, invoice_items.created_at)) >= ?',
                [$start]
            );
        }
        if ($end) {
            $query->whereRaw(
                'DATE(COALESCE(penjualan.saledate, invoices.created_at, invoice_items.created_at)) <= ?',
                [$end]
            );
        }

        return $query;
    }

    /**
     * Completed sales in the filtered date range that still need sale-list confirmation
     * and include at least one consignment supplier line (optionally for one supplier).
     */
    protected function countUnconfirmedConsignmentReceipts(Request $request): int
    {
        if (! Schema::hasColumn('penjualan', 'confirmation_status')) {
            return 0;
        }

        $start = $request->filled('start_date') ? $this->normalizeDateToYmd($request->input('start_date')) : null;
        $end = $request->filled('end_date') ? $this->normalizeDateToYmd($request->input('end_date')) : null;
        if (! $start && ! $end) {
            return 0;
        }

        $supplierId = $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null;

        $query = Penjualan::query()
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNull('confirmation_status')
                    ->orWhereIn('confirmation_status', ['pending', 'review', 'defect']);
            })
            ->whereHas('details', function ($dq) use ($supplierId) {
                $dq->whereHas('produk', function ($pq) use ($supplierId) {
                    $pq->whereHas('supplier', function ($sq) use ($supplierId) {
                        $sq->whereRaw('UPPER(TRIM(mop)) = ?', ['CONSIGNMENT']);
                        if ($supplierId) {
                            $sq->where('id_supplier', $supplierId);
                        }
                    });
                });
                if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
                    $dq->where(function ($iq) {
                        $iq->whereNull('item_confirmation_status')
                            ->orWhere('item_confirmation_status', 'pending')
                            ->orWhere('item_confirmation_status', '');
                    });
                }
            });

        if ($start) {
            $query->whereDate(DB::raw('COALESCE(penjualan.saledate, penjualan.created_at)'), '>=', $start);
        }
        if ($end) {
            $query->whereDate(DB::raw('COALESCE(penjualan.saledate, penjualan.created_at)'), '<=', $end);
        }

        return (int) $query->distinct()->count('penjualan.id_penjualan');
    }

    /**
     * Build a grouped query for pending consignments (one row per supplier) for server-side pagination.
     */
    protected function buildPendingGroupedQuery(Request $request)
    {
        $query = DB::table('invoice_items')
            ->join('supplier', 'invoice_items.supplier_id', '=', 'supplier.id_supplier')
            ->leftJoin('penjualan', 'invoice_items.penjualan_id', '=', 'penjualan.id_penjualan')
            ->leftJoin('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('produk', 'invoice_items.produk_id', '=', 'produk.id_produk')
            ->whereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CONSIGNMENT'])
            ->whereRaw('(produk.is_incomplete = 0 OR produk.is_incomplete IS NULL OR invoice_items.penjualan_id IS NOT NULL)')
            ->whereRaw('invoice_items.amount - COALESCE(invoice_items.amount_paid, 0) > 0');

        if ($request->filled('supplier_ids')) {
            $supplierIds = is_array($request->input('supplier_ids'))
                ? $request->input('supplier_ids')
                : explode(',', $request->input('supplier_ids'));
            $query->whereIn('invoice_items.supplier_id', array_filter($supplierIds));
        } elseif ($request->filled('supplier_id')) {
            $sid = (int) $request->input('supplier_id');
            if ($sid > 0) {
                $query->where('invoice_items.supplier_id', $sid);
            }
        }

        if ($request->filled('supplier_name')) {
            $name = trim($request->input('supplier_name'));
            $query->where('supplier.nama', 'like', '%' . $name . '%');
        }

        // Date filter: match export PDF / consignment overview — use sale date when linked to penjualan,
        // not only invoice_items.created_at (wide year ranges failed when created_at differed from sale date).
        $startDate = $request->filled('start_date') ? $this->normalizeDateToYmd($request->input('start_date')) : null;
        $endDate = $request->filled('end_date') ? $this->normalizeDateToYmd($request->input('end_date')) : null;
        if ($startDate && $endDate && strcmp($startDate, $endDate) > 0) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $effectiveSaleDateSql = 'DATE(COALESCE(penjualan.saledate, penjualan.created_at, invoices.created_at, invoice_items.created_at))';
        if ($startDate) {
            $query->whereRaw($effectiveSaleDateSql.' >= ?', [$startDate]);
        }
        if ($endDate) {
            $query->whereRaw($effectiveSaleDateSql.' <= ?', [$endDate]);
        }

        $query->groupBy('invoice_items.supplier_id', 'supplier.nama', 'supplier.telepon')
            ->select(
                'invoice_items.supplier_id as supplier_id',
                'supplier.nama as supplier_name',
                'supplier.telepon as supplier_phone',
                DB::raw('SUM(invoice_items.amount - COALESCE(invoice_items.amount_paid, 0)) as total_pending'),
                DB::raw('COUNT(*) as item_count')
            )
            ->orderBy('supplier.nama');

        return $query;
    }

    public function pendingConsignmentsData(Request $request)
    {
        try {
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 50);
            $length = min(max($length, 1), 100);

            // Build fresh query for total count (subquery so pagination query is independent)
            $countQuery = $this->buildPendingGroupedQuery($request);
            $total = (int) DB::table(DB::raw('(' . $countQuery->toSql() . ') as t'))
                ->mergeBindings($countQuery)
                ->count();

            // Build fresh query for this page only
            $groupedQuery = $this->buildPendingGroupedQuery($request);
            $page = $groupedQuery->offset($start)->limit($length)->get();
            $draw = (int) $request->input('draw', 0);
            $u = auth()->user();

            $data = [];
            foreach ($page as $index => $row) {
                $supplierId = $row->supplier_id;
                $supplierName = $row->supplier_name ?? 'Unknown';
                $supplierPhone = $row->supplier_phone ?? '';
                $totalPending = (float) $row->total_pending;
                $itemCount = (int) $row->item_count;

                $payUrl = route('payment.create', $supplierId);
                if ($request->filled('start_date')) {
                    $payUrl .= (strpos($payUrl, '?') !== false ? '&' : '?') . 'start_date=' . urlencode($request->input('start_date'));
                }
                if ($request->filled('end_date')) {
                    $payUrl .= (strpos($payUrl, '?') !== false ? '&' : '?') . 'end_date=' . urlencode($request->input('end_date'));
                }

                $aksi = '<div class="btn-group">';
                if ($u && ($u->can_read || $u->hasModulePermission('consignment', 'read') || $u->hasRole('admin'))) {
                    $aksi .= '<button type="button" onclick="viewPendingConsignment('.$supplierId.')" class="btn btn-xs btn-info btn-flat" title="View pending items and dates"><i class="fa fa-eye"></i> View</button>';
                }
                if ($u && ($u->can_create || $u->hasModulePermission('consignment', 'create') || $u->hasRole('admin'))) {
                    $aksi .= '<a href="'. $payUrl .'" class="btn btn-xs btn-success btn-flat"><i class="fa fa-credit-card"></i> Pay Now</a>';
                }
                if ($u && $u->hasRole('admin')) {
                    $aksi .= '<button onclick="deleteConsignmentItems('.$supplierId.')" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i> Delete</button>';
                }
                $aksi .= '</div>';

                $data[] = [
                    'checkbox' => '<input type="checkbox" class="row-checkbox" value="' . $supplierId . '" data-supplier-id="' . $supplierId . '">',
                    'DT_RowIndex' => $start + $index + 1,
                    'supplier_name' => $supplierName,
                    'supplier_phone' => $supplierPhone,
                    'total_pending' => 'ksh ' . format_uang($totalPending),
                    'item_count' => $itemCount . ' item(s)',
                    'aksi' => $aksi,
                ];
            }

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $data,
                'unconfirmed_receipts' => $this->countUnconfirmedConsignmentReceipts($request),
            ]);
        } catch (\Throwable $e) {
            Log::error('pendingConsignmentsData error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'draw' => (int) request('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'unconfirmed_receipts' => 0,
                'error' => 'Failed to load consignment list. Please try again.',
            ], 200);
        }
    }

    public function deletePendingConsignmentItems($supplierId)
    {
        try {
            // Only admins can delete consignment items
            if (!auth()->user()->hasRole('admin')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. Only administrators can delete consignment items.'], 403);
            }

            DB::beginTransaction();

            // Get all pending invoice items for this supplier (with outstanding balance > 0)
            $pendingItems = InvoiceItem::where('supplier_id', $supplierId)
                ->whereRaw('amount - COALESCE(amount_paid, 0) > 0')
                ->get();

            if ($pendingItems->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending items found to delete.'], 404);
            }

            $deletedCount = 0;
            $invoiceIdsToUpdate = [];
            
            foreach ($pendingItems as $item) {
                // Check if there are any payments associated with this invoice item
                $hasPayments = DB::table('payment_items')
                    ->join('payments', 'payment_items.payment_id', '=', 'payments.id')
                    ->where('payment_items.invoice_id', $item->invoice_id)
                    ->exists();

                if (!$hasPayments) {
                    // Store invoice ID before deleting
                    $invoiceIdsToUpdate[] = $item->invoice_id;
                    // Only delete if no payments have been made
                    $item->delete();
                    $deletedCount++;
                }
            }

            // Update invoice totals for invoices that had items deleted
            $uniqueInvoiceIds = array_unique($invoiceIdsToUpdate);
            foreach ($uniqueInvoiceIds as $invoiceId) {
                $invoice = Invoice::find($invoiceId);
                if ($invoice) {
                    $invoiceTotal = InvoiceItem::where('invoice_id', $invoiceId)->sum('amount');
                    $invoice->total = $invoiceTotal;
                    $invoice->save();
                }
            }

            DB::commit();

            if ($deletedCount > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully deleted {$deletedCount} pending consignment item(s)."
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete items that have associated payments.'
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting pending consignment items: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting consignment items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportPendingConsignmentsPdf(Request $request)
    {
        // Same criteria as pending table: Consignment suppliers, complete products only, outstanding balance
        $pendingItems = InvoiceItem::with(['supplier', 'produk.shop', 'invoice', 'penjualan'])
            ->join('supplier', 'invoice_items.supplier_id', '=', 'supplier.id_supplier')
            ->join('produk', 'invoice_items.produk_id', '=', 'produk.id_produk')
            ->leftJoin('penjualan', 'invoice_items.penjualan_id', '=', 'penjualan.id_penjualan')
            ->whereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CONSIGNMENT'])
            ->whereRaw('(produk.is_incomplete = 0 OR produk.is_incomplete IS NULL OR invoice_items.penjualan_id IS NOT NULL)')
            ->whereRaw('invoice_items.amount - COALESCE(invoice_items.amount_paid, 0) > 0')
            ->select('invoice_items.*');
        
        // Apply supplier filter (optional: selected rows on pending page, or dropdown)
        if ($request->filled('supplier_ids')) {
            $supplierIds = is_array($request->input('supplier_ids')) 
                ? $request->input('supplier_ids') 
                : explode(',', $request->input('supplier_ids'));
            $pendingItems->whereIn('invoice_items.supplier_id', array_filter($supplierIds));
        } elseif ($request->filled('supplier_id')) {
            $sid = (int) $request->input('supplier_id');
            if ($sid > 0) {
                $pendingItems->where('invoice_items.supplier_id', $sid);
            }
        }

        if ($request->filled('supplier_name')) {
            $name = trim($request->input('supplier_name'));
            $pendingItems->where('supplier.nama', 'like', '%' . $name . '%');
        }
        
        // Apply date filters - filter by sale date (when receipt was sold), not invoice_item created_at
        if ($request->filled('start_date')) {
            $pendingItems->where(function($q) use ($request) {
                $q->where(function($subQ) use ($request) {
                    $subQ->whereNotNull('penjualan.id_penjualan')
                         ->where(function($dateQ) use ($request) {
                             $dateQ->whereDate('penjualan.saledate', '>=', $request->input('start_date'))
                                   ->orWhere(function($dateQ2) use ($request) {
                                       $dateQ2->whereNull('penjualan.saledate')
                                              ->whereDate('penjualan.created_at', '>=', $request->input('start_date'));
                                   });
                         });
                })
                ->orWhere(function($subQ) use ($request) {
                    $subQ->whereNull('penjualan.id_penjualan')
                         ->whereDate('invoice_items.created_at', '>=', $request->input('start_date'));
                });
            });
        }
        if ($request->filled('end_date')) {
            $pendingItems->where(function($q) use ($request) {
                $q->where(function($subQ) use ($request) {
                    $subQ->whereNotNull('penjualan.id_penjualan')
                         ->where(function($dateQ) use ($request) {
                             $dateQ->whereDate('penjualan.saledate', '<=', $request->input('end_date'))
                                   ->orWhere(function($dateQ2) use ($request) {
                                       $dateQ2->whereNull('penjualan.saledate')
                                              ->whereDate('penjualan.created_at', '<=', $request->input('end_date'));
                                   });
                         });
                })
                ->orWhere(function($subQ) use ($request) {
                    $subQ->whereNull('penjualan.id_penjualan')
                         ->whereDate('invoice_items.created_at', '<=', $request->input('end_date'));
                });
            });
        }
        
        $pendingItems = $pendingItems->orderByRaw('COALESCE(penjualan.saledate, penjualan.created_at, invoice_items.created_at) DESC')
            ->get()
            ->filter(function($item) {
                $outstanding = floatval($item->amount) - floatval($item->amount_paid ?? 0);
                return $outstanding > 0;
            });

        // Group by supplier
        $groupedBySupplier = $pendingItems->groupBy('supplier_id')->map(function($items, $supplierId) {
            $supplier = $items->first()->supplier;
            $totalPending = $items->sum(function($item) {
                return floatval($item->amount) - floatval($item->amount_paid ?? 0);
            });
            
            return [
                'supplier_id' => $supplierId,
                'supplier_name' => $supplier->nama ?? 'Unknown',
                'supplier_phone' => $supplier->telepon ?? '',
                'total_pending' => $totalPending,
            ];
        })->sortBy('supplier_name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $totalPending = $groupedBySupplier->sum('total_pending');

        // Get supplier names for filter display
        $supplierNames = 'All Suppliers';
        if ($request->filled('supplier_ids')) {
            $supplierIds = is_array($request->input('supplier_ids')) 
                ? $request->input('supplier_ids') 
                : explode(',', $request->input('supplier_ids'));
            $suppliers = Supplier::whereIn('id_supplier', array_filter($supplierIds))->pluck('nama');
            $supplierNames = $suppliers->implode(', ');
        } elseif ($request->filled('supplier_id') && (int) $request->input('supplier_id') > 0) {
            $one = Supplier::find((int) $request->input('supplier_id'));
            $supplierNames = $one ? $one->nama : 'All Suppliers';
        }

        $filters = [
            'supplier_name' => $request->input('supplier_name'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        $setting = Setting::first();
        $pdf = PDF::loadView('payment.pending_pdf', [
            'suppliers' => $groupedBySupplier,
            'totalPending' => $totalPending,
            'supplierNames' => $supplierNames,
            'filters' => $filters,
            'setting' => $setting,
        ]);
        $pdf->getDomPDF()->set_option('isPhpEnabled', true);

        return $pdf->download('pending_consignments_' . now()->format('Ymd_His') . '.pdf');
    }

    public function consignmentOverview()
    {
        $suppliers = Supplier::where('mop', 'Consignment')->orderBy('nama')->get(['id_supplier', 'nama']);
        $missingBuyingPriceCount = app(MissingBuyingPriceLedgerService::class)->countForContext('consignment');
        $consignmentSuppliers = Supplier::whereRaw('UPPER(TRIM(mop)) = ?', ['CONSIGNMENT'])->orderBy('nama')->get(['id_supplier', 'nama']);
        $cashSuppliersForFix = Supplier::where(function ($q) {
            $q->whereRaw('UPPER(TRIM(mop)) = ?', ['CASH'])
                ->orWhereRaw("nama LIKE '% (Cash)'");
        })->orderBy('nama')->get(['id_supplier', 'nama']);
        $missingBuyContext = 'consignment';

        return view('payment.consignments', compact('suppliers', 'missingBuyingPriceCount', 'consignmentSuppliers', 'cashSuppliersForFix', 'missingBuyContext'));
    }

    public function consignmentOverviewData(Request $request)
    {
        try {
            $consignments = $this->buildConsignmentQuery($request);

            // Supplier totals: always SUM(stock_out × buying_price). invoice_items.amount = supplier payable (set at import); ignore ConfirmPrice and discounts.
            // applyConsignmentFilters uses sales_date (penjualan.saledate) for monthly filtering.
            $consignmentTotals = $this->applyConsignmentFilters($request, InvoiceItem::query()
                ->leftJoin('penjualan', 'invoice_items.penjualan_id', '=', 'penjualan.id_penjualan')
                ->leftJoin('invoices', 'invoice_items.invoice_id', '=', 'invoices.id'))
            ->join('supplier', 'invoice_items.supplier_id', '=', 'supplier.id_supplier')
            ->whereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CONSIGNMENT'])
            ->selectRaw('COALESCE(SUM(invoice_items.amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(invoice_items.amount_paid, 0)), 0) as total_paid')
            ->selectRaw('COALESCE(SUM(invoice_items.amount - COALESCE(invoice_items.amount_paid, 0)), 0) as total_balance')
            ->first();

        $convertedTotals = $this->applyConsignmentFilters($request, InvoiceItem::query()
                ->leftJoin('penjualan', 'invoice_items.penjualan_id', '=', 'penjualan.id_penjualan')
                ->leftJoin('invoices', 'invoice_items.invoice_id', '=', 'invoices.id'))
            ->join('supplier', 'invoice_items.supplier_id', '=', 'supplier.id_supplier')
            ->whereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CASH'])
            ->whereExists(function($subquery) {
                $subquery->select(\DB::raw(1))
                    ->from('pembelian_detail')
                    ->join('pembelian', 'pembelian_detail.id_pembelian', '=', 'pembelian.id_pembelian')
                    ->whereColumn('pembelian_detail.id_produk', 'invoice_items.produk_id')
                    ->whereColumn('pembelian_detail.jumlah', 'invoice_items.quantity')
                    ->whereColumn('pembelian.id_supplier', 'invoice_items.supplier_id');
            })
            ->selectRaw('COALESCE(SUM(invoice_items.amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(invoice_items.amount_paid, 0)), 0) as total_paid')
            ->selectRaw('COALESCE(SUM(invoice_items.amount - COALESCE(invoice_items.amount_paid, 0)), 0) as total_balance')
            ->first();

        $totalsRow = (object) [
            'total_amount' => (floatval($consignmentTotals->total_amount ?? 0) + floatval($convertedTotals->total_amount ?? 0)),
            'total_paid' => (floatval($consignmentTotals->total_paid ?? 0) + floatval($convertedTotals->total_paid ?? 0)),
            'total_balance' => (floatval($consignmentTotals->total_balance ?? 0) + floatval($convertedTotals->total_balance ?? 0)),
        ];

        $totals = [
            'total_amount' => (float) ($totalsRow->total_amount ?? 0),
            'total_paid' => (float) ($totalsRow->total_paid ?? 0),
            'total_balance' => max(0, (float) ($totalsRow->total_balance ?? 0)),
        ];

        $unconfirmedReceipts = $this->countUnconfirmedConsignmentReceipts($request);

        // Server-side pagination + custom global search (default Yajra search breaks on joined / addColumn fields)
        return datatables()
            ->eloquent($consignments)
            ->skipTotalRecords()
            ->filter(function ($query) {
                $keyword = request()->input('search.value');
                if (! is_string($keyword)) {
                    return;
                }
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }
                $kw = '%' . $keyword . '%';
                $query->where(function ($q) use ($kw) {
                    $q->where('penjualan.receiptno', 'like', $kw)
                        ->orWhere('supplier.nama', 'like', $kw)
                        ->orWhere('supplier.telepon', 'like', $kw)
                        ->orWhere('produk.nama_produk', 'like', $kw)
                        ->orWhere('invoices.invoice_number', 'like', $kw)
                        ->orWhere('shops.shop_name', 'like', $kw);
                });
            }, false)
            ->addIndexColumn()
            ->addColumn('supplier_name', function ($item) {
                return $item->supplier->nama ?? 'Unknown';
            })
            ->addColumn('supplier_phone', function ($item) {
                return $item->supplier->telepon ?? 'N/A';
            })
            ->addColumn('product_name', function ($item) {
                return $item->produk->nama_produk ?? 'Unknown';
            })
            ->addColumn('invoice_number', function ($item) {
                return $item->invoice->invoice_number ?? 'N/A';
            })
            ->addColumn('receipt_number', function ($item) {
                return optional($item->penjualan)->receiptno ?? '—';
            })
            ->addColumn('shop_name', function ($item) {
                return optional($item->produk->shop)->shop_name ?? 'N/A';
            })
            ->addColumn('quantity', function ($item) {
                return number_format($item->quantity ?? 0);
            })
            ->addColumn('total_amount', function ($item) {
                return 'ksh ' . format_uang($item->amount ?? 0);
            })
            ->addColumn('amount_paid', function ($item) {
                $paid = floatval($item->amount_paid ?? 0);
                return 'ksh ' . format_uang($paid);
            })
            ->addColumn('outstanding_balance', function ($item) {
                $balance = max(0, floatval($item->amount) - floatval($item->amount_paid ?? 0));
                return 'ksh ' . format_uang($balance);
            })
            ->addColumn('transaction_date', function ($item) {
                return $item->created_at ? date('d/m/Y', strtotime($item->created_at)) : 'N/A';
            })
            ->addColumn('status', function ($item) {
                $balance = max(0, floatval($item->amount) - floatval($item->amount_paid ?? 0));
                $labelClass = $balance <= 0 ? 'label-success' : 'label-warning';
                $text = $balance <= 0 ? 'Paid' : 'Pending';
                return '<span class="label ' . $labelClass . '">' . $text . '</span>';
            })
            ->addColumn('aksi', function ($item) {
                $u = auth()->user();
                $buttons = '<div class="btn-group">';
                if ($u && ($u->can_read || $u->hasModulePermission('consignment', 'read') || $u->hasRole('admin'))) {
                    $buttons .= '<button onclick="viewConsignment('.$item->supplier_id.', '.$item->id.')" class="btn btn-xs btn-info btn-flat"><i class="fa fa-eye"></i> View</button>';
                }
                if ($u && ($u->can_create || $u->hasModulePermission('consignment', 'create') || $u->hasRole('admin'))) {
                    $buttons .= '<button type="button" onclick="editConsignmentItem('.$item->id.')" class="btn btn-xs btn-primary btn-flat" title="Edit"><i class="fa fa-edit"></i> Edit</button>';
                    $buttons .= '<a href="'. route('payment.create', $item->supplier_id) .'" class="btn btn-xs btn-success btn-flat"><i class="fa fa-credit-card"></i> Pay</a>';
                }
                // Add Convert to Cash button - only show for Consignment suppliers
                if ($u && ($u->can_create || $u->hasModulePermission('consignment', 'create') || $u->hasRole('admin'))) {
                    $supplierMop = strtoupper(trim($item->supplier->mop ?? ''));
                    if ($supplierMop === 'CONSIGNMENT') {
                        $buttons .= '<button onclick="convertToCash('.$item->supplier_id.', '.$item->id.')" class="btn btn-xs btn-warning btn-flat"><i class="fa fa-exchange"></i> Convert to Cash</button>';
                    }
                }
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['status', 'aksi'])
            ->orderColumn('receipt_number', 'penjualan.receiptno $1')
            ->orderColumn('supplier_name', 'supplier.nama $1')
            ->orderColumn('supplier_phone', 'supplier.telepon $1')
            ->orderColumn('product_name', 'produk.nama_produk $1')
            ->orderColumn('invoice_number', 'invoices.invoice_number $1')
            ->orderColumn('shop_name', 'shops.shop_name $1')
            ->orderColumn('quantity', 'invoice_items.quantity $1')
            ->orderColumn('total_amount', 'invoice_items.amount $1')
            ->orderColumn('amount_paid', 'invoice_items.amount_paid $1')
            ->orderColumn('transaction_date', 'invoice_items.created_at $1')
            ->with('totals', $totals)
            ->with('unconfirmed_receipts', $unconfirmedReceipts)
            ->make(true);
        } catch (\Throwable $e) {
            Log::error('consignmentOverviewData error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'draw' => (int) request('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'totals' => ['total_amount' => 0, 'total_paid' => 0, 'total_balance' => 0],
                'unconfirmed_receipts' => 0,
                'error' => 'Failed to load consignments. Please try again.',
            ], 200);
        }
    }

    /**
     * Get a single consignment (invoice) item for editing (JSON for modal).
     */
    public function editConsignmentItem($id)
    {
        $item = InvoiceItem::with(['produk', 'supplier', 'invoice'])
            ->where('id', $id)
            ->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Consignment item not found.'], 404);
        }

        $invoice = $item->invoice;
        $invoiceNumber = $invoice ? ($invoice->invoice_number ?? 'N/A') : 'N/A';

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'product_name' => $item->produk->nama_produk ?? 'Unknown',
                'supplier_name' => $item->supplier->nama ?? 'Unknown',
                'invoice_number' => $invoiceNumber,
                'quantity' => (int) $item->quantity,
                'amount' => (float) $item->amount,
                'amount_paid' => (float) ($item->amount_paid ?? 0),
                'balance' => (float) ($item->balance ?? ($item->amount - ($item->amount_paid ?? 0))),
            ],
        ]);
    }

    /**
     * Update a consignment (invoice) item amount/quantity and recalc invoice total.
     */
    public function updateConsignmentItem(Request $request, $id)
    {
        $u = auth()->user();
        if (!$u || (!$u->can_create && !$u->hasModulePermission('consignment', 'create') && !$u->hasRole('admin'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to edit consignment items.'], 403);
        }

        $item = InvoiceItem::with('invoice')->find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Consignment item not found.'], 404);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ], [
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount cannot be negative.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',
        ]);

        $amount = round((float) $request->input('amount'), 2);
        $quantity = (int) $request->input('quantity');

        DB::beginTransaction();
        try {
            $oldAmount = (float) $item->amount;
            $item->amount = $amount;
            $item->quantity = $quantity;
            $item->balance = $amount - (float) ($item->amount_paid ?? 0);
            $item->save();

            // Recalculate parent invoice total
            $invoice = $item->invoice;
            if ($invoice) {
                $newTotal = InvoiceItem::where('invoice_id', $invoice->id)->sum(DB::raw('amount'));
                $invoice->total = round($newTotal, 2);
                $invoice->save();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Consignment item updated successfully.',
                'item' => [
                    'id' => $item->id,
                    'amount' => $item->amount,
                    'quantity' => $item->quantity,
                    'balance' => (float) $item->balance,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('updateConsignmentItem error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to update: ' . $e->getMessage()], 500);
        }
    }

    public function exportConsignmentsPdf(Request $request)
    {
        $consignments = $this->buildConsignmentQuery($request)
            ->orderBy('invoice_items.created_at', 'desc')
            ->get();
        $totals = [
            'total_amount' => $consignments->sum('amount'),
            'total_paid' => $consignments->sum(function($item){ return floatval($item->amount_paid ?? 0); }),
        ];
        $totals['total_balance'] = max(0, $totals['total_amount'] - $totals['total_paid']);

        $filters = [
            'status' => $request->input('status'),
            'supplier' => optional(Supplier::find($request->input('supplier_id')))->nama,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        $setting = Setting::first();
        $pdf = PDF::loadView('payment.consignments_pdf', [
            'consignments' => $consignments,
            'totals' => $totals,
            'filters' => $filters,
            'setting' => $setting,
        ]);

        return $pdf->download('consignments_' . now()->format('Ymd_His') . '.pdf');
    }

    public function exportConsignmentsExcel(Request $request)
    {
        $consignments = $this->buildConsignmentQuery($request)
            ->orderBy('invoice_items.created_at', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Supplier', 'Product', 'Invoice', 'Quantity', 'Total Amount', 'Amount Paid', 'Balance', 'Status', 'Date'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        foreach ($consignments as $item) {
            $amount = floatval($item->amount ?? 0);
            $paid = floatval($item->amount_paid ?? 0);
            $balance = max(0, $amount - $paid);
            $status = $balance <= 0 ? 'Paid' : ($paid > 0 ? 'Partially Paid' : 'Pending');

            $sheet->fromArray([
                $item->supplier->nama ?? 'N/A',
                $item->produk->nama_produk ?? 'N/A',
                $item->invoice->invoice_number ?? 'N/A',
                $item->quantity ?? 0,
                number_format($amount, 2),
                number_format($paid, 2),
                number_format($balance, 2),
                $status,
                optional($item->created_at)->format('Y-m-d'),
            ], null, 'A' . $row);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(10);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(15);

        $writer = new Xlsx($spreadsheet);
        $fileName = 'consignments_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/' . $fileName);
        $writer->save($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend(true);
    }

    // Cash Payment Methods
    public function cashPaymentsIndex()
    {
        return view('payment.cash_payments');
    }

    public function cashPaymentHistory()
    {
        $suppliers = Supplier::where('mop', 'Cash')->orderBy('nama')->get(['id_supplier', 'nama']);
        return view('payment.cash_payment_history', compact('suppliers'));
    }

    public function cashSuppliersWithPendingPayments()
    {
        try {
            // Get cash suppliers with their pending payment amounts
            $suppliers = Supplier::where('mop', 'Cash')->orderBy('nama')->get()->map(function($supplier) {
                // Calculate total pending payment from Pembelian records
                $pendingResult = Pembelian::where('id_supplier', $supplier->id_supplier)
                    ->select(DB::raw('COALESCE(SUM(total_harga - COALESCE(bayar, 0)), 0) as pending'))
                    ->first();
                
                $pendingAmount = $pendingResult && isset($pendingResult->pending) ? floatval($pendingResult->pending) : 0;
                
                return [
                    'id_supplier' => $supplier->id_supplier,
                    'nama' => $supplier->nama,
                    'telepon' => $supplier->telepon ?? 'N/A',
                    'alamat' => $supplier->alamat ?? 'N/A',
                    'pending_amount' => $pendingAmount,
                ];
            })->filter(function($supplier) {
                return $supplier['pending_amount'] > 0;
            })->sortByDesc(function($supplier) {
                return $supplier['pending_amount'];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $suppliers
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching cash suppliers with pending payments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error loading suppliers: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    public function cashPaymentPendingItems($supplierId)
    {
        try {
            $supplier = Supplier::findOrFail($supplierId);
            
            if ($supplier->mop !== 'Cash') {
                return response()->json(['success' => false, 'error' => 'Supplier is not a cash supplier'], 400);
            }

            // Get pending Pembelian records for this supplier
            $ledgerEnsure = app(EnsureSaleSupplierLedgerService::class);
            $pendingPurchases = Pembelian::with(['details.produk'])
                ->where('id_supplier', $supplierId)
                ->whereRaw('total_harga - COALESCE(bayar, 0) > 0')
                ->orderBy('purchasedate2', 'desc')
                ->get()
                ->map(function($pembelian) use ($ledgerEnsure) {
                    $visibleDetails = $pembelian->details->filter(function ($detail) use ($ledgerEnsure, $pembelian) {
                        return ! $ledgerEnsure->pembelianDetailIsFromUnconfirmedDeferredSale($detail, $pembelian);
                    });
                    if ($visibleDetails->isEmpty()) {
                        return null;
                    }
                    $outstanding = floatval($pembelian->total_harga) - floatval($pembelian->bayar ?? 0);
                    if ($outstanding <= 0) {
                        return null;
                    }
                    return [
                        'id_pembelian' => $pembelian->id_pembelian,
                        'purchase_date' => $pembelian->purchasedate2 ? \Carbon\Carbon::parse($pembelian->purchasedate2)->format('Y-m-d') : null,
                        'total_harga' => floatval($pembelian->total_harga),
                        'bayar' => floatval($pembelian->bayar ?? 0),
                        'outstanding' => $outstanding,
                        'items' => $visibleDetails->map(function($detail) {
                            return [
                                'product_name' => $detail->produk->nama_produk ?? 'Unknown',
                                'quantity' => $detail->jumlah,
                                'unit_price' => floatval($detail->harga_beli),
                                'subtotal' => floatval($detail->subtotal),
                            ];
                        })->values()
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'supplier' => [
                    'id_supplier' => $supplier->id_supplier,
                    'nama' => $supplier->nama,
                    'telepon' => $supplier->telepon,
                ],
                'pending_purchases' => $pendingPurchases
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching cash payment pending items: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error loading pending items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function processCashPayment(Request $request, $supplierId)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'pembelian_ids' => 'required|array',
            'pembelian_ids.*' => 'exists:pembelian,id_pembelian',
        ]);

        $supplier = Supplier::findOrFail($supplierId);
        
        if ($supplier->mop !== 'Cash') {
            return response()->json(['error' => 'Supplier is not a cash supplier'], 400);
        }

        DB::beginTransaction();
        try {
            $totalOutstanding = 0;
            $pembelianIds = $request->pembelian_ids;
            
            // Calculate total outstanding for selected purchases
            foreach ($pembelianIds as $pembelianId) {
                $pembelian = Pembelian::findOrFail($pembelianId);
                if ($pembelian->id_supplier != $supplierId) {
                    throw new \Exception('Purchase does not belong to this supplier');
                }
                $outstanding = floatval($pembelian->total_harga) - floatval($pembelian->bayar ?? 0);
                $totalOutstanding += $outstanding;
            }

            $paymentAmount = floatval($request->payment_amount);
            
            if ($paymentAmount > $totalOutstanding) {
                throw new \Exception('Payment amount exceeds total outstanding');
            }

            // Create payment record
            $payment = Payment::create([
                'type' => 'cash_payment',
                'amount' => $paymentAmount,
                'date' => $request->payment_date,
                'reference_number' => 'CASH-' . date('Ymd') . '-' . rand(1000, 9999),
                'uniqid' => uniqid(),
                'supplier_id' => $supplierId,
                'payment_method' => $request->payment_method,
                'notes' => json_encode(['pembelian_ids' => $pembelianIds])
            ]);

            // Distribute payment across purchases
            $remainingPayment = $paymentAmount;
            foreach ($pembelianIds as $pembelianId) {
                if ($remainingPayment <= 0) break;
                
                $pembelian = Pembelian::findOrFail($pembelianId);
                $outstanding = floatval($pembelian->total_harga) - floatval($pembelian->bayar ?? 0);
                
                if ($outstanding > 0) {
                    $amountToPay = min($remainingPayment, $outstanding);
                    $pembelian->bayar = floatval($pembelian->bayar ?? 0) + $amountToPay;
                    $pembelian->save();
                    
                    $remainingPayment -= $amountToPay;
                }
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Cash payment processed successfully',
                'payment_id' => $payment->id
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error processing cash payment: ' . $e->getMessage(), [
                'supplier_id' => $supplierId,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Unable to process payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cashPaymentHistoryData(Request $request)
    {
        $query = Payment::with('supplier')
            ->where('type', 'cash_payment')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->input('end_date'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        $payments = $query->get();

        return datatables()
            ->of($payments)
            ->addIndexColumn()
            ->addColumn('supplier_name', function ($payment) {
                return $payment->supplier->nama ?? 'N/A';
            })
            ->addColumn('supplier_phone', function ($payment) {
                return $payment->supplier->telepon ?? 'N/A';
            })
            ->addColumn('amount', function ($payment) {
                return 'Ksh ' . number_format($payment->amount, 2);
            })
            ->addColumn('date_formatted', function ($payment) {
                return $payment->date ? \Carbon\Carbon::parse($payment->date)->format('d/m/Y') : 'N/A';
            })
            ->addColumn('payment_method', function ($payment) {
                return $payment->payment_method ?? 'N/A';
            })
            ->addColumn('pembelian_count', function ($payment) {
                if ($payment->notes) {
                    $notes = json_decode($payment->notes, true);
                    if (isset($notes['pembelian_ids']) && is_array($notes['pembelian_ids'])) {
                        return count($notes['pembelian_ids']);
                    }
                }
                return 'N/A';
            })
            ->addColumn('aksi', function ($payment) {
                $viewBtn = '<button type="button" onclick="viewPaymentDetails(' . $payment->id . ')" class="btn btn-xs btn-info btn-flat"><i class="fa fa-eye"></i> View</button>';
                $pdfBtn = '<a href="' . route('payment.cash.export-pdf', $payment->id) . '" target="_blank" class="btn btn-xs btn-danger btn-flat" title="Print PDF"><i class="fa fa-file-pdf-o"></i></a>';
                return $viewBtn . ' ' . $pdfBtn;
            })
            ->addColumn('id', function ($payment) {
                return $payment->id;
            })
            ->rawColumns(['aksi', 'amount'])
            ->make(true);
    }

    public function cashPaymentDetails($id)
    {
        $payment = Payment::with('supplier')->findOrFail($id);
        
        if ($payment->type !== 'cash_payment') {
            return response()->json(['error' => 'Payment is not a cash payment'], 400);
        }

        $pembelianIds = [];
        if ($payment->notes) {
            $notes = json_decode($payment->notes, true);
            if (isset($notes['pembelian_ids']) && is_array($notes['pembelian_ids'])) {
                $pembelianIds = $notes['pembelian_ids'];
            }
        }

        $pembelianDetails = Pembelian::with(['details.produk'])
            ->whereIn('id_pembelian', $pembelianIds)
            ->get()
            ->map(function($pembelian) {
                return [
                    'id_pembelian' => $pembelian->id_pembelian,
                    'purchase_date' => $pembelian->purchasedate2 ? \Carbon\Carbon::parse($pembelian->purchasedate2)->format('d/m/Y') : 'N/A',
                    'total_harga' => floatval($pembelian->total_harga),
                    'bayar' => floatval($pembelian->bayar ?? 0),
                    'items' => $pembelian->details->map(function($detail) {
                        return [
                            'product_name' => $detail->produk->nama_produk ?? 'Unknown',
                            'quantity' => $detail->jumlah,
                            'unit_price' => floatval($detail->harga_beli),
                            'subtotal' => floatval($detail->subtotal),
                        ];
                    })
                ];
            });

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'reference_number' => $payment->reference_number,
                'supplier_name' => $payment->supplier->nama ?? 'N/A',
                'supplier_phone' => $payment->supplier->telepon ?? 'N/A',
                'amount' => floatval($payment->amount),
                'date' => $payment->date ? \Carbon\Carbon::parse($payment->date)->format('d/m/Y') : 'N/A',
                'payment_method' => $payment->payment_method ?? 'N/A',
                'created_at' => $payment->created_at ? \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y H:i') : 'N/A',
            ],
            'pembelian_details' => $pembelianDetails
        ]);
    }

    public function exportCashPaymentPdf($id)
    {
        $payment = Payment::with('supplier')->findOrFail($id);
        
        if ($payment->type !== 'cash_payment') {
            abort(404, 'Payment not found');
        }

        $pembelianIds = [];
        if ($payment->notes) {
            $notes = json_decode($payment->notes, true);
            if (isset($notes['pembelian_ids']) && is_array($notes['pembelian_ids'])) {
                $pembelianIds = $notes['pembelian_ids'];
            }
        }

        $pembelianDetails = Pembelian::with(['details.produk'])
            ->whereIn('id_pembelian', $pembelianIds)
            ->get();

        $setting = Setting::first();

        $pdf = PDF::loadView('payment.cash_payment_pdf', compact('payment', 'pembelianDetails', 'setting'));
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->download('cash_payment_' . $payment->reference_number . '_' . now()->format('Ymd_His') . '.pdf');
    }

    public function exportCashPaymentHistoryPdf(Request $request)
    {
        $query = Payment::with('supplier')
            ->where('type', 'cash_payment')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->input('end_date'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        $payments = $query->get();

        $setting = Setting::first();

        $filters = [
            'supplier' => $request->filled('supplier_id') ? Supplier::find($request->input('supplier_id'))->nama ?? 'All' : 'All',
            'payment_method' => $request->input('payment_method') ?? 'All',
            'start_date' => $request->input('start_date') ?? 'All',
            'end_date' => $request->input('end_date') ?? 'All',
        ];

        $pdf = PDF::loadView('payment.cash_payment_history_pdf', compact('payments', 'setting', 'filters'));
        $pdf->setPaper('A4', 'landscape');
        
        return $pdf->download('cash_payment_history_' . now()->format('Ymd_His') . '.pdf');
    }

    public function salesGeneratedCashPayments()
    {
        // Cash suppliers: mop = CASH or name ends with " (Cash)" (sales import)
        $suppliers = Supplier::where(function ($q) {
            $q->whereRaw('UPPER(TRIM(mop)) = ?', ['CASH'])
              ->orWhereRaw("nama LIKE '% (Cash)'");
        })->orderBy('nama')->get(['id_supplier', 'nama']);
        $missingBuyingPriceCount = app(MissingBuyingPriceLedgerService::class)->countForContext('cash');
        $consignmentSuppliers = Supplier::whereRaw('UPPER(TRIM(mop)) = ?', ['CONSIGNMENT'])->orderBy('nama')->get(['id_supplier', 'nama']);
        $cashSuppliersForFix = Supplier::where(function ($q) {
            $q->whereRaw('UPPER(TRIM(mop)) = ?', ['CASH'])
                ->orWhereRaw("nama LIKE '% (Cash)'");
        })->orderBy('nama')->get(['id_supplier', 'nama']);
        $missingBuyContext = 'cash';

        return view('payment.sales_generated_cash_payments', compact(
            'suppliers',
            'missingBuyingPriceCount',
            'consignmentSuppliers',
            'cashSuppliersForFix',
            'missingBuyContext'
        ));
    }

    public function missingBuyingPriceCount(Request $request)
    {
        $context = $request->get('context', 'consignment');
        if (! in_array($context, ['consignment', 'cash'], true)) {
            $context = 'consignment';
        }
        $count = app(MissingBuyingPriceLedgerService::class)->countForContext($context);

        return response()->json(['count' => $count]);
    }

    public function missingBuyingPriceData(Request $request)
    {
        $context = $request->get('context', 'consignment');
        if (! in_array($context, ['consignment', 'cash'], true)) {
            $context = 'consignment';
        }

        $service = app(MissingBuyingPriceLedgerService::class);
        $query = $service->baseDetailsQuery($context)->with(['penjualan', 'produk.supplier']);

        return datatables()
            ->eloquent($query)
            ->addIndexColumn()
            ->addColumn('receiptno', function ($row) {
                return $row->penjualan->receiptno ?? '—';
            })
            ->addColumn('sale_date', function ($row) {
                $p = $row->penjualan;
                if (! $p) {
                    return '—';
                }
                $d = $p->saledate ?? $p->created_at;

                return $d ? Carbon::parse($d)->format('Y-m-d') : '—';
            })
            ->addColumn('product_name', function ($row) {
                return $row->produk->nama_produk ?? '—';
            })
            ->addColumn('qty', fn ($row) => (int) ($row->jumlah ?? 0))
            ->addColumn('sale_price', function ($row) {
                return 'Ksh '.number_format((float) ($row->harga_jual ?? 0), 0);
            })
            ->addColumn('buying_price_status', fn () => '<span class="label label-danger">Missing buying price</span>')
            ->addColumn('owner', function ($row) {
                $s = $row->produk->supplier ?? null;
                if (! $s) {
                    return '<span class="text-muted">Not assigned</span>';
                }

                return e($s->nama).' <small class="text-muted">('.e($s->mop ?? '—').')</small>';
            })
            ->addColumn('aksi', function ($row) use ($context) {
                $sid = (int) ($row->produk->id_supplier ?? 0);
                $sname = optional($row->produk->supplier)->nama ?? '';

                return '<button type="button" class="btn btn-xs btn-primary btn-missing-buying-fix" '
                    .'data-id="'.(int) $row->id_penjualan_detail.'" '
                    .'data-context="'.e($context).'" '
                    .'data-product="'.e($row->produk->nama_produk ?? '').'" '
                    .'data-qty="'.(int) $row->jumlah.'" '
                    .'data-sale-price="'.(float) ($row->harga_jual ?? 0).'" '
                    .'data-supplier-id="'.$sid.'" '
                    .'data-supplier-name="'.e($sname).'"'
                    .'><i class="fa fa-edit"></i> Edit / Assign</button>';
            })
            ->rawColumns(['buying_price_status', 'owner', 'aksi'])
            ->make(true);
    }

    public function missingBuyingPriceFix(Request $request)
    {
        $request->validate([
            'id_penjualan_detail' => 'required|integer|exists:penjualan_detail,id_penjualan_detail',
            'harga_beli' => 'required|numeric|min:0.01',
            'id_supplier' => 'required|integer|exists:supplier,id_supplier',
            'accounting_type' => 'required|in:consignment,cash',
        ]);

        $detail = PenjualanDetail::with(['penjualan', 'produk'])->findOrFail($request->id_penjualan_detail);
        $penjualan = $detail->penjualan;

        if (! $penjualan) {
            return response()->json(['success' => false, 'message' => 'Sale not found for this line.'], 422);
        }

        if (Schema::hasColumn('penjualan', 'status') && ($penjualan->status ?? '') !== 'completed') {
            return response()->json(['success' => false, 'message' => 'Only completed sales can be fixed.'], 422);
        }

        $supplier = Supplier::findOrFail($request->id_supplier);
        $svc = app(MissingBuyingPriceLedgerService::class);

        if (! $svc->validateSupplierMatchesAccountingType($supplier, $request->accounting_type)) {
            return response()->json([
                'success' => false,
                'message' => 'The selected supplier does not match the accounting type (Consignment vs Cash).',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $produk = Produk::lockForUpdate()->find($detail->id_produk);
            if (! $produk) {
                DB::rollBack();

                return response()->json(['success' => false, 'message' => 'Product not found.'], 422);
            }

            $produk->harga_beli = (int) round((float) $request->harga_beli);
            $produk->id_supplier = $supplier->id_supplier;
            $produk->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('missingBuyingPriceFix: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Unable to save. Please try again.',
            ], 500);
        }

        // Create invoice / pembelian rows after commit so ledger queries see the updated product cost.
        $penjualanModel = Penjualan::find($penjualan->id_penjualan);
        $ledgerPayload = ['invoice_items_created' => 0, 'cash_pembelian_created' => 0, 'cash_lines_appended' => 0];
        if ($penjualanModel) {
            try {
                $ensure = app(EnsureSaleSupplierLedgerService::class);
                if (strtoupper(trim((string) ($supplier->mop ?? ''))) === 'CONSIGNMENT') {
                    $ledgerPayload['invoice_items_created'] = $ensure->fillConsignmentGapsWithRetries($penjualanModel, null, 5);
                } elseif ($ensure->isCashSupplier($supplier)) {
                    $ledgerPayload['cash_pembelian_created'] = $ensure->fillCashPembelianIfAbsentForPenjualan($penjualanModel);
                    $ledgerPayload['cash_lines_appended'] = $ensure->appendCashPembelianDetailsForPenjualan($penjualanModel);
                }
            } catch (\Throwable $e) {
                Log::error('missingBuyingPriceFix ledger: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

                return response()->json([
                    'success' => false,
                    'message' => 'Product was saved but ledger rows could not be created: '
                        .(config('app.debug') ? $e->getMessage() : 'See logs or try again.'),
                    'ledger' => $ledgerPayload,
                ], 500);
            }
        }

        $msg = 'Buying price and supplier saved.';
        if (($ledgerPayload['invoice_items_created'] + $ledgerPayload['cash_pembelian_created'] + $ledgerPayload['cash_lines_appended']) > 0) {
            $msg .= ' New supplier ledger lines were added for this sale.';
        } else {
            $msg .= ' No new consignment or cash-purchase lines were added (they may already exist, or run a DB migration if invoice_items.penjualan_id is missing).';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'ledger' => $ledgerPayload,
        ]);
    }

    /**
     * Build query for Sales Generated Cash Payments - same pattern as buildConsignmentQuery.
     * Returns one row per pembelian_detail (like Consignment returns one row per invoice_item).
     */
    protected function buildSalesGeneratedCashQuery(Request $request)
    {
        $query = PembelianDetail::query()
            ->join('pembelian', 'pembelian_detail.id_pembelian', '=', 'pembelian.id_pembelian')
            ->join('supplier', 'pembelian.id_supplier', '=', 'supplier.id_supplier')
            ->leftJoin('produk', 'pembelian_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('shops', 'produk.shop_id', '=', 'shops.id')
            ->where(function ($q) {
                $q->whereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CASH'])
                  ->orWhereRaw("supplier.nama LIKE '% (Cash)'");
            })
            ->whereRaw('pembelian.total_harga - COALESCE(pembelian.bayar, 0) > 0')
            ->select(
                'pembelian_detail.*',
                'pembelian.id_supplier',
                'pembelian.purchasedate2',
                'supplier.nama as supplier_nama',
                'produk.nama_produk',
                'produk.harga_jual',
                'shops.shop_name'
            )
            ->orderBy('pembelian.purchasedate2', 'desc')
            ->orderBy('pembelian_detail.id_pembelian_detail', 'desc');

        $query = app(EnsureSaleSupplierLedgerService::class)
            ->applyConfirmedOnlyFilterToCashPembelianDetailQuery($query);

        return $this->applySalesGeneratedCashFilters($request, $query);
    }

    protected function applySalesGeneratedCashFilters(Request $request, $query)
    {
        if ($request->filled('supplier_id')) {
            $query->where('pembelian.id_supplier', $request->input('supplier_id'));
        }

        $startDate = $request->filled('start_date') ? $this->normalizeDateToYmd($request->input('start_date')) : null;
        $endDate = $request->filled('end_date') ? $this->normalizeDateToYmd($request->input('end_date')) : null;

        // Avoid loading the entire sales history on first paint (50k+ rows timed out DataTables).
        if (! $startDate && ! $endDate) {
            $startDate = Carbon::now()->startOfMonth()->toDateString();
            $endDate = Carbon::now()->toDateString();
        }

        if ($startDate) {
            $query->whereDate('pembelian.purchasedate2', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('pembelian.purchasedate2', '<=', $endDate);
        }

        return $query;
    }

    public function salesGeneratedCashPaymentsData(Request $request)
    {
        try {
            set_time_limit(120);
            $query = $this->buildSalesGeneratedCashQuery($request);

            return datatables()
                ->of($query)
                ->addIndexColumn()
                ->addColumn('supplier_name', function ($row) {
                    return $row->supplier_nama ?? 'N/A';
                })
                ->addColumn('purchase_date', function ($row) {
                    return $row->purchasedate2 ? \Carbon\Carbon::parse($row->purchasedate2)->format('d/m/Y') : 'N/A';
                })
                ->addColumn('item_names', function ($row) {
                    return $row->nama_produk ?? 'N/A';
                })
                ->addColumn('shop_names', function ($row) {
                    return $row->shop_name ?? 'N/A';
                })
                ->addColumn('quantities', function ($row) {
                    return (int) ($row->jumlah ?? 0);
                })
                ->addColumn('selling_prices', function ($row) {
                    return 'Ksh ' . number_format((float) ($row->harga_jual ?? 0), 2);
                })
                ->addColumn('total_amount', function ($row) {
                    $qty = (int) ($row->jumlah ?? 0);
                    $price = (float) ($row->harga_jual ?? 0);
                    return 'Ksh ' . number_format($qty * $price, 2);
                })
                ->addColumn('aksi', function ($row) {
                    $u = auth()->user();
                    $buttons = '<div class="btn-group">';
                    $buttons .= '<button type="button" onclick="convertToConsignment(' . (int) $row->id_supplier . ', ' . (int) $row->id_pembelian . ')" class="btn btn-xs btn-warning btn-flat"><i class="fa fa-exchange"></i> Convert to Consignment</button>';
                    if ($u && $u->hasRole('admin')) {
                        $buttons .= '<button type="button" onclick="deleteCashPurchase(' . (int) $row->id_pembelian . ')" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i> Delete</button>';
                    }
                    $buttons .= '</div>';
                    return $buttons;
                })
                ->rawColumns(['aksi'])
                ->make(true);
        } catch (\Throwable $e) {
            Log::error('Sales generated cash payments data error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'draw' => (int) ($request->input('draw', 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred loading the table.',
            ]);
        }
    }

    public function deleteCashPurchase($id)
    {
        try {
            // Only admins can delete cash purchases
            if (!auth()->user()->hasRole('admin')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. Only administrators can delete cash purchases.'], 403);
            }

            DB::beginTransaction();

            $pembelian = Pembelian::with('details')->findOrFail($id);

            // Check if pembelian has been paid (bayar > 0)
            if (floatval($pembelian->bayar ?? 0) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete purchase that has been paid. Please undo the payment first.'
                ], 400);
            }

            // Check if any items have been converted to consignment
            $hasConversions = false;
            foreach ($pembelian->details as $detail) {
                $expectedAmount = $detail->harga_beli * $detail->jumlah;
                $pembelianDate = $pembelian->created_at ?? $pembelian->purchasedate2;
                if ($pembelianDate) {
                    $pembelianDate = \Carbon\Carbon::parse($pembelianDate);
                    $isConverted = DB::table('invoice_items')
                        ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                        ->where('invoice_items.supplier_id', $pembelian->id_supplier)
                        ->where('invoice_items.produk_id', $detail->id_produk)
                        ->where('invoice_items.quantity', $detail->jumlah)
                        ->whereRaw('ABS(invoice_items.amount - ?) < 0.01', [$expectedAmount])
                        ->whereRaw('invoices.created_at > ?', [$pembelianDate])
                        ->whereRaw('invoices.created_at <= DATE_ADD(?, INTERVAL 7 DAY)', [$pembelianDate])
                        ->exists();

                    if ($isConverted) {
                        $hasConversions = true;
                        break;
                    }
                }
            }

            if ($hasConversions) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete purchase that has items converted to consignment.'
                ], 400);
            }

            // Delete pembelian details first
            PembelianDetail::where('id_pembelian', $id)->delete();

            // Delete pembelian
            $pembelian->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cash purchase deleted successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting cash purchase: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting cash purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPembelianDetails($id)
    {
        try {
            $pembelian = Pembelian::with(['details.produk', 'supplier'])->findOrFail($id);
            
            return response()->json([
                'id_pembelian' => $pembelian->id_pembelian,
                'purchasedate2' => $pembelian->purchasedate2 ? \Carbon\Carbon::parse($pembelian->purchasedate2)->format('d/m/Y') : null,
                'total_harga' => floatval($pembelian->total_harga),
                'bayar' => floatval($pembelian->bayar ?? 0),
                'supplier' => [
                    'nama' => $pembelian->supplier->nama ?? 'N/A',
                    'telepon' => $pembelian->supplier->telepon ?? 'N/A',
                ],
                'details' => $pembelian->details->map(function($detail) {
                    return [
                        'id_pembelian_detail' => $detail->id_pembelian_detail,
                        'produk' => [
                            'id_produk' => $detail->produk->id_produk ?? null,
                            'nama_produk' => $detail->produk->nama_produk ?? 'Unknown',
                        ],
                        'jumlah' => $detail->jumlah,
                        'harga_beli' => floatval($detail->harga_beli),
                        'subtotal' => floatval($detail->subtotal),
                    ];
                })
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pembelian details: ' . $e->getMessage(), [
                'pembelian_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error loading purchase details: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportSalesGeneratedCashPaymentsPdf(Request $request)
    {
        // Same criteria as buildSalesGeneratedCashQuery (mop = CASH or name like '% (Cash)')
        $query = Pembelian::with(['supplier', 'details.produk.shop'])
            ->join('supplier', 'pembelian.id_supplier', '=', 'supplier.id_supplier')
            ->where(function ($q) {
                $q->whereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CASH'])
                  ->orWhereRaw("supplier.nama LIKE '% (Cash)'");
            })
            ->whereRaw('pembelian.total_harga - COALESCE(pembelian.bayar, 0) > 0')
            ->select('pembelian.*')
            ->orderBy('pembelian.purchasedate2', 'desc');

        // Apply filters
        if ($request->filled('supplier_id')) {
            $query->where('pembelian.id_supplier', $request->input('supplier_id'));
        }

        if ($request->filled('start_date')) {
            $startDate = $this->normalizeDateToYmd($request->input('start_date'));
            if ($startDate) {
                $query->whereDate('pembelian.purchasedate2', '>=', $startDate);
            }
        }

        if ($request->filled('end_date')) {
            $endDate = $this->normalizeDateToYmd($request->input('end_date'));
            if ($endDate) {
                $query->whereDate('pembelian.purchasedate2', '<=', $endDate);
            }
        }

        $pembelians = $query->get();

        // Filter out purchases where ALL items have been converted to consignment
        $pembelians->load('details.produk');
        $ledgerEnsure = app(EnsureSaleSupplierLedgerService::class);
        $pembelians = $pembelians->filter(function($pembelian) use ($ledgerEnsure) {
            // Reload details if not loaded
            if (!$pembelian->relationLoaded('details')) {
                $pembelian->load('details.produk');
            }

            $visibleDetails = $pembelian->details->filter(function ($detail) use ($ledgerEnsure, $pembelian) {
                return ! $ledgerEnsure->pembelianDetailIsFromUnconfirmedDeferredSale($detail, $pembelian);
            });
            $pembelian->setRelation('details', $visibleDetails->values());
            
            // Check how many items from THIS SPECIFIC purchase have been converted
            $convertedCount = 0;
            $totalItems = $pembelian->details->count();
            
            if ($totalItems == 0) {
                return false; // Exclude purchases with no items
            }
            
            // For each detail, check if it has been converted
            foreach ($pembelian->details as $detail) {
                if ($detail->produk) {
                    // Check if THIS specific pembelian_detail has been converted
                    // We check if there's an invoice item that matches this detail's product/quantity/supplier
                    // AND was created from a conversion (by checking if invoice was created after pembelian)
                    // We use a reasonable time window (within 7 days) to ensure it's from this specific purchase conversion
                    // This prevents matching invoice items from previous conversions while allowing reasonable conversion time
                    $pembelianDate = $pembelian->created_at ?? $pembelian->purchasedate2;
                    $isConverted = false;
                    
                    if ($pembelianDate) {
                        $pembelianDate = \Carbon\Carbon::parse($pembelianDate);
                        // Check for invoice items created within 7 days after the purchase
                        // This ensures we only match conversions from THIS purchase, not previous ones
                        // The 7-day window allows for conversions made within a week of the purchase
                        // Check if this specific pembelian_detail has been converted to an invoice item
                        // We need to match: product, quantity, supplier, amount, and time window
                        // Use the EXACT same criteria as the consignment query to ensure consistency
                        $expectedAmount = $detail->harga_beli * $detail->jumlah;
                        $isConverted = DB::table('invoice_items')
                            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                            ->where('invoice_items.supplier_id', $pembelian->id_supplier)
                            ->where('invoice_items.produk_id', $detail->id_produk)
                            ->where('invoice_items.quantity', $detail->jumlah)
                            // Match the amount: invoice item amount must exactly match pembelian_detail's total
                            ->whereRaw('ABS(invoice_items.amount - ?) < 0.01', [$expectedAmount])
                            // CRITICAL: Invoice must be created AFTER pembelian (proves it's a conversion)
                            ->whereRaw('invoices.created_at > ?', [$pembelianDate])
                            // Time window: within 7 days (prevents matching old conversions)
                            ->whereRaw('invoices.created_at <= DATE_ADD(?, INTERVAL 7 DAY)', [$pembelianDate])
                            // Invoice item must also be created after pembelian
                            ->whereRaw('invoice_items.created_at > ?', [$pembelianDate])
                            ->whereRaw('invoice_items.created_at <= DATE_ADD(?, INTERVAL 7 DAY)', [$pembelianDate])
                            ->exists();
                    }
                    
                    if ($isConverted) {
                        $convertedCount++;
                    }
                }
            }
            
            // Only exclude if ALL items from THIS purchase have been converted
            return $convertedCount < $totalItems;
        });

        $setting = Setting::first();

        $filters = [
            'supplier' => $request->filled('supplier_id') ? Supplier::find($request->input('supplier_id'))->nama ?? 'All' : 'All',
            'start_date' => $request->input('start_date') ?? 'All',
            'end_date' => $request->input('end_date') ?? 'All',
        ];

        // Calculate total amount based on quantity × selling price
        $totalAmount = 0;
        foreach ($pembelians as $pembelian) {
            foreach ($pembelian->details as $detail) {
                if ($detail->produk) {
                    $sellingPrice = $detail->produk->harga_jual ?? 0;
                    $totalAmount += $detail->jumlah * $sellingPrice;
                }
            }
        }

        $pdf = PDF::loadView('payment.sales_generated_cash_payments_pdf', compact('pembelians', 'setting', 'filters', 'totalAmount'));
        $pdf->setPaper('A4', 'landscape');
        
        return $pdf->download('sales_generated_cash_payments_' . now()->format('Ymd_His') . '.pdf');
    }

    public function convertToConsignment($supplierId, Request $request)
    {
        try {
            DB::beginTransaction();

            $pembelianId = $request->input('pembelian_id');
            if (!$pembelianId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase ID is required'
                ], 400);
            }

            // Get the purchase record
            $pembelian = Pembelian::with(['details.produk', 'supplier'])->findOrFail($pembelianId);

            // Verify supplier matches
            if ($pembelian->id_supplier != $supplierId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase does not belong to this supplier'
                ], 400);
            }

            // Check if purchase has details
            if (!$pembelian->details || $pembelian->details->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase has no items to convert'
                ], 400);
            }

            // Verify supplier is currently on Cash payment method (case-insensitive check)
            if (!$pembelian->supplier || strtoupper(trim($pembelian->supplier->mop ?? '')) !== 'CASH') {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier is not on Cash payment method. Current method: ' . ($pembelian->supplier->mop ?? 'Unknown')
                ], 400);
            }

            // Get selected items from request
            $selectedItems = $request->input('selected_items', []);
            $selectedDetailIds = collect($selectedItems)->pluck('detail_id')->toArray();

            if (empty($selectedDetailIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select at least one item to convert'
                ], 400);
            }

            // Get selected details
            $selectedDetails = $pembelian->details->whereIn('id_pembelian_detail', $selectedDetailIds);
            
            // Note: We don't check for duplicates here because:
            // 1. There's no direct link between invoice_items and pembelian_details
            // 2. The same product/quantity from different purchases should be allowed
            // 3. Users can see in the UI which items are already converted
            // If a user tries to convert the same item twice, it will just create duplicate invoice items
            // which can be handled separately if needed

            // Create invoice for this purchase
            $invoice = new Invoice();
            $invoice->id_supplier = $supplierId;
            $invoice->total = 0; // Will be calculated from items
            $invoice->save();

            $invoiceTotal = 0;

            // Create invoice items only for selected purchase details
            foreach ($pembelian->details as $detail) {
                // Only process if this detail is in the selected items
                if (in_array($detail->id_pembelian_detail, $selectedDetailIds) && $detail->produk) {
                    $invoiceItem = new InvoiceItem();
                    $invoiceItem->uniqid = uniqid();
                    $invoiceItem->produk_id = $detail->id_produk;
                    $invoiceItem->supplier_id = $supplierId;
                    $invoiceItem->invoice_id = $invoice->id;
                    $invoiceItem->status = 'Not paid';
                    $invoiceItem->quantity = $detail->jumlah;
                    $invoiceItem->discount = 0; // No discount for converted purchases
                    // Use buying price (harga_beli) for the invoice item amount
                    $amount = $detail->jumlah * $detail->harga_beli;
                    $invoiceItem->amount = $amount;
                    $invoiceItem->balance = $amount; // Initial balance equals amount
                    $invoiceItem->amount_paid = 0;
                    $invoiceItem->save();

                    $invoiceTotal += $amount;
                }
            }

            // Update invoice total
            $invoice->total = $invoiceTotal;
            $invoice->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase successfully converted to Consignment. An invoice has been created.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error converting purchase to consignment: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'supplier_id' => $supplierId,
                'pembelian_id' => $request->input('pembelian_id')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error converting purchase: ' . $e->getMessage(),
                'error_details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Convert consignment invoice items to cash purchase
     */
    public function convertToCash($supplierId, Request $request)
    {
        try {
            DB::beginTransaction();

            // Get selected invoice item IDs
            $selectedItemIds = $request->input('selected_items', []);
            if (empty($selectedItemIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select at least one item to convert'
                ], 400);
            }

            // Get the supplier
            $supplier = Supplier::findOrFail($supplierId);

            // Verify supplier is on Consignment payment method
            if (strtoupper(trim($supplier->mop ?? '')) !== 'CONSIGNMENT') {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier is not on Consignment payment method. Current method: ' . ($supplier->mop ?? 'Unknown')
                ], 400);
            }

            // Get selected invoice items
            $invoiceItems = InvoiceItem::with(['produk', 'invoice'])
                ->where('supplier_id', $supplierId)
                ->whereIn('id', $selectedItemIds)
                ->get();

            if ($invoiceItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid invoice items found to convert'
                ], 400);
            }

            // Create purchase record
            $pembelian = new Pembelian();
            $pembelian->id_supplier = $supplierId;
            $pembelian->total_item = 0;
            $pembelian->total_harga = 0;
            $pembelian->reorder = 0;
            $pembelian->bayar = 0;
            $pembelian->purchasedate2 = Carbon::now()->toDateString();
            $pembelian->save();

            $totalItem = 0;
            $totalHarga = 0;

            // Create purchase details from invoice items
            foreach ($invoiceItems as $invoiceItem) {
                if (!$invoiceItem->produk) {
                    continue; // Skip if product doesn't exist
                }

                // Calculate unit price from invoice item amount
                $unitPrice = $invoiceItem->quantity > 0 
                    ? ($invoiceItem->amount / $invoiceItem->quantity) 
                    : 0;

                // Create purchase detail
                $pembelianDetail = new PembelianDetail();
                $pembelianDetail->id_pembelian = $pembelian->id_pembelian;
                $pembelianDetail->id_produk = $invoiceItem->produk_id;
                $pembelianDetail->harga_beli = $unitPrice;
                $pembelianDetail->jumlah = $invoiceItem->quantity;
                $pembelianDetail->subtotal = $invoiceItem->amount;
                $pembelianDetail->save();

                $totalItem += $invoiceItem->quantity;
                $totalHarga += $invoiceItem->amount;
            }

            // Update purchase totals
            $pembelian->total_item = $totalItem;
            $pembelian->total_harga = $totalHarga;
            $pembelian->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Consignment items successfully converted to Cash purchase. A purchase record has been created.',
                'pembelian_id' => $pembelian->id_pembelian
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error converting consignment to cash: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'supplier_id' => $supplierId,
                'selected_items' => $request->input('selected_items', [])
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error converting consignment: ' . $e->getMessage(),
                'error_details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}