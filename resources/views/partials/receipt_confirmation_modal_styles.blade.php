@push('css')
    <link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/select2/dist/css/select2.min.css') }}">
    <style>
        #modal-items-table {
            table-layout: auto;
            width: 100%;
        }
        #modal-items-table .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
            max-width: 100%;
        }
        #modal-items-table .btn-group .btn {
            flex: 0 0 auto;
            white-space: nowrap;
            font-size: 11px;
            padding: 2px 6px;
        }
        #modal-items-table .btn-group .btn i {
            margin-right: 2px;
        }
        #modal-items-table td {
            vertical-align: middle;
            padding: 8px 4px;
        }
        #modal-items-table th {
            vertical-align: middle;
            padding: 8px 4px;
        }
        #modal-items-table .form-control {
            font-size: 12px;
            padding: 4px 6px;
        }
        #modal-receipt-confirmation .modal-body {
            overflow-x: auto;
            max-height: 70vh;
            overflow-y: auto;
        }
        #modal-items-table {
            min-width: 1200px;
        }
        #modal-receipt-confirmation .item-product-code.has-error {
            border-color: #a94442;
            box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
        }
        #modal-items-table .item-name-display {
            display: inline-block;
            max-width: 100%;
            white-space: normal;
            text-align: left;
            font-size: 12px;
            line-height: 1.35;
            padding: 6px 8px;
        }
        #modal-items-table .item-name-display .item-product-name {
            background: transparent;
            border: none;
            box-shadow: none;
            color: #fff;
            font-weight: 600;
            min-width: 160px;
            padding: 0 4px;
        }
        #modal-items-table .item-code-display {
            font-weight: 600;
        }
        #modal-receipt-confirmation .select2-container {
            font-size: 12px;
            min-width: 220px;
        }
        #modal-receipt-confirmation .select2-container--default .select2-results > .select2-results__options {
            max-height: 280px;
        }
        #modal-receipt-confirmation .select2-dropdown {
            z-index: 10050;
        }
        .rc-prod-opt {
            padding: 4px 2px;
            line-height: 1.35;
            text-align: left;
        }
        .rc-prod-opt-line1 {
            font-size: 12px;
        }
        .rc-prod-opt-line2 {
            font-size: 11px;
            margin-top: 2px;
        }
    </style>
@endpush
