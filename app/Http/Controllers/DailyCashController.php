<?php

namespace App\Http\Controllers;

use App\Models\DailyCash;
use App\Models\Penjualan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use PDF;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;
use App\Services\BackupRunnerService;

class DailyCashController extends Controller
{
    /**
     * Check if today is opened
     */
    public function check()
    {
        $today = DailyCash::getToday();
        
        if (!$today || !$today->is_opened) {
            return response()->json([
                'opened' => false,
                'message' => 'Cash on Hand must be entered before sales can be recorded.'
            ]);
        }

        if ($today->is_closed) {
            return response()->json([
                'opened' => false,
                'closed' => true,
                'message' => 'Today has already been closed. Please open a new day.'
            ]);
        }

        return response()->json([
            'opened' => true,
            'opening_cash' => $today->opening_cash
        ]);
    }

    /**
     * Show open day form
     */
    public function openForm()
    {
        $today = DailyCash::getToday();
        
        // If already opened and not closed, show edit option for admin
        if ($today && $today->is_opened && !$today->is_closed) {
            if (auth()->user()->hasRole('admin')) {
                return view('daily_cash.open', [
                    'isReopening' => false,
                    'isEditing' => true,
                    'existingCash' => $today
                ]);
            } else {
                return redirect()->route('dashboard')->with('info', 'Day is already opened.');
            }
        }

        // If closed, show message that admin can reopen
        if ($today && $today->is_closed) {
            if (auth()->user()->hasRole('admin')) {
                return view('daily_cash.open', ['isReopening' => true, 'existingCash' => $today]);
            } else {
                return redirect()->route('dashboard')->withErrors(['error' => 'This day has been closed. Only administrators can reopen it.']);
            }
        }

        return view('daily_cash.open', ['isReopening' => false, 'isEditing' => false]);
    }

    /**
     * Open the day with cash on hand
     */
    public function open(Request $request)
    {
        $request->validate([
            'opening_cash' => 'required|numeric|min:0'
        ]);

        $today = DailyCash::getToday();

        // If already opened and not closed, allow admin to edit opening cash
        if ($today && $today->is_opened && !$today->is_closed) {
            // Only admin can edit opening cash
            if (!auth()->user()->hasRole('admin')) {
                return redirect()->back()->withErrors(['error' => 'Day is already opened. Only administrators can edit the opening cash.']);
            }
            
            // Update opening cash (keep other data intact)
            $oldAmount = $today->opening_cash;
            $today->update([
                'opening_cash' => $request->opening_cash,
                // Recalculate net sales with new opening cash
                'net_sales' => $today->total_sales - $request->opening_cash
            ]);

            $this->dispatchBackupAfterDailyOpen();

            return redirect()->route('dashboard')->with('success', 'Opening cash updated successfully. Changed from Ksh ' . number_format($oldAmount, 2) . ' to Ksh ' . number_format($request->opening_cash, 2));
        }

        // If record exists and is closed, allow admin to reopen it
        if ($today && $today->is_closed) {
            // Only admin can reopen a closed day
            if (!auth()->user()->hasRole('admin')) {
                return redirect()->back()->withErrors(['error' => 'This day has been closed. Only administrators can reopen it.']);
            }
            
            // Reopen the existing day with new opening cash
            $today->update([
                'opening_cash' => $request->opening_cash,
                'is_opened' => true,
                'is_closed' => false,
                'opened_by' => auth()->id(),
                'opened_at' => Carbon::now(),
                'closed_by' => null,
                'closed_at' => null,
                // Recalculate net sales
                'net_sales' => $today->total_sales - $request->opening_cash
            ]);

            $this->dispatchBackupAfterDailyOpen();

            return redirect()->route('dashboard')->with('success', 'Day reopened successfully. Cash on Hand: Ksh ' . number_format($request->opening_cash, 2));
        }

        // Create new record for today
        $dailyCash = DailyCash::updateOrCreate(
            ['date' => Carbon::today()],
            [
                'opening_cash' => $request->opening_cash,
                'is_opened' => true,
                'is_closed' => false,
                'opened_by' => auth()->id(),
                'opened_at' => Carbon::now(),
                'total_sales' => 0,
                'net_sales' => 0
            ]
        );

        $this->dispatchBackupAfterDailyOpen();

        return redirect()->route('dashboard')->with('success', 'Day opened successfully. Cash on Hand: Ksh ' . number_format($request->opening_cash, 2));
    }

    /**
     * Queue a DB backup after the HTTP response so opening the day is not blocked by mysqldump.
     */
    protected function dispatchBackupAfterDailyOpen(): void
    {
        App::terminating(function () {
            try {
                app(BackupRunnerService::class)->run('daily_open', false);
            } catch (\Throwable $e) {
                Log::error('[Backup] Daily open backup failed', ['error' => $e->getMessage()]);
            }
        });
    }

    /**
     * Show close day form
     */
    public function closeForm()
    {
        $today = DailyCash::getToday();

        if (!$today || !$today->is_opened) {
            return redirect()->route('daily-cash.open-form')->withErrors(['error' => 'Cash on Hand must be entered before closing the day.']);
        }

        if ($today->is_closed) {
            return redirect()->route('dashboard')->with('info', 'Day is already closed.');
        }

        // Calculate total sales for today (only completed sales)
        $query = Penjualan::whereDate('created_at', Carbon::today());
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }

        $totalSales = $query->sum('bayar');
        $netSales = $totalSales - $today->opening_cash;

        // Update today's record with current totals
        $today->total_sales = $totalSales;
        $today->net_sales = $netSales;
        $today->save();

        return view('daily_cash.close', compact('today', 'totalSales', 'netSales'));
    }

    /**
     * Close the day
     */
    public function close(Request $request)
    {
        $today = DailyCash::getToday();

        if (!$today || !$today->is_opened) {
            return redirect()->route('daily-cash.open-form')->withErrors(['error' => 'Cash on Hand must be entered before closing the day.']);
        }

        if ($today->is_closed) {
            return redirect()->route('dashboard')->with('info', 'Day is already closed.');
        }

        // Calculate total sales for today
        $query = Penjualan::whereDate('created_at', Carbon::today());
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }

        $totalSales = $query->sum('bayar');
        $netSales = $totalSales - $today->opening_cash;

        // Close the day
        $today->update([
            'total_sales' => $totalSales,
            'net_sales' => $netSales,
            'is_closed' => true,
            'closed_by' => auth()->id(),
            'closed_at' => Carbon::now(),
            'notes' => $request->notes
        ]);

        return redirect()->route('daily-cash.close-report', $today->id)->with('success', 'Day closed successfully.');
    }

    /**
     * Show closing report
     */
    public function closeReport($id)
    {
        $dailyCash = DailyCash::with(['openedByUser', 'closedByUser'])->findOrFail($id);
        
        return view('daily_cash.report', compact('dailyCash'));
    }

    /**
     * Export closing report as PDF
     */
    public function exportPdf($id)
    {
        $dailyCash = DailyCash::with(['openedByUser', 'closedByUser'])->findOrFail($id);
        $setting = Setting::first();
        
        $pdf = PDF::loadView('daily_cash.report_pdf', compact('dailyCash', 'setting'));
        return $pdf->download('daily_closing_report_' . $dailyCash->date->format('Y-m-d') . '.pdf');
    }

    /**
     * Reopen a closed day (Admin only) - Quick reopen without changing opening cash
     */
    public function reopen($id)
    {
        // Check if user is admin
        if (!auth()->user()->hasRole('admin')) {
            return redirect()->back()->withErrors(['error' => 'Only administrators can reopen closed days.']);
        }

        $dailyCash = DailyCash::findOrFail($id);

        if (!$dailyCash->is_closed) {
            return redirect()->back()->withErrors(['error' => 'This day is not closed.']);
        }

        // Reopen the day - keep existing opening cash and sales data
        $dailyCash->update([
            'is_closed' => false,
            'closed_by' => null,
            'closed_at' => null,
            'is_opened' => true // Ensure it's marked as opened
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Day has been reopened successfully. You can now create sales for this day.');
    }
}
