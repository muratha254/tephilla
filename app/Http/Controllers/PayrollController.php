<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Setting;
use App\Models\AdvanceSalaryPayment;
use App\Services\PayrollCalculatorService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use PDF;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class PayrollController extends Controller
{
    protected $calculator;

    public function __construct(PayrollCalculatorService $calculator)
    {
        $this->calculator = $calculator;
    }

    public function dashboard()
    {
        $stats = Cache::remember('payroll.dashboard.stats', now()->addSeconds(60), function () {
            return [
                'activeEmployees' => Employee::where('status', 'active')->count(),
                'pendingPayrolls' => Payroll::where('status', 'pending')->count(),
                'completedPayrolls' => Payroll::where('status', 'completed')->count(),
                'complianceIssues' => 0, // Placeholder for custom logic
            ];
        });

        $recentPayrolls = Cache::remember('payroll.dashboard.recent', now()->addSeconds(60), function () {
            return Payroll::with('employee:id,name')
                ->latest()
                ->limit(10)
                ->get();
        });

        $payeBands = $this->calculator->getPAYEBands();
        $nhifTable = $this->calculator->getNHIFTable();
        $nssfDetails = $this->calculator->getNSSFDetails();

        return view('payroll.dashboard', compact(
            'stats',
            'recentPayrolls',
            'payeBands',
            'nhifTable',
            'nssfDetails'
        ));
    }

    public function calculate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gross_salary' => 'required|numeric|min:0',
            'employer_pension' => 'nullable|numeric|min:0',
            'employee_pension' => 'nullable|numeric|min:0',
            'other_additions' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors(), 'message' => 'Please check your input'], 422);
        }

        // Get employee advance salary if employee_id is provided
        $advanceSalary = 0;
        if ($request->has('employee_id') && $request->employee_id) {
            $employee = Employee::find($request->employee_id);
            if ($employee) {
                // Check if user specified a partial amount
                if ($request->has('advance_salary_amount') && $request->advance_salary_amount > 0) {
                    $requestedAmount = $request->advance_salary_amount;
                    $outstandingAdvance = $employee->advance_salary ?? 0;
                    // Use the minimum of requested amount and outstanding balance
                    $advanceSalary = min($requestedAmount, $outstandingAdvance);
                } else {
                    // Deduct full advance salary (default behavior)
                    $advanceSalary = $employee->advance_salary ?? 0;
                }
            }
        }

        $result = $this->calculator->calculatePayroll(
            $request->gross_salary,
            $request->employer_pension ?? 0,
            $request->employee_pension ?? 0,
            $request->other_additions ?? 0,
            $request->other_deductions ?? 0,
            $advanceSalary
        );

        return response()->json(['data' => $result, 'message' => 'Payroll calculated successfully']);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'payroll_date' => 'required|date',
            'type' => 'required|in:salary,maternity,bonus,allowance,other',
            'gross_salary' => 'required|numeric|min:0',
            'employer_pension' => 'nullable|numeric|min:0',
            'employee_pension' => 'nullable|numeric|min:0',
            'other_additions' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors(), 'message' => 'Please check your input'], 422);
        }

        // Get employee advance salary
        $employee = Employee::find($request->employee_id);
        $advanceSalary = 0;
        if ($employee) {
            // Check if user specified a partial amount
            if ($request->has('advance_salary_amount') && $request->advance_salary_amount > 0) {
                $requestedAmount = $request->advance_salary_amount;
                $outstandingAdvance = $employee->advance_salary ?? 0;
                // Use the minimum of requested amount and outstanding balance
                $advanceSalary = min($requestedAmount, $outstandingAdvance);
            } else {
                // Deduct full advance salary (default behavior)
                $advanceSalary = $employee->advance_salary ?? 0;
            }
        }

        // Calculate payroll
        $calculation = $this->calculator->calculatePayroll(
            $request->gross_salary,
            $request->employer_pension ?? 0,
            $request->employee_pension ?? 0,
            $request->other_additions ?? 0,
            $request->other_deductions ?? 0,
            $advanceSalary
        );

        // Create payroll record
        $payroll = Payroll::create([
            'employee_id' => $request->employee_id,
            'payroll_date' => $request->payroll_date,
            'type' => $request->type,
            'status' => 'pending',
            'gross_salary' => $calculation['gross_salary'],
            'employer_pension' => $calculation['employer_pension'],
            'employee_pension' => $calculation['employee_pension'],
            'other_additions' => $calculation['other_additions'],
            'other_deductions' => $calculation['other_deductions'],
            'advance_salary' => $calculation['advance_salary'],
            'paye' => $calculation['paye'],
            'nhif' => $calculation['nhif'],
            'nssf_employee' => $calculation['nssf_employee'],
            'nssf_employer' => $calculation['nssf_employer'],
            'net_salary' => $calculation['net_salary'],
            'employer_contributions' => $calculation['employer_contributions'],
            'notes' => $request->notes,
        ]);

        // If advance salary was deducted, record it as paid and reduce employee's advance balance
        if ($advanceSalary > 0) {
            // Record the payment
            AdvanceSalaryPayment::create([
                'employee_id' => $request->employee_id,
                'payroll_id' => $payroll->id,
                'amount' => $advanceSalary,
                'payment_date' => $request->payroll_date,
                'payment_method' => 'payroll_deduction',
                'status' => 'paid',
                'notes' => 'Deducted from payroll',
            ]);

            // Reduce employee's advance salary balance
            $employee->advance_salary = max(0, ($employee->advance_salary ?? 0) - $advanceSalary);
            $employee->save();
        }

        return response()->json(['data' => $payroll, 'message' => 'Payroll created successfully']);
    }

    public function index()
    {
        return view('payroll.index');
    }

    public function data(Request $request)
    {
        $query = Payroll::query()
            ->select([
                'id',
                'employee_id',
                'payroll_date',
                'type',
                'status',
                'gross_salary',
                'employee_pension',
                'employer_pension',
                'other_additions',
                'other_deductions',
                'advance_salary',
                'paye',
                'nhif',
                'nssf_employee',
                'nssf_employer',
                'net_salary',
                'employer_contributions',
                'created_at',
            ])
            ->with(['employee:id,name,email'])
            ->when($request->start_date, fn ($q) => $q->whereDate('payroll_date', '>=', $request->start_date))
            ->when($request->end_date, fn ($q) => $q->whereDate('payroll_date', '<=', $request->end_date))
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest('payroll_date');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('employee_name', function ($payroll) {
                return $payroll->employee->name ?? 'N/A';
            })
            ->addColumn('status_badge', function ($payroll) {
                $badge = $payroll->status === 'completed' ? 'success' : ($payroll->status === 'pending' ? 'warning' : 'danger');
                return '<span class="label label-'.$badge.'">'.ucfirst($payroll->status).'</span>';
            })
            ->addColumn('net_salary', function ($payroll) {
                return 'KES ' . number_format($payroll->net_salary, 2);
            })
            ->addColumn('aksi', function ($payroll) {
                $u = auth()->user();
                $buttons = '<div class="btn-group">';
                if ($u && ($u->hasModulePermission('payroll', 'read') || $u->hasRole('admin'))) {
                    $buttons .= '<button type="button" onclick="viewPayroll(`'. route('payroll.show', $payroll->id) .'`)" class="btn btn-xs btn-info btn-flat" title="View"><i class="fa fa-eye"></i></button>';
                    $buttons .= '<a href="'. route('payroll.payslip', $payroll->id) .'" target="_blank" class="btn btn-xs btn-primary btn-flat" title="Print Payslip"><i class="fa fa-print"></i></a>';
                }
                if ($payroll->employee && $payroll->employee->email && $u && ($u->hasModulePermission('payroll', 'read') || $u->hasRole('admin'))) {
                    $buttons .= '<button type="button" onclick="sendPayslip(`'. route('payroll.send-payslip', $payroll->id) .'`)" class="btn btn-xs btn-success btn-flat" title="Email Payslip"><i class="fa fa-envelope"></i></button>';
                }
                if ($payroll->status === 'pending' && $u && ($u->hasModulePermission('payroll', 'update') || $u->hasRole('admin'))) {
                    $buttons .= '<button type="button" onclick="completePayroll(`'. route('payroll.complete', $payroll->id) .'`)" class="btn btn-xs btn-warning btn-flat" title="Complete"><i class="fa fa-check"></i></button>';
                }
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['aksi', 'status_badge'])
            ->make(true);
    }

    public function show($id)
    {
        $payroll = Payroll::with('employee')->findOrFail($id);
        return response()->json(['data' => $payroll]);
    }

    public function complete($id)
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->status = 'completed';
        $payroll->save();

        return response()->json(['message' => 'Payroll marked as completed']);
    }

    public function getEmployees()
    {
        $employees = Employee::where('status', 'active')->get(['id', 'name', 'id_number', 'kra_pin', 'nssf_number', 'employer_number', 'gross_salary']);
        return response()->json(['data' => $employees]);
    }

    public function payslip($id)
    {
        $payroll = Payroll::with('employee')->findOrFail($id);
        $setting = Setting::first();
        
        $pdf = PDF::loadView('payroll.payslip', compact('payroll', 'setting'));
        $pdf->setPaper('a4', 'portrait');
        
        return $pdf->stream('payslip_' . $payroll->employee->name . '_' . $payroll->payroll_date . '.pdf');
    }

    public function sendPayslip($id)
    {
        $payroll = Payroll::with('employee')->findOrFail($id);
        
        if (!$payroll->employee || !$payroll->employee->email) {
            return response()->json(['message' => 'Employee email not found'], 422);
        }

        $setting = Setting::first();
        
        // Generate PDF
        $pdf = PDF::loadView('payroll.payslip', compact('payroll', 'setting'));
        $pdf->setPaper('a4', 'portrait');
        
        // Send email
        try {
            Mail::send('payroll.payslip_email', ['payroll' => $payroll, 'setting' => $setting], function ($message) use ($payroll, $pdf) {
                $message->to($payroll->employee->email, $payroll->employee->name)
                    ->subject('Payslip for ' . Carbon::parse($payroll->payroll_date)->format('F Y'))
                    ->attachData($pdf->output(), 'payslip_' . $payroll->employee->name . '_' . $payroll->payroll_date . '.pdf', [
                        'mime' => 'application/pdf',
                    ]);
            });
            
            return response()->json(['message' => 'Payslip sent successfully to ' . $payroll->employee->email]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error sending email: ' . $e->getMessage()], 500);
        }
    }

    public function exportReport(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        $employeeId = $request->get('employee_id');
        $status = $request->get('status');

        $query = Payroll::with('employee')
            ->whereDate('payroll_date', '>=', $startDate)
            ->whereDate('payroll_date', '<=', $endDate);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $payrolls = $query->orderBy('payroll_date', 'desc')->get();
        $setting = Setting::first();
        
        $totalNetSalary = $payrolls->sum('net_salary');
        $totalGrossSalary = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum(function($p) {
            return $p->paye + $p->nhif + $p->nssf_employee + $p->employee_pension + $p->other_deductions;
        });

        $pdf = PDF::loadView('payroll.report_pdf', compact('payrolls', 'setting', 'startDate', 'endDate', 'totalNetSalary', 'totalGrossSalary', 'totalDeductions'));
        $pdf->setPaper('a4', 'landscape');
        
        return $pdf->download('payroll_report_' . $startDate . '_to_' . $endDate . '.pdf');
    }

    public function salaryPaymentReport(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        $employeeId = $request->get('employee_id');

        $employees = Employee::orderBy('name')->get();

        $query = Payroll::with('employee')
            ->whereDate('payroll_date', '>=', $startDate)
            ->whereDate('payroll_date', '<=', $endDate)
            ->orderBy('payroll_date', 'desc');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $payrolls = $query->get();

        return view('reports.salary_payments', compact(
            'payrolls',
            'employees',
            'startDate',
            'endDate',
            'employeeId'
        ));
    }

    public function exportSalaryPaymentPdf(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        $employeeId = $request->get('employee_id');

        $employee = null;
        if ($employeeId) {
            $employee = Employee::find($employeeId);
        }

        $query = Payroll::with('employee')
            ->whereDate('payroll_date', '>=', $startDate)
            ->whereDate('payroll_date', '<=', $endDate)
            ->orderBy('payroll_date', 'desc');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $payrolls = $query->get();

        $totalNetSalary = $payrolls->sum('net_salary');
        $totalGrossSalary = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum(function($p) {
            return $p->paye + $p->nhif + $p->nssf_employee + $p->employee_pension + $p->other_deductions + $p->advance_salary;
        });
        $totalAdditions = $payrolls->sum('other_additions');
        $totalPaye = $payrolls->sum('paye');
        $totalNhif = $payrolls->sum('nhif');
        $totalNssf = $payrolls->sum('nssf_employee');
        $totalEmployerContributions = $payrolls->sum('employer_contributions');

        $pdf = PDF::loadView('reports.salary_payments_pdf', compact(
            'payrolls',
            'startDate',
            'endDate',
            'employee',
            'totalNetSalary',
            'totalGrossSalary',
            'totalDeductions',
            'totalAdditions',
            'totalPaye',
            'totalNhif',
            'totalNssf',
            'totalEmployerContributions'
        ));

        $filename = 'salary_payment_report_' . $startDate . '_to_' . $endDate . '.pdf';
        return $pdf->download($filename);
    }
}
