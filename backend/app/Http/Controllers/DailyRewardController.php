<?php

namespace App\Http\Controllers;

use App\Models\DailyReward;
use App\Models\Chuchemon;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class DailyRewardController extends Controller
{
    /**
     * Obtiene los daily rewards disponibles para el usuario
     */
    public function getDailyRewards(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            // Obtener los rewards xux y chuchemon
            $xuxReward = DailyReward::where('user_id', $user->id)
                ->where('reward_type', 'xux')
                ->with('item')
                ->first();

            $chuchemonReward = DailyReward::where('user_id', $user->id)
                ->where('reward_type', 'chuchemon')
                ->with('chuchemon')
                ->first();

            // Si no existen, crearlos
            if (!$xuxReward) {
                $xuxReward = $this->createDailyXuxReward($user->id);
            }
            if (!$chuchemonReward) {
                $chuchemonReward = $this->createDailyChuchemonReward($user->id);
            }

            return response()->json([
                'xux' => $xuxReward,
                'chuchemon' => $chuchemonReward,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reclama el daily reward de xuxes
     */
    public function claimXuxReward(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $reward = DailyReward::where('user_id', $user->id)
                ->where('reward_type', 'xux')
                ->first();

            if (!$reward) {
                $reward = $this->createDailyXuxReward($user->id);
            }

            // Verificar si ya fue reclamado hoy
            if ($reward->claimed_at && $reward->claimed_at->isToday()) {
                return response()->json(['message' => 'El reward de xuxes ya fue reclamado hoy'], 400);
            }

            // Verificar si está disponible
            if ($reward->next_available_at > now()) {
                return response()->json([
                    'message' => 'El reward no está disponible aún',
                    'available_at' => $reward->next_available_at,
                ], 400);
            }

            // Agregar los xuxes a la mochila
            DB::table('mochila_xuxes')->updateOrCreate(
                ['user_id' => $user->id, 'item_id' => $reward->item_id],
                ['quantity' => DB::raw('quantity + ' . $reward->quantity)]
            );

            // Actualizar el reward
            $reward->update([
                'claimed_at' => now(),
                'next_available_at' => now()->addHours(24)->setHour(8)->setMinute(0)->setSecond(0),
            ]);

            return response()->json([
                'message' => 'Reward de xuxes reclamado exitosamente',
                'quantity' => $reward->quantity,
                'next_available_at' => $reward->next_available_at,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reclama el daily reward de chuchemon
     */
    public function claimChuchemonReward(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $reward = DailyReward::where('user_id', $user->id)
                ->where('reward_type', 'chuchemon')
                ->with('chuchemon')
                ->first();

            if (!$reward) {
                $reward = $this->createDailyChuchemonReward($user->id);
            }

            // Verificar si ya fue reclamado hoy
            if ($reward->claimed_at && $reward->claimed_at->isToday()) {
                return response()->json(['message' => 'El reward de chuchemon ya fue reclamado hoy'], 400);
            }

            // Verificar si está disponible
            if ($reward->next_available_at > now()) {
                return response()->json([
                    'message' => 'El reward no está disponible aún',
                    'available_at' => $reward->next_available_at,
                ], 400);
            }

            // Capturar el chuchemon
            DB::table('user_chuchemons')->updateOrCreate(
                ['user_id' => $user->id, 'chuchemon_id' => $reward->chuchemon_id],
                ['count' => DB::raw('COALESCE(count, 0) + 1')]
            );

            // Actualizar el reward
            $reward->update([
                'claimed_at' => now(),
                'next_available_at' => now()->addHours(24)->setHour(8)->setMinute(0)->setSecond(0),
            ]);

            return response()->json([
                'message' => 'Reward de chuchemon reclamado exitosamente',
                'chuchemon' => $reward->chuchemon,
                'next_available_at' => $reward->next_available_at,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Crea un daily reward de xuxes
     */
    private function createDailyXuxReward($userId): DailyReward
    {
        // Obtener un item aleatorio de tipo 'xux' (Xocolatina, Xal de fruits, etc.)
        $item = Item::where('type', 'vaccine')->inRandomOrder()->first();
        if (!$item) {
            $item = Item::first(); // Fallback al primer item
        }

        $nextAvailable = now()->addHours(24)->setHour(8)->setMinute(0)->setSecond(0);
        if (now()->hour >= 8) {
            $nextAvailable = $nextAvailable->addDay();
        }

        return DailyReward::create([
            'user_id' => $userId,
            'reward_type' => 'xux',
            'item_id' => $item->id,
            'quantity' => 10,
            'next_available_at' => $nextAvailable,
        ]);
    }

    /**
     * Crea un daily reward de chuchemon
     */
    private function createDailyChuchemonReward($userId): DailyReward
    {
        // Obtener un chuchemon aleatorio
        $chuchemon = Chuchemon::inRandomOrder()->first();

        $nextAvailable = now()->addHours(24)->setHour(8)->setMinute(0)->setSecond(0);
        if (now()->hour >= 8) {
            $nextAvailable = $nextAvailable->addDay();
        }

        return DailyReward::create([
            'user_id' => $userId,
            'reward_type' => 'chuchemon',
            'chuchemon_id' => $chuchemon->id,
            'next_available_at' => $nextAvailable,
        ]);
    }
}
