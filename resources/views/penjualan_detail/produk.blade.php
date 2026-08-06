<div class="modal fade" id="modal-produk" tabindex="-1" role="dialog" aria-labelledby="modal-produk">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Select Product</h4>
            </div>
            <div class="modal-body">
                <table class="table table-striped table-bordered table-produk table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Stock</th>
                        <th>Selling Price</th>
                        <th><i class="fa fa-cog"></i></th>
                    </thead>
                    <tbody>
                        @foreach ($produk as $key => $item)
                            <tr>
                                <td width="5%">{{ $key+1 }}</td>
                                <td><span class="label label-success">{{ $item->kode_produk }}</span></td>
                                <td>{{ $item->nama_produk }}</td>
                                <td>
                                    @if($item->stok > 0)
                                        <span class="label label-info">{{ $item->stok }} available</span>
                                    @else
                                        <span class="label label-danger">Out of Stock</span>
                                    @endif
                                </td>
                                <td>Ksh {{ number_format($item->harga_jual, 2) }}</td>
                                <td>
                                    @if($item->stok > 0)
                                        <a href="#" class="btn btn-primary btn-xs btn-flat"
                                            onclick="pilihProduk('{{ $item->id_produk }}', '{{ $item->kode_produk }}')">
                                            <i class="fa fa-check-circle"></i>
                                            Select
                                        </a>
                                    @else
                                        <button class="btn btn-default btn-xs btn-flat" disabled>
                                            <i class="fa fa-ban"></i>
                                            Out of Stock
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>