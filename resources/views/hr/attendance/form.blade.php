@extends('layouts.fleet')

@php $isEdit = $record->exists; @endphp
@section('title', $isEdit ? 'Update Attendance' : 'Add Attendance')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Time Attendance',
    'subtitle' => $isEdit ? 'Update Attendance' : 'Add Attendance',
    'backUrl' => route('hr.attendance.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Attendance', 'url' => route('hr.attendance.index')],
        ['label' => $isEdit ? 'Edit' : 'Add'],
    ],
])

<form method="post" action="{{ $isEdit ? route('hr.attendance.update', $record) : route('hr.attendance.store') }}" class="sx-item-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto; max-width:720px; margin:0 auto;">
            <div class="form-group">
                <label>Employee <span class="sx-req">*</span></label>
                <select name="employee_id" class="form-control" required>
                    <option value="">~~Select Employee~~</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @if((string) old('employee_id', $record->employee_id) === (string) $employee->id) selected @endif>
                            {{ $employee->fullName() }}-{{ $employee->displayCode() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Date <span class="sx-req">*</span></label>
                <input type="date" name="attendance_date" class="form-control" value="{{ old('attendance_date', optional($record->attendance_date)->format('Y-m-d') ?: now()->toDateString()) }}" required>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Clock In</label>
                        <input type="time" name="clock_in" class="form-control" value="{{ old('clock_in', $record->clock_in ? substr((string) $record->clock_in, 0, 5) : '') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Clock Out</label>
                        <input type="time" name="clock_out" class="form-control" value="{{ old('clock_out', $record->clock_out ? substr((string) $record->clock_out, 0, 5) : '') }}">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Status <span class="sx-req">*</span></label>
                <select name="status" class="form-control" required>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @if(old('status', $record->status) === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $record->notes) }}</textarea>
            </div>
            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('hr.attendance.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
