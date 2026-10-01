@extends('layouts.fleet')

@section('title', 'Colours')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Colours',
    'subtitle' => 'Reusable colours for product variants',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Colours'],
    ],
])

<div class="row">
    <div class="col-md-7">
        <div class="sx-box">
            <div class="sx-box-body">
                <table class="table table-bordered sx-gold-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            @if($canManage)<th>Action</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($colours as $colour)
                            <tr>
                                <td>{{ $colour->name }}</td>
                                <td>{{ $colour->description ?: '—' }}</td>
                                <td>
                                    @if($colour->is_active)
                                        <span class="sx-status-active"><i class="fa fa-check-circle"></i> Active</span>
                                    @else
                                        <span class="sx-status-inactive"><i class="fa fa-times-circle"></i> Inactive</span>
                                    @endif
                                </td>
                                @if($canManage)
                                    <td>
                                        <form action="{{ route('colours.update', $colour) }}" method="post" class="form-inline">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="name" value="{{ $colour->name }}">
                                            <input type="hidden" name="description" value="{{ $colour->description }}">
                                            <input type="hidden" name="is_active" value="{{ $colour->is_active ? 0 : 1 }}">
                                            <button type="submit" class="btn btn-xs {{ $colour->is_active ? 'btn-warning' : 'btn-success' }}">
                                                {{ $colour->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 4 : 3 }}">No colours yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if($canManage)
    <div class="col-md-5">
        <div class="sx-box">
            <h3 class="sx-box-title">Add colour</h3>
            <div class="sx-box-body">
                <form action="{{ route('colours.store') }}" method="post">
                    @csrf
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" value="{{ old('description') }}">
                    </div>
                    <input type="hidden" name="is_active" value="1">
                    <button type="submit" class="btn btn-success">Save colour</button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
