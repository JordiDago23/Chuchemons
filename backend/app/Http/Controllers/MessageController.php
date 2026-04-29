<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class MessageController extends Controller
{
    public function store(Request $request, $friendId)
    {
        $user = Auth::user();
        if (!$this->areFriends($user->id, $friendId)) {
            return response()->json(['error' => 'No sois amigos.'], 403);
        }
        $request->validate(['content' => 'required|string']);
        $message = Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $friendId,
            'content' => $request->content,
            'is_read' => false,
        ]);
        return response()->json(['data' => $message], 201);
    }

    public function getConversation($friendId)
    {
        $user = Auth::user();
        if (!$this->areFriends($user->id, $friendId)) {
            return response()->json(['error' => 'No sois amigos.'], 403);
        }
        $messages = Message::conversationWith($user->id, $friendId)->orderBy('created_at')->get();
        return response()->json(['data' => $messages]);
    }

    public function getConversations()
    {
        $user = Auth::user();
        $friendIds = Friendship::where('user_id', $user->id)->orWhere('friend_id', $user->id)->pluck('user_id', 'friend_id')->flatten()->unique()->toArray();
        $conversations = [];
        foreach ($friendIds as $fid) {
            if ($fid == $user->id) continue;
            $lastMessage = Message::conversationWith($user->id, $fid)->orderByDesc('created_at')->first();
            if ($lastMessage) {
                $conversations[] = [
                    'id' => $fid,
                    'last_message' => $lastMessage->content,
                    'last_message_time' => $lastMessage->created_at,
                    'unread_count' => Message::where('sender_id', $fid)->where('receiver_id', $user->id)->where('is_read', false)->count(),
                ];
            }
        }
        return response()->json(['data' => $conversations]);
    }

    public function markAsRead($messageId)
    {
        $user = Auth::user();
        $message = Message::findOrFail($messageId);
        if ($message->receiver_id !== $user->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }
        $message->is_read = true;
        $message->read_at = Carbon::now();
        $message->save();
        return response()->json(['data' => $message]);
    }

    public function markConversationAsRead($friendId)
    {
        $user = Auth::user();
        Message::where('sender_id', $friendId)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => Carbon::now()]);
        return response()->json(['success' => true]);
    }

    private function areFriends($userId, $friendId)
    {
        return Friendship::where(function ($q) use ($userId, $friendId) {
            $q->where('user_id', $userId)->where('friend_id', $friendId);
        })->orWhere(function ($q) use ($userId, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $userId);
        })->where('status', 'accepted')->exists();
    }
}
