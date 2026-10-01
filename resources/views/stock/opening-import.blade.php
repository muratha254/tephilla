@extends('layouts.fleet')

@section('title', 'Opening stock import')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Opening stock import',
    'subtitle' => 'CSV, XLS, or XLSX. Columns: product, colour, quantity, unit, branch. If any row is invalid, nothing is imported.',
    'backUrl' => route('stock.manager'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Opening stock'],
    ],
])

<div class="sx-box">
    <div class="sx-box-body">
        <form action="{{ route('stock.opening.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>Spreadsheet</label>
                <input type="file" name="file" class="form-control" accept=".csv,text/csv,.xlsx,.xls,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </div>
            <button type="submit" class="btn btn-success">Import</button>
        </form>
        <p class="help-block" style="margin-top:12px;">
            Example: <code>product,colour,quantity,unit,branch</code><br>
            <code>Ridge,Black,20,PCS,MAIN</code><br>
            <code>Nails,,318.5,KG,MAIN</code>
        </p>
    </div>
</div>

<div class="sx-box">
    <h3 class="sx-box-title">Old colour register</h3>
    <div class="sx-box-body">
        <form action="{{ route('stock.register.preview') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>Workbook</label>
                <input type="file" name="register" class="form-control" accept=".csv,.xlsx,.xls,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </div>
            <button type="submit" class="btn btn-default">Preview register</button>
        </form>
        @if(!empty($preview['total']))
            <h4>Import preview</h4>
            <ul>
                @foreach($preview['colours'] as $colour => $count)
                    <li>{{ $colour }}: {{ $count }} records</li>
                @endforeach
            </ul>
            <p>Total records: {{ $preview['total'] }}</p>
            @if(!empty($preview['warnings']))
                <p><strong>Review these lines. Nothing was changed.</strong></p>
                <ul>
                    @foreach($preview['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            @endif
            <form action="{{ route('stock.register.confirm') }}" method="post">
                @csrf
                <button type="submit" class="btn btn-success">Confirm import</button>
            </form>
        @endif
        <p class="help-block" style="margin-top:12px;">
            Use the old flatsheet workbook. Each colour keeps its own Ridges, Bardge Box, Valleys, Side Flash, and Flatsheet balance.
            A folded flatsheet reduces that colour. A folded finished item increases that colour. The narration is kept as written.
        </p>
    </div>
</div>

@if(!empty($result['errors']))
<div class="sx-box">
    <h3 class="sx-box-title">Rejected rows</h3>
    <div class="sx-box-body">
        <ul>
            @foreach($result['errors'] as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif
@endsection
