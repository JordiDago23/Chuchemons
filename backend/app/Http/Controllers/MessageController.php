<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Friendship;
use App\Models\User;
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
        $request->validate(['content' => 'required|string|max:2000']);
        $message = Message::create([
            'sender_id'   => $user->id,
            'receiver_id' => $friendId,
            'content'     => $request->content,
            'is_read'     => false,
        ]);
        return response()->json(['data' => $message], 201);
    }

    // Acepta ?since_id=N para devolver sólo mensajes nuevos (polling eficiente)
    public function getConversation(Request $request, $friendId)
    {
        $user = Auth::user();
        if (!$this->areFriends($user->id, $friendId)) {
            return response()->json(['error' => 'No sois amigos.'], 403);
        }
        $query = Message::conversationWith($user->id, $friendId)->orderBy('created_at');
        if ($request->has('since_id') && is_numeric($request->since_id)) {
            $query->where('id', '>', (int) $request->since_id);
        }
        return response()->json(['data' => $query->get()]);
    }

    public function getConversations()
    {
        $user = Auth::user();

        $friendships = Friendship::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
            })->get();

        $conversations = [];
        foreach ($friendships as $friendship) {
            $fid = $friendship->sender_id === $user->id
                ? $friendship->receiver_id
                : $friendship->sender_id;

            $lastMessage = Message::conversationWith($user->id, $fid)
                ->orderByDesc('created_at')->first();

            $friend = User::find($fid);
            $conversations[] = [
                'id'                => $fid,
                'friend_name'       => $friend ? ($friend->player_id ?: $friend->nombre) : "Usuario $fid",
                'last_message'      => $lastMessage ? $lastMessage->content : null,
                'last_message_time' => $lastMessage ? $lastMessage->created_at : null,
                'unread_count'      => Message::where('sender_id', $fid)
                    ->where('receiver_id', $user->id)
                    ->where('is_read', false)
                    ->count(),
            ];
        }

        usort($conversations, function ($a, $b) {
            if (!$a['last_message_time'] && !$b['last_message_time']) return 0;
            if (!$a['last_message_time']) return 1;
            if (!$b['last_message_time']) return -1;
            return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
        });

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
        return Friendship::where('status', 'accepted')
            ->where(function ($q) use ($userId, $friendId) {
                $q->where(function ($q2) use ($userId, $friendId) {
                    $q2->where('sender_id', $userId)->where('receiver_id', $friendId);
                })->orWhere(function ($q2) use ($userId, $friendId) {
                    $q2->where('sender_id', $friendId)->where('receiver_id', $userId);
                });
            })->exists();
    }
}
