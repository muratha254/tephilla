@extends('layouts.master')

@section('title')
    Backup
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Backup</li>
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

        <!-- Manual Backup Section -->
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Manual Backup</h3>
            </div>
            <div class="box-body">
                <form action="{{ route('backup.run') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label for="backup_path">Save Backup To (Optional)</label>
                        <input type="text" 
                               class="form-control" 
                               id="backup_path" 
                               name="backup_path" 
                               placeholder="Leave empty to download automatically. Example: C:\Backups\ or /home/user/backups/"
                               value="{{ old('backup_path') }}">
                        <small class="help-block">
                            Enter a directory path to save the backup file. If left empty, the backup will be downloaded automatically.
                            <br>Windows example: <code>C:\Backups\</code> or <code>D:\DatabaseBackups\</code>
                            <br>Linux example: <code>/home/user/backups/</code> or <code>/var/backups/</code>
                        </small>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-cloud-download"></i> Backup Now
                    </button>
                </form>
            </div>
        </div>

        <!-- Auto Backup Settings Section -->
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Automatic Backup Settings</h3>
            </div>
            <div class="box-body">
                <form action="{{ route('backup.settings') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label>
                            <input type="checkbox" 
                                   name="auto_backup_enabled" 
                                   value="1" 
                                   {{ $settings->auto_backup_enabled ? 'checked' : '' }}>
                            Enable Automatic Backups
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="schedule_frequency">Backup Frequency</label>
                        @php
                            $freqsWithScheduleTime = ['hourly', 'everyThreeHours', 'everyXHours', 'daily', 'weekly'];
                            $showScheduleTime = in_array($settings->schedule_frequency ?? '', $freqsWithScheduleTime, true);
                        @endphp
                        <select class="form-control" id="schedule_frequency" name="schedule_frequency" required>
                            <option value="every5Minutes" {{ $settings->schedule_frequency == 'every5Minutes' ? 'selected' : '' }}>Every 5 Minutes</option>
                            <option value="every10Minutes" {{ $settings->schedule_frequency == 'every10Minutes' ? 'selected' : '' }}>Every 10 Minutes</option>
                            <option value="every20Minutes" {{ $settings->schedule_frequency == 'every20Minutes' ? 'selected' : '' }}>Every 20 Minutes</option>
                            <option value="every30Minutes" {{ $settings->schedule_frequency == 'every30Minutes' ? 'selected' : '' }}>Every 30 Minutes</option>
                            <option value="hourly" {{ $settings->schedule_frequency == 'hourly' ? 'selected' : '' }}>Every Hour</option>
                            <option value="everyThreeHours" {{ $settings->schedule_frequency == 'everyThreeHours' ? 'selected' : '' }}>Every 3 Hours</option>
                            <option value="everyXHours" {{ $settings->schedule_frequency == 'everyXHours' ? 'selected' : '' }}>Every X Hours</option>
                            <option value="daily" {{ $settings->schedule_frequency == 'daily' ? 'selected' : '' }}>Daily</option>
                            <option value="weekly" {{ $settings->schedule_frequency == 'weekly' ? 'selected' : '' }}>Weekly</option>
                        </select>
                    </div>

                    <div class="form-group" id="schedule_time_group" style="display: {{ $showScheduleTime ? 'block' : 'none' }};">
                        <label for="schedule_time">Schedule Time (HH:MM)</label>
                        <input type="time" 
                               class="form-control" 
                               id="schedule_time" 
                               name="schedule_time" 
                               value="{{ $settings->schedule_time ?? '02:00' }}">
                        <small class="help-block">
                            For hourly/every-3-hours: this sets the minute.
                            For daily/weekly: this sets the time of day.
                        </small>
                    </div>

                    <div class="form-group" id="schedule_day_group" style="display: {{ $settings->schedule_frequency == 'weekly' ? 'block' : 'none' }};">
                        <label for="schedule_day">Schedule Day</label>
                        <select class="form-control" id="schedule_day" name="schedule_day">
                            <option value="">Select Day</option>
                            <option value="monday" {{ $settings->schedule_day == 'monday' ? 'selected' : '' }}>Monday</option>
                            <option value="tuesday" {{ $settings->schedule_day == 'tuesday' ? 'selected' : '' }}>Tuesday</option>
                            <option value="wednesday" {{ $settings->schedule_day == 'wednesday' ? 'selected' : '' }}>Wednesday</option>
                            <option value="thursday" {{ $settings->schedule_day == 'thursday' ? 'selected' : '' }}>Thursday</option>
                            <option value="friday" {{ $settings->schedule_day == 'friday' ? 'selected' : '' }}>Friday</option>
                            <option value="saturday" {{ $settings->schedule_day == 'saturday' ? 'selected' : '' }}>Saturday</option>
                            <option value="sunday" {{ $settings->schedule_day == 'sunday' ? 'selected' : '' }}>Sunday</option>
                        </select>
                        <small class="help-block">Day of week to run the backup (for weekly schedule)</small>
                    </div>

                    <div class="form-group">
                        <label for="default_backup_path">Backup folder on this computer</label>
                        <input type="text" 
                               class="form-control" 
                               id="default_backup_path" 
                               name="default_backup_path" 
                               placeholder="e.g. C:\Backups\Utamaduni — leave empty for storage/app/backups"
                               value="{{ $settings->default_backup_path ?? '' }}">
                        <small class="help-block">
                            Full path to a folder on the server PC where SQL files are written. Used for scheduled backups and for backups when someone opens the day (cash on hand). The web server user must have write permission to this folder. If empty, files go to the app’s default storage (<code>storage/app/backups</code>).
                        </small>
                    </div>

                    <div class="form-group" id="schedule_interval_hours_group" style="display: {{ $settings->schedule_frequency == 'everyXHours' ? 'block' : 'none' }};">
                        <label for="schedule_interval_hours">Every X Hours</label>
                        <input type="number"
                               class="form-control"
                               id="schedule_interval_hours"
                               name="schedule_interval_hours"
                               min="1"
                               max="24"
                               value="{{ $settings->schedule_interval_hours ?? 3 }}">
                        <small class="help-block">Runs every N hours at the minute selected in `Schedule Time`.</small>
                    </div>

                    <div class="form-group">
                        <label for="keep_backups_days">Keep Backups For (Days)</label>
                        <input type="number" 
                               class="form-control" 
                               id="keep_backups_days" 
                               name="keep_backups_days" 
                               min="1" 
                               max="365" 
                               value="{{ $settings->keep_backups_days ?? 30 }}" 
                               required>
                        <small class="help-block">Number of days to keep backup files before automatic deletion</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Backup List Section -->
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Backup History</h3>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Filename</th>
                                <th>Date Created</th>
                                <th>Size</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($backups as $index => $backup)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $backup['filename'] }}</td>
                                <td>{{ $backup['datetime']->format('Y-m-d H:i:s') }}</td>
                                <td>{{ number_format($backup['size'] / 1024, 2) }} KB</td>
                                <td>
                                    <a href="{{ route('backup.download', $backup['filename']) }}" class="btn btn-primary btn-xs">
                                        <i class="fa fa-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No backups found yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const frequencySelect = document.getElementById('schedule_frequency');
    const timeGroup = document.getElementById('schedule_time_group');
    const dayGroup = document.getElementById('schedule_day_group');
    const intervalHoursGroup = document.getElementById('schedule_interval_hours_group');

    const freqsNeedTime = ['hourly', 'everyThreeHours', 'everyXHours', 'daily', 'weekly'];

    frequencySelect.addEventListener('change', function() {
        if (freqsNeedTime.indexOf(this.value) !== -1) {
            timeGroup.style.display = 'block';
        } else {
            timeGroup.style.display = 'none';
        }

        if (this.value === 'weekly') {
            dayGroup.style.display = 'block';
        } else {
            dayGroup.style.display = 'none';
        }

        if (this.value === 'everyXHours') {
            intervalHoursGroup.style.display = 'block';
        } else {
            intervalHoursGroup.style.display = 'none';
        }
    });
});
</script>
@endsection










