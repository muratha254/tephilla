@extends('layouts.master')

@section('title')
    Purchase List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Purchase List</li>
@endsection
@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                 <button onclick="updatePeriode()" class="btn btn-primary btn-flat"><i class="fa fa-plus-circle"></i> Search By Date</button>
                  <button class="btn btn-warning btn-flat" id="printButton">Print purchases</button>
                <button onclick="addForm()" class="btn btn-success btn-flat"><i class="fa fa-plus-circle"></i> Add New Purchase</button>
                <a href="{{ route('pembelian.import') }}" class="btn btn-info btn-flat"><i class="fa fa-upload"></i> Import Purchases</a>

                @empty(! session('id_pembelian'))
                <a href="{{ route('pembelian_detail.index') }}" class="btn btn-info btn-xs btn-flat"><i class="fa fa-pencil"></i> Active Transaction</a>
                @endempty
            </div>
            <div class="box-body table-responsive">
                <table id="yourDataTable" border="3" class="table table-stiped table-bordered table-pembelian table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                        <th>Discount</th>
                        <th>Total Pay</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                     <tfoot>
                            <tr>
                                <th colspan="5">Total Amount</th>
                                <th id="totalBayar"></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- visit "codeastro" for more projects! -->
@includeIf('pembelian.supplier')
@includeIf('pembelian.detail')
@includeIf('pembelian.form')
@endsection

@push('scripts')
 <script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>

<script>
    let table, table1;
    let formattedTotalBayar;

    $(function () {
        table = $('.table-pembelian').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                    url: '{{ route('pembelian.data') }}',
                    data: function (d) {
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    },
                },

            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
               // {data: 'purchasedate2'},
               {data: 'purchasedate2',
               render: function (data, type, row) {
        // Format the date to display only the date portion
        if (type === 'display' || type === 'filter') {
            return moment(data).format('DD/MM/YYYY');
        }
        return data;
    }




           },
                {data: 'supplier'},
                {data: 'total_item'},
                 {data: 'bayar'},
                {data: 'diskon'},
                {data: 'total_harga'},
                {data: 'aksi', searchable: false, sortable: false},
            ],
            footerCallback: function (row, data, start, end, display) {
                    var api = this.api(), data;
                    // Convert the bayar column data to floats for summation
                    var intVal = function (i) {
                        return typeof i === 'string' ?
                            i.replace(/[^\d.-]/g, '') * 1 :
                            typeof i === 'number' ?
                            i : 0;
                    };
                    // Total over all pages
                    totalBayar = api
                        .column(6) // Index of the bayar column
                        .data()
                        .reduce(function (a, b) {
                            // Handle non-numeric values
                            var numA = intVal(a);
                            var numB = intVal(b);
                            if (!isNaN(numA) && !isNaN(numB)) {
                                return numA + numB;
                            } else if (!isNaN(numA)) {
                                return numA;
                            } else if (!isNaN(numB)) {
                                return numB;
                            } else {
                                return 0;
                            }
                        }, 0);

                    // Update the total bayar cell in the footer
                    // Assuming totalBayar is a number
                  formattedTotalBayar = totalBayar.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            $('#totalBayar').html('Ksh ' + formattedTotalBayar);
                }
        });

        $('.table-supplier').DataTable();
        table1 = $('.table-detail').DataTable({
            processing: true,
            bSort: false,
            dom: 'Brt',
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'kode_produk'},
                {data: 'nama_produk'},
                {data: 'harga_beli'},
                {data: 'jumlah'},
                {data: 'subtotal'},
            ]
        });
        $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true
            });
    });

    function addForm() {
        $('#modal-supplier').modal('show');
    }

    function showDetail(url) {
        $('#modal-detail').modal('show');

        table1.ajax.url(url);
        table1.ajax.reload();
    }

    function deleteData(url) {
        if (confirm('Are you sure you want to delete selected data?')) {
            $.post(url, {
                    '_token': $('[name=csrf-token]').attr('content'),
                    '_method': 'delete'
                })
                .done((response) => {
                    table.ajax.reload();
                })
                .fail((errors) => {
                    alert('Unable to delete data');
                    return;
                });
        }
    }
    
    function updatePeriode() {
            $('#modal-form').modal('show');
        }

        $('#printButton').on('click', function () {
        var dataTable = $('#yourDataTable').DataTable();
        if (dataTable.data().any()) {
            var data = dataTable.rows().data().toArray();
            var filteredData = data.map(function (row) {
                return [
                    row.DT_RowIndex,
                    row.supplier,
                    row.total_item,
                    row.total_harga,
                    row.diskon,
                    
                    row.bayar,
                ];
            });

            var tableHtml = '<table border="1" style="border-collapse: collapse;">';
            tableHtml += '<thead><tr><th>#</th><th>Supplier</th><th>Quantity</th><th>Total Price</th><th>Discount</th><th>Total Pay</th></tr></thead>';
            tableHtml += '<tbody>';

            filteredData.forEach(function (row) {
                tableHtml += '<tr>';
                row.forEach(function (cell) {
                    tableHtml += '<td>' + cell + '</td>';
                });
                tableHtml += '</tr>';
            });

            tableHtml += '</tbody>';
            // Append the footer section
            tableHtml += '<tfoot><tr><th colspan="5">Total Purchases</th><th>Ksh ' + formattedTotalBayar + '</th></tr></tfoot>';
            tableHtml += '</table>';
            
          


            var printWindow = window.open('', '_target');
            printWindow.document.write('<html><head><title>PURCHASE LIST</title></head><body>');
            var imagePath = '{{ asset('images/utamaduni.png') }}';
           var startDate = $('#start_date').val();
            var endDate = $('#end_date').val();
            
            printWindow.document.write('<div style="display: inline-block;margin: 20px;">');
            printWindow.document.write('<img src="' + imagePath + '" style="width: 60px; height: 60px;" alt="Example Image">');
            printWindow.document.write('<h2 style="display: inline-block; margin-left: 10px;">UTAMADUNI CRAFT CENTRE</h2>');
            printWindow.document.write('</div>');
             printWindow.document.write('</br>');
            printWindow.document.write('<div style="display: inline-block;">');
            printWindow.document.write('<p>Start Date: ' + startDate + '</p> <p>End Date: ' + endDate + '</p>');
           // printWindow.document.write('<p>End Date: ' + endDate + '</p>');
            printWindow.document.write('</div>');
            //printWindow.document.write('');
            printWindow.document.write(tableHtml);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        } else {
            alert('No data available to print.');
        }
    });
</script>
@endpush