@extends('layouts.fleet')
@section('title', 'Advance Salary')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Advance Salary',
    'subtitle' => 'View/Search Advance Salary',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Advance Salary'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('hr.advance-salary.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Allocate Advance</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'adv-table'])
        <div class="table-responsive">
            <table id="adv-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="adv-check-all"></th>
                        <th>Employee</th>
                        <th>Month</th>
                        <th>Advance Date</th>
                        <th>Amount</th>
                        <th>Comment</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($advances as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="adv-row-check"></td>
                            <td>{{ optional($row->employee)->fullName() }}-{{ optional($row->employee)->displayCode() }}</td>
                            <td>{{ $row->monthLabel() }}</td>
                            <td data-order="{{ optional($row->advance_date)->format('Y-m-d') }}">{{ optional($row->advance_date)->format('Y-m-d') }}</td>
                            <td data-order="{{ $row->amount }}">{{ number_format((float) $row->amount, 2) }}</td>
                            <td>{{ $row->description }}</td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li><a href="{{ route('hr.advance-salary.edit', $row) }}"><i class="fa fa-pencil"></i> Edit/View Advance</a></li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-adv-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-adv-{{ $row->id }}" action="{{ route('hr.advance-salary.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
                                            </li>
                                        </ul>
                                    </div>
                                @endif
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
    'tableId' => 'adv-table', 'lengthId' => 'adv-table-length', 'searchId' => 'adv-table-search',
    'exportId' => 'adv-table-export', 'colvisId' => 'adv-table-colvis',
    'checkAll' => 'adv-check-all', 'rowCheck' => 'adv-row-check',
    'title' => 'Advance Salary', 'filename' => 'advance-salary', 'noSort' => [0, 6], 'order' => [[3, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Delete Advance',text:'This advance salary record will be removed.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Delete'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Delete this advance?')) form.submit();
    });
})(jQuery);
</script>
@endpush
