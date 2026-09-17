@extends('layouts.fleet')

@section('title', 'Journal Entry List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Journal Entry List',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Journal Entry List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        <div class="sx-toolbar-actions">
            @if($canManage)
                <a href="{{ route('accounting.journal.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> Add Journal</a>
                <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#sx-multi-journal-modal"><i class="fa fa-copy"></i> Multiple Journal</button>
            @endif
        </div>
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'je-table'])
        <div class="table-responsive">
            <table id="je-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="je-check-all"></th>
                        <th>Account Name</th>
                        <th>Transaction Date</th>
                        <th>Ref.</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="je-row-check"></td>
                            <td>{{ optional($row->account)->name }}</td>
                            <td data-order="{{ optional(optional($row->entry)->entry_date)->format('Y-m-d') }}">{{ optional(optional($row->entry)->entry_date)->format('d-m-Y') }}</td>
                            <td>{{ optional($row->entry)->reference ?: optional($row->entry)->number }}</td>
                            <td>{{ $row->description ?: optional($row->entry)->description }}</td>
                            <td>{{ number_format($row->debit, 2) }}</td>
                            <td>{{ number_format($row->credit, 2) }}</td>
                            <td>
                                @if($canManage && $row->entry)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-je-{{ $row->entry->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-je-{{ $row->entry->id }}" action="{{ route('accounting.journal.destroy', $row->entry) }}" method="post" class="hidden">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
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

@if($canManage)
<div class="modal fade" id="sx-multi-journal-modal" tabindex="-1">
    <div class="modal-dialog sx-mj-dialog" role="document">
        <form method="post" action="{{ route('accounting.journal.multiple') }}" class="modal-content sx-conversion-modal sx-mj-modal">
            @csrf
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-list"></i> Record Multiple Journals</h4>
            </div>
            <div class="modal-body">
                <div class="sx-mj-cols">
                    <div>Account Name <i class="fa fa-question-circle" title="Select the ledger account for this line"></i></div>
                    <div>Amount</div>
                    <div>Debit/Credit</div>
                    <div>Description</div>
                    <div></div>
                </div>
                <div class="row sx-mj-meta">
                    <div class="col-sm-7">
                        <label>Branch <span class="sx-req">*</span></label>
                        <select name="branch_id" class="form-control" required>
                            @foreach($branches as $option)
                                <option value="{{ $option->id }}" @if((string) ($selectedBranchId ?? '') === (string) $option->id) selected @endif>{{ ($companyName ?? '') ? $companyName.' - '.$option->name : $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-5">
                        <label>Transaction Date <span class="sx-req">*</span></label>
                        <input type="date" name="entry_date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <div id="sx-mj-lines">
                    <div class="sx-mj-line">
                        <select name="lines[0][ledger_account_id]" class="form-control">
                            <option value="">~~Select Account~~</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.01" min="0" name="lines[0][amount]" class="form-control" placeholder="Enter Amount">
                        <select name="lines[0][side]" class="form-control">
                            <option value="">~~Trans Type~~</option>
                            <option value="debit">Debit</option>
                            <option value="credit">Credit</option>
                        </select>
                        <input type="text" name="lines[0][description]" class="form-control" placeholder="Type here">
                        <button type="button" class="btn btn-success sx-mj-add" title="Add line"><i class="fa fa-plus"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer sx-mj-footer">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Submit</button>
                <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
            </div>
        </form>
    </div>
</div>
<template id="sx-mj-row-tpl">
    <div class="sx-mj-line">
        <select name="lines[__i__][ledger_account_id]" class="form-control">
            <option value="">~~Select Account~~</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}">{{ $account->name }}</option>
            @endforeach
        </select>
        <input type="number" step="0.01" min="0" name="lines[__i__][amount]" class="form-control" placeholder="Enter Amount">
        <select name="lines[__i__][side]" class="form-control">
            <option value="">~~Trans Type~~</option>
            <option value="debit">Debit</option>
            <option value="credit">Credit</option>
        </select>
        <input type="text" name="lines[__i__][description]" class="form-control" placeholder="Type here">
        <button type="button" class="btn btn-danger sx-mj-remove" title="Remove line"><i class="fa fa-minus"></i></button>
    </div>
</template>
@endif
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'je-table',
    'lengthId' => 'je-table-length',
    'searchId' => 'je-table-search',
    'exportId' => 'je-table-export',
    'colvisId' => 'je-table-colvis',
    'checkAll' => 'je-check-all',
    'rowCheck' => 'je-row-check',
    'title' => 'Journal Entry List',
    'filename' => 'journal-entries',
    'noSort' => [0, 7],
    'order' => [[2, 'desc']],
])

@push('scripts')
<script>
(function ($) {
    var mjIndex = 1;
    $(document).on('click', '.sx-mj-add', function () {
        var tpl = document.getElementById('sx-mj-row-tpl');
        if (!tpl) return;
        var html = tpl.innerHTML.replace(/__i__/g, String(mjIndex++));
        $('#sx-mj-lines').append(html);
    });
    $(document).on('click', '.sx-mj-remove', function () {
        $(this).closest('.sx-mj-line').remove();
    });
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({ icon: 'warning', title: 'Delete Journal', text: 'This journal will be removed.', showCancelButton: true, confirmButtonColor: '#dd4b39', confirmButtonText: 'Delete' })
                .then(function (result) { if (result.isConfirmed) form.submit(); });
            return;
        }
        if (window.confirm('Delete this journal?')) form.submit();
    });
})(jQuery);
</script>
@endpush
