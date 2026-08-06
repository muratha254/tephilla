<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Chat page: list conversations and thread with selected user.
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $selectedUserId = $request->get('with');

        // Users the current user has chatted with (as sender or receiver)
        $sentTo = Message::where('sender_id', $currentUser->id)->pluck('receiver_id');
        $receivedFrom = Message::where('receiver_id', $currentUser->id)->pluck('sender_id');
        $conversationUserIds = $sentTo->merge($receivedFrom)->unique()->values();

        $conversations = User::whereIn('id', $conversationUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Add unread count per conversation
        $unreadCounts = Message::unreadBy($currentUser->id)
            ->selectRaw('sender_id, COUNT(*) as cnt')
            ->groupBy('sender_id')
            ->pluck('cnt', 'sender_id');

        $selectedUser = null;
        $messages = collect();

        if ($selectedUserId) {
            $selectedUser = User::find($selectedUserId);
            if ($selectedUser && $selectedUser->id !== $currentUser->id) {
                $messages = Message::betweenUsers($currentUser->id, $selectedUser->id)
                    ->with(['sender:id,name', 'receiver:id,name'])
                    ->orderBy('created_at')
                    ->get();
                // Mark messages received from this user as read
                Message::where('sender_id', $selectedUser->id)
                    ->where('receiver_id', $currentUser->id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }
        }

        // All users (except current) for "New conversation"
        $allUsers = User::where('id', '!=', $currentUser->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $totalUnread = Message::unreadBy($currentUser->id)->count();

        return view('chat.index', compact(
            'conversations',
            'selectedUser',
            'messages',
            'allUsers',
            'unreadCounts',
            'totalUnread'
        ));
    }

    /**
     * Redirect to chat with specific user (for direct links).
     */
    public function thread(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('chat.index');
        }
        return redirect()->route('chat.index', ['with' => $user->id]);
    }

    /**
     * Send a message.
     */
    public function send(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'body' => 'required|string|max:5000',
        ]);

        $currentUser = auth()->user();
        if ((int) $request->receiver_id === $currentUser->id) {
            return response()->json(['message' => 'Cannot send message to yourself.'], 422);
        }

        $message = Message::create([
            'sender_id' => $currentUser->id,
            'receiver_id' => $request->receiver_id,
            'body' => $request->body,
        ]);

        $message->load(['sender:id,name', 'receiver:id,name']);

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'success' => true]);
        }

        return redirect()->route('chat.index', ['with' => $request->receiver_id])
            ->with('success', 'Message sent.');
    }

    /**
     * JSON endpoint for polling unread message count (for notifications).
     */
    public function unreadCount(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['count' => 0, 'last_from' => null]);
        }
        $count = Message::unreadBy($user->id)->count();
        $lastUnread = Message::unreadBy($user->id)
            ->with('sender:id,name')
            ->orderByDesc('created_at')
            ->first();
        $lastFrom = null;
        if ($lastUnread && $lastUnread->relationLoaded('sender') && $lastUnread->sender) {
            $lastFrom = ['id' => $lastUnread->sender->id, 'name' => $lastUnread->sender->name];
        }
        return response()->json([
            'count' => (int) $count,
            'last_from' => $lastFrom,
        ]);
    }
}
