@extends('layouts.fleet')

@php $isEdit = $user->exists; @endphp

@section('title', $isEdit ? 'Edit User' : 'Create User')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Edit User' : 'Create User',
    'subtitle' => 'Enter User Information',
    'backUrl' => route('users.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'View Users', 'url' => route('users.index')],
        ['label' => $isEdit ? 'Edit User' : 'Create User'],
    ],
])

<form class="sx-item-form" method="post" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}" enctype="multipart/form-data">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-box">
        <div class="sx-box-body">
            <div class="row">
                <div class="col-md-7">
                    <div class="form-group">
                        <label>Username <span class="sx-req">*</span></label>
                        <input type="text" name="username" class="form-control" placeholder="Username" value="{{ old('username', $user->username ?: $user->name) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="sx-req">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="Email Address" value="{{ old('email', $user->email) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Branch <span class="sx-req">*</span></label>
                        <select name="branch_id" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @if((string) old('branch_id', $user->branch_id) === (string) $branch->id) selected @endif>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Role <span class="sx-req">*</span></label>
                        <select name="role_id" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @if((string) old('role_id', $user->role_id) === (string) $role->id) selected @endif>{{ $role->display_name ?: $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>mobile</label>
                        <input type="text" name="phone" class="form-control" placeholder="Mobile" value="{{ old('phone', $user->phone) }}">
                    </div>
                    <div class="form-group">
                        <label>Password @if(! $isEdit)<span class="sx-req">*</span>@endif</label>
                        <input type="password" name="password" class="form-control" placeholder="{{ $isEdit ? 'Leave blank to keep current' : 'New Password' }}" @if(! $isEdit) required @endif>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password @if(! $isEdit)<span class="sx-req">*</span>@endif</label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="Confirm Password" @if(! $isEdit) required @endif>
                    </div>
                    @if($isEdit)
                        <div class="form-group">
                            <label>
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @if(old('is_active', $user->is_active)) checked @endif>
                                Active
                            </label>
                        </div>
                    @endif
                </div>
                <div class="col-md-5 text-center">
                    <div class="form-group">
                        <img id="sx-user-photo-preview" src="{{ $user->profile_photo_url ?? asset('AdminLTE-2/dist/img/user2-160x160.jpg') }}" alt="Photo" class="img-circle" style="width:140px;height:140px;object-fit:cover;border:3px solid #ddd;">
                    </div>
                    <div class="form-group text-left">
                        <label>Passport Photo</label>
                        <input type="file" name="photo" id="sx-user-photo" class="form-control" accept="image/*">
                    </div>
                    <div class="form-group text-left">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="5" placeholder="Any other information">{{ old('description', $user->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="sx-form-actions text-center">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('users.index') }}" class="btn btn-warning">Close</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function ($) {
    $('#sx-user-photo').on('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) { $('#sx-user-photo-preview').attr('src', e.target.result); };
        reader.readAsDataURL(file);
    });
})(jQuery);
</script>
@endpush
