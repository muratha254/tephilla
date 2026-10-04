@extends('layouts.fleet')
@section('title', 'Packaging Setup')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Packaging Setup',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Packaging Setup'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div><strong>Packaging List</strong></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#sx-packaging-setup-modal"><i class="fa fa-plus"></i> Create Setup</button>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'ps-table', 'exportId' => 'ps-table-export-hidden'])
        <style>#ps-table-export-hidden{display:none!important}</style>
        <div class="table-responsive">
            <table id="ps-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="ps-check-all"></th>
                        <th>Product</th>
                        <th>Description</th>
                        <th>Created By</th>
                        <th>Created Date</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($setups as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="ps-row-check"></td>
                            <td>{{ optional($row->product)->name }}</td>
                            <td>{{ $row->description }}</td>
                            <td>{{ optional($row->user)->name }}</td>
                            <td data-order="{{ $row->created_at }}">{{ optional($row->created_at)->format('Y-m-d H:i:s') }}</td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-ps-{{ $row->id }}"><i class="fa fa-trash"></i> Delete</a>
                                                <form id="sx-del-ps-{{ $row->id }}" action="{{ route('manufacturing.packaging.setups.destroy', $row) }}" method="post" class="hidden">@csrf @method('DELETE')</form>
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
<div class="modal fade" id="sx-packaging-setup-modal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('manufacturing.packaging.setups.store') }}" class="modal-content">
            @csrf
            <div class="modal-header" style="background:#A2502B;color:#fff;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-list"></i> Packaging Setup</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>PRODUCT <span class="sx-req">*</span></label>
                    <select name="product_id" class="form-control" required>
                        <option value="">Select Item</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>DESCRIPTION <span class="sx-req">*</span></label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Description" required></textarea>
                </div>
                <div id="sx-ps-lines">
                    <div class="row sx-ps-line" style="margin-bottom:8px;">
                        <div class="col-xs-7">
                            <label>ITEM NAME</label>
                            <select name="items[0][product_id]" class="form-control" required>
                                <option value="">~~Select Item~~</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xs-3">
                            <label>ITEM QTY</label>
                            <input type="number" step="0.0001" min="0.0001" name="items[0][quantity]" class="form-control" value="1" required>
                        </div>
                        <div class="col-xs-2" style="padding-top:24px;">
                            <button type="button" class="btn btn-success btn-sm" id="sx-ps-add"><i class="fa fa-plus"></i></button>
                            <button type="button" class="btn btn-danger btn-sm sx-ps-remove"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="text-align:left;">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'ps-table', 'lengthId' => 'ps-table-length', 'searchId' => 'ps-table-search',
    'exportId' => 'ps-table-export-hidden', 'colvisId' => 'ps-table-colvis',
    'checkAll' => 'ps-check-all', 'rowCheck' => 'ps-row-check',
    'title' => 'Packaging Setup', 'filename' => 'packaging-setups', 'noSort' => [0, 5], 'order' => [[4, 'desc']],
])
@push('scripts')
<script>
(function ($) {
    var idx = 1;
    var opts = @json($products->map(fn($p) => ['id'=>$p->id,'name'=>$p->name])->values());
    function optHtml() {
        var h = '<option value="">~~Select Item~~</option>';
        opts.forEach(function (p) { h += '<option value="'+p.id+'">'+p.name+'</option>'; });
        return h;
    }
    $('#sx-ps-add').on('click', function () {
        var i = idx++;
        $('#sx-ps-lines').append(
            '<div class="row sx-ps-line" style="margin-bottom:8px;">'+
            '<div class="col-xs-7"><label>ITEM NAME</label><select name="items['+i+'][product_id]" class="form-control" required>'+optHtml()+'</select></div>'+
            '<div class="col-xs-3"><label>ITEM QTY</label><input type="number" step="0.0001" min="0.0001" name="items['+i+'][quantity]" class="form-control" value="1" required></div>'+
            '<div class="col-xs-2" style="padding-top:24px;"><button type="button" class="btn btn-danger btn-sm sx-ps-remove"><i class="fa fa-minus"></i></button></div></div>'
        );
    });
    $(document).on('click', '.sx-ps-remove', function () {
        if ($('.sx-ps-line').length <= 1) return;
        $(this).closest('.sx-ps-line').remove();
    });
    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({icon:'warning',title:'Delete setup',text:'This packaging setup will be removed.',showCancelButton:true,confirmButtonColor:'#dd4b39',confirmButtonText:'Delete'})
                .then(function (r) { if (r.isConfirmed) form.submit(); });
            return;
        }
        if (confirm('Delete this setup?')) form.submit();
    });
})(jQuery);
</script>
@endpush
