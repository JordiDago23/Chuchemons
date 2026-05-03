<?php

namespace App\Http\Controllers;

use App\Models\Chuchemon;
use App\Models\GameSetting;
use App\Models\Item;
use App\Models\MochilaXux;
use App\Models\User;
use App\Models\UserTeam;
use App\Http\Controllers\LevelingController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ChuchemonController extends Controller
{
    private static function normalizeMalaltiaName(?string $name): string
    {
        return str_replace(
            ['á', 'à', 'é', 'è', 'í', 'ì', 'ó', 'ò', 'ú', 'ù'],
            ['a', 'a', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u'],
            mb_strtolower((string) $name)
        );
    }

    private static function effectiveSpeed(int $base, string $currentMida): float
    {
        return LevelingController::effectiveAttack($base, $currentMida);
    }

    private static function progressPercent(?int $current, ?int $total): ?float
    {
        if ($current === null || $total === null || $total <= 0) {
            return null;
        }

        return round(($current / $total) * 100, 2);
    }

    private static function buildUserChuchemonPayload(object $chuchemon, ?object $pivot, bool $isAuthenticated): array
    {
        $baseAttack = $chuchemon->attack ?? 50;
        $baseDefense = $chuchemon->defense ?? 50;
        $baseSpeed = $chuchemon->speed ?? 50;
        $captured = $pivot !== null;
        $currentMida = $captured ? ($pivot->current_mida ?? 'Petit') : ($chuchemon->mida ?? 'Petit');
        $level = $captured ? ($pivot->level ?? 1) : null;
        $count = (int) ($pivot->count ?? 0);
        $experience = $captured ? ($pivot->experience ?? 0) : null;
        $xpForNext = $captured ? ($pivot->experience_for_next_level ?? LevelingController::experienceForMida($currentMida)) : null;
        $maxHp = $captured
            ? ($pivot->max_hp ?? LevelingController::computeMaxHp($baseDefense, $level ?? 1, $currentMida))
            : null;
        $currentHp = $captured && $maxHp !== null
            ? min($pivot->current_hp ?? $maxHp, $maxHp)
            : null;
        $attackBoost = $captured ? ($pivot->attack_boost ?? 0) : 0;
        $defenseBoost = $captured ? ($pivot->defense_boost ?? 0) : 0;

        return [
            'id' => $chuchemon->id,
            'name' => $chuchemon->name,
            'element' => $chuchemon->element,
            'mida' => $chuchemon->mida,
            'current_mida' => $currentMida,
            'image' => $chuchemon->image,
            'attack' => $baseAttack,
            'defense' => $baseDefense,
            'speed' => $baseSpeed,
            'effective_attack' => $captured
                ? round(LevelingController::effectiveAttack($baseAttack, $currentMida) * (1 + ($attackBoost / 100)), 1)
                : (float) $baseAttack,
            'effective_defense' => $captured
                ? round(LevelingController::effectiveDefense($baseDefense, $currentMida) * (1 + ($defenseBoost / 100)), 1)
                : (float) $baseDefense,
            'effective_speed' => self::effectiveSpeed($baseSpeed, $currentMida),
            'attack_boost' => $captured ? $attackBoost : 0,
            'defense_boost' => $captured ? $defenseBoost : 0,
            'captured' => $isAuthenticated ? $captured : null,
            'count' => $count,
            'level' => $level,
            'experience' => $experience,
            'experience_for_next_level' => $xpForNext,
            'experience_progress' => self::progressPercent($experience, $xpForNext),
            'current_hp' => $currentHp,
            'max_hp' => $maxHp,
            'hp_percent' => self::progressPercent($currentHp, $maxHp),
            'created_at' => $chuchemon->created_at,
            'updated_at' => $chuchemon->updated_at,
        ];
    }

    /**
     * Obtiene todos los Chuchemons
     * Si el usuario está autenticado, incluye información de qué ha capturado
     */
    public function index(Request $request): JsonResponse
    {
        $user = null;
        
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            // Sin autenticación, está bien
            $user = null;
        }

        $allChuchemons = Chuchemon::query()
            ->when($request->query('element'), fn($q, $v) => $q->where('element', $v))
            ->when($request->query('mida'), fn($q, $v) => $q->where('mida', $v))
            ->get();

        $userChuchemons = collect();
        $infectionMap = collect();
        if ($user) {
            $userChuchemons = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->select(
                    'chuchemon_id',
                    'count',
                    'level',
                    'experience',
                    'experience_for_next_level',
                    'current_mida',
                    'current_hp',
                    'max_hp',
                    'attack_boost',
                    'defense_boost'
                )
                ->get()
                ->keyBy('chuchemon_id');

            $infectionMap = LevelingController::mapActiveInfections($user->id, $userChuchemons->keys()->all());
        }

        $chuchemons = $allChuchemons->map(function ($chuchemon) use ($userChuchemons, $infectionMap, $user) {
            $pivot = $user ? $userChuchemons->get($chuchemon->id) : null;
            $payload = self::buildUserChuchemonPayload($chuchemon, $pivot, $user !== null);
            $activeInfections = collect($infectionMap->get($chuchemon->id, []));
            $cannotEat = $activeInfections->contains(function ($infection) {
                return self::normalizeMalaltiaName($infection['name'] ?? null) === 'atracon';
            });

            return array_merge($payload, [
                'active_infections' => $user ? $activeInfections->all() : [],
                'has_active_infections' => $user ? $activeInfections->isNotEmpty() : false,
                'cannot_eat' => $user ? $cannotEat : false,
                'cannot_eat_reason' => $user && $cannotEat ? 'Atracón activo: este Xuxemon no puede comer más Xuxes por ahora.' : null,
            ]);
        });
        
        return response()->json($chuchemons);
    }

    /**
     * Obtiene un Chuchemon por ID
     */
    public function show(int $id): JsonResponse
    {
        $chuchemon = Chuchemon::find($id);

        if (!$chuchemon) {
            return response()->json(['message' => 'Chuchemon not found'], 404);
        }

        return response()->json($chuchemon);
    }

    /**
     * Filtra Chuchemons por elemento
     */
    public function filterByElement(string $element): JsonResponse
    {
        $chuchemons = Chuchemon::where('element', $element)->get();
        return response()->json($chuchemons);
    }

    /**
     * Filtra Chuchemons per mida (Petit, Mitjà, Gran)
     */
    public function filterByMida(string $mida): JsonResponse
    {
        $chuchemons = Chuchemon::where('mida', $mida)->get();
        return response()->json($chuchemons);
    }

    /**
     * Busca Chuchemons por nombre
     */
    public function search(string $query): JsonResponse
    {
        $chuchemons = Chuchemon::where('name', 'like', "%{$query}%")->get();
        return response()->json($chuchemons);
    }

    /**
     * Obtiene los Chuchemons capturados por el usuario autenticado
     */
    public function getMyChuchemons(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            // Get captured chuchemons directly from user_chuchemons table
            $userChuchemons = DB::table('user_chuchemons')
                ->join('chuchemons', 'user_chuchemons.chuchemon_id', '=', 'chuchemons.id')
                ->where('user_chuchemons.user_id', $user->id)
                ->select(
                    'chuchemons.*',
                    'user_chuchemons.count',
                    'user_chuchemons.level',
                    'user_chuchemons.experience',
                    'user_chuchemons.experience_for_next_level',
                    'user_chuchemons.current_mida',
                    'user_chuchemons.current_hp',
                    'user_chuchemons.max_hp',
                    'user_chuchemons.attack_boost',
                    'user_chuchemons.defense_boost'
                )
                ->get();

            $infectionMap = LevelingController::mapActiveInfections($user->id, $userChuchemons->pluck('id')->all());

            $userChuchemons = $userChuchemons->map(function ($chuchemon) use ($infectionMap) {
                    $payload = self::buildUserChuchemonPayload($chuchemon, $chuchemon, true);
                    $activeInfections = collect($infectionMap->get($chuchemon->id, []));

                    $cannotEat = $activeInfections->contains(function ($infection) {
                        return self::normalizeMalaltiaName($infection['name'] ?? null) === 'atracon';
                    });

                    return array_merge($payload, [
                        'active_infections' => $activeInfections->all(),
                        'has_active_infections' => $activeInfections->isNotEmpty(),
                        'cannot_eat' => $cannotEat,
                        'cannot_eat_reason' => $cannotEat ? 'Atracón activo: este Xuxemon no puede comer más Xuxes por ahora.' : null,
                    ]);
                });

            return response()->json($userChuchemons->values()->all());
        } catch (\Exception $e) {
            Log::error('Error en getMyChuchemons: ' . $e->getMessage());
            return response()->json(['message' => 'Sin chuchemons capturados'], 200);
        }
    }

    /**
     * Captura un Chuchemon (incrementa el contador)
     */
    public function capture(int $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $chuchemon = Chuchemon::find($id);
            
            if (!$chuchemon) {
                return response()->json(['message' => 'Chuchemon not found'], 404);
            }

            // Usar attach/sync para manejar la relación many-to-many
            $existing = $user->capturedChuchemons()
                ->where('chuchemon_id', $id)
                ->first();

            if ($existing) {
                // Incrementar el contador
                $existing->pivot->increment('count');
            } else {
                // Agregar nuevo — inicialitzar HP basat en stats base + nivell 1
                $maxHp = LevelingController::computeMaxHp($chuchemon->defense ?? 50, 1, 'Petit');
                $user->capturedChuchemons()->attach($id, [
                    'count'                     => 1,
                    'current_mida'              => 'Petit',
                    'level'                     => 1,
                    'experience'                => 0,
                    'experience_for_next_level' => LevelingController::experienceForMida('Petit'),
                    'max_hp'                    => $maxHp,
                    'current_hp'                => $maxHp,
                ]);
            }

            return response()->json([
                'message' => 'Chuchemon capturado exitosamente',
                'chuchemon_id' => $id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene el equipo del usuario
     */
    public function getTeam(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $team = $user->team;

            if (!$team) {
                return response()->json([
                    'message' => 'Usuario aún no tiene equipo',
                    'team' => null,
                ], 200);
            }

            // Obtener los datos de los chuchemons con HP per-user
            $teamData = [];
            $slots = ['chuchemon_1_id', 'chuchemon_2_id', 'chuchemon_3_id'];
            foreach ($slots as $slot) {
                if ($team->$slot) {
                    $c = Chuchemon::find($team->$slot);
                    if ($c) {
                        $uc = DB::table('user_chuchemons')
                            ->where('user_id', $user->id)
                            ->where('chuchemon_id', $c->id)
                            ->where('count', '>', 0)
                            ->first();

                        if (!$uc) {
                            // El usuario ya no tiene este Chuchemon (fue robado); limpiar slot
                            $team->$slot = null;
                            $team->save();
                            continue;
                        }
                        $maxHp  = $uc->max_hp    ?? LevelingController::computeMaxHp($c->defense ?? 50, $uc->level ?? 1, $uc->current_mida ?? 'Petit');
                        $currHp = $uc->current_hp ?? $maxHp;
                        $xpForNext = $uc->experience_for_next_level ?? LevelingController::experienceForMida($uc->current_mida ?? 'Petit');
                        $xp        = $uc->experience ?? 0;
                        $atkBoost  = ($uc->attack_boost  ?? 0) / 100;
                        $defBoost  = ($uc->defense_boost ?? 0) / 100;
                        $teamData[] = [
                            'id'                        => $c->id,
                            'name'                      => $c->name,
                            'element'                   => $c->element,
                            'mida'                      => $c->mida,
                            'image'                     => $c->image,
                            'attack'                    => $c->attack ?? 50,
                            'defense'                   => $c->defense ?? 50,
                            'speed'                     => $c->speed ?? 50,
                            'attack_boost'              => $uc->attack_boost  ?? 0,
                            'defense_boost'             => $uc->defense_boost ?? 0,
                            'current_mida'              => $uc->current_mida ?? 'Petit',
                            'level'                     => $uc->level ?? 1,
                            'current_hp'                => $currHp,
                            'max_hp'                    => $maxHp,
                            'hp_percent'                => $maxHp > 0 ? round(($currHp / $maxHp) * 100, 1) : 100,
                            'experience'                => $xp,
                            'experience_for_next_level' => $xpForNext,
                            'xp_percent'                => $xpForNext > 0 ? round(($xp / $xpForNext) * 100, 1) : 0,
                            'effective_attack'          => round(LevelingController::effectiveAttack($c->attack ?? 50, $uc->current_mida ?? 'Petit') * (1 + $atkBoost), 1),
                            'effective_defense'         => round(LevelingController::effectiveDefense($c->defense ?? 50, $uc->current_mida ?? 'Petit') * (1 + $defBoost), 1),
                            'effective_speed'           => self::effectiveSpeed($c->speed ?? 50, $uc->current_mida ?? 'Petit'),
                        ];
                    }
                }
            }

            return response()->json([
                'team' => $teamData,
                'team_ids' => [
                    $team->chuchemon_1_id,
                    $team->chuchemon_2_id,
                    $team->chuchemon_3_id,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Guarda el equipo del usuario
     */
    public function saveTeam(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $validated = $request->validate([
                'chuchemon_1_id' => 'nullable|exists:chuchemons,id',
                'chuchemon_2_id' => 'nullable|exists:chuchemons,id',
                'chuchemon_3_id' => 'nullable|exists:chuchemons,id',
            ]);

            $team = UserTeam::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'chuchemon_1_id' => $validated['chuchemon_1_id'] ?? null,
                    'chuchemon_2_id' => $validated['chuchemon_2_id'] ?? null,
                    'chuchemon_3_id' => $validated['chuchemon_3_id'] ?? null,
                ]
            );

            // Actualizar si ya existe
            if ($team->wasRecentlyCreated === false) {
                $team->update([
                    'chuchemon_1_id' => $validated['chuchemon_1_id'] ?? null,
                    'chuchemon_2_id' => $validated['chuchemon_2_id'] ?? null,
                    'chuchemon_3_id' => $validated['chuchemon_3_id'] ?? null,
                ]);
            }

            return response()->json([
                'message' => 'Equipo guardado exitosamente',
                'team' => $team,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─── CRUD ADMIN ──────────────────────────────────────────────────────────

    /**
     * Crea un nou Xuxemon (admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:100|unique:chuchemons,name',
            'element' => 'required|in:Terra,Aire,Aigua',
            'mida'    => 'required|in:Petit,Mitjà,Gran',
            'image'   => 'nullable|string|max:255',
            'attack'  => 'nullable|integer|min:0|max:255',
            'defense' => 'nullable|integer|min:0|max:255',
            'speed'   => 'nullable|integer|min:0|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chuchemon = Chuchemon::create($request->only(['name', 'element', 'mida', 'image', 'attack', 'defense', 'speed']));

        return response()->json($chuchemon, 201);
    }

    /**
     * Actualitza un Xuxemon (admin)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $chuchemon = Chuchemon::find($id);

        if (!$chuchemon) {
            return response()->json(['message' => 'Chuchemon no trobat'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'sometimes|string|max:100|unique:chuchemons,name,' . $id,
            'element' => 'sometimes|in:Terra,Aire,Aigua',
            'mida'    => 'sometimes|in:Petit,Mitjà,Gran',
            'image'   => 'nullable|string|max:255',
            'attack'  => 'sometimes|integer|min:0|max:255',
            'defense' => 'sometimes|integer|min:0|max:255',
            'speed'   => 'sometimes|integer|min:0|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chuchemon->update($request->only(['name', 'element', 'mida', 'image', 'attack', 'defense', 'speed']));

        return response()->json($chuchemon);
    }

    /**
     * Elimina un Xuxemon (admin)
     */
    public function destroy(int $id): JsonResponse
    {
        $chuchemon = Chuchemon::find($id);

        if (!$chuchemon) {
            return response()->json(['message' => 'Chuchemon no trobat'], 404);
        }

        $chuchemon->delete();

        return response()->json(['message' => 'Chuchemon eliminat correctament']);
    }

    /**
     * Evoluciona un Xuxemon capturado del usuario
     * Petit -> Mitjà -> Gran
     */
    public function evolve(int $chuchemonId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            // Get the user's captured Chuchemon
            $userChuchemon = $user->capturedChuchemsWithEvolution()
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$userChuchemon) {
                return response()->json(['message' => 'No has capturado este Xuxemon'], 404);
            }

            $currentMida = $userChuchemon->pivot->current_mida;
            $nextMida = null;
            $cost = 0;

            // Atracón blocks feeding / evolving
            $activeInfections = LevelingController::mapActiveInfections($user->id, [$chuchemonId])
                ->get($chuchemonId, collect());
            if ($activeInfections->contains(fn ($inf) => LevelingController::normalizeMalaltiaName($inf['name'] ?? null) === 'atracon')) {
                return response()->json([
                    'message' => 'Atracón activo: este Xuxemon no puede alimentarse ni evolucionar.',
                ], 422);
            }

            // Determine next size and cost
            if ($currentMida === 'Petit') {
                $nextMida = 'Mitjà';
                $cost = GameSetting::getInt('xux_petit_mitja', 3);
            } elseif ($currentMida === 'Mitjà') {
                $nextMida = 'Gran';
                $cost = GameSetting::getInt('xux_mitja_gran', 5);
            } elseif ($currentMida === 'Gran') {
                return response()->json(['message' => 'Tu Xuxemon ya está en su máxima evolución'], 400);
            }

            // Bajón de azúcar: +2 extra xuxes per evolution
            if ($activeInfections->contains(fn ($inf) => in_array(
                LevelingController::normalizeMalaltiaName($inf['name'] ?? null),
                ['bajon de azucar', 'bajo de azucar']
            ))) {
                $cost += 2;
            }

            $xuxExpItemId = Item::idByName(Item::NAME_XUX_EXP);
            if (!$xuxExpItemId) {
                return response()->json([
                    'message' => 'No existe el item Xux Exp en la base de datos.',
                ], 409);
            }

            // Check Xux Exp for evolution
            $totalXuxExp = MochilaXux::where('user_id', $user->id)
                ->where('item_id', $xuxExpItemId)
                ->sum('quantity');

            if ($totalXuxExp < $cost) {
                return response()->json([
                    'message' => "Necesitas {$cost} Xux Exp para evolucionar. Tienes {$totalXuxExp}.",
                ], 400);
            }

            // Deduct Xux Exp
            $remaining = $cost;
            $xuxRows = MochilaXux::where('user_id', $user->id)
                ->where('item_id', $xuxExpItemId)
                ->where('quantity', '>', 0)
                ->orderBy('quantity', 'asc')
                ->get();

            foreach ($xuxRows as $row) {
                if ($remaining <= 0) break;
                $deduct = min($remaining, $row->quantity);
                $row->quantity -= $deduct;
                $row->save();
                $remaining -= $deduct;
            }

            // Update the pivot table with new mida
            DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->update([
                    'current_mida'   => $nextMida,
                    'evolution_count' => DB::raw('evolution_count + 1'),
                    'experience_for_next_level' => LevelingController::experienceForMida($nextMida),
                ]);

            // Recalculate max_hp and scale current_hp on evolution
            $ucRow      = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();
            $baseDefense = $userChuchemon->defense ?? 50;
            $newMaxHp    = LevelingController::computeMaxHp($baseDefense, $ucRow->level ?? 1, $nextMida);
            $oldMaxHp    = $ucRow->max_hp ?? 105;
            $oldCurrHp   = $ucRow->current_hp ?? $oldMaxHp;
            // Keep HP ratio proportional after evolution
            $newCurrHp   = (int) round(($oldCurrHp / max($oldMaxHp, 1)) * $newMaxHp);
            $newCurrHp   = max(1, min($newCurrHp, $newMaxHp));

            DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->update([
                    'max_hp'     => $newMaxHp,
                    'current_hp' => $newCurrHp,
                ]);

            // +25 XP al evolucionar a Mitjà, +50 XP a Gran
            $xpGain = $nextMida === 'Gran' ? 50 : 25;
            $user->addExperience($xpGain);

            // Get updated data
            $updated = $user->capturedChuchemsWithEvolution()
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            return response()->json([
                'message'   => "Tu {$userChuchemon->name} ha evolucionado a {$nextMida}!",
                'xp_gained' => $xpGain,
                'chuchemon' => [
                    'id'              => $updated->id,
                    'name'            => $updated->name,
                    'element'         => $updated->element,
                    'mida'            => $updated->mida,
                    'image'           => $updated->image,
                    'current_mida'    => $updated->pivot->current_mida,
                    'evolution_count' => $updated->pivot->evolution_count,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get evolution info for a captured Chuchemon
     */
    public function getEvolutionInfo(int $chuchemonId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $userChuchemon = $user->capturedChuchemsWithEvolution()
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$userChuchemon) {
                return response()->json(['message' => 'No has capturado este Xuxemon'], 404);
            }

            $currentMida = $userChuchemon->pivot->current_mida;
            $canEvolve = $currentMida !== 'Gran';
            $nextMida = $currentMida === 'Petit' ? 'Mitjà' : ($currentMida === 'Mitjà' ? 'Gran' : null);

            return response()->json([
                'chuchemon_id' => $chuchemonId,
                'name' => $userChuchemon->name,
                'current_mida' => $currentMida,
                'next_mida' => $nextMida,
                'can_evolve' => $canEvolve,
                'evolution_count' => $userChuchemon->pivot->evolution_count,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}


