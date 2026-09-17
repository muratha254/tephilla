@extends('layouts.fleet')

@php
    $isEdit = $file->exists;
@endphp

@section('title', $isEdit ? 'Update File' : 'New File')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Files',
    'subtitle' => 'Add/Update File',
    'backUrl' => route('documents.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Files List', 'url' => route('documents.index')],
        ['label' => 'Files'],
    ],
])

<form class="sx-item-form" method="post" action="{{ $isEdit ? route('documents.update', $file) : route('documents.store') }}" enctype="multipart/form-data">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>File Category <span class="sx-req">*</span></label>
                        <select name="file_category_id" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @if((string) old('file_category_id', $file->file_category_id) === (string) $category->id) selected @endif>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>File Title <span class="sx-req">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="File Title" value="{{ old('title', $file->title) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Upload File @if(!$isEdit)<span class="sx-req">*</span>@endif</label>
                        <input type="file" name="upload" class="form-control" @if(!$isEdit) required @endif>
                        @if($isEdit && $file->original_name)
                            <small class="text-muted">Current: {{ $file->original_name }}</small>
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="expires_at" class="form-control" value="{{ old('expires_at', optional($file->expires_at)->format('Y-m-d')) }}">
                    </div>
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="8" placeholder="Type here...">{{ old('description', $file->description) }}</textarea>
                    </div>
                    <div class="sx-form-actions">
                        <a href="{{ route('documents.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
