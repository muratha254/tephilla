@extends('layouts.fleet')
@section('title', 'Generate Payroll')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Generate Payroll',
    'subtitle' => 'Create payroll run for selected employees',
    'backUrl' => route('hr.payroll.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Payroll', 'url' => route('hr.payroll.index')],
        ['label' => 'Generate'],
    ],
])

<form method="post" action="{{ route('hr.payroll.store') }}" class="sx-item-form">
    @csrf
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Period Label <span class="sx-req">*</span></label>
                        <input type="text" name="period_label" class="form-control" value="{{ old('period_label', $period_label) }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Period Start <span class="sx-req">*</span></label>
                        <input type="date" name="period_start" class="form-control" value="{{ old('period_start', $period_start) }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Period End <span class="sx-req">*</span></label>
                        <input type="date" name="period_end" class="form-control" value="{{ old('period_end', $period_end) }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Branch</label>
                        <select name="branch_id" class="form-control">
                            <option value="">All / Company</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @if((string) old('branch_id', $selectedBranchId) === (string) $branch->id) selected @endif>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>

            <div class="form-group">
                <label>Employees <span class="sx-req">*</span></label>
                <div style="margin-bottom:8px;">
                    <button type="button" class="btn btn-xs btn-default" id="sx-pay-select-all">Select All</button>
                    <button type="button" class="btn btn-xs btn-default" id="sx-pay-clear-all">Clear</button>
                </div>
                <div class="table-responsive" style="max-height:360px;overflow:auto;">
                    <table class="table table-bordered sx-gold-table">
                        <thead>
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Code</th>
                                <th>Name</th>
                                <th class="text-right">Basic Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employees as $employee)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" class="sx-pay-emp"
                                            @if(collect(old('employee_ids', []))->contains($employee->id)) checked @endif>
                                    </td>
                                    <td>{{ $employee->displayCode() }}</td>
                                    <td>{{ $employee->fullName() }}</td>
                                    <td class="text-right">{{ number_format((float) $employee->basic_salary, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-cog"></i> Generate</button>
                <a href="{{ route('hr.payroll.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    $('#sx-pay-select-all').on('click', function () { $('.sx-pay-emp').prop('checked', true); });
    $('#sx-pay-clear-all').on('click', function () { $('.sx-pay-emp').prop('checked', false); });
})(jQuery);
</script>
@endpush
