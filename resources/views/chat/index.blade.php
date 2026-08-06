@extends('layouts.master')

@section('title')
    Chat
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Chat</li>
@endsection

@section('content')
@php($currentUser = auth()->user())
<style>
.chat-layout { display: flex; gap: 0; min-height: 70vh; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; }
.chat-sidebar { width: 280px; min-width: 280px; background: #f9f9f9; border-right: 1px solid #ddd; overflow-y: auto; }
.chat-sidebar .chat-user { display: block; padding: 12px 15px; border-bottom: 1px solid #eee; color: #333; text-decoration: none; }
.chat-sidebar .chat-user:hover { background: #f0f0f0; }
.chat-sidebar .chat-user.active { background: #e3f2fd; border-left: 3px solid #2196F3; }
.chat-sidebar .chat-user .name { font-weight: 600; }
.chat-sidebar .chat-user .unread { float: right; background: #f44336; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.chat-main { flex: 1; display: flex; flex-direction: column; background: #fff; }
.chat-header { padding: 12px 15px; border-bottom: 1px solid #ddd; background: #fafafa; font-weight: 600; }
.chat-messages { flex: 1; overflow-y: auto; padding: 15px; min-height: 200px; }
.chat-msg { margin-bottom: 12px; max-width: 75%; }
.chat-msg.sent { margin-left: auto; }
.chat-msg .bubble { padding: 10px 14px; border-radius: 12px; display: inline-block; }
.chat-msg.received .bubble { background: #e8e8e8; border-bottom-left-radius: 4px; }
.chat-msg.sent .bubble { background: #2196F3; color: #fff; border-bottom-right-radius: 4px; }
.chat-msg .meta { font-size: 11px; color: #888; margin-top: 4px; }
.chat-form { padding: 12px 15px; border-top: 1px solid #ddd; background: #fafafa; }
.chat-form .form-group { margin-bottom: 0; }
.chat-empty { padding: 40px 20px; text-align: center; color: #888; }
.chat-new { padding: 10px 15px; border-bottom: 1px solid #ddd; background: #fff; }
.chat-new select { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
</style>

@if(session('success'))
<div class="alert alert-success alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    {{ session('success') }}
</div>
@endif

<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-comments"></i> Chat</h3>
                @if($totalUnread > 0)
                <span class="label label-danger">{{ $totalUnread }} unread</span>
                @endif
            </div>
            <div class="box-body no-padding">
                <div class="chat-layout">
                    <div class="chat-sidebar">
                        <div class="chat-new">
                            <label class="text-muted" style="font-size: 11px;">Start new conversation</label>
                            <select id="chat-select-user" class="form-control">
                                <option value="">-- Select user --</option>
                                @foreach($allUsers as $u)
                                <option value="{{ $u->id }}" data-url="{{ route('chat.index', ['with' => $u->id]) }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @forelse($conversations as $u)
                        <a href="{{ route('chat.index', ['with' => $u->id]) }}" class="chat-user {{ $selectedUser && $selectedUser->id === $u->id ? 'active' : '' }}">
                            <span class="name">{{ $u->name }}</span>
                            @if(isset($unreadCounts[$u->id]) && $unreadCounts[$u->id] > 0)
                            <span class="unread">{{ $unreadCounts[$u->id] }}</span>
                            @endif
                        </a>
                        @empty
                        <div class="chat-empty" style="padding: 20px; font-size: 13px;">No conversations yet. Select a user above to start.</div>
                        @endforelse
                    </div>
                    <div class="chat-main">
                        @if($selectedUser)
                        <div class="chat-header">
                            <i class="fa fa-user"></i> {{ $selectedUser->name }}
                        </div>
                        <div class="chat-messages" id="chat-messages">
                            @foreach($messages as $msg)
                            <div class="chat-msg {{ $msg->sender_id === $currentUser->id ? 'sent' : 'received' }}">
                                <div class="bubble">{{ nl2br(e($msg->body)) }}</div>
                                <div class="meta">
                                    {{ $msg->sender->name ?? 'User' }} · {{ $msg->created_at->format('d M Y, H:i') }}
                                    @if($msg->read_at && $msg->receiver_id === $currentUser->id)<span class="text-muted"> · Read</span>@endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="chat-form">
                            <form id="chat-send-form" action="{{ route('chat.send') }}" method="post">
                                @csrf
                                <input type="hidden" name="receiver_id" value="{{ $selectedUser->id }}">
                                <div class="form-group">
                                    <div class="input-group">
                                        <textarea name="body" id="chat-body" class="form-control" rows="2" placeholder="Type a message..." maxlength="5000" required></textarea>
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-primary" id="chat-send-btn"><i class="fa fa-send"></i> Send</button>
                                        </span>
                                    </div>
                                </div>
                            </form>
                        </div>
                        @else
                        <div class="chat-empty">
                            <i class="fa fa-comments-o" style="font-size: 48px; margin-bottom: 15px;"></i>
                            <p>Select a user from the list or start a new conversation above.</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    $('#chat-select-user').on('change', function() {
        var url = $(this).find('option:selected').data('url');
        if (url) window.location.href = url;
    });

    $('#chat-send-form').on('submit', function(e) {
        var body = $('#chat-body').val();
        if (!body || !body.trim()) {
            e.preventDefault();
            return false;
        }
        $('#chat-send-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');
    });
});
</script>
@endpush
