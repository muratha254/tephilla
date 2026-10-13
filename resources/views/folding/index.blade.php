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
                    <th class="text-right">Remaining</th>
                    <th>Accessories produced</th>
                    <th>Qty</th>
                    <th>Narration</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($foldings as $folding)
                    @php
                        $outputs = $folding->outputs;
                        $span = max($outputs->count(), 1);
                        $fmt = function ($n) {
                            $text = rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
                            return $text === '' ? '0' : $text;
                        };
                    @endphp
                    <tr>
                        <td rowspan="{{ $span }}">{{ $folding->number() }}</td>
                        <td rowspan="{{ $span }}">{{ optional($folding->folded_on)->format('d/m/Y') }}</td>
                        <td rowspan="{{ $span }}">{{ optional($folding->branch)->name }}</td>
                        <td rowspan="{{ $span }}">{{ optional($folding->product)->name }}</td>
                        <td rowspan="{{ $span }}">{{ optional($folding->colour)->name ?: '—' }}</td>
                        <td rowspan="{{ $span }}" class="text-right">{{ $fmt($folding->quantity) }}</td>
                        <td rowspan="{{ $span }}" class="text-right">
                            @if(array_key_exists($folding->id, $remaining))
                                {{ $fmt($remaining[$folding->id]) }}
                            @else
                                —
                            @endif
                        </td>
                        @if($outputs->isEmpty())
                            <td>—</td>
                            <td>—</td>
                        @else
                            <td>{{ trim(optional($outputs->first()->product)->name . ' ' . optional($outputs->first()->colour)->name) }}</td>
                            <td>{{ $fmt($outputs->first()->quantity) }}</td>
                        @endif
                        <td rowspan="{{ $span }}">{{ $folding->notes }}</td>
                        <td rowspan="{{ $span }}">{{ ucfirst($folding->status ?: 'confirmed') }}</td>
                        <td rowspan="{{ $span }}">
                            @if($canRecord && ($folding->status ?: 'confirmed') !== 'voided')
                                <form method="post" action="{{ route('folding.void', $folding) }}">
                                    @csrf
                                    <input type="text" name="void_reason" class="form-control input-sm" placeholder="Reason" required>
                                    <button type="submit" class="btn btn-warning btn-xs">Reverse</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @foreach($outputs->slice(1) as $output)
                        <tr>
                            <td>{{ trim(optional($output->product)->name . ' ' . optional($output->colour)->name) }}</td>
                            <td>{{ $fmt($output->quantity) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="12">No folding records for this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $foldings->links() }}
    </div>
</div>
@endsection
