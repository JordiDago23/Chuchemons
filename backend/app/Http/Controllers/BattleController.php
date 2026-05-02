<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\BattleRequest;
use App\Models\BattleSelection;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class BattleController extends Controller
{
    public function overview(): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        $onlineFriends = Friendship::with([
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
            ->map(function (Friendship $friendship) use ($user) {
                return $friendship->sender_id === $user->id ? $friendship->receiver : $friendship->sender;
            })
            ->filter(fn (User $friend) => $this->isUserOnline($friend))
            ->map(fn (User $friend) => $this->formatBattleUser($friend))
            ->values();

        $pendingReceived = BattleRequest::with('challenger:id,nombre,apellidos,email,player_id,bio,last_seen_at')
            ->where('challenged_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function (BattleRequest $request) {
                return [
                    'id' => $request->id,
                    'created_at' => optional($request->created_at)?->toISOString(),
                    'user' => $this->formatBattleUser($request->challenger),
                ];
            })
            ->values();

        $pendingSent = BattleRequest::with('challenged:id,nombre,apellidos,email,player_id,bio,last_seen_at')
            ->where('challenger_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function (BattleRequest $request) {
                return [
                    'id' => $request->id,
                    'created_at' => optional($request->created_at)?->toISOString(),
                    'user' => $this->formatBattleUser($request->challenged),
                ];
            })
            ->values();

        $activeBattles = Battle::with([
            'challenger:id,nombre,apellidos,email,player_id,bio,last_seen_at',
            'challenged:id,nombre,apellidos,email,player_id,bio,last_seen_at',
            'selections',
        ])
            ->whereIn('status', ['pending_selection', 'in_combat'])
            ->where(function ($query) use ($user) {
                $query->where('challenger_id', $user->id)
                    ->orWhere('challenged_id', $user->id);
            })
            ->latest()
            ->get()
            ->map(fn (Battle $battle) => $this->formatBattleSummary($battle, $user->id))
            ->values();

        $recentBattles = Battle::with([
            'challenger:id,nombre,apellidos,email,player_id,bio,last_seen_at',
            'challenged:id,nombre,apellidos,email,player_id,bio,last_seen_at',
            'selections',
        ])
            ->where('status', 'completed')
            ->where(function ($query) use ($user) {
                $query->where('challenger_id', $user->id)
                    ->orWhere('challenged_id', $user->id);
            })
            ->latest('resolved_at')
            ->limit(10)
            ->get()
            ->map(fn (Battle $battle) => $this->formatBattleSummary($battle, $user->id))
            ->values();

        $stats = $this->computeStats($user->id);

        return response()->json([
            'online_friends' => $onlineFriends,
            'pending_received' => $pendingReceived,
            'pending_sent' => $pendingSent,
            'active_battles' => $activeBattles,
            'recent_battles' => $recentBattles,
            'stats' => $stats,
        ]);
    }

    public function sendRequest(Request $request): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'friend_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $friendId = (int) $request->input('friend_id');

        if ($friendId === (int) $user->id) {
            return response()->json(['message' => 'No puedes desafiarte a ti mismo.'], 422);
        }

        if (!$this->areFriends($user->id, $friendId)) {
            return response()->json(['message' => 'Solo puedes desafiar a jugadores que sean tus amigos.'], 403);
        }

        $existingPending = BattleRequest::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($user, $friendId) {
                $query->where(function ($inner) use ($user, $friendId) {
                    $inner->where('challenger_id', $user->id)
                        ->where('challenged_id', $friendId);
                })->orWhere(function ($inner) use ($user, $friendId) {
                    $inner->where('challenger_id', $friendId)
                        ->where('challenged_id', $user->id);
                });
            })
            ->first();

        if ($existingPending) {
            return response()->json(['message' => 'Ya existe una solicitud de batalla pendiente entre ambos jugadores.'], 409);
        }

        $existingOpenBattle = Battle::query()
            ->where('status', 'pending_selection')
            ->where(function ($query) use ($user, $friendId) {
                $query->where(function ($inner) use ($user, $friendId) {
                    $inner->where('challenger_id', $user->id)
                        ->where('challenged_id', $friendId);
                })->orWhere(function ($inner) use ($user, $friendId) {
                    $inner->where('challenger_id', $friendId)
                        ->where('challenged_id', $user->id);
                });
            })
            ->first();

        if ($existingOpenBattle) {
            return response()->json([
                'message' => 'Ya tenéis una batalla activa pendiente de selección.',
                'battle_id' => $existingOpenBattle->id,
            ], 409);
        }

        $battleRequest = BattleRequest::create([
            'challenger_id' => $user->id,
            'challenged_id' => $friendId,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Desafío enviado correctamente.',
            'request' => [
                'id' => $battleRequest->id,
                'friend_id' => $friendId,
                'status' => 'pending',
            ],
        ], 201);
    }

    public function acceptRequest(BattleRequest $battleRequest): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ((int) $battleRequest->challenged_id !== (int) $user->id || $battleRequest->status !== 'pending') {
            return response()->json(['message' => 'No puedes aceptar esta solicitud.'], 403);
        }

        $battle = DB::transaction(function () use ($battleRequest) {
            $battle = Battle::create([
                'challenger_id' => $battleRequest->challenger_id,
                'challenged_id' => $battleRequest->challenged_id,
                'status' => 'pending_selection',
            ]);

            $battleRequest->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'battle_id' => $battle->id,
            ]);

            return $battle;
        });

        return response()->json([
            'message' => 'Desafío aceptado. La batalla está lista para seleccionar Xuxemons.',
            'battle_id' => $battle->id,
        ]);
    }

    public function destroyRequest(BattleRequest $battleRequest): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!in_array((int) $user->id, [(int) $battleRequest->challenger_id, (int) $battleRequest->challenged_id], true)) {
            return response()->json(['message' => 'No puedes eliminar esta solicitud.'], 403);
        }

        if ($battleRequest->status !== 'pending') {
            return response()->json(['message' => 'Esta solicitud ya no está pendiente.'], 409);
        }

        $battleRequest->delete();

        return response()->json([
            'message' => 'Solicitud de batalla eliminada correctamente.',
        ]);
    }

    public function show(Battle $battle): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$this->isBattleParticipant($battle, (int) $user->id)) {
            return response()->json(['message' => 'No puedes acceder a esta batalla.'], 403);
        }

        $battle->load(['challenger:id,nombre,apellidos,email,player_id,bio,last_seen_at', 'challenged:id,nombre,apellidos,email,player_id,bio,last_seen_at', 'selections']);

        return response()->json([
            'battle' => $this->formatBattleSummary($battle, $user->id),
            'my_roster' => $this->getBattleRoster($user->id),
            'opponent_roster' => $this->getBattleRoster($this->opponentId($battle, $user->id)),
        ]);
    }

    public function selectChuchemon(Request $request, Battle $battle): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$this->isBattleParticipant($battle, (int) $user->id)) {
            return response()->json(['message' => 'No puedes participar en esta batalla.'], 403);
        }

        if ($battle->status !== 'pending_selection') {
            return response()->json(['message' => 'La batalla ya fue resuelta.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'chuchemon_id' => 'required|integer|exists:chuchemons,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chuchemonId = (int) $request->input('chuchemon_id');
        $owns = DB::table('user_chuchemons')
            ->where('user_id', $user->id)
            ->where('chuchemon_id', $chuchemonId)
            ->where('count', '>', 0)
            ->exists();

        if (!$owns) {
            return response()->json(['message' => 'Solo puedes seleccionar Xuxemons de tu colección.'], 422);
        }

        BattleSelection::updateOrCreate(
            ['battle_id' => $battle->id, 'user_id' => $user->id],
            ['chuchemon_id' => $chuchemonId]
        );

        $battle->load('selections');

        if ($battle->selections->count() < 2) {
            return response()->json([
                'message' => 'Xuxemon seleccionado. Esperando al rival.',
                'battle' => $this->formatBattleSummary($battle->fresh(['challenger', 'challenged', 'selections']), $user->id),
                'resolved' => false,
            ]);
        }

        // Ambos han seleccionado → iniciar combate por turnos
        $challengerSel = $battle->selections->firstWhere('user_id', $battle->challenger_id);
        $challengedSel  = $battle->selections->firstWhere('user_id', $battle->challenged_id);

        $fighterA = $this->getOwnedFighter((int) $battle->challenger_id, (int) $challengerSel->chuchemon_id);
        $fighterB = $this->getOwnedFighter((int) $battle->challenged_id, (int) $challengedSel->chuchemon_id);

        // El de más velocidad empieza; empate → el challenger
        $firstTurnId = ((int) ($fighterB->speed ?? 0) > (int) ($fighterA->speed ?? 0))
            ? (int) $battle->challenged_id
            : (int) $battle->challenger_id;

        $battle->update([
            'status'                 => 'in_combat',
            'challenger_current_hp'  => (int) ($fighterA->current_hp ?? $fighterA->max_hp ?? 100),
            'challenged_current_hp'  => (int) ($fighterB->current_hp ?? $fighterB->max_hp ?? 100),
            'current_turn_id'        => $firstTurnId,
            'combat_log'             => [],
            'last_roll'              => null,
        ]);

        return response()->json([
            'message' => '¡Comienza el combate! ' . ($firstTurnId === (int) $user->id ? 'Es tu turno.' : 'Turno del rival.'),
            'battle'  => $this->formatBattleSummary($battle->fresh(['challenger', 'challenged', 'selections']), $user->id),
            'resolved' => false,
        ]);
    }

    public function claimChuchemon(Request $request, Battle $battle): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($battle->status !== 'completed') {
            return response()->json(['message' => 'La batalla no ha sido resuelta todavía.'], 409);
        }

        if ((int) $battle->winner_id !== (int) $user->id) {
            return response()->json(['message' => 'Solo el ganador puede reclamar un Xuxemon.'], 403);
        }

        if (!is_null($battle->winner_chuchemon_id)) {
            return response()->json(['message' => 'Ya has reclamado tu recompensa de esta batalla.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'chuchemon_id' => 'required|integer|exists:chuchemons,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chuchemonId = (int) $request->input('chuchemon_id');
        $loserUserId = (int) $battle->loser_id;

        $owns = DB::table('user_chuchemons')
            ->where('user_id', $loserUserId)
            ->where('chuchemon_id', $chuchemonId)
            ->where('count', '>', 0)
            ->exists();

        if (!$owns) {
            return response()->json(['message' => 'El rival ya no tiene ese Xuxemon.'], 422);
        }

        $transferred = DB::transaction(function () use ($battle, $loserUserId, $user, $chuchemonId) {
            $ok = $this->transferChuchemon($loserUserId, (int) $user->id, $chuchemonId);
            if ($ok) {
                $battle->update([
                    'winner_chuchemon_id' => $chuchemonId,
                    'loser_chuchemon_id' => $chuchemonId,
                ]);
            }
            return $ok;
        });

        if (!$transferred) {
            return response()->json(['message' => 'No se pudo transferir el Xuxemon. Intenta con otro.'], 422);
        }

        return response()->json([
            'message' => '¡Has robado el Xuxemon seleccionado!',
            'battle' => $this->formatBattleSummary($battle->fresh(['challenger', 'challenged', 'selections']), (int) $user->id),
        ]);
    }

    public function rollDice(Battle $battle): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$this->isBattleParticipant($battle, (int) $user->id)) {
            return response()->json(['message' => 'No puedes participar en esta batalla.'], 403);
        }
        if ($battle->status !== 'in_combat') {
            return response()->json(['message' => 'El combate no está en curso.'], 409);
        }
        if ((int) $battle->current_turn_id !== (int) $user->id) {
            return response()->json(['message' => 'No es tu turno.'], 403);
        }

        $battle->load('selections');
        $isChallenger    = (int) $battle->challenger_id === (int) $user->id;
        $opponentId      = $this->opponentId($battle, (int) $user->id);
        $mySel           = $battle->selections->firstWhere('user_id', $user->id);
        $opponentSel     = $battle->selections->firstWhere('user_id', $opponentId);

        $myFighter       = $this->getOwnedFighter((int) $user->id, (int) $mySel->chuchemon_id);
        $opponentFighter = $this->getOwnedFighter($opponentId, (int) $opponentSel->chuchemon_id);

        $myCurrentHp       = $isChallenger ? (int) $battle->challenger_current_hp : (int) $battle->challenged_current_hp;
        $opponentCurrentHp = $isChallenger ? (int) $battle->challenged_current_hp : (int) $battle->challenger_current_hp;

        // ── Cálculo del turno ────────────────────────────
        $roll      = random_int(1, 6);
        $sizeMod   = $this->sizeModifier((string) ($myFighter->current_mida ?? 'Petit'));
        $typeMod   = $this->elementModifier((string) $myFighter->element, (string) $opponentFighter->element);
        $attackTotal = (int) ($myFighter->attack ?? 50) + $roll + $sizeMod + $typeMod;
        $damage      = max(0, $attackTotal - (int) ($opponentFighter->defense ?? 50));
        $newOpponentHp = max(0, $opponentCurrentHp - $damage);

        $log = $battle->combat_log ?? [];
        $turnEntry = [
            'turn'              => count($log) + 1,
            'attacker_id'       => (int) $user->id,
            'attacker_name'     => $myFighter->name,
            'defender_name'     => $opponentFighter->name,
            'roll'              => $roll,
            'size_mod'          => $sizeMod,
            'type_mod'          => $typeMod,
            'attack'            => (int) ($myFighter->attack ?? 50),
            'attack_total'      => $attackTotal,
            'defense'           => (int) ($opponentFighter->defense ?? 50),
            'damage'            => $damage,
            'hp_before'         => $opponentCurrentHp,
            'hp_after'          => $newOpponentHp,
        ];
        $log[] = $turnEntry;

        // ── ¿Batalla terminada? ──────────────────────────
        if ($newOpponentHp <= 0) {
            $updateData = [
                'status'      => 'completed',
                'winner_id'   => $user->id,
                'loser_id'    => $opponentId,
                'resolved_at' => now(),
                'combat_log'  => $log,
                'last_roll'   => $turnEntry,
                'result_payload' => ['combat_turns' => count($log)],
                'current_turn_id' => null,
            ];
            $isChallenger
                ? ($updateData['challenged_current_hp'] = 0)
                : ($updateData['challenger_current_hp']  = 0);

            $battle->update($updateData);

            return response()->json([
                'message'     => '¡Batalla terminada!',
                'battle'      => $this->formatBattleSummary($battle->fresh(['challenger', 'challenged', 'selections']), (int) $user->id),
                'last_roll'   => $turnEntry,
                'battle_over' => true,
            ]);
        }

        // ── Siguiente turno ──────────────────────────────
        $updateData = [
            'current_turn_id' => $opponentId,
            'combat_log'      => $log,
            'last_roll'       => $turnEntry,
        ];
        $isChallenger
            ? ($updateData['challenged_current_hp'] = $newOpponentHp)
            : ($updateData['challenger_current_hp']  = $newOpponentHp);

        $battle->update($updateData);

        return response()->json([
            'message'     => 'Turno completado.',
            'battle'      => $this->formatBattleSummary($battle->fresh(['challenger', 'challenged', 'selections']), (int) $user->id),
            'last_roll'   => $turnEntry,
            'battle_over' => false,
        ]);
    }

    public function cancelBattle(Battle $battle): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$this->isBattleParticipant($battle, (int) $user->id)) {
            return response()->json(['message' => 'No puedes cancelar esta batalla.'], 403);
        }

        if (!in_array($battle->status, ['pending_selection', 'in_combat'])) {
            return response()->json(['message' => 'Solo se puede cancelar una batalla activa.'], 409);
        }

        $battle->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Batalla cancelada.']);
    }

    private function resolveBattle(int $battleId, int $viewerId): array
    {
        return DB::transaction(function () use ($battleId, $viewerId) {
            /** @var Battle $battle */
            $battle = Battle::query()
                ->lockForUpdate()
                ->with(['challenger:id,nombre,apellidos,email,player_id,bio,last_seen_at', 'challenged:id,nombre,apellidos,email,player_id,bio,last_seen_at', 'selections'])
                ->findOrFail($battleId);

            if ($battle->status === 'completed') {
                return $this->formatBattleSummary($battle, $viewerId);
            }

            $challengerSelection = $battle->selections->firstWhere('user_id', $battle->challenger_id);
            $challengedSelection = $battle->selections->firstWhere('user_id', $battle->challenged_id);

            if (!$challengerSelection || !$challengedSelection) {
                return $this->formatBattleSummary($battle, $battle->challenger_id);
            }

            $fighterA = $this->getOwnedFighter((int) $battle->challenger_id, (int) $challengerSelection->chuchemon_id);
            $fighterB = $this->getOwnedFighter((int) $battle->challenged_id, (int) $challengedSelection->chuchemon_id);

            if (!$fighterA || !$fighterB) {
                return $this->formatBattleSummary($battle, $battle->challenger_id);
            }

            $aRoll = random_int(1, 6);
            $bRoll = random_int(1, 6);

            $aTypeMod = $this->elementModifier((string) $fighterA->element, (string) $fighterB->element);
            $bTypeMod = $this->elementModifier((string) $fighterB->element, (string) $fighterA->element);

            $aSizeMod = $this->sizeModifier((string) ($fighterA->current_mida ?? 'Petit'));
            $bSizeMod = $this->sizeModifier((string) ($fighterB->current_mida ?? 'Petit'));

            $aScore = $aRoll + $aTypeMod + $aSizeMod;
            $bScore = $bRoll + $bTypeMod + $bSizeMod;

            $winnerUserId = null;
            $loserUserId = null;
            $winnerChuchemonId = null;
            $loserChuchemonId = null;

            if ($aScore === $bScore) {
                $aSpeed = (int) ($fighterA->speed ?? 0);
                $bSpeed = (int) ($fighterB->speed ?? 0);

                if ($aSpeed === $bSpeed) {
                    $winnerUserId = random_int(0, 1) === 0 ? (int) $battle->challenger_id : (int) $battle->challenged_id;
                } else {
                    $winnerUserId = $aSpeed > $bSpeed ? (int) $battle->challenger_id : (int) $battle->challenged_id;
                }
            } else {
                $winnerUserId = $aScore > $bScore ? (int) $battle->challenger_id : (int) $battle->challenged_id;
            }

            $loserUserId = $winnerUserId === (int) $battle->challenger_id ? (int) $battle->challenged_id : (int) $battle->challenger_id;
            $winnerChuchemonId = $winnerUserId === (int) $battle->challenger_id ? (int) $challengerSelection->chuchemon_id : (int) $challengedSelection->chuchemon_id;
            $loserChuchemonId = $loserUserId === (int) $battle->challenger_id ? (int) $challengerSelection->chuchemon_id : (int) $challengedSelection->chuchemon_id;

            $battle->update([
                'status' => 'completed',
                'winner_id' => $winnerUserId,
                'loser_id' => $loserUserId,
                'resolved_at' => now(),
                'result_payload' => [
                    'rolls' => [
                        'challenger' => $aRoll,
                        'challenged' => $bRoll,
                    ],
                    'type_modifiers' => [
                        'challenger' => $aTypeMod,
                        'challenged' => $bTypeMod,
                    ],
                    'size_modifiers' => [
                        'challenger' => $aSizeMod,
                        'challenged' => $bSizeMod,
                    ],
                    'final_scores' => [
                        'challenger' => $aScore,
                        'challenged' => $bScore,
                    ],
                ],
            ]);

            return $this->formatBattleSummary($battle->fresh(['challenger', 'challenged', 'selections']), $viewerId);
        });
    }

    private function transferChuchemon(int $fromUserId, int $toUserId, int $chuchemonId): bool
    {
        $loserRow = DB::table('user_chuchemons')
            ->where('user_id', $fromUserId)
            ->where('chuchemon_id', $chuchemonId)
            ->lockForUpdate()
            ->first();

        if (!$loserRow || (int) $loserRow->count <= 0) {
            return false;
        }

        DB::table('user_chuchemons')
            ->where('user_id', $fromUserId)
            ->where('chuchemon_id', $chuchemonId)
            ->decrement('count', 1);

        $updatedLoserRow = DB::table('user_chuchemons')
            ->where('user_id', $fromUserId)
            ->where('chuchemon_id', $chuchemonId)
            ->lockForUpdate()
            ->first();

        if ($updatedLoserRow && (int) $updatedLoserRow->count <= 0) {
            DB::table('user_chuchemons')
                ->where('user_id', $fromUserId)
                ->where('chuchemon_id', $chuchemonId)
                ->delete();
        }

        $winnerRow = DB::table('user_chuchemons')
            ->where('user_id', $toUserId)
            ->where('chuchemon_id', $chuchemonId)
            ->lockForUpdate()
            ->first();

        if ($winnerRow) {
            DB::table('user_chuchemons')
                ->where('user_id', $toUserId)
                ->where('chuchemon_id', $chuchemonId)
                ->increment('count', 1);
        } else {
            DB::table('user_chuchemons')->insert([
                'user_id' => $toUserId,
                'chuchemon_id' => $chuchemonId,
                'count' => 1,
                'current_mida' => $loserRow->current_mida ?? 'Petit',
                'evolution_count' => $loserRow->evolution_count ?? 0,
                'level' => $loserRow->level ?? 1,
                'experience' => $loserRow->experience ?? 0,
                'experience_for_next_level' => $loserRow->experience_for_next_level ?? 100,
                'max_hp' => $loserRow->max_hp ?? 105,
                'current_hp' => $loserRow->current_hp ?? ($loserRow->max_hp ?? 105),
                'attack_boost' => 0,
                'defense_boost' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return true;
    }

    private function getOwnedFighter(int $userId, int $chuchemonId): ?object
    {
        return DB::table('user_chuchemons')
            ->join('chuchemons', 'user_chuchemons.chuchemon_id', '=', 'chuchemons.id')
            ->where('user_chuchemons.user_id', $userId)
            ->where('user_chuchemons.chuchemon_id', $chuchemonId)
            ->where('user_chuchemons.count', '>', 0)
            ->select(
                'chuchemons.id',
                'chuchemons.name',
                'chuchemons.element',
                'chuchemons.attack',
                'chuchemons.defense',
                'chuchemons.speed',
                'user_chuchemons.current_mida',
                'user_chuchemons.count',
                'user_chuchemons.current_hp',
                'user_chuchemons.max_hp'
            )
            ->first();
    }

    private function getBattleRoster(int $userId)
    {
        return DB::table('user_chuchemons')
            ->join('chuchemons', 'user_chuchemons.chuchemon_id', '=', 'chuchemons.id')
            ->where('user_chuchemons.user_id', $userId)
            ->where('user_chuchemons.count', '>', 0)
            ->select(
                'chuchemons.id',
                'chuchemons.name',
                'chuchemons.element',
                'chuchemons.image',
                'chuchemons.attack',
                'chuchemons.defense',
                'chuchemons.speed',
                'user_chuchemons.count',
                'user_chuchemons.level',
                'user_chuchemons.current_mida',
                'user_chuchemons.current_hp',
                'user_chuchemons.max_hp'
            )
            ->orderByDesc('user_chuchemons.level')
            ->orderBy('chuchemons.name')
            ->get()
            ->map(function ($entry) {
                $maxHp = max((int) ($entry->max_hp ?? 1), 1);
                $currentHp = max((int) ($entry->current_hp ?? $maxHp), 0);

                return [
                    'id' => (int) $entry->id,
                    'name' => $entry->name,
                    'element' => $entry->element,
                    'image' => $entry->image,
                    'attack' => (int) ($entry->attack ?? 50),
                    'defense' => (int) ($entry->defense ?? 50),
                    'speed' => (int) ($entry->speed ?? 50),
                    'count' => (int) ($entry->count ?? 0),
                    'level' => (int) ($entry->level ?? 1),
                    'current_mida' => $entry->current_mida ?? 'Petit',
                    'current_hp' => $currentHp,
                    'max_hp' => $maxHp,
                    'hp_percent' => round(($currentHp / $maxHp) * 100, 1),
                ];
            })
            ->values();
    }

    private function formatBattleSummary(Battle $battle, int $viewerId): array
    {
        $opponent = (int) $battle->challenger_id === $viewerId ? $battle->challenged : $battle->challenger;

        $mySelection = $battle->selections->firstWhere('user_id', $viewerId);
        $opponentSelection = $battle->selections->firstWhere('user_id', $this->opponentId($battle, $viewerId));

        $isChallenger    = (int) $battle->challenger_id === $viewerId;
        $myCurrentHp     = $isChallenger ? $battle->challenger_current_hp : $battle->challenged_current_hp;
        $opponentCurrentHp = $isChallenger ? $battle->challenged_current_hp : $battle->challenger_current_hp;

        return [
            'id'                   => $battle->id,
            'status'               => $battle->status,
            'challenger_id'        => $battle->challenger_id,
            'challenged_id'        => $battle->challenged_id,
            'created_at'           => optional($battle->created_at)?->toISOString(),
            'resolved_at'          => optional($battle->resolved_at)?->toISOString(),
            'opponent'             => $opponent ? $this->formatBattleUser($opponent) : null,
            'my_selection'         => $mySelection ? (int) $mySelection->chuchemon_id : null,
            'opponent_selection'   => $opponentSelection ? (int) $opponentSelection->chuchemon_id : null,
            'winner_id'            => $battle->winner_id,
            'loser_id'             => $battle->loser_id,
            'winner_chuchemon_id'  => $battle->winner_chuchemon_id,
            'loser_chuchemon_id'   => $battle->loser_chuchemon_id,
            'can_claim'            => $battle->status === 'completed' && (int) $battle->winner_id === $viewerId && is_null($battle->winner_chuchemon_id),
            'result_payload'       => $battle->result_payload,
            // Estado de combate por turnos
            'current_turn_id'      => $battle->current_turn_id,
            'is_my_turn'           => (int) $battle->current_turn_id === $viewerId,
            'my_current_hp'        => $myCurrentHp,
            'opponent_current_hp'  => $opponentCurrentHp,
            'last_roll'            => $battle->last_roll,
            'combat_log'           => $battle->combat_log ?? [],
        ];
    }

    private function formatBattleUser(User $user): array
    {
        return [
            'id' => $user->id,
            'nombre' => $user->nombre,
            'apellidos' => $user->apellidos,
            'display_name' => trim($user->nombre . ' ' . $user->apellidos),
            'player_id' => $user->player_id,
            'bio' => $user->bio,
            'is_online' => $this->isUserOnline($user),
            'last_seen_at' => optional($user->last_seen_at)?->toISOString(),
        ];
    }

    private function computeStats(int $userId): array
    {
        $victories = Battle::query()->where('status', 'completed')->where('winner_id', $userId)->count();
        $defeats = Battle::query()->where('status', 'completed')->where('loser_id', $userId)->count();
        $total = $victories + $defeats;

        return [
            'victories' => $victories,
            'defeats' => $defeats,
            'total' => $total,
            'win_rate' => $total > 0 ? round(($victories / $total) * 100, 1) : 0,
            'streak' => $this->currentStreak($userId),
        ];
    }

    private function currentStreak(int $userId): int
    {
        $latestBattles = Battle::query()
            ->where('status', 'completed')
            ->where(function ($query) use ($userId) {
                $query->where('winner_id', $userId)
                    ->orWhere('loser_id', $userId);
            })
            ->latest('resolved_at')
            ->limit(50)
            ->get(['winner_id', 'loser_id']);

        $streak = 0;

        foreach ($latestBattles as $battle) {
            if ((int) $battle->winner_id === $userId) {
                $streak++;
                continue;
            }

            break;
        }

        return $streak;
    }

    private function areFriends(int $firstUserId, int $secondUserId): bool
    {
        return Friendship::query()
            ->where('status', 'accepted')
            ->where(function ($query) use ($firstUserId, $secondUserId) {
                $query->where(function ($inner) use ($firstUserId, $secondUserId) {
                    $inner->where('sender_id', $firstUserId)
                        ->where('receiver_id', $secondUserId);
                })->orWhere(function ($inner) use ($firstUserId, $secondUserId) {
                    $inner->where('sender_id', $secondUserId)
                        ->where('receiver_id', $firstUserId);
                });
            })
            ->exists();
    }

    private function isBattleParticipant(Battle $battle, int $userId): bool
    {
        return in_array($userId, [(int) $battle->challenger_id, (int) $battle->challenged_id], true);
    }

    private function opponentId(Battle $battle, int $userId): int
    {
        return (int) $battle->challenger_id === (int) $userId
            ? (int) $battle->challenged_id
            : (int) $battle->challenger_id;
    }

    private function isUserOnline(User $user): bool
    {
        return (bool) $user->last_seen_at && $user->last_seen_at->gte(now()->subMinutes(10));
    }

    private function elementModifier(string $attackerElement, string $defenderElement): int
    {
        if ($attackerElement === $defenderElement) {
            return 0;
        }

        $advantage = [
            'Terra' => 'Aigua',
            'Aigua' => 'Aire',
            'Aire' => 'Terra',
        ];

        return ($advantage[$attackerElement] ?? null) === $defenderElement ? 1 : -1;
    }

    private function sizeModifier(string $currentMida): int
    {
        if ($currentMida === 'Gran') {
            return 2;
        }

        if ($currentMida === 'Mitjà' || $currentMida === 'Mitja') {
            return 1;
        }

        return 0;
    }
}
