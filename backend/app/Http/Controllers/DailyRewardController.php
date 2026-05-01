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
    private const STACK_SIZE = 5;
    private const MAX_SPACES = 20;

    /**
     * Calcula cuántos slots ocupa un item de la mochila.
     * - Vacunas: NO apilables (1 vacuna = 1 slot)
     * - Items no_apilable: NO apilables (1 item = 1 slot)
     * - Xuxes y otros: Apilables (5 por slot)
     */
    private static function calculateItemSlots($item): int
    {
        // Vacunas: NO apilables
        if ($item->vaccine_id) {
            return $item->quantity;
        }
        
        // Items: verificar si es no_apilable
        if ($item->item_id && $item->item && $item->item->type === 'no_apilable') {
            return $item->quantity;
        }
        
        // Xuxes y items apilables: 5 por slot
        return (int) ceil($item->quantity / self::STACK_SIZE);
    }

    /**
     * Valida si hay espacio en la mochila para añadir items,
     * considerando que items apilables pueden ir a slots parcialmente llenos.
     * 
     * @param int $userId
     * @param array $itemsToAdd Ejemplo: [['type' => 'xux', 'item_id' => 1, 'quantity' => 7], ...]
     * @return array ['can_fit' => bool, 'free_slots' => int, 'slots_needed' => int, 'currently_used' => int]
     */
    private function canFitItems(int $userId, array $itemsToAdd): array
    {
        // Obtener todos los items actuales de la mochila (con relaciones para calcular slots correctamente)
        $currentItems = MochilaXux::with('item')
            ->where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->get();
        
        // Calcular slots actualmente ocupados usando el helper
        $currentlyUsedSlots = $currentItems->sum(fn($item) => self::calculateItemSlots($item));
        
        $freeSlots = self::MAX_SPACES - $currentlyUsedSlots;
        
        // Calcular cuántos slots nuevos se necesitarían
        $slotsNeeded = 0;
        
        foreach ($itemsToAdd as $newItem) {
            $quantity = $newItem['quantity'];
            
            // Buscar si ya existe un registro del mismo tipo
            $existingRow = null;
            foreach ($currentItems as $item) {
                // Items genéricos
                if ($newItem['type'] === 'item' && 
                    isset($newItem['item_id']) &&
                    $item->item_id === $newItem['item_id'] && 
                    !$item->chuchemon_id && 
                    !$item->vaccine_id) {
                    $existingRow = $item;
                    break;
                }
                
                // Xuxes from daily rewards use item_id
                if ($newItem['type'] === 'xux' && 
                    isset($newItem['item_id']) &&
                    $item->item_id === $newItem['item_id'] && 
                    !$item->chuchemon_id && 
                    !$item->vaccine_id) {
                    $existingRow = $item;
                    break;
                }
                
                // Xuxes from admin/chuchemon use chuchemon_id
                if ($newItem['type'] === 'xux' && 
                    isset($newItem['chuchemon_id']) &&
                    $item->chuchemon_id === $newItem['chuchemon_id'] && 
                    !$item->item_id && 
                    !$item->vaccine_id) {
                    $existingRow = $item;
                    break;
                }
                
                // Vaccines
                if ($newItem['type'] === 'vaccine' && 
                    isset($newItem['vaccine_id']) &&
                    $item->vaccine_id === $newItem['vaccine_id'] && 
                    !$item->item_id && 
                    !$item->chuchemon_id) {
                    $existingRow = $item;
                    break;
                }
            }
            
            // Determinar el stack size según el tipo de item
            // Vacunas NO son apilables (1 vacuna = 1 slot)
            // Xuxes e Items SÍ son apilables (5 por slot)
            $stackSize = ($newItem['type'] === 'vaccine') ? 1 : self::STACK_SIZE;
            
            if ($existingRow) {
                // Ya existe - calcular slots antes y después de añadir
                $currentSlots = (int) ceil($existingRow->quantity / $stackSize);
                $newTotalQuantity = $existingRow->quantity + $quantity;
                $newSlots = (int) ceil($newTotalQuantity / $stackSize);
                
                // Solo necesitamos la diferencia de slots
                $slotsNeeded += ($newSlots - $currentSlots);
            } else {
                // No existe - necesita crear nuevos slots
                $slotsNeeded += (int) ceil($quantity / $stackSize);
            }
        }
        
        Log::info('Mochila space validation', [
            'user_id' => $userId,
            'currently_used' => $currentlyUsedSlots,
            'free_slots' => $freeSlots,
            'slots_needed' => $slotsNeeded,
            'can_fit' => $slotsNeeded <= $freeSlots,
        ]);
        
        return [
            'can_fit' => $slotsNeeded <= $freeSlots,
            'free_slots' => $freeSlots,
            'slots_needed' => $slotsNeeded,
            'currently_used' => $currentlyUsedSlots,
        ];
    }

    private function nextAvailableAt(string $settingKey, string $defaultHour): Carbon
    {
        $hourValue = (string) GameSetting::getValue($settingKey, $defaultHour);
        [$hour, $minute] = array_pad(explode(':', $hourValue), 2, '00');

        $currentTime = now();
        $nextAvailable = $currentTime->copy()->setHour((int) $hour)->setMinute((int) $minute)->setSecond(0);
        
        // Si ya pasó la hora de hoy, programar para mañana
        if ($currentTime->gte($nextAvailable)) {
            $nextAvailable->addDay();
        }

        Log::info('Calculated next_available_at', [
            'setting_key' => $settingKey,
            'hour_value' => $hourValue,
            'parsed_hour' => $hour,
            'parsed_minute' => $minute,
            'current_time' => $currentTime->toDateTimeString(),
            'next_available' => $nextAvailable->toDateTimeString(),
        ]);

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

            // Incluir configuración actual de horarios y cantidades
            $config = [
                'daily_xux_quantity' => GameSetting::getInt('daily_xux_quantity', 10),
                'daily_xux_hour' => GameSetting::getValue('daily_xux_hour', '08:00'),
                'daily_chuchemon_hour' => GameSetting::getValue('daily_chuchemon_hour', '08:00'),
            ];

            return response()->json([
                'xux' => $xuxReward,
                'chuchemon' => $chuchemonReward,
                'config' => $config,
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

            // Dar solo chuches aleatorias (sin vacunas)
            $totalItems = $reward->quantity; // 10 o lo que esté configurado
            
            // Obtener un item aleatorio (de tipo apilable)
            $randomItem = Item::where('type', '!=', 'no_apilable')->inRandomOrder()->first();
            if (!$randomItem) {
                return response()->json(['message' => 'No hay items disponibles'], 500);
            }

            // Validar espacio ANTES de añadir items (considerando slots parcialmente llenos)
            $itemsToAdd = [
                ['type' => 'item', 'item_id' => $randomItem->id, 'quantity' => $totalItems],
            ];
            
            $spaceCheck = $this->canFitItems($user->id, $itemsToAdd);
            
            if (!$spaceCheck['can_fit']) {
                return response()->json([
                    'message' => 'Tu mochila está llena. Libera espacio antes de reclamar las Chuches.',
                    'free_spaces' => $spaceCheck['free_slots'],
                    'slots_needed' => $spaceCheck['slots_needed'],
                    'currently_used' => $spaceCheck['currently_used'],
                ], 400);
            }

            // Agregar las chuches a la mochila
            $itemRow = MochilaXux::where('user_id', $user->id)
                ->where('item_id', $randomItem->id)
                ->whereNull('chuchemon_id')
                ->whereNull('vaccine_id')
                ->first();

            if ($itemRow) {
                $itemRow->increment('quantity', $totalItems);
            } else {
                MochilaXux::create([
                    'user_id'  => $user->id,
                    'item_id'  => $randomItem->id,
                    'quantity' => $totalItems,
                ]);
            }

            // Actualizar el reward
            $nextAvailable = $this->nextAvailableAt('daily_xux_hour', '08:00');
            $reward->update([
                'claimed_at' => now(),
                'next_available_at' => $nextAvailable,
            ]);
            $reward->refresh(); // Refrescar para obtener el valor correcto de la BD

            return response()->json([
                'message' => 'Reward reclamado exitosamente',
                'item_name' => $randomItem->name,
                'quantity' => $totalItems,
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

            Log::info('Claiming Chuchemon reward', [
                'user_id' => $user->id,
                'chuchemon_id' => $reward->chuchemon_id,
                'existing' => $existing ? 'yes' : 'no',
            ]);

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
            $nextAvailable = $this->nextAvailableAt('daily_chuchemon_hour', '08:00');
            $reward->update([
                'claimed_at' => now(),
                'next_available_at' => $nextAvailable,
            ]);
            $reward->refresh(); // Refrescar para obtener el valor correcto de la BD

            Log::info('Chuchemon reward claimed successfully', [
                'user_id' => $user->id,
                'chuchemon_id' => $reward->chuchemon_id,
                'next_available_at' => $reward->next_available_at,
            ]);

            return response()->json([
                'message' => 'Reward de chuchemon reclamado exitosamente',
                'chuchemon' => $reward->chuchemon,
                'chuchemon_id' => $reward->chuchemon_id,
                'was_new' => !$existing,
                'next_available_at' => $reward->next_available_at,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error claiming daily chuchemon reward', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
