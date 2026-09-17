@extends('layouts.fleet')

@section('title', 'Customers Category')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customers Category',
    'subtitle' => '',
    'backUrl' => route('customers.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Customers Category'],
    ],
])

<div class="sx-split">
    <div class="sx-panel sx-split-form">
        <div class="sx-panel-head"><i class="fa fa-edit"></i> Add/Edit</div>
        <div class="sx-panel-body">
            <form class="sx-item-form" method="post" action="{{ $edit ? route('customers.categories.update', $edit) : route('customers.categories.store') }}">
                @csrf
                @if($edit)
                    @method('PUT')
                @endif
                <div class="form-group">
                    <label>Category Name: <span class="sx-req">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Category Name" value="{{ old('name', optional($edit)->name) }}" required>
                </div>
                <div class="form-group">
                    <label>Charge Rate: <span class="sx-req">*</span></label>
                    <input type="number" step="0.01" min="0" name="discount_percent" class="form-control" placeholder="Category Rate" value="{{ old('discount_percent', optional($edit)->discount_percent ?? 0) }}" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Description">{{ old('description', optional($edit)->description) }}</textarea>
                </div>
                @if($canManage)
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> {{ $edit ? 'Update' : 'Submit' }}</button>
                    <a href="{{ route('customers.categories') }}" class="btn btn-danger"><i class="fa fa-times"></i> Cancel</a>
                @endif
            </form>
        </div>
    </div>

    <div class="sx-panel sx-split-list">
        <div class="sx-panel-head"><i class="fa fa-list"></i> Category List</div>
        <div class="sx-panel-body" style="padding:0;">
            <table class="table table-bordered sx-gold-table" style="margin:0;">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Category Name</th>
                        <th>Charge Rate</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ number_format((float) $category->discount_percent, 2) }}</td>
                            <td>{{ $category->description }}</td>
                            <td>
                                @if($canManage)
                                    <a href="{{ route('customers.categories', ['edit' => $category->id]) }}" class="btn btn-success btn-xs"><i class="fa fa-edit"></i> Update</a>
                                    <form action="{{ route('customers.categories.destroy', $category) }}" method="post" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Delete this category?')"><i class="fa fa-trash"></i> Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
