@extends('layouts.master')

@section('title')
    Switch User
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Switch User</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <ul style="margin-bottom: 0;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Switch to another user</h3>
            </div>
            <div class="box-body">
                <p class="text-muted">Select a user below to switch into their account. You will stay logged in as that user until you log out or switch again.</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $index => $u)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $u->name }}</td>
                                <td>{{ $u->email }}</td>
                                <td>{{ ucfirst($u->role ?? 'cashier') }}</td>
                                <td>
                                    <form action="{{ route('switch-user.switch', $u) }}" method="POST" class="form-inline" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm btn-flat">
                                            <i class="fa fa-sign-in"></i> Switch
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No other users available to switch to.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
