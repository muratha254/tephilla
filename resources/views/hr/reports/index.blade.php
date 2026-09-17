@extends('layouts.fleet')
@section('title', 'HR Reports')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'HR Reports',
    'subtitle' => 'Attendance, payroll and payment reports',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'HR Reports'],
    ],
])

<div class="row">
    @foreach($links as $link)
        <div class="col-md-4" style="margin-bottom:16px;">
            <a href="{{ route($link['route']) }}" class="sx-box" style="display:block;padding:24px;text-decoration:none;color:inherit;">
                <i class="fa {{ $link['icon'] }} fa-2x"></i>
                <h4 style="margin-top:12px;">{{ $link['label'] }}</h4>
            </a>
        </div>
    @endforeach
</div>
@endsection
