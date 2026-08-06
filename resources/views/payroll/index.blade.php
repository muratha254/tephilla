@extends('layouts.master')

@section('title')
    Payroll List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Payroll List</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                @php($u = auth()->user())
                @if($u && ($u->hasModulePermission('payroll', 'read') || $u->hasRole('admin')))
                <a href="{{ route('payroll.dashboard') }}" class="btn btn-primary btn-flat"><i class="fa fa-dashboard"></i> Payroll Dashboard</a>
                <button onclick="updatePeriode()" class="btn btn-info btn-flat"><i class="fa fa-filter"></i> Filter</button>
                <button onclick="exportReport()" class="btn btn-success btn-flat"><i class="fa fa-file-pdf-o"></i> Export PDF</button>
                @endif
            </div>
            <div class="box-body table-responsive">
                <table class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th width="20%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('payroll.filter')

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
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script>
    let table;

    $(function () {
        table = $('.table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('payroll.data') }}',
                data: function (d) {
                    d.start_date = $('#start_date').val();
                    d.end_date = $('#end_date').val();
                    d.employee_id = $('#employee_id').val();
                    d.status = $('#status').val();
                },
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'payroll_date'},
                {data: 'employee_name'},
                {data: 'type'},
                {data: 'net_salary'},
                {data: 'status_badge'},
                {data: 'aksi', searchable: false, sortable: false},
            ]
        });

        // Date picker
        $('#start_date, #end_date').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true
        });
    });

    function updatePeriode() {
        $('#modal-filter').modal('show');
    }

    function exportReport() {
        const startDate = $('#start_date').val() || '{{ date('Y-m-01') }}';
        const endDate = $('#end_date').val() || '{{ date('Y-m-d') }}';
        const employeeId = $('#employee_id').val() || '';
        const status = $('#status').val() || '';
        
        let url = '{{ route('payroll.export-report') }}?start_date=' + startDate + '&end_date=' + endDate;
        if (employeeId) url += '&employee_id=' + employeeId;
        if (status) url += '&status=' + status;
        
        window.open(url, '_blank');
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
                        <tr><th>Status:</th><td><span class="label label-${payroll.status === 'completed' ? 'success' : 'warning'}">${payroll.status}</span></td></tr>
                        <tr><th>Gross Salary:</th><td>KES ${parseFloat(payroll.gross_salary).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>PAYE:</th><td>KES ${parseFloat(payroll.paye).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>SHA:</th><td>KES ${parseFloat(payroll.nhif).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>NSSF (Employee):</th><td>KES ${parseFloat(payroll.nssf_employee).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>NSSF (Employer):</th><td>KES ${parseFloat(payroll.nssf_employer).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>Employee Pension:</th><td>KES ${parseFloat(payroll.employee_pension).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>Employer Pension:</th><td>KES ${parseFloat(payroll.employer_pension).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>Other Additions:</th><td>KES ${parseFloat(payroll.other_additions).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        <tr><th>Other Deductions:</th><td>KES ${parseFloat(payroll.other_deductions).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        ${payroll.advance_salary > 0 ? `<tr><th>Advance Salary:</th><td class="text-warning"><strong>KES ${parseFloat(payroll.advance_salary).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td></tr>` : ''}
                        <tr><th>Net Salary:</th><td><strong>KES ${parseFloat(payroll.net_salary).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td></tr>
                        <tr><th>Employer Contributions:</th><td>KES ${parseFloat(payroll.employer_contributions).toLocaleString('en-US', {minimumFractionDigits: 2})}</td></tr>
                        ${payroll.notes ? `<tr><th>Notes:</th><td>${payroll.notes}</td></tr>` : ''}
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
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
                table.ajax.reload();
            });
        }
    }

    function sendPayslip(url) {
        if (confirm('Send payslip to employee email?')) {
            $.post(url, {
                _token: $('meta[name="csrf-token"]').attr('content')
            }, function(response) {
                alert(response.message || 'Payslip sent successfully');
            }).fail(function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    alert(xhr.responseJSON.message);
                } else {
                    alert('Error sending payslip');
                }
            });
        }
    }
</script>
@endpush


