<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class LevelingController extends Controller
{
    /**
     * Obtiene el nivel y experiencia de un chuchemon del usuario
     */
    public function getChuchemonLevel(int $chuchemonId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $userChuchemon = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$userChuchemon) {
                return response()->json(['message' => 'Chuchemon no encontrado en tu colección'], 404);
            }

            return response()->json([
                'level' => $userChuchemon->level,
                'experience' => $userChuchemon->experience,
                'experience_for_next_level' => $userChuchemon->experience_for_next_level,
                'experience_progress' => round(($userChuchemon->experience / $userChuchemon->experience_for_next_level) * 100, 2),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Agrega experiencia a un chuchemon y verifica si sube de nivel
     */
    public function addExperience(int $chuchemonId, int $experienceAmount): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $userChuchemon = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$userChuchemon) {
                return response()->json(['message' => 'Chuchemon no encontrado en tu colección'], 404);
            }

            $newExperience = $userChuchemon->experience + $experienceAmount;
            $level = $userChuchemon->level;
            $experienceForNextLevel = $userChuchemon->experience_for_next_level;
            $leveledUp = false;

            // Verifica si sube de nivel
            while ($newExperience >= $experienceForNextLevel) {
                $newExperience -= $experienceForNextLevel;
                $level++;
                $experienceForNextLevel = 100 + ($level * 50); // Fórmula para XP necesario
                $leveledUp = true;
            }

            // Actualizar el chuchemon
            DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->update([
                    'experience' => $newExperience,
                    'level' => $level,
                    'experience_for_next_level' => $experienceForNextLevel,
                ]);

            return response()->json([
                'message' => $leveledUp ? 'Chuchemon subió de nivel!' : 'Experiencia añadida',
                'level' => $level,
                'experience' => $newExperience,
                'experience_for_next_level' => $experienceForNextLevel,
                'level_up' => $leveledUp,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene todos los chuchemons del usuario con sus niveles
     */
    public function getAllChuchemonsWithLevels(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $chuchemons = DB::table('user_chuchemons')
                ->join('chuchemons', 'user_chuchemons.chuchemon_id', '=', 'chuchemons.id')
                ->where('user_chuchemons.user_id', $user->id)
                ->select(
                    'chuchemons.id',
                    'chuchemons.name',
                    'chuchemons.element',
                    'chuchemons.mida',
                    'chuchemons.image',
                    'user_chuchemons.level',
                    'user_chuchemons.experience',
                    'user_chuchemons.experience_for_next_level',
                    'user_chuchemons.count',
                )
                ->get()
                ->map(function ($chuchemon) {
                    $chuchemon->experience_progress = round(($chuchemon->experience / $chuchemon->experience_for_next_level) * 100, 2);
                    return $chuchemon;
                });

            return response()->json($chuchemons, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
