<?php

namespace App\Http\Controllers;

use App\Models\UserInfection;
use App\Models\Malaltia;
use App\Models\MochilaXux;
use App\Models\Vaccine;
use App\Models\Chuchemon;
use App\Http\Controllers\LevelingController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class InfectionController extends Controller
{
    /**
     * Obtiene las infecciones activas del usuario
     */
    public function getActiveInfections(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $infections = $user->infections()
                ->where('is_active', true)
                ->with('chuchemon', 'malaltia')
                ->get();

            return response()->json($infections, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Infecta un chuchemon del usuario
     */
    public function infectChuchemon(int $chuchemonId, int $malaltiaId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            // Verificar que el usuario posee este chuchemon
            $userChuchemon = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$userChuchemon) {
                return response()->json(['message' => 'Chuchemon no encontrado en tu colección'], 404);
            }

            // Verificar que la malaltia existe
            $malaltia = Malaltia::find($malaltiaId);
            if (!$malaltia) {
                return response()->json(['message' => 'Malaltia no encontrada'], 404);
            }

            // Crear la infección
            $infection = UserInfection::create([
                'user_id' => $user->id,
                'chuchemon_id' => $chuchemonId,
                'malaltia_id' => $malaltiaId,
                'infection_percentage' => rand(10, 50),
                'infected_at' => now(),
            ]);

            return response()->json([
                'message' => 'El chuchemon ha sido infectado',
                'infection' => $infection,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cura una infección usando una vacuna
     */
    public function cureInfection(int $infectionId, int $vaccineId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $infection = UserInfection::where('id', $infectionId)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (!$infection) {
                return response()->json(['message' => 'Infección no encontrada'], 404);
            }

            $vaccine = Vaccine::find($vaccineId);
            if (!$vaccine) {
                return response()->json(['message' => 'Vacuna no encontrada'], 404);
            }

            // Insulina (name='Insulina') cures ALL diseases; others must match malaltia_id
            $isInsulina = strtolower(trim($vaccine->name)) === 'insulina';
            if (!$isInsulina && $vaccine->malaltia_id !== $infection->malaltia_id) {
                return response()->json(['message' => 'Esta vacuna no cura esta enfermedad'], 400);
            }

            // Check user has the vaccine in mochila
            $mochilaItem = MochilaXux::where('user_id', $user->id)
                ->where('vaccine_id', $vaccineId)
                ->where('quantity', '>', 0)
                ->first();

            if (!$mochilaItem) {
                return response()->json(['message' => 'No tienes esta vacuna en tu mochila'], 400);
            }

            // Consume 1 vaccine
            if ($mochilaItem->quantity > 1) {
                $mochilaItem->decrement('quantity');
            } else {
                $mochilaItem->delete();
            }

            // If Insulina → cure ALL active infections for this chuchemon
            if ($isInsulina) {
                $allInfections = UserInfection::where('user_id', $user->id)
                    ->where('chuchemon_id', $infection->chuchemon_id)
                    ->where('is_active', true)
                    ->get();

                foreach ($allInfections as $inf) {
                    $this->restoreMidaIfNeeded($inf);
                    $inf->update([
                        'is_active' => false,
                        'cured_at' => now(),
                        'infection_percentage' => 0,
                    ]);
                }
            } else {
                // Cure single infection
                $this->restoreMidaIfNeeded($infection);
                $infection->update([
                    'is_active' => false,
                    'cured_at' => now(),
                    'infection_percentage' => 0,
                ]);
            }

            return response()->json([
                'message' => 'Infección curada exitosamente',
                'infection' => $infection->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * If this infection is Sobredosis de sucre with a stored original_mida,
     * restore the chuchemon's size and recalculate HP.
     */
    private function restoreMidaIfNeeded(UserInfection $infection): void
    {
        if (!$infection->original_mida) {
            return;
        }

        $malaltia = Malaltia::find($infection->malaltia_id);
        $normalizedName = LevelingController::normalizeMalaltiaName($malaltia->name ?? '');
        if ($normalizedName !== 'sobredosis de sucre') {
            return;
        }

        $uc = DB::table('user_chuchemons')
            ->where('user_id', $infection->user_id)
            ->where('chuchemon_id', $infection->chuchemon_id)
            ->first();

        if (!$uc || $uc->current_mida === $infection->original_mida) {
            return;
        }

        $chuchemon = Chuchemon::find($infection->chuchemon_id);
        $baseDefense = $chuchemon->defense ?? 50;
        $newMaxHp = LevelingController::computeMaxHp($baseDefense, $uc->level, $infection->original_mida);
        $newCurrentHp = min($uc->current_hp, $newMaxHp);

        DB::table('user_chuchemons')
            ->where('user_id', $infection->user_id)
            ->where('chuchemon_id', $infection->chuchemon_id)
            ->update([
                'current_mida' => $infection->original_mida,
                'max_hp'       => $newMaxHp,
                'current_hp'   => $newCurrentHp,
                'updated_at'   => now(),
            ]);
    }

    /**
     * Obtiene lista de malalties
     */
    public function getMalalties(): JsonResponse
    {
        try {
            $malalties = Malaltia::with('vaccines')->get();
            return response()->json($malalties, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene lista de vacunas
     */
    public function getVaccines(): JsonResponse
    {
        try {
            $vaccines = Vaccine::with('malaltia')->get();
            return response()->json($vaccines, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
