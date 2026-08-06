@extends('layouts.master')

@section('title')
    Payroll Dashboard
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Payroll Dashboard</li>
@endsection

@push('css')
<style>
    .kpi-card {
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 20px;
        margin-bottom: 20px;
        background: white;
    }
    .kpi-value {
        font-size: 32px;
        font-weight: bold;
        margin: 10px 0;
    }
    .kpi-label {
        color: #666;
        font-size: 14px;
        text-transform: uppercase;
    }
    .status-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    .status-success { background-color: #28a745; }
    .status-warning { background-color: #ffc107; }
    .status-danger { background-color: #dc3545; }
    .status-info { background-color: #17a2b8; }
    .calculator-panel {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .results-card {
        background: #e8f5e9;
        border-left: 4px solid #4caf50;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
    }
    .statutory-table {
        font-size: 12px;
    }
</style>
@endpush

@section('content')
<div class="row">
    <!-- KPI Summary Cards -->
    <div class="col-lg-3 col-xs-6">
        <div class="kpi-card">
            <div class="kpi-label">Active Employees</div>
            <div class="kpi-value text-success">{{ $stats['activeEmployees'] ?? 0 }}</div>
            <div><span class="status-indicator status-success"></span>Currently Active</div>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="kpi-card">
            <div class="kpi-label">Pending Payrolls</div>
            <div class="kpi-value text-warning">{{ $stats['pendingPayrolls'] ?? 0 }}</div>
            <div><span class="status-indicator status-warning"></span>Awaiting Processing</div>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="kpi-card">
            <div class="kpi-label">Completed Payrolls</div>
            <div class="kpi-value text-info">{{ $stats['completedPayrolls'] ?? 0 }}</div>
            <div><span class="status-indicator status-info"></span>This Month</div>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="kpi-card">
            <div class="kpi-label">Compliance Issues</div>
            <div class="kpi-value text-danger">{{ $stats['complianceIssues'] ?? 0 }}</div>
            <div><span class="status-indicator status-danger"></span>Requires Attention</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Payroll Calculator Section -->
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-calculator"></i> Payroll Calculator</h3>
            </div>
            <div class="box-body">
                <form id="payroll-form">
                    <div class="row">
                        <!-- Panel A: Employee Information -->
                        <div class="col-md-6">
                            <div class="calculator-panel">
                                <h4><i class="fa fa-user"></i> Employee Information</h4>
                                <div class="form-group">
                                    <label>Employee Name</label>
                                    <select class="form-control" id="employee_id" name="employee_id">
                                        <option value="">Select Employee</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Employer (Payroll) Number</label>
                                    <input type="text" class="form-control" id="employer_number" name="employer_number" readonly>
                                </div>
                                <div class="form-group">
                                    <label>ID Number</label>
                                    <input type="text" class="form-control" id="id_number" name="id_number" readonly>
                                </div>
                                <div class="form-group">
                                    <label>KRA PIN</label>
                                    <input type="text" class="form-control" id="kra_pin" name="kra_pin" readonly>
                                </div>
                                <div class="form-group">
                                    <label>NSSF Number</label>
                                    <input type="text" class="form-control" id="nssf_number" name="nssf_number" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Panel B: Salary & Earnings Information -->
                        <div class="col-md-6">
                            <div class="calculator-panel">
                                <h4><i class="fa fa-money"></i> Salary & Earnings Information</h4>
                                <div class="form-group">
                                    <label>Gross Salary (KES)</label>
                                    <input type="number" class="form-control" id="gross_salary" name="gross_salary" step="0.01" min="0" required>
                                </div>
                                <div class="form-group">
                                    <label>Statutory Deductions (Auto-calculated)</label>
                                    <div class="form-control-static">
                                        <small>PAYE, SHA, NSSF will be calculated automatically</small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Employer Pension (Optional)</label>
                                    <input type="number" class="form-control" id="employer_pension" name="employer_pension" step="0.01" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Employee Pension (Optional)</label>
                                    <input type="number" class="form-control" id="employee_pension" name="employee_pension" step="0.01" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Other Additions (Optional)</label>
                                    <input type="number" class="form-control" id="other_additions" name="other_additions" step="0.01" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Other Deductions (Optional)</label>
                                    <input type="number" class="form-control" id="other_deductions" name="other_deductions" step="0.01" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Payroll Date</label>
                                    <input type="date" class="form-control" id="payroll_date" name="payroll_date" value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="form-group">
                                    <label>Type</label>
                                    <select class="form-control" id="type" name="type" required>
                                        <option value="salary">Salary</option>
                                        <option value="maternity">Maternity</option>
                                        <option value="bonus">Bonus</option>
                                        <option value="allowance">Allowance</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div id="advance-salary-info" style="display: none;"></div>
                                
                                <!-- Advance Salary Deduction Options -->
                                <div id="advance-salary-options" style="display: none; margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 5px; border: 1px solid #ffc107;">
                                    <h5><i class="fa fa-money"></i> Advance Salary Deduction</h5>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="deduct_full_advance" checked onchange="toggleAdvanceSalaryInput()">
                                            Deduct Full Advance Salary
                                        </label>
                                    </div>
                                    <div id="partial-advance-container" style="display: none;">
                                        <div class="form-group">
                                            <label>Partial Advance Salary Amount (KES)</label>
                                            <input type="number" id="partial_advance_salary" class="form-control" step="0.01" min="0" placeholder="Enter amount to deduct">
                                            <small class="help-block">Enter the amount to deduct (must be less than or equal to outstanding advance)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-primary btn-lg btn-block" onclick="calculatePayroll()">
                                    <i class="fa fa-calculator"></i> Calculate Payroll
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Results Section -->
                <div id="results-section" style="display: none;">
                    <div class="results-card">
                        <h4><i class="fa fa-file-text"></i> Calculation Results</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th>PAYE</th>
                                        <td id="result-paye" class="text-right">KES 0.00</td>
                                    </tr>
                                    <tr>
                                        <th>SHA</th>
                                        <td id="result-nhif" class="text-right">KES 0.00</td>
                                    </tr>
                                    <tr>
                                        <th>NSSF (Employee)</th>
                                        <td id="result-nssf-employee" class="text-right">KES 0.00</td>
                                    </tr>
                                    <tr>
                                        <th>NSSF (Employer)</th>
                                        <td id="result-nssf-employer" class="text-right">KES 0.00</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Net Salary</th>
                                        <td id="result-net-salary" class="text-right text-success"><strong>KES 0.00</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Employer Contributions</th>
                                        <td id="result-employer-contributions" class="text-right">KES 0.00</td>
                                    </tr>
                                    <tr>
                                        <th>Advance Salary</th>
                                        <td id="result-advance-salary" class="text-right text-warning">KES 0.00</td>
                                    </tr>
                                    <tr>
                                        <th>Total Deductions</th>
                                        <td id="result-total-deductions" class="text-right text-danger">KES 0.00</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                        @php($u = auth()->user())
                        @if($u && ($u->hasModulePermission('payroll', 'create') || $u->hasRole('admin')))
                        <button type="button" class="btn btn-success" onclick="savePayroll()">
                            <i class="fa fa-save"></i> Save Payroll
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Payroll Activity Table -->
    <div class="col-lg-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-history"></i> Recent Payroll Activity</h3>
            </div>
            <div class="box-body table-responsive">
                <table id="payroll-table" class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th width="10%">Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <!-- Statutory Information Panel -->
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> Statutory Information</h3>
            </div>
            <div class="box-body">
                <!-- PAYE Tax Bands -->
                <h5>PAYE Tax Bands</h5>
                <table class="table table-bordered statutory-table">
                    <thead>
                        <tr>
                            <th>Band</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payeBands as $band)
                        <tr>
                            <td>{{ $band['band'] }}</td>
                            <td>{{ $band['rate'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- SHA Contribution Table -->
                <h5>SHA (Social Health Authority) Contribution</h5>
                <div style="max-height: 200px; overflow-y: auto;">
                    <table class="table table-bordered statutory-table">
                        <thead>
                            <tr>
                                <th>Salary Range</th>
                                <th>Contribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($nhifTable as $row)
                            <tr>
                                <td>{{ $row['salary_range'] }}</td>
                                <td>{{ $row['contribution'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- NSSF Contribution Details -->
                <h5>NSSF Contribution</h5>
                <table class="table table-bordered statutory-table">
                    <thead>
                        <tr>
                            <th>Tier</th>
                            <th>Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($nssfDetails as $detail)
                        <tr>
                            <td>{{ $detail['tier'] }}</td>
                            <td>{{ $detail['rate'] ?: $detail['pensionable_earnings'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for viewing payroll details -->
<div class="modal fade" id="modal-form" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let payrollTable;
    let calculationResult = null;

    $(function () {
        // Load employees
        loadEmployees();

        // Initialize payroll table
        payrollTable = $('#payroll-table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('payroll.data') }}',
            },
            columns: [
                { data: 'payroll_date' },
                { data: 'employee_name' },
                { data: 'type' },
                { data: 'net_salary' },
                { data: 'status_badge' },
                { data: 'aksi', searchable: false, sortable: false },
            ],
        });

        // Auto-fill employee details when selected
        $('#employee_id').on('change', function() {
            const employeeId = $(this).val();
            if (employeeId) {
                $.get(`/employee/${employeeId}`, function(response) {
                    const emp = response.data;
                    $('#employer_number').val(emp.employer_number || '');
                    $('#id_number').val(emp.id_number || '');
                    $('#kra_pin').val(emp.kra_pin || '');
                    $('#nssf_number').val(emp.nssf_number || '');
                    $('#gross_salary').val(emp.gross_salary || '');
                    // Show advance salary if exists
                    if (emp.advance_salary && emp.advance_salary > 0) {
                        $('#advance-salary-info').html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> This employee has an outstanding advance salary of KES ' + parseFloat(emp.advance_salary).toLocaleString('en-US', {minimumFractionDigits: 2}) + '.</div>').show();
                        $('#advance-salary-options').show();
                        $('#deduct_full_advance').prop('checked', true);
                        $('#partial_advance_salary').attr('max', emp.advance_salary);
                        $('#partial_advance_salary').val('');
                        toggleAdvanceSalaryInput();
                    } else {
                        $('#advance-salary-info').hide();
                        $('#advance-salary-options').hide();
                    }
                });
            } else {
                // Reset when no employee selected
                $('#advance-salary-info').hide();
                $('#advance-salary-options').hide();
            }
        });
    });

    function loadEmployees() {
        $.get('{{ route('payroll.employees') }}', function(response) {
            const select = $('#employee_id');
            select.empty().append('<option value="">Select Employee</option>');
            response.data.forEach(function(emp) {
                select.append(`<option value="${emp.id}">${emp.name} (${emp.id_number})</option>`);
            });
        });
    }

    function toggleAdvanceSalaryInput() {
        const deductFull = $('#deduct_full_advance').is(':checked');
        if (deductFull) {
            $('#partial-advance-container').slideUp();
            $('#partial_advance_salary').val('');
        } else {
            $('#partial-advance-container').slideDown();
            $('#partial_advance_salary').focus();
        }
    }

    function calculatePayroll() {
        const employeeId = $('#employee_id').val();
        const formData = {
            gross_salary: parseFloat($('#gross_salary').val()) || 0,
            employer_pension: parseFloat($('#employer_pension').val()) || 0,
            employee_pension: parseFloat($('#employee_pension').val()) || 0,
            other_additions: parseFloat($('#other_additions').val()) || 0,
            other_deductions: parseFloat($('#other_deductions').val()) || 0,
        };
        
        if (employeeId) {
            formData.employee_id = employeeId;
            
            // Handle advance salary deduction
            const deductFull = $('#deduct_full_advance').is(':checked');
            if (!deductFull) {
                const partialAmount = parseFloat($('#partial_advance_salary').val()) || 0;
                const maxAmount = parseFloat($('#partial_advance_salary').attr('max')) || 0;
                
                if (partialAmount <= 0) {
                    alert('Please enter a valid partial advance salary amount or select "Deduct Full Advance Salary"');
                    return;
                }
                
                if (partialAmount > maxAmount) {
                    alert('Partial amount cannot exceed outstanding advance salary of KES ' + maxAmount.toLocaleString('en-US', {minimumFractionDigits: 2}));
                    return;
                }
                
                formData.advance_salary_amount = partialAmount;
            } else {
                // Full deduction - will be handled by backend
                formData.deduct_full_advance = true;
            }
        }

        if (!formData.gross_salary || formData.gross_salary <= 0) {
            alert('Please enter a valid gross salary');
            return;
        }

        $.ajax({
            url: '{{ route('payroll.calculate') }}',
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                calculationResult = response.data;
                displayResults(response.data);
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    alert('Validation errors: ' + JSON.stringify(xhr.responseJSON.errors));
                } else {
                    alert('Error calculating payroll');
                }
            }
        });
    }

    function displayResults(data) {
        $('#result-paye').text('KES ' + parseFloat(data.paye).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#result-nhif').text('KES ' + parseFloat(data.nhif).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#result-nssf-employee').text('KES ' + parseFloat(data.nssf_employee).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#result-nssf-employer').text('KES ' + parseFloat(data.nssf_employer).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#result-advance-salary').text('KES ' + parseFloat(data.advance_salary || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#result-net-salary').html('<strong>KES ' + parseFloat(data.net_salary).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong>');
        $('#result-employer-contributions').text('KES ' + parseFloat(data.employer_contributions).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#result-total-deductions').text('KES ' + parseFloat(data.total_deductions).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#results-section').slideDown();
    }

    function savePayroll() {
        if (!calculationResult) {
            alert('Please calculate payroll first');
            return;
        }

        const employeeId = $('#employee_id').val();
        if (!employeeId) {
            alert('Please select an employee');
            return;
        }

        const formData = {
            employee_id: employeeId,
            payroll_date: $('#payroll_date').val(),
            type: $('#type').val(),
            gross_salary: calculationResult.gross_salary,
            employer_pension: calculationResult.employer_pension,
            employee_pension: calculationResult.employee_pension,
            other_additions: calculationResult.other_additions,
            other_deductions: calculationResult.other_deductions,
            notes: $('#notes').val(),
        };
        
        // Include advance salary amount if partial deduction
        const deductFull = $('#deduct_full_advance').is(':checked');
        if (!deductFull) {
            const partialAmount = parseFloat($('#partial_advance_salary').val()) || 0;
            if (partialAmount > 0) {
                formData.advance_salary_amount = partialAmount;
            }
        }

        $.ajax({
            url: '{{ route('payroll.store') }}',
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                alert('Payroll saved successfully!');
                payrollTable.ajax.reload();
                $('#payroll-form')[0].reset();
                $('#results-section').slideUp();
                $('#advance-salary-options').hide();
                $('#advance-salary-info').hide();
                calculationResult = null;
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    alert('Validation errors: ' + JSON.stringify(xhr.responseJSON.errors));
                } else {
                    alert('Error saving payroll');
                }
            }
        });
    }

    function viewPayroll(url) {
        $.get(url, function(response) {
            const payroll = response.data;
            let html = `
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">Payroll Details</h4>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <tr><th>Employee:</th><td>${payroll.employee ? payroll.employee.name : 'N/A'}</td></tr>
                        <tr><th>Date:</th><td>${payroll.payroll_date}</td></tr>
                        <tr><th>Type:</th><td>${payroll.type}</td></tr>
                        <tr><th>Status:</th><td>${payroll.status}</td></tr>
                        <tr><th>Gross Salary:</th><td>KES ${parseFloat(payroll.gross_salary).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>PAYE:</th><td>KES ${parseFloat(payroll.paye).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>SHA:</th><td>KES ${parseFloat(payroll.nhif).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>NSSF (Employee):</th><td>KES ${parseFloat(payroll.nssf_employee).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>Net Salary:</th><td><strong>KES ${parseFloat(payroll.net_salary).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td></tr>
                    </table>
                </div>
            `;
            $('#modal-form .modal-content').html(html);
            $('#modal-form').modal('show');
        });
    }

    function completePayroll(url) {
        if (confirm('Mark this payroll as completed?')) {
            $.post(url, {
                _token: $('meta[name="csrf-token"]').attr('content')
            }, function(response) {
                alert('Payroll marked as completed');
                payrollTable.ajax.reload();
            });
        }
    }
</script>
@endpush

