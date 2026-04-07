<?php

namespace App\Http\Controllers;

use App\Models\Chuchemon;
use App\Models\User;
use App\Models\UserTeam;
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

        $capturedCounts = [];
        $infectionMap = collect();
        if ($user) {
            $capturedCounts = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->pluck('count', 'chuchemon_id')
                ->all();

            $infectionMap = DB::table('user_infections')
                ->join('malalties', 'user_infections.malaltia_id', '=', 'malalties.id')
                ->where('user_infections.user_id', $user->id)
                ->where('user_infections.is_active', true)
                ->select(
                    'user_infections.chuchemon_id',
                    'user_infections.infection_percentage',
                    'malalties.id as malaltia_id',
                    'malalties.name',
                    'malalties.type',
                    'malalties.severity'
                )
                ->get()
                ->groupBy('chuchemon_id');
        }

        $chuchemons = $allChuchemons->map(function ($chuchemon) use ($capturedCounts, $infectionMap, $user) {
            $count = (int) ($capturedCounts[$chuchemon->id] ?? 0);
            $captured = $user ? $count > 0 : null;
            $activeInfections = collect($infectionMap->get($chuchemon->id, []))
                ->map(function ($infection) {
                    return [
                        'id' => $infection->malaltia_id,
                        'name' => $infection->name,
                        'type' => $infection->type,
                        'severity' => $infection->severity,
                        'infection_percentage' => $infection->infection_percentage,
                    ];
                })->values();
            $cannotEat = $activeInfections->contains(function ($infection) {
                return self::normalizeMalaltiaName($infection['name'] ?? null) === 'atracon';
            });

            return [
                'id' => $chuchemon->id,
                'name' => $chuchemon->name,
                'element' => $chuchemon->element,
                'mida' => $chuchemon->mida,
                'image' => $chuchemon->image,
                'attack' => $chuchemon->attack ?? 50,
                'defense' => $chuchemon->defense ?? 50,
                'speed' => $chuchemon->speed ?? 50,
                'captured' => $captured,
                'count' => $count,
                'active_infections' => $user ? $activeInfections->all() : [],
                'has_active_infections' => $user ? $activeInfections->isNotEmpty() : false,
                'cannot_eat' => $user ? $cannotEat : false,
                'cannot_eat_reason' => $user && $cannotEat ? 'Atracón activo: este Xuxemon no puede comer más Xuxes por ahora.' : null,
                'created_at' => $chuchemon->created_at,
                'updated_at' => $chuchemon->updated_at,
            ];
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
                ->select('chuchemons.*', 'user_chuchemons.count')
                ->get();

            $infectionMap = DB::table('user_infections')
                ->join('malalties', 'user_infections.malaltia_id', '=', 'malalties.id')
                ->where('user_infections.user_id', $user->id)
                ->where('user_infections.is_active', true)
                ->whereIn('user_infections.chuchemon_id', $userChuchemons->pluck('id')->all())
                ->select(
                    'user_infections.chuchemon_id',
                    'user_infections.infection_percentage',
                    'malalties.id as malaltia_id',
                    'malalties.name',
                    'malalties.type',
                    'malalties.severity'
                )
                ->get()
                ->groupBy('chuchemon_id');

            $userChuchemons = $userChuchemons->map(function ($chuchemon) use ($infectionMap) {
                    $activeInfections = collect($infectionMap->get($chuchemon->id, []))
                        ->map(function ($infection) {
                            return [
                                'id' => $infection->malaltia_id,
                                'name' => $infection->name,
                                'type' => $infection->type,
                                'severity' => $infection->severity,
                                'infection_percentage' => $infection->infection_percentage,
                            ];
                        })->values();

                    $cannotEat = $activeInfections->contains(function ($infection) {
                        return self::normalizeMalaltiaName($infection['name'] ?? null) === 'atracon';
                    });

                    return [
                        'id' => $chuchemon->id,
                        'name' => $chuchemon->name,
                        'element' => $chuchemon->element,
                        'mida' => $chuchemon->mida,
                        'image' => $chuchemon->image,
                        'attack' => $chuchemon->attack ?? 50,
                        'defense' => $chuchemon->defense ?? 50,
                        'speed' => $chuchemon->speed ?? 50,
                        'count' => $chuchemon->count ?? 1,
                        'captured' => true,
                        'active_infections' => $activeInfections->all(),
                        'has_active_infections' => $activeInfections->isNotEmpty(),
                        'cannot_eat' => $cannotEat,
                        'cannot_eat_reason' => $cannotEat ? 'Atracón activo: este Xuxemon no puede comer más Xuxes por ahora.' : null,
                        'created_at' => $chuchemon->created_at,
                        'updated_at' => $chuchemon->updated_at,
                    ];
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
                    'count'      => 1,
                    'max_hp'     => $maxHp,
                    'current_hp' => $maxHp,
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
                            ->first();
                        $maxHp  = $uc->max_hp    ?? LevelingController::computeMaxHp($c->defense ?? 50, $uc->level ?? 1, $uc->current_mida ?? 'Petit');
                        $currHp = $uc->current_hp ?? $maxHp;
                        $xpForNext = $uc->experience_for_next_level ?? 150;
                        $xp        = $uc->experience ?? 0;
                        $teamData[] = [
                            'id'                        => $c->id,
                            'name'                      => $c->name,
                            'element'                   => $c->element,
                            'mida'                      => $c->mida,
                            'image'                     => $c->image,
                            'attack'                    => $c->attack ?? 50,
                            'defense'                   => $c->defense ?? 50,
                            'speed'                     => $c->speed ?? 50,
                            'current_mida'              => $uc->current_mida ?? 'Petit',
                            'level'                     => $uc->level ?? 1,
                            'current_hp'                => $currHp,
                            'max_hp'                    => $maxHp,
                            'hp_percent'                => $maxHp > 0 ? round(($currHp / $maxHp) * 100, 1) : 100,
                            'experience'                => $xp,
                            'experience_for_next_level' => $xpForNext,
                            'xp_percent'                => $xpForNext > 0 ? round(($xp / $xpForNext) * 100, 1) : 0,
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

            // Determine next size
            if ($currentMida === 'Petit') {
                $nextMida = 'Mitjà';
            } elseif ($currentMida === 'Mitjà') {
                $nextMida = 'Gran';
            } elseif ($currentMida === 'Gran') {
                return response()->json(['message' => 'Tu Xuxemon ya está en su máxima evolución'], 400);
            }

            // Update the pivot table with new mida
            DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->update([
                    'current_mida'   => $nextMida,
                    'evolution_count' => DB::raw('evolution_count + 1'),
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

            // Get updated data
            $updated = $user->capturedChuchemsWithEvolution()
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            return response()->json([
                'message' => "Tu {$userChuchemon->name} ha evolucionado a {$nextMida}!",
                'chuchemon' => [
                    'id' => $updated->id,
                    'name' => $updated->name,
                    'element' => $updated->element,
                    'mida' => $updated->mida,
                    'image' => $updated->image,
                    'current_mida' => $updated->pivot->current_mida,
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


