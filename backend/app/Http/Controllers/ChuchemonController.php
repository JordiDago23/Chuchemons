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

        $allChuchemons = Chuchemon::all();
        
        // Get all captured chuchemons for this user (if authenticated)
        $capturedIds = [];
        $capturedCounts = [];
        if ($user) {
            $userChuchemons = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->get();
            foreach ($userChuchemons as $uc) {
                $capturedIds[] = $uc->chuchemon_id;
                $capturedCounts[$uc->chuchemon_id] = $uc->count;
            }
        }

        $chuchemons = $allChuchemons->map(function ($chuchemon) use ($capturedIds, $capturedCounts, $user) {
            $captured = null;
            $count = 0;
            
            if ($user) {
                $captured = in_array($chuchemon->id, $capturedIds);
                $count = $capturedCounts[$chuchemon->id] ?? 0;
            }
            
            return [
                'id' => $chuchemon->id,
                'name' => $chuchemon->name,
                'element' => $chuchemon->element,
                'mida' => $chuchemon->mida,
                'image' => $chuchemon->image,
                'captured' => $captured,
                'count' => $count,
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
                ->get()
                ->map(function ($chuchemon) {
                    return [
                        'id' => $chuchemon->id,
                        'name' => $chuchemon->name,
                        'element' => $chuchemon->element,
                        'mida' => $chuchemon->mida,
                        'image' => $chuchemon->image,
                        'count' => $chuchemon->count ?? 1,
                        'captured' => true,
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
                // Agregar nuevo
                $user->capturedChuchemons()->attach($id, ['count' => 1]);
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

            // Obtener los datos de los chuchemons
            $teamData = [];
            
            if ($team->chuchemon_1_id) {
                $teamData[] = Chuchemon::find($team->chuchemon_1_id);
            }
            if ($team->chuchemon_2_id) {
                $teamData[] = Chuchemon::find($team->chuchemon_2_id);
            }
            if ($team->chuchemon_3_id) {
                $teamData[] = Chuchemon::find($team->chuchemon_3_id);
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
            'element' => 'required|in:Tierra,Aire,Agua',
            'mida'    => 'required|in:Petit,Mitjà,Gran',
            'image'   => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chuchemon = Chuchemon::create($request->only(['name', 'element', 'mida', 'image']));

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
            'element' => 'sometimes|in:Tierra,Aire,Agua',
            'mida'    => 'sometimes|in:Petit,Mitjà,Gran',
            'image'   => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chuchemon->update($request->only(['name', 'element', 'mida', 'image']));

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
    public function evolve(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $validator = Validator::make($request->all(), [
                'chuchemon_id' => 'required|exists:chuchemons,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $chuchemonId = $request->chuchemon_id;

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

            // Update the pivot table
            DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->update([
                    'current_mida' => $nextMida,
                    'evolution_count' => DB::raw('evolution_count + 1'),
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


