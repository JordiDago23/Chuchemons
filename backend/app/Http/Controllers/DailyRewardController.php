<?php

namespace App\Http\Controllers;

use App\Models\DailyReward;
use App\Models\Chuchemon;
use App\Models\GameSetting;
use App\Models\Item;
use App\Models\MochilaXux;
use App\Models\Vaccine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class DailyRewardController extends Controller
{
    private function nextAvailableAt(string $settingKey, string $defaultHour): Carbon
    {
        $hourValue = (string) GameSetting::getValue($settingKey, $defaultHour);
        [$hour, $minute] = array_pad(explode(':', $hourValue), 2, '00');

        $nextAvailable = now()->copy()->setHour((int) $hour)->setMinute((int) $minute)->setSecond(0);
        if (now()->gte($nextAvailable)) {
            $nextAvailable->addDay();
        }

        return $nextAvailable;
    }

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
            Log::error('Error loading daily rewards', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
            ]);
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

            if (!$reward->item_id) {
                return response()->json(['message' => 'La recompensa diaria de Xux no está configurada correctamente.'], 409);
            }

            // Repartir 10 items: cantidad aleatoria de vacunas (1-3), el resto xuxes
            $totalItems = $reward->quantity; // 10
            $vaccineQty = rand(1, 3);
            $xuxQty = $totalItems - $vaccineQty;

            // Agregar los xuxes a la mochila
            $xuxRow = MochilaXux::where('user_id', $user->id)
                ->where('item_id', $reward->item_id)
                ->whereNull('chuchemon_id')
                ->whereNull('vaccine_id')
                ->first();

            if ($xuxRow) {
                $xuxRow->increment('quantity', $xuxQty);
            } else {
                MochilaXux::create([
                    'user_id'  => $user->id,
                    'item_id'  => $reward->item_id,
                    'quantity' => $xuxQty,
                ]);
            }

            // Agregar vacunas aleatorias a la mochila
            $vaccine = Vaccine::inRandomOrder()->first();
            $vaccineName = null;
            if ($vaccine) {
                $vaccineRow = MochilaXux::where('user_id', $user->id)
                    ->where('vaccine_id', $vaccine->id)
                    ->whereNull('item_id')
                    ->whereNull('chuchemon_id')
                    ->first();

                if ($vaccineRow) {
                    $vaccineRow->increment('quantity', $vaccineQty);
                } else {
                    MochilaXux::create([
                        'user_id'    => $user->id,
                        'vaccine_id' => $vaccine->id,
                        'quantity'   => $vaccineQty,
                    ]);
                }
                $vaccineName = $vaccine->name;
            }

            // Actualizar el reward
            $reward->update([
                'claimed_at' => now(),
                'next_available_at' => $this->nextAvailableAt('daily_xux_hour', '06:00'),
            ]);

            return response()->json([
                'message' => 'Reward reclamado exitosamente',
                'xux_quantity'    => $xuxQty,
                'vaccine_quantity' => $vaccineQty,
                'vaccine'  => $vaccineName,
                'next_available_at' => $reward->next_available_at,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error claiming daily xux reward', [
                'message' => $e->getMessage(),
            ]);
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

            if (!$reward->chuchemon_id) {
                return response()->json(['message' => 'La recompensa diaria de Xuxemon no está configurada correctamente.'], 409);
            }

            // Capturar el chuchemon
            $existing = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $reward->chuchemon_id)
                ->first();

            if ($existing) {
                DB::table('user_chuchemons')
                    ->where('id', $existing->id)
                    ->increment('count');
            } else {
                $rewardChuchemon = Chuchemon::find($reward->chuchemon_id);
                $maxHp = $rewardChuchemon
                    ? \App\Http\Controllers\LevelingController::computeMaxHp($rewardChuchemon->defense ?? 50, 1, 'Petit')
                    : 105;

                DB::table('user_chuchemons')->insert([
                    'user_id'                    => $user->id,
                    'chuchemon_id'               => $reward->chuchemon_id,
                    'count'                      => 1,
                    'current_mida'               => 'Petit',
                    'level'                      => 1,
                    'experience'                 => 0,
                    'experience_for_next_level'  => \App\Http\Controllers\LevelingController::experienceForMida('Petit'),
                    'max_hp'                     => $maxHp,
                    'current_hp'                 => $maxHp,
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ]);
            }

            // Actualizar el reward
            $reward->update([
                'claimed_at' => now(),
                'next_available_at' => $this->nextAvailableAt('daily_chuchemon_hour', '08:00'),
            ]);

            return response()->json([
                'message' => 'Reward de chuchemon reclamado exitosamente',
                'chuchemon' => $reward->chuchemon,
                'next_available_at' => $reward->next_available_at,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error claiming daily chuchemon reward', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Crea un daily reward de xuxes
     */
    private function createDailyXuxReward($userId): DailyReward
    {
        $item = Item::where('type', 'apilable')->inRandomOrder()->first();
        if (!$item) {
            $item = Item::query()->firstOrCreate(
                ['name' => Item::NAME_XUX_MADUIXA],
                [
                    'description' => 'Llaminadura de maduixa. Recupera 20 punts de salut',
                    'type' => 'apilable',
                    'image' => 'xux-maduixa.png',
                ]
            );
        }

        return DailyReward::create([
            'user_id' => $userId,
            'reward_type' => 'xux',
            'item_id' => $item->id,
            'quantity' => GameSetting::getInt('daily_xux_quantity', 10),
            'next_available_at' => now(),
        ]);
    }

    /**
     * Crea un daily reward de chuchemon
     */
    private function createDailyChuchemonReward($userId): DailyReward
    {
        // Obtener un chuchemon aleatorio de tamaño Petit
        $chuchemon = Chuchemon::where('mida', 'Petit')->inRandomOrder()->first();
        if (!$chuchemon) {
            $chuchemon = Chuchemon::inRandomOrder()->first();
        }

        if (!$chuchemon) {
            throw new \RuntimeException('No hay Chuchemons disponibles para generar la recompensa diaria.');
        }

        return DailyReward::create([
            'user_id' => $userId,
            'reward_type' => 'chuchemon',
            'chuchemon_id' => $chuchemon->id,
            'next_available_at' => now(),
        ]);
    }
}
