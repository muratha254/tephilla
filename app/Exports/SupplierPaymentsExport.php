<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Http\Request;

class SupplierPaymentsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        return Payment::with('supplier')
            ->when($this->request->filled('start_date'), function($q) {
                return $q->whereDate('date', '>=', $this->request->start_date);
            })
            ->when($this->request->filled('end_date'), function($q) {
                return $q->whereDate('date', '<=', $this->request->end_date);
            })
            ->when($this->request->filled('supplier_id'), function($q) {
                return $q->where('supplier_id', $this->request->supplier_id);
            })
            ->orderBy('date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Reference Number',
            'Supplier',
            'Amount',
            'Payment Method',
            'Notes'
        ];
    }

    public function map($payment): array
    {
        return [
            date('Y-m-d', strtotime($payment->date)),
            $payment->reference_number ?? '',
            $payment->supplier->nama ?? '',
            number_format($payment->amount, 2),
            $payment->payment_method ?? '',
            $payment->notes ?? ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E4E4E4']
                ]
            ],
            'D' => ['numberFormat' => ['formatCode' => '#,##0.00']],
        ];
    }
}
