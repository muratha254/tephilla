<?php

namespace App\Http\Controllers;

use App\Services\FleetVehicleAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FleetAvailabilityController extends Controller
{
    public function index(Request $request, FleetVehicleAvailabilityService $availability)
    {
        $month = $this->resolveMonth($request->query('month'));
        $today = now()->startOfDay();

        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $startOfGrid = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $endOfGrid = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

        $days = [];
        $cursor = $startOfGrid->copy();
        while ($cursor->lte($endOfGrid)) {
            $snapshot = $availability->dailySnapshot($cursor->copy());
            $bookedCount = $snapshot['booked_count'];
            $total = $snapshot['total'];

            $days[] = [
                'date' => $cursor->copy(),
                'is_current_month' => $cursor->month === $month->month,
                'is_today' => $cursor->equalTo($today),
                'availability' => $snapshot,
                'status' => $this->dayStatus($bookedCount, $total),
                'unavailable_vehicles' => $snapshot['booked_vehicles'],
            ];
            $cursor->addDay();
        }

        return view('fleet.availability', [
            'activeMenu' => 'availability',
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
            'monthLabel' => $month->format('F Y'),
            'currentMonth' => $month->format('Y-m'),
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'days' => $days,
        ]);
    }

    private function resolveMonth(?string $monthParam): Carbon
    {
        if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            [$year, $month] = array_map('intval', explode('-', $monthParam));

            return Carbon::create($year, $month, 1)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function dayStatus(int $bookedCount, int $total): string
    {
        if ($total === 0 || $bookedCount === 0) {
            return 'all_available';
        }

        if ($bookedCount >= $total) {
            return 'fully_booked';
        }

        return 'partial';
    }
}
