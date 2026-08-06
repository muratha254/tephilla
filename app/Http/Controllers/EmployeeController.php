<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\AdvanceSalaryPayment;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class EmployeeController extends Controller
{
    public function index()
    {
        return view('employee.index');
    }

    public function data(Request $request)
    {
        $query = Employee::query()
            ->select([
                'id',
                'name',
                'id_number',
                'kra_pin',
                'nssf_number',
                'gross_salary',
                'advance_salary',
                'status',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest();

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('status_badge', function ($employee) {
                $badge = $employee->status === 'active' ? 'success' : ($employee->status === 'inactive' ? 'warning' : 'danger');
                return '<span class="label label-'.$badge.'">'.ucfirst($employee->status).'</span>';
            })
            ->addColumn('gross_salary', function ($employee) {
                return 'KES ' . number_format($employee->gross_salary, 2);
            })
            ->addColumn('advance_salary', function ($employee) {
                $advance = $employee->advance_salary ?? 0;
                $class = $advance > 0 ? 'text-warning' : '';
                $status = $advance > 0 ? '<br><small class="label label-warning">Unpaid</small>' : '<br><small class="label label-success">Paid</small>';
                return '<span class="' . $class . '">KES ' . number_format($advance, 2) . '</span>' . $status;
            })
            ->addColumn('aksi', function ($employee) {
                $u = auth()->user();
                $buttons = '<div class="btn-group">';
                if ($u && ($u->hasModulePermission('payroll', 'update') || $u->hasRole('admin'))) {
                    $buttons .= '<button type="button" onclick="editForm(`'. route('employee.update', $employee->id) .'`)" class="btn btn-xs btn-primary btn-flat" title="Edit"><i class="fa fa-pencil"></i></button>';
                    $buttons .= '<button type="button" onclick="addAdvanceSalary(`'. $employee->id .'`, `'. addslashes($employee->name) .'`, `'. ($employee->advance_salary ?? 0) .'`)" class="btn btn-xs btn-warning btn-flat" title="Add Advance Salary"><i class="fa fa-money"></i></button>';
                    if (($employee->advance_salary ?? 0) > 0) {
                        $buttons .= '<button type="button" onclick="recordAdvancePayment(`'. $employee->id .'`, `'. addslashes($employee->name) .'`, `'. ($employee->advance_salary ?? 0) .'`)" class="btn btn-xs btn-success btn-flat" title="Record Payment"><i class="fa fa-check"></i></button>';
                    }
                }
                if ($u && ($u->hasModulePermission('payroll', 'delete') || $u->hasRole('admin'))) {
                    $buttons .= '<button type="button" onclick="deleteData(`'. route('employee.destroy', $employee->id) .'`)" class="btn btn-xs btn-danger btn-flat" title="Delete"><i class="fa fa-trash"></i></button>';
                }
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['aksi', 'status_badge', 'advance_salary'])
            ->make(true);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'id_number' => 'required|string|unique:employees,id_number',
            'employer_number' => 'nullable|string',
            'kra_pin' => 'nullable|string',
            'nssf_number' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'date_of_employment' => 'nullable|date',
            'status' => 'required|in:active,inactive,terminated',
            'gross_salary' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors(), 'message' => 'Please check your input'], 422);
        }

        $employee = Employee::create($request->all());

        return response()->json(['data' => $employee, 'message' => 'Employee created successfully']);
    }

    public function show($id)
    {
        $employee = Employee::with('payrolls')->findOrFail($id);
        return response()->json(['data' => $employee]);
    }

    public function edit($id)
    {
        $employee = Employee::findOrFail($id);
        return response()->json(['data' => $employee]);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'id_number' => 'required|string|unique:employees,id_number,' . $id,
            'employer_number' => 'nullable|string',
            'kra_pin' => 'nullable|string',
            'nssf_number' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'date_of_employment' => 'nullable|date',
            'status' => 'required|in:active,inactive,terminated',
            'gross_salary' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors(), 'message' => 'Please check your input'], 422);
        }

        $employee->update($request->all());

        return response()->json(['data' => $employee, 'message' => 'Employee updated successfully']);
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return response()->json(['message' => 'Employee deleted successfully']);
    }

    public function addAdvanceSalary(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'request_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors(), 'message' => 'Please check your input'], 422);
        }

        // Add to existing advance salary
        $currentAdvance = $employee->advance_salary ?? 0;
        $newAdvance = $currentAdvance + $request->amount;
        
        $employee->advance_salary = $newAdvance;
        $employee->save();

        return response()->json([
            'data' => $employee,
            'message' => 'Advance salary added successfully. New total: KES ' . number_format($newAdvance, 2)
        ]);
    }

    public function recordAdvancePayment(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:payroll_deduction,cash,bank_transfer,cheque,other',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors(), 'message' => 'Please check your input'], 422);
        }

        $paymentAmount = $request->amount;
        $currentAdvance = $employee->advance_salary ?? 0;

        if ($paymentAmount > $currentAdvance) {
            return response()->json(['errors' => ['amount' => ['Payment amount cannot exceed outstanding advance salary']], 'message' => 'Payment amount exceeds outstanding balance'], 422);
        }

        // Record the payment
        $payment = AdvanceSalaryPayment::create([
            'employee_id' => $id,
            'payroll_id' => $request->payroll_id ?? null,
            'amount' => $paymentAmount,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'status' => 'paid',
            'notes' => $request->notes,
        ]);

        // Reduce employee's advance salary balance
        $employee->advance_salary = max(0, $currentAdvance - $paymentAmount);
        $employee->save();

        return response()->json([
            'data' => $payment,
            'message' => 'Advance salary payment recorded successfully. Remaining balance: KES ' . number_format($employee->advance_salary, 2)
        ]);
    }
}
