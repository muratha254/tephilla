@extends('layouts.fleet')

@section('title', 'Product types')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Product types',
    'subtitle' => 'Delete a type that is no longer used',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Items/Products', 'url' => route('products.index')],
        ['label' => 'Product types'],
    ],
])

<div class="sx-box">
    <div class="sx-box-body">
        <table class="table table-bordered sx-gold-table">
            <thead>
                <tr>
                    <th>Product type</th>
                    <th class="text-right">Products</th>
                    @if($canDelete)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($types as $type)
                    <tr>
                        <td>{{ $type->name }}</td>
                        <td class="text-right">{{ $type->products_count }}</td>
                        @if($canDelete)
                            <td>
                                <form method="post" action="{{ route('products.types.destroy', $type) }}" onsubmit="return confirm('Delete {{ $type->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs" @if($type->products_count > 0 || $type->children_count > 0) disabled title="Move its products first" @endif>
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canDelete ? 3 : 2 }}">No product types yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
