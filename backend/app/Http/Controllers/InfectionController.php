<?php

namespace App\Http\Controllers;

use App\Models\UserInfection;
use App\Models\Malaltia;
use App\Models\Vaccine;
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
                ->first();

            if (!$infection) {
                return response()->json(['message' => 'Infección no encontrada'], 404);
            }

            $vaccine = Vaccine::find($vaccineId);
            if (!$vaccine) {
                return response()->json(['message' => 'Vacuna no encontrada'], 404);
            }

            // Verificar que la vacuna cura esta malaltia
            if ($vaccine->malaltia_id !== $infection->malaltia_id) {
                return response()->json(['message' => 'Esta vacuna no cura esta malaltia'], 400);
            }

            // Actualizar la infección
            $infection->update([
                'is_active' => false,
                'cured_at' => now(),
                'infection_percentage' => 0,
            ]);

            return response()->json([
                'message' => 'Infección curada exitosamente',
                'infection' => $infection,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
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
