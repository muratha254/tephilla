@extends('layouts.fleet')

@php $isEdit = $item->exists; @endphp

@section('title', $isEdit ? 'Edit ' . $config['title'] : 'Add ' . $config['title'])

@section('content')
@include('layouts.partials.page-header', [
    'title' => $config['title'],
    'subtitle' => 'Add/Update ' . $config['title'],
    'backUrl' => route('settings.lookups', $kind),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => $config['list_title'], 'url' => route('settings.lookups', $kind)],
        ['label' => $config['title']],
    ],
])

<form method="post" action="{{ $isEdit ? route('settings.lookups.update', [$kind, $item]) : route('settings.lookups.store', $kind) }}" class="sx-item-form" style="max-width:640px;">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-box">
        <div class="sx-box-body">
            <div class="form-group">
                <label>{{ $config['name_label'] }} <span class="sx-req">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $item->name) }}" required>
            </div>

            @if(!empty($config['has_code']))
                <div class="form-group">
                    <label>{{ $config['code_label'] ?? 'Code' }} <span class="sx-req">*</span></label>
                    <input type="text" name="code" class="form-control" value="{{ old('code', $item->code) }}" required>
                </div>
            @endif

            @if(!empty($config['has_symbol']))
                <div class="form-group">
                    <label>{{ $config['symbol_label'] ?? 'Symbol' }} <span class="sx-req">*</span></label>
                    <input type="text" name="symbol" class="form-control" value="{{ old('symbol', $item->symbol) }}" required>
                </div>
            @endif

            @if(!empty($config['has_rate']))
                <div class="form-group">
                    <label>{{ $config['rate_label'] ?? 'Rate' }} <span class="sx-req">*</span></label>
                    <input type="number" step="0.000001" min="0" name="rate" class="form-control" value="{{ old('rate', $item->rate ?? 0) }}" required>
                </div>
            @endif

            @if(!empty($config['has_parent']))
                <div class="form-group">
                    <label>{{ $config['parent_label'] }}</label>
                    <select name="parent_id" class="form-control">
                        <option value="">-Select-</option>
                        @foreach($parents as $parent)
                            <option value="{{ $parent->id }}" @if((string) old('parent_id', $item->parent_id) === (string) $parent->id) selected @endif>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($isEdit)
                <div class="form-group">
                    <label>Status</label>
                    <select name="is_active" class="form-control">
                        <option value="Yes" @if(old('is_active', $item->is_active ? 'Yes' : 'No') === 'Yes') selected @endif>Active</option>
                        <option value="No" @if(old('is_active', $item->is_active ? 'Yes' : 'No') === 'No') selected @endif>Inactive</option>
                    </select>
                </div>
            @endif

            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('settings.lookups', $kind) }}" class="btn btn-warning">Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
