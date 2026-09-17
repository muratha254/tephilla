@extends('layouts.fleet')
@section('title', 'Payroll')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Payroll',
    'subtitle' => 'Payroll Runs',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Payroll'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('hr.payroll.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Generate Payroll</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'payroll-table'])
        <div class="table-responsive">
            <table id="payroll-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Period</th>
                        <th>Dates</th>
                        <th>Employees</th>
                        <th>Gross</th>
                        <th>Deductions</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($runs as $row)
                        <tr>
                            <td>{{ $row->number }}</td>
                            <td>{{ $row->period_label }}</td>
                            <td>{{ optional($row->period_start)->format('Y-m-d') }} — {{ optional($row->period_end)->format('Y-m-d') }}</td>
                            <td>{{ $row->items_count }}</td>
                            <td data-order="{{ $row->total_gross }}">{{ number_format((float) $row->total_gross, 2) }}</td>
                            <td data-order="{{ $row->total_deductions }}">{{ number_format((float) $row->total_deductions, 2) }}</td>
                            <td data-order="{{ $row->total_net }}">{{ number_format((float) $row->total_net, 2) }}</td>
                            <td>{{ ucfirst($row->status) }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li><a href="{{ route('hr.payroll.show', $row) }}"><i class="fa fa-eye"></i> View</a></li>
                                        @if($canManage && $row->status === 'draft')
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-pay-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-pay-{{ $row->id }}" action="{{ route('hr.payroll.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'payroll-table', 'lengthId' => 'payroll-table-length', 'searchId' => 'payroll-table-search',
    'exportId' => 'payroll-table-export', 'colvisId' => 'payroll-table-colvis',
    'title' => 'Payroll', 'filename' => 'payroll-runs', 'noSort' => [8], 'order' => [[0, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (form && confirm('Delete this draft payroll?')) form.submit();
    });
})(jQuery);
</script>
@endpush
