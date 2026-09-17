@extends('layouts.fleet')

@section('title', 'Files List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Files List',
    'subtitle' => 'View/Search Files',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Files List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('documents.create') }}" class="btn sx-btn-gold"><i class="fa fa-plus"></i> New File</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', [
            'tableId' => 'files-table',
            'exportId' => 'files-table-export-hidden',
        ])
        <style>#files-table-export-hidden { display: none !important; }</style>
        <div class="table-responsive">
            <table id="files-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="files-check-all"></th>
                        <th>File Title</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Created By</th>
                        <th>Status</th>
                        <th>Expire Date</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($files as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="files-row-check" value="{{ $row->id }}"></td>
                            <td>{{ $row->title }}</td>
                            <td>{{ optional($row->category)->name }}</td>
                            <td>{{ $row->description }}</td>
                            <td>{{ optional($row->user)->name }}</td>
                            <td>
                                @if($row->resolveStatus() === 'expired')
                                    <span class="sx-status-inactive">Expired</span>
                                @else
                                    <span class="sx-status-active">Active</span>
                                @endif
                            </td>
                            <td data-order="{{ optional($row->expires_at)->format('Y-m-d') }}">{{ optional($row->expires_at)->format('d-m-Y') ?: '-' }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li><a href="{{ route('documents.download', $row) }}"><i class="fa fa-download"></i> Download</a></li>
                                        @if($canManage)
                                            <li><a href="{{ route('documents.edit', $row) }}"><i class="fa fa-edit"></i> Edit</a></li>
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-file-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-file-{{ $row->id }}" action="{{ route('documents.destroy', $row) }}" method="post" class="hidden">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
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
    'tableId' => 'files-table',
    'lengthId' => 'files-table-length',
    'searchId' => 'files-table-search',
    'exportId' => 'files-table-export-hidden',
    'colvisId' => 'files-table-colvis',
    'checkAll' => 'files-check-all',
    'rowCheck' => 'files-row-check',
    'title' => 'Files List',
    'filename' => 'files-list',
    'noSort' => [0, 7],
    'order' => [[1, 'asc']],
])

@push('scripts')
<script>
(function ($) {
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({ icon: 'warning', title: 'Delete File', text: 'This file will be removed.', showCancelButton: true, confirmButtonColor: '#dd4b39', confirmButtonText: 'Delete' })
                .then(function (result) { if (result.isConfirmed) form.submit(); });
            return;
        }
        if (window.confirm('Delete this file?')) form.submit();
    });
})(jQuery);
</script>
@endpush
