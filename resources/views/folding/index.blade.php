@extends('layouts.fleet')

@section('title', 'Folding')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Folding',
    'subtitle' => 'Finished goods produced by colour, employee, and date',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Folding'],
    ],
])

<div class="sx-box">
    <div class="sx-box-body">
        <form method="get" action="{{ route('folding.index') }}" class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label>From</label>
                    <input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>To</label>
                    <input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Product</label>
                    <select name="product_id" class="form-control">
                        <option value="">All</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @if((string) ($filters['product_id'] ?? '') === (string) $product->id) selected @endif>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Colour</label>
                    <select name="colour_id" class="form-control">
                        <option value="">All</option>
                        @foreach($colours as $colour)
                            <option value="{{ $colour->id }}" @if((string) ($filters['colour_id'] ?? '') === (string) $colour->id) selected @endif>{{ $colour->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Employee</label>
                    <input type="text" name="employee" class="form-control" value="{{ $filters['employee'] ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-success">Filter</button>
                        @if($canRecord)
                            <a href="{{ route('folding.create') }}" class="btn btn-primary">Record</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="sx-box">
    <div class="sx-box-body">
        <table class="table table-bordered sx-gold-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Date</th>
                    <th>Branch</th>
                    <th>Raw material</th>
                    <th>Colour</th>
                    <th class="text-right">Used</th>
                    <th>Produced</th>
                    <th>Narration</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($foldings as $folding)
                    <tr>
                        <td>{{ $folding->number() }}</td>
                        <td>{{ optional($folding->folded_on)->format('d/m/Y') }}</td>
                        <td>{{ optional($folding->branch)->name }}</td>
                        <td>{{ optional($folding->product)->name }}</td>
                        <td>{{ optional($folding->colour)->name ?: '—' }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $folding->quantity, 4, '.', ''), '0'), '.') }}</td>
                        <td>
                            @forelse($folding->outputs as $output)
                                {{ optional($output->product)->name }} {{ optional($output->colour)->name }} {{ rtrim(rtrim(number_format((float) $output->quantity, 4, '.', ''), '0'), '.') }}<br>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>{{ $folding->notes }}</td>
                        <td>{{ ucfirst($folding->status ?: 'confirmed') }}</td>
                        <td>
                            @if($canRecord && ($folding->status ?: 'confirmed') !== 'voided')
                                <form method="post" action="{{ route('folding.void', $folding) }}">
                                    @csrf
                                    <input type="text" name="void_reason" class="form-control input-sm" placeholder="Reason" required>
                                    <button type="submit" class="btn btn-warning btn-xs">Reverse</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10">No folding records for this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $foldings->links() }}
    </div>
</div>
@endsection
