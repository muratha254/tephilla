@extends('layouts.fleet')

@section('title', 'Income/Expense Categories')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-account-categories.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-vendor-head">
        <h1 class="fleet-page-title">Income/Expense Categories</h1>
        <button type="button" class="fleet-vendor-add-icon" id="account-category-add-btn" title="Add Category"><i class="fa fa-plus"></i></button>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Income/Expense Categories</li>
    </ul>
</div>

<div class="fleet-panel fleet-vendor-card fleet-account-category-card">
    <div class="fleet-table-wrap">
        <table class="fleet-vendor-table fleet-account-category-table" id="account-category-table">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $index => $category)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $category->name }}</td>
                    <td>
                        <form action="{{ route('account-categories.destroy', $category) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this category?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="fleet-vendor-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="fleet-empty-row">No categories found. Click + to add one.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="fleet-modal" id="account-category-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-account-category-modal></div>
    <div class="fleet-modal-dialog fleet-account-category-modal">
        <div class="fleet-modal-header">
            <h3>Add Category</h3>
            <button type="button" class="fleet-modal-close" data-close-account-category-modal aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="{{ route('account-categories.store') }}">
            @csrf
            <div class="fleet-modal-body">
                @if ($errors->any())
                <div class="fleet-alert fleet-alert-error">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                <div class="fleet-account-category-field">
                    <label for="account-category-name">Name</label>
                    <input type="text" id="account-category-name" name="name" class="fleet-maint-input" value="{{ old('name') }}" placeholder="Enter category name" required>
                </div>
            </div>
            <div class="fleet-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-default" data-close-account-category-modal>Close</button>
                <button type="submit" class="fleet-btn fleet-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('account-category-modal');
    var addBtn = document.getElementById('account-category-add-btn');

    if (addBtn && modal) {
        addBtn.addEventListener('click', function () {
            modal.hidden = false;
            document.body.classList.add('fleet-modal-open');
        });

        modal.querySelectorAll('[data-close-account-category-modal]').forEach(function (el) {
            el.addEventListener('click', function () {
                modal.hidden = true;
                document.body.classList.remove('fleet-modal-open');
            });
        });
    }

    @if ($errors->any())
    if (modal) {
        modal.hidden = false;
        document.body.classList.add('fleet-modal-open');
    }
    @endif
})();
</script>
@endpush
