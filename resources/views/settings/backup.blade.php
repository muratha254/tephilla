@extends('layouts.fleet')

@section('title', 'Database Backups')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Database Backups',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Database Backups'],
    ],
])

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar" style="background:#d9edf7;border-bottom:1px solid #bce8f1;padding:10px 14px;">
        <h3 class="sx-box-title" style="flex:none;margin:0;font-size:16px;">DB Backups</h3>
        <form method="post" action="{{ route('settings.backup.run') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> New Backup</button>
        </form>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>##</th>
                        <th>Backup</th>
                        <th>Time Stamp</th>
                        <th>Created By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($backups as $i => $backup)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <a href="{{ route('settings.backup.download', $backup['filename']) }}">
                                    {{ $backup['filename'] }}
                                </a>
                            </td>
                            <td>{{ $backup['datetime']->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $backup['created_by'] }}</td>
                            <td>
                                <form method="post" action="{{ route('settings.backup.destroy', $backup['filename']) }}" style="display:inline;" onsubmit="return confirm('Delete this backup?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No backups found. Click <strong>New Backup</strong> to create one.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
