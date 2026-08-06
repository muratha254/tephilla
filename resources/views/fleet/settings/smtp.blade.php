@extends('layouts.fleet')

@section('title', 'SMTP Configuration')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-settings.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">SMTP Configuration</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Home</a></li>
        <li>SMTP Configuration</li>
    </ul>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="fleet-form-errors">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="fleet-panel fleet-settings-smtp-card">
    <form action="{{ route('settings.smtp.update') }}" method="POST" class="fleet-settings-smtp-form">
        @csrf
        @method('PUT')

        <div class="fleet-settings-smtp-grid">
            <div class="fleet-settings-field">
                <label for="smtp_host">Host</label>
                <input type="text" id="smtp_host" name="host" class="fleet-settings-input" value="{{ old('host', $smtp['host']) }}" required>
            </div>

            <div class="fleet-settings-field">
                <label for="smtp_auth">SMTPAuth</label>
                <select id="smtp_auth" name="smtp_auth" class="fleet-settings-input" required>
                    <option value="True" {{ old('smtp_auth', $smtp['smtp_auth']) === 'True' ? 'selected' : '' }}>True</option>
                    <option value="False" {{ old('smtp_auth', $smtp['smtp_auth']) === 'False' ? 'selected' : '' }}>False</option>
                </select>
            </div>

            <div class="fleet-settings-field">
                <label for="smtp_username">Username</label>
                <input type="text" id="smtp_username" name="username" class="fleet-settings-input" value="{{ old('username', $smtp['username']) }}">
            </div>

            <div class="fleet-settings-field">
                <label for="smtp_password">Password</label>
                <input
                    type="password"
                    id="smtp_password"
                    name="password"
                    class="fleet-settings-input"
                    placeholder="{{ $hasPassword ? 'Leave blank to keep current password' : 'Enter password' }}"
                    autocomplete="new-password"
                >
            </div>

            <div class="fleet-settings-field">
                <label for="smtp_secure">SMTPSecure</label>
                <select id="smtp_secure" name="smtp_secure" class="fleet-settings-input" required>
                    <option value="SSL" {{ old('smtp_secure', $smtp['smtp_secure']) === 'SSL' ? 'selected' : '' }}>SSL</option>
                    <option value="TLS" {{ old('smtp_secure', $smtp['smtp_secure']) === 'TLS' ? 'selected' : '' }}>TLS</option>
                    <option value="None" {{ old('smtp_secure', $smtp['smtp_secure']) === 'None' ? 'selected' : '' }}>None</option>
                </select>
            </div>

            <div class="fleet-settings-field">
                <label for="smtp_port">Port</label>
                <input type="number" id="smtp_port" name="port" class="fleet-settings-input" value="{{ old('port', $smtp['port']) }}" min="1" max="65535" required>
            </div>
        </div>

        <div class="fleet-settings-footer">
            <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-save"></i> Save Configuration</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var secure = document.getElementById('smtp_secure');
    var port = document.getElementById('smtp_port');
    if (!secure || !port) return;

    secure.addEventListener('change', function () {
        if (this.value === 'SSL' && !port.value) {
            port.value = '465';
        } else if (this.value === 'TLS' && (port.value === '465' || !port.value)) {
            port.value = '587';
        }
    });
})();
</script>
@endpush
