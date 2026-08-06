<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Member;
use App\Models\Pembelian;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Supplier;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $kategori = Kategori::count();
        $produk = Produk::count();
        $supplier = Supplier::count();
        $member = Member::count();
        $penjualan = Penjualan::sum('diterima');
        $pengeluaran = Pengeluaran::sum('nominal');
        $pembelian = Pembelian::sum('bayar');
        $outOfStockCount = Produk::where('stok', '<=', 0)->count();
        $outOfStockProducts = Produk::with('shop:id,shop_name')
            ->where('stok', '<=', 0)
            ->orderBy('nama_produk')
            ->limit(10)
            ->get(['id_produk', 'nama_produk', 'stok', 'shop_id']);

        $tanggal_awal = date('Y-m-01');
        $tanggal_akhir = date('Y-m-d');

        $data_tanggal = array();
        $data_pendapatan = array();

        while (strtotime($tanggal_awal) <= strtotime($tanggal_akhir)) {
            $data_tanggal[] = (int) substr($tanggal_awal, 8, 2);

            $total_penjualan = Penjualan::where('created_at', 'LIKE', "%$tanggal_awal%")->sum('bayar');
            $total_pembelian = Pembelian::where('created_at', 'LIKE', "%$tanggal_awal%")->sum('bayar');
            $total_pengeluaran = Pengeluaran::where('created_at', 'LIKE', "%$tanggal_awal%")->sum('nominal');

            $pendapatan = $total_penjualan - $total_pembelian - $total_pengeluaran;
            $data_pendapatan[] += $pendapatan;

            $tanggal_awal = date('Y-m-d', strtotime("+1 day", strtotime($tanggal_awal)));
        }

        $tanggal_awal = date('Y-m-01');

        if (auth()->user()->level == 1) {
            return view('admin.dashboard', compact(
                'kategori',
                'produk',
                'supplier',
                'member',
                'penjualan',
                'pengeluaran',
                'pembelian',
                'tanggal_awal',
                'tanggal_akhir',
                'data_tanggal',
                'data_pendapatan',
                'outOfStockCount',
                'outOfStockProducts'
            ));
        } else {
            return view('kasir.dashboard');
        }
    }

    /**
     * Activities hub: list all system activities so user can work from one page.
     */
    public function activities()
    {
        $incompleteCount = Produk::where('is_incomplete', true)->count();
        $pendingReceiptCount = Penjualan::receiptConfirmationBadgeCount();
        return view('activities.index', compact('incompleteCount', 'pendingReceiptCount'));
    }
}
// visit "codeastro" for more projects!