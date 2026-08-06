<div class="modal fade" id="modal-supplier" tabindex="-1" role="dialog" aria-labelledby="modal-supplier">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Select Supplier - Pending Consignment Payments</h4>
            </div>
            <div class="modal-body">
                <div id="supplier-loading" style="text-align: center; padding: 20px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i> Loading suppliers...
                </div>
                <table class="table table-striped table-bordered table-supplier table-hover" id="supplier-table">
                    <thead>
                        <th width="5%">#</th>
                        <th>Supplier Name</th>
                        <th>Telephone</th>
                        <th>Address</th>
                        <th>Pending Amount</th>
                        <th><i class="fa fa-cog"></i></th>
                    </thead>
                    <tbody id="supplier-tbody">
                        @foreach ($suppliers as $key => $item)
                            <tr>
                                <td width="5%">{{ $key+1 }}</td>
                                <td>{{ $item->nama }}</td>
                                <td>{{ $item->telepon }}</td>
                                <td>{{ $item->alamat }}</td>
                                <td>
                                    @if(isset($item->pending_amount) && $item->pending_amount > 0)
                                        <span class="label label-warning">Ksh {{ number_format($item->pending_amount, 2) }}</span>
                                    @else
                                        <span class="label label-success">Ksh 0.00</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('payment.create', $item->id_supplier) }}" class="btn btn-primary btn-xs btn-flat">
                                        <i class="fa fa-check-circle"></i>
                                        Select
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div id="supplier-empty" style="display: none; text-align: center; padding: 20px;">
                    <p>No suppliers found with pending payments.</p>
                </div>
            </div>
        </div>
    </div>
</div>
