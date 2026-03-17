<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Chuchemon;
use App\Models\MochilaXux;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminController extends Controller
{
    private const MAX_SPACES = 20;
    private const STACK_SIZE = 5;

    private function checkAdmin(): ?JsonResponse
    {
        if (!JWTAuth::parseToken()->authenticate()?->is_admin) {
            return response()->json(['message' => 'No autoritzat'], 403);
        }
        return null;
    }

    // ── GET /api/admin/stats ──────────────────────────────────────────────────
    public function stats(): JsonResponse
    {
        if ($err = $this->checkAdmin()) return $err;

        return response()->json([
            'jugadors'      => User::where('is_admin', false)->count(),
            'total_usuaris' => User::count(),
            'xuemons'       => Chuchemon::count(),
            'taxa_infeccio' => 12,
        ]);
    }

    // ── GET /api/admin/users ──────────────────────────────────────────────────
    public function listUsers(): JsonResponse
    {
        if ($err = $this->checkAdmin()) return $err;

        $users = User::all()->map(function ($user) {
            $xuxCount = MochilaXux::where('user_id', $user->id)->sum('quantity');
            return [
                'id'        => $user->id,
                'nombre'    => $user->nombre,
                'player_id' => $user->player_id,
                'email'     => $user->email,
                'is_admin'  => $user->is_admin,
                'xux_count' => (int) $xuxCount,
                'level'     => 1,
                'wins'      => 0,
            ];
        });

        return response()->json(['users' => $users]);
    }

    // ── POST /api/admin/users/{id}/add-xux ───────────────────────────────────
    public function addXuxToUser(Request $request, int $id): JsonResponse
    {
        if ($err = $this->checkAdmin()) return $err;

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Jugador no trobat.'], 404);
        }

        // Use the first chuchemon as the generic "Xux" carrier
        $firstChuch = Chuchemon::first();
        if (!$firstChuch) {
            return response()->json(['message' => "No hi ha Xuxemons a la base de dades."], 404);
        }

        $qtyToAdd   = (int) $request->quantity;
        $items      = MochilaXux::where('user_id', $targetUser->id)->get();
        $usedSpaces = $items->sum(fn($i) => (int) ceil($i->quantity / self::STACK_SIZE));
        $freeSpaces = self::MAX_SPACES - $usedSpaces;

        if ($freeSpaces <= 0) {
            return response()->json([
                'message'   => 'La motxilla del jugador està plena.',
                'added'     => 0,
                'discarded' => $qtyToAdd,
            ], 422);
        }

        $existing     = $items->firstWhere('chuchemon_id', $firstChuch->id);
        $currentQty   = $existing ? (int) $existing->quantity : 0;
        $currentSlots = $currentQty > 0 ? (int) ceil($currentQty / self::STACK_SIZE) : 0;
        $maxAddable   = ($currentSlots + $freeSpaces) * self::STACK_SIZE - $currentQty;
        $actualAdded  = min($qtyToAdd, $maxAddable);
        $discarded    = $qtyToAdd - $actualAdded;

        if ($existing) {
            $existing->quantity += $actualAdded;
            $existing->save();
        } else {
            MochilaXux::create([
                'user_id'      => $targetUser->id,
                'chuchemon_id' => $firstChuch->id,
                'quantity'     => $actualAdded,
            ]);
        }

        return response()->json([
            'message'   => "S'han afegit {$actualAdded} Xuxes a {$targetUser->player_id}.",
            'added'     => $actualAdded,
            'discarded' => $discarded,
        ]);
    }

    // ── POST /api/admin/users/{id}/add-random-chuchemon ──────────────────────
    public function addRandomChuchemon(int $id): JsonResponse
    {
        if ($err = $this->checkAdmin()) return $err;

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Jugador no trobat.'], 404);
        }

        $chuchemon = Chuchemon::inRandomOrder()->first();
        if (!$chuchemon) {
            return response()->json(['message' => 'No hi ha Xuxemons a la base de dades.'], 404);
        }

        $items      = MochilaXux::where('user_id', $targetUser->id)->get();
        $usedSpaces = $items->sum(fn($i) => (int) ceil($i->quantity / self::STACK_SIZE));

        if ($usedSpaces >= self::MAX_SPACES) {
            return response()->json(['message' => 'La motxilla del jugador està plena.'], 422);
        }

        $existing = $items->firstWhere('chuchemon_id', $chuchemon->id);
        if ($existing) {
            $existing->quantity += 1;
            $existing->save();
        } else {
            MochilaXux::create([
                'user_id'      => $targetUser->id,
                'chuchemon_id' => $chuchemon->id,
                'quantity'     => 1,
            ]);
        }

        return response()->json([
            'message'   => "S'ha afegit 1 {$chuchemon->name} a la motxilla de {$targetUser->player_id}.",
            'chuchemon' => $chuchemon,
        ]);
    }
}
