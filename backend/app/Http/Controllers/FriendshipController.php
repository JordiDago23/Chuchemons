<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class FriendshipController extends Controller
{
    public function index()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $friends = Friendship::with([
            'sender:id,nombre,apellidos,email,player_id,bio,last_seen_at',
            'receiver:id,nombre,apellidos,email,player_id,bio,last_seen_at',
        ])
            ->where('status', 'accepted')
            ->where(function ($query) use ($user) {
                $query->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->latest()
            ->get()
            ->map(fn (Friendship $friendship) => $this->formatFriendshipUser(
                $friendship->sender_id === $user->id ? $friendship->receiver : $friendship->sender,
                $friendship,
                'friends'
            ))
            ->sortByDesc('is_online')
            ->values();

        $pendingReceived = Friendship::with('sender:id,nombre,apellidos,email,player_id,bio,last_seen_at')
            ->where('receiver_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (Friendship $friendship) => $this->formatFriendshipUser($friendship->sender, $friendship, 'pending_received'))
            ->values();

        $pendingSent = Friendship::with('receiver:id,nombre,apellidos,email,player_id,bio,last_seen_at')
            ->where('sender_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (Friendship $friendship) => $this->formatFriendshipUser($friendship->receiver, $friendship, 'pending_sent'))
            ->values();

        $onlineCount = $friends->where('is_online', true)->count();

        return response()->json([
            'friends' => $friends,
            'pending_received' => $pendingReceived,
            'pending_sent' => $pendingSent,
            'stats' => [
                'total' => $friends->count(),
                'online' => $onlineCount,
                'offline' => max($friends->count() - $onlineCount, 0),
            ],
        ]);
    }

    public function search(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = trim((string) $request->input('query'));

        $results = User::query()
            ->select('id', 'nombre', 'apellidos', 'email', 'player_id', 'bio', 'last_seen_at')
            ->where('id', '!=', $user->id)
            ->where(function ($builder) use ($query) {
                $builder->where('player_id', 'like', '%' . $query . '%')
                    ->orWhere('nombre', 'like', '%' . $query . '%')
                    ->orWhere('apellidos', 'like', '%' . $query . '%');
            })
            ->limit(10)
            ->get()
            ->map(function (User $candidate) use ($user) {
                $friendship = $this->findFriendshipBetween($user->id, $candidate->id);
                $status = 'none';

                if ($friendship) {
                    if ($friendship->status === 'accepted') {
                        $status = 'friends';
                    } elseif ($friendship->sender_id === $user->id) {
                        $status = 'pending_sent';
                    } else {
                        $status = 'pending_received';
                    }
                }

                return $this->formatFriendshipUser($candidate, $friendship, $status);
            })
            ->values();

        return response()->json([
            'results' => $results,
            'message' => $results->isEmpty() ? 'No se ha encontrado al usuario.' : null,
        ]);
    }

    public function sendRequest(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetId = (int) $request->input('user_id');

        if ($targetId === (int) $user->id) {
            return response()->json(['message' => 'No puedes enviarte una solicitud a ti mismo.'], 422);
        }

        $existing = $this->findFriendshipBetween($user->id, $targetId);

        if ($existing) {
            if ($existing->status === 'accepted') {
                return response()->json(['message' => 'Ya sois amigos.'], 409);
            }

            if ($existing->sender_id === $user->id) {
                return response()->json(['message' => 'Ya has enviado una solicitud pendiente.'], 409);
            }

            return response()->json(['message' => 'Ese usuario ya te ha enviado una solicitud. Revísala en pendientes.'], 409);
        }

        $friendship = Friendship::create([
            'sender_id' => $user->id,
            'receiver_id' => $targetId,
            'status' => 'pending',
        ]);

        $friendship->load('receiver:id,nombre,apellidos,email,player_id,bio,last_seen_at');

        return response()->json([
            'message' => 'Solicitud de amistad enviada correctamente.',
            'friendship' => $this->formatFriendshipUser($friendship->receiver, $friendship, 'pending_sent'),
        ], 201);
    }

    public function acceptRequest(Friendship $friendship)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ((int) $friendship->receiver_id !== (int) $user->id || $friendship->status !== 'pending') {
            return response()->json(['message' => 'No puedes aceptar esta solicitud.'], 403);
        }

        $friendship->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $friendship->load([
            'sender:id,nombre,apellidos,email,player_id,bio,last_seen_at',
            'receiver:id,nombre,apellidos,email,player_id,bio,last_seen_at',
        ]);

        $friendUser = $friendship->sender_id === $user->id ? $friendship->receiver : $friendship->sender;

        return response()->json([
            'message' => 'Solicitud aceptada correctamente.',
            'friendship' => $this->formatFriendshipUser($friendUser, $friendship, 'friends'),
        ]);
    }

    public function destroyRequest(Friendship $friendship)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!in_array((int) $user->id, [(int) $friendship->sender_id, (int) $friendship->receiver_id], true) || $friendship->status !== 'pending') {
            return response()->json(['message' => 'No puedes eliminar esta solicitud.'], 403);
        }

        $friendship->delete();

        return response()->json([
            'message' => 'Solicitud eliminada correctamente.',
        ]);
    }

    public function removeFriend(User $friend)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $friendship = Friendship::query()
            ->where('status', 'accepted')
            ->where(function ($query) use ($user, $friend) {
                $query->where(function ($inner) use ($user, $friend) {
                    $inner->where('sender_id', $user->id)
                        ->where('receiver_id', $friend->id);
                })->orWhere(function ($inner) use ($user, $friend) {
                    $inner->where('sender_id', $friend->id)
                        ->where('receiver_id', $user->id);
                });
            })
            ->first();

        if (!$friendship) {
            return response()->json(['message' => 'No existe una amistad con este usuario.'], 404);
        }

        $friendship->delete();

        return response()->json([
            'message' => 'Amigo eliminado correctamente.',
        ]);
    }

    private function findFriendshipBetween(int $firstUserId, int $secondUserId): ?Friendship
    {
        return Friendship::query()
            ->where(function ($query) use ($firstUserId, $secondUserId) {
                $query->where(function ($inner) use ($firstUserId, $secondUserId) {
                    $inner->where('sender_id', $firstUserId)
                        ->where('receiver_id', $secondUserId);
                })->orWhere(function ($inner) use ($firstUserId, $secondUserId) {
                    $inner->where('sender_id', $secondUserId)
                        ->where('receiver_id', $firstUserId);
                });
            })
            ->first();
    }

    private function formatFriendshipUser(User $user, ?Friendship $friendship, string $status): array
    {
        return [
            'id' => $user->id,
            'nombre' => $user->nombre,
            'apellidos' => $user->apellidos,
            'display_name' => trim($user->nombre . ' ' . $user->apellidos),
            'email' => $user->email,
            'player_id' => $user->player_id,
            'bio' => $user->bio,
            'is_online' => $this->isUserOnline($user),
            'last_seen_at' => optional($user->last_seen_at)?->toISOString(),
            'friendship_id' => $friendship?->id,
            'friendship_status' => $status,
            'status' => $friendship?->status,
        ];
    }

    private function isUserOnline(User $user): bool
    {
        return (bool) $user->last_seen_at && $user->last_seen_at->gte(now()->subMinutes(10));
    }
}
