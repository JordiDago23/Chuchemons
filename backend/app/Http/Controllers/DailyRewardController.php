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

    private function nextAvailableAt(string $settingKey, string $defaultHour, bool $forceNextDay = false): Carbon
    {
        $hourValue = (string) GameSetting::getValue($settingKey, $defaultHour);
        [$hour, $minute] = array_pad(explode(':', $hourValue), 2, '00');

        $currentTime = now();
        $nextAvailable = $currentTime->copy()->setHour((int) $hour)->setMinute((int) $minute)->setSecond(0);
        
        // Si forceNextDay = true (después de reclamar), SIEMPRE programar para mañana
        if ($forceNextDay) {
            $nextAvailable->addDay();
        } else {
            // Si ya pasó la hora de hoy, programar para mañana
            if ($currentTime->gte($nextAvailable)) {
                $nextAvailable->addDay();
            }
        }

        Log::info('Calculated next_available_at', [
            'setting_key' => $settingKey,
            'hour_value' => $hourValue,
            'parsed_hour' => $hour,
            'parsed_minute' => $minute,
            'current_time' => $currentTime->toDateTimeString(),
            'next_available' => $nextAvailable->toDateTimeString(),
            'force_next_day' => $forceNextDay,
        ]);

        return $nextAvailable;
    }

    /**
     * Recalcula next_available_at si la hora configurada ha cambiado
     */
    private function recalculateNextAvailableIfNeeded(?DailyReward $reward, string $settingKey, string $defaultHour): void
    {
        if (!$reward) {
            return;
        }

        $currentTime = now();
        
        // IMPORTANTE: Si la recompensa ya está disponible (next_available_at <= now) 
        // y NO fue reclamada hoy, NO recalcular (dejarla disponible)
        $isAlreadyAvailable = $reward->next_available_at <= $currentTime;
        $wasClaimedToday = $reward->claimed_at && $reward->claimed_at->isToday();
        
        if ($isAlreadyAvailable && !$wasClaimedToday) {
            // La recompensa está disponible y no ha sido reclamada hoy
            // NO recalcular para evitar posponerla incorrectamente
            Log::info('Skipping recalculation - reward is available and not claimed today', [
                'reward_type' => $reward->reward_type,
                'user_id' => $reward->user_id,
                'next_available_at' => $reward->next_available_at->toDateTimeString(),
                'current_time' => $currentTime->toDateTimeString(),
            ]);
            return;
        }

        // Obtener la hora configurada actualmente
        $configuredHour = (string) GameSetting::getValue($settingKey, $defaultHour);
        [$configHour, $configMinute] = array_pad(explode(':', $configuredHour), 2, '00');

        // Obtener la hora de next_available_at
        $nextAvailableHour = $reward->next_available_at->format('H');
        $nextAvailableMinute = $reward->next_available_at->format('i');

        // Si las horas coinciden, no hay nada que hacer
        if ((int)$nextAvailableHour === (int)$configHour && (int)$nextAvailableMinute === (int)$configMinute) {
            return;
        }

        Log::info('Detected configuration change, recalculating next_available_at', [
            'reward_type' => $reward->reward_type,
            'user_id' => $reward->user_id,
            'old_hour' => $nextAvailableHour . ':' . $nextAvailableMinute,
            'new_hour' => $configHour . ':' . $configMinute,
            'claimed_at' => $reward->claimed_at ? $reward->claimed_at->toDateTimeString() : 'null',
            'was_claimed_today' => $wasClaimedToday,
        ]);

        // Recalcular next_available_at
        // Si fue reclamado hoy, mantener que sea para mañana
        $newNextAvailable = $this->nextAvailableAt($settingKey, $defaultHour, $wasClaimedToday);

        $reward->update([
            'next_available_at' => $newNextAvailable,
        ]);

        $reward->refresh();

        Log::info('Recalculated next_available_at', [
            'reward_type' => $reward->reward_type,
            'user_id' => $reward->user_id,
            'new_next_available_at' => $newNextAvailable->toDateTimeString(),
        ]);
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

            // AUTO-CORRECCIÓN: Detectar recompensas afectadas por bug de reprogramación
            // Si next_available_at está en el futuro Y el usuario NO reclamó hoy,
            // pero la hora configurada de hoy ya pasó, resetear a now() para permitir reclamo
            $currentTime = now();
            
            if ($xuxReward) {
                $wasClaimedToday = $xuxReward->claimed_at && $xuxReward->claimed_at->isToday();
                $isFuture = $xuxReward->next_available_at->isFuture();
                
                if ($isFuture && !$wasClaimedToday) {
                    // La recompensa está programada para el futuro pero el usuario no reclamó hoy
                    // Verificar si la hora configurada de HOY ya pasó
                    $configuredHour = (string) GameSetting::getValue('daily_xux_hour', '08:00');
                    [$hour, $minute] = array_pad(explode(':', $configuredHour), 2, '00');
                    $todayAtConfiguredHour = $currentTime->copy()->setHour((int) $hour)->setMinute((int) $minute)->setSecond(0);
                    
                    if ($currentTime->gte($todayAtConfiguredHour)) {
                        // La hora de hoy ya pasó, debería poder reclamar ahora
                        Log::warning('Auto-correcting xux reward affected by bug', [
                            'user_id' => $user->id,
                            'old_next_available_at' => $xuxReward->next_available_at->toDateTimeString(),
                            'current_time' => $currentTime->toDateTimeString(),
                            'resetting_to' => $currentTime->toDateTimeString(),
                        ]);
                        
                        $xuxReward->update([
                            'next_available_at' => $currentTime,
                        ]);
                        $xuxReward->refresh();
                    }
                }
            }
            
            if ($chuchemonReward) {
                $wasClaimedToday = $chuchemonReward->claimed_at && $chuchemonReward->claimed_at->isToday();
                $isFuture = $chuchemonReward->next_available_at->isFuture();
                
                if ($isFuture && !$wasClaimedToday) {
                    $configuredHour = (string) GameSetting::getValue('daily_chuchemon_hour', '08:00');
                    [$hour, $minute] = array_pad(explode(':', $configuredHour), 2, '00');
                    $todayAtConfiguredHour = $currentTime->copy()->setHour((int) $hour)->setMinute((int) $minute)->setSecond(0);
                    
                    if ($currentTime->gte($todayAtConfiguredHour)) {
                        Log::warning('Auto-correcting chuchemon reward affected by bug', [
                            'user_id' => $user->id,
                            'old_next_available_at' => $chuchemonReward->next_available_at->toDateTimeString(),
                            'current_time' => $currentTime->toDateTimeString(),
                            'resetting_to' => $currentTime->toDateTimeString(),
                        ]);
                        
                        $chuchemonReward->update([
                            'next_available_at' => $currentTime,
                        ]);
                        $chuchemonReward->refresh();
                    }
                }
            }

            // Regenerar items_data SOLO cuando corresponda un nuevo día
            if ($xuxReward) {
                $currentTime = now();
                $hasItems = !empty($xuxReward->items_data);
                
                // Determinar si es un "nuevo día de recompensa"
                // Caso 1: items_data está vacío (primera vez o después de reclamar)
                // Caso 2: next_available_at ya pasó Y claimed_at es de un día diferente (no hoy)
                $shouldRegenerate = false;
                
                if (!$hasItems) {
                    // No hay tirada generada, generar una nueva
                    $shouldRegenerate = true;
                    $reason = 'no_items_data';
                } else {
                    // Ya hay una tirada generada, verificar si es de un día anterior
                    $isAvailable = $xuxReward->next_available_at <= $currentTime;
                    $wasClaimedToday = $xuxReward->claimed_at && $xuxReward->claimed_at->isToday();
                    
                    // Solo regenerar si está disponible Y NO fue reclamado hoy
                    // Esto significa que es un día nuevo (pasó 24h) y no se ha reclamado todavía hoy
                    if ($isAvailable && !$wasClaimedToday) {
                        // Verificar que claimed_at sea de un día anterior (no de hoy)
                        $claimedDate = $xuxReward->claimed_at ? $xuxReward->claimed_at->toDateString() : null;
                        $todayDate = $currentTime->toDateString();
                        
                        if ($claimedDate && $claimedDate !== $todayDate) {
                            // La última reclamación fue en un día diferente, regenerar
                            $shouldRegenerate = true;
                            $reason = 'new_day_after_claim';
                        } elseif (!$claimedDate) {
                            // Nunca se ha reclamado pero next_available_at ya pasó
                            // NO regenerar si ya hay items_data (tirada activa sin reclamar)
                            $shouldRegenerate = false;
                            $reason = 'active_unclaimed_roll';
                        }
                    }
                }
                
                Log::info('Checking if should regenerate xux items_data', [
                    'user_id' => $user->id,
                    'should_regenerate' => $shouldRegenerate,
                    'reason' => $reason ?? 'not_needed',
                    'has_items' => $hasItems,
                    'current_time' => $currentTime->toDateTimeString(),
                    'next_available_at' => $xuxReward->next_available_at->toDateTimeString(),
                    'claimed_at' => $xuxReward->claimed_at ? $xuxReward->claimed_at->toDateTimeString() : 'null',
                ]);
                
                if ($shouldRegenerate) {
                    $newQuantity = GameSetting::getInt('daily_xux_quantity', 10);
                    $newItemsData = $this->generateItemsDistribution($newQuantity);
                    
                    $xuxReward->update([
                        'quantity' => $newQuantity,
                        'items_data' => $newItemsData,
                    ]);
                    $xuxReward->refresh();
                    
                    Log::info('Generated new daily xux items distribution', [
                        'user_id' => $user->id,
                        'items_data' => $newItemsData,
                        'reason' => $reason ?? 'unknown',
                    ]);
                }
            }

            // Recalcular next_available_at si la hora configurada ha cambiado
            $this->recalculateNextAvailableIfNeeded($xuxReward, 'daily_xux_hour', '08:00');
            $this->recalculateNextAvailableIfNeeded($chuchemonReward, 'daily_chuchemon_hour', '08:00');

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

            // Obtener la distribución de items (DEBE existir - se genera solo en getDailyRewards)
            $itemsData = $reward->items_data;
            
            // Si no existe items_data, el usuario debe cargar primero la pantalla de recompensas
            if (empty($itemsData)) {
                return response()->json([
                    'message' => 'Recompensa no disponible. Por favor, recarga la página.',
                    'error' => 'items_data_not_generated'
                ], 400);
            }

            // Cargar los items completos desde la base de datos
            $itemIds = array_column($itemsData, 'item_id');
            $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');
            
            // Construir array de items con objetos completos
            $itemsDistribution = array_map(function($data) use ($items) {
                return [
                    'item' => $items[$data['item_id']],
                    'quantity' => $data['quantity']
                ];
            }, $itemsData);

            // Preparar array para validación de espacio
            $itemsToAdd = array_map(function($dist) {
                return [
                    'type' => 'item',
                    'item_id' => $dist['item']->id,
                    'quantity' => $dist['quantity']
                ];
            }, $itemsDistribution);
            
            // Validar espacio ANTES de añadir items (considerando slots parcialmente llenos)
            $spaceCheck = $this->canFitItems($user->id, $itemsToAdd);
            
            if (!$spaceCheck['can_fit']) {
                return response()->json([
                    'message' => 'Tu mochila está llena. Libera espacio antes de reclamar las Chuches.',
                    'free_spaces' => $spaceCheck['free_slots'],
                    'slots_needed' => $spaceCheck['slots_needed'],
                    'currently_used' => $spaceCheck['currently_used'],
                ], 400);
            }

            // Agregar todas las chuches a la mochila
            foreach ($itemsDistribution as $dist) {
                $itemRow = MochilaXux::where('user_id', $user->id)
                    ->where('item_id', $dist['item']->id)
                    ->whereNull('chuchemon_id')
                    ->whereNull('vaccine_id')
                    ->first();

                if ($itemRow) {
                    $itemRow->increment('quantity', $dist['quantity']);
                } else {
                    MochilaXux::create([
                        'user_id'  => $user->id,
                        'item_id'  => $dist['item']->id,
                        'quantity' => $dist['quantity'],
                    ]);
                }
            }

            // Actualizar el reward y LIMPIAR items_data (para que se regenere mañana)
            // Usar forceNextDay = true para garantizar que sea mañana
            $nextAvailable = $this->nextAvailableAt('daily_xux_hour', '08:00', true);
            
            Log::info('Claiming xux reward - setting next_available_at', [
                'user_id' => $user->id,
                'current_time' => now()->toDateTimeString(),
                'next_available_at' => $nextAvailable->toDateTimeString(),
            ]);
            
            $reward->update([
                'claimed_at' => now(),
                'next_available_at' => $nextAvailable,
                'items_data' => null, // Limpiar para que se genere nueva tirada mañana
            ]);
            $reward->refresh(); // Refrescar para obtener el valor correcto de la BD

            // Preparar respuesta con todos los items recibidos
            $itemsSummary = array_map(function($dist) {
                return [
                    'name' => $dist['item']->name,
                    'quantity' => $dist['quantity'],
                    'image' => $dist['item']->image
                ];
            }, $itemsDistribution);

            return response()->json([
                'message' => 'Reward reclamado exitosamente',
                'items' => $itemsSummary,
                'total_quantity' => array_sum(array_column($itemsSummary, 'quantity')),
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

            // Actualizar el reward (forceNextDay = true para garantizar que sea mañana)
            $nextAvailable = $this->nextAvailableAt('daily_chuchemon_hour', '08:00', true);
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
        $item = Item::where('type', '!=', 'no_apilable')->inRandomOrder()->first();
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

        $quantity = GameSetting::getInt('daily_xux_quantity', 10);
        
        // Generar distribución de items (2-3 tipos diferentes)
        $itemsData = $this->generateItemsDistribution($quantity);

        return DailyReward::create([
            'user_id' => $userId,
            'reward_type' => 'xux',
            'item_id' => $item->id, // Mantener para compatibilidad
            'quantity' => $quantity,
            'items_data' => $itemsData,
            'next_available_at' => now(),
        ]);
    }

    /**
     * Genera una distribución aleatoria de items (2-3 tipos diferentes)
     */
    private function generateItemsDistribution(int $totalQuantity): array
    {
        // Obtener todos los items apilables disponibles
        $availableItems = Item::where('type', '!=', 'no_apilable')->get();
        
        if ($availableItems->count() < 2) {
            // Fallback: si no hay suficientes items, devolver uno solo
            $item = $availableItems->first();
            return [
                [
                    'item_id' => $item->id,
                    'quantity' => $totalQuantity,
                ]
            ];
        }

        // Decidir cuántos tipos diferentes dar (2 o 3 aleatorio)
        $numTypes = rand(2, min(3, $availableItems->count()));
        
        // Seleccionar items aleatorios sin repetir
        $selectedItems = $availableItems->random($numTypes);
        
        // Distribuir la cantidad total aleatoriamente entre los items seleccionados
        $distribution = [];
        $remaining = $totalQuantity;
        
        foreach ($selectedItems as $index => $item) {
            if ($index === $numTypes - 1) {
                // Último item: dar todo lo que queda
                $quantity = $remaining;
            } else {
                // Items anteriores: dar entre 1 y lo que queda menos (numTypes - index - 1)
                $minQuantity = 1;
                $maxQuantity = $remaining - ($numTypes - $index - 1);
                $quantity = rand($minQuantity, $maxQuantity);
                $remaining -= $quantity;
            }
            
            $distribution[] = [
                'item_id' => $item->id,
                'quantity' => $quantity
            ];
        }

        return $distribution;
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
