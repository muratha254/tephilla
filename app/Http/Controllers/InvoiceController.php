<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pembelian;
use App\Models\Invoice;
use App\Models\Produk;
use App\Models\InvoiceItem;
use App\Models\PembelianDetail;
use App\Models\Supplier;

use PDF;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Pembelian::query();

        if ($request->filled('start_date')) {
            $query->whereDate('purchasedate2', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('purchasedate2', '<=', $request->input('end_date'));
        }

         $supplier = Supplier::orderBy('nama')->get();


       return view('invoice.index', compact('supplier'));

   
    }
    
    public function data(Request $request)
    {
        $query = Invoice::orderBy('id', 'desc');
    
        // Date Filtering
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
    
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }
    
        $invoices = $query->get();
    
        return datatables()
            ->of($invoices)
            ->addIndexColumn()
            ->addColumn('total', function ($invoice) {
                return 'Ksh '. format_uang($invoice->total);
            })
            ->addColumn('supplier', function ($invoice) {
                // Assuming you have a Supplier model related to the invoice
                return $invoice->supplier->nama;
            })
            ->addColumn('aksi', function ($invoice) {
                return '
                <div class="btn-group">
                    <button onclick="showDetail(`'. route('invoice.show', $invoice->id) .'`)" class="btn btn-xs btn-primary btn-flat"><i class="fa fa-eye"></i></button>
                    <button onclick="deleteData(`'. route('invoice.destroy', $invoice->id) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                </div>
                ';
            })
            ->rawColumns(['aksi'])
            ->make(true);
    }
    
    

    public function show($id)
    {

        $data = InvoiceItem::with('produk')->where('invoice_id', $id)->get();


        return datatables()
            ->of($data)
            ->addIndexColumn()
            ->addColumn('kode_produk', function ($data) {
                return '<span class="label label-success">'. $data->produk->kode_produk .'</span>';
            })
            ->addColumn('nama_produk', function ($data) {
                return $data->produk->nama_produk;
            })
            ->addColumn('harga_beli', function ($data) {
                return 'ksh '. format_uang($data->harga_beli);
            })
            ->addColumn('jumlah', function ($data) {
                return format_uang($data->jumlah);
            })
            ->addColumn('subtotal', function ($data) {
                return 'ksh '. format_uang($data->subtotal);
            })
            ->rawColumns(['kode_produk'])
            ->make(true);
    }

    public function destroy($id)
    {
        $invoice = Invoice::find($id);
        $detail    = InvoiceItem::where('invoice_id', $invoice->id)->get();
        foreach ($detail as $item) {
            $item->delete();
        }

        $invoice->delete();

        return response(null, 204);
    }
}
