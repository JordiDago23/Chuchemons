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
     * - Items no_apilable: NO apilables (1 item = 1 slot)
     * - Todo lo demás (xuxes, vacunas): Apilables (5 por slot)
     */
    private static function calculateItemSlots($item): int
    {
        // Items no_apilable: 1 slot por unidad
        if ($item->item_id && $item->item && $item->item->type === 'no_apilable') {
            return $item->quantity;
        }

        // Todo lo demás (xuxes, vacunas): apilable 5 por slot
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
            
            // Todo apilable a 5 por slot (xuxes, vacunas, items apilables)
            $stackSize = self::STACK_SIZE;
            
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

            // Regenerar chuchemon_id si no está asignado, o si el asignado ya lo posee el usuario
            if ($chuchemonReward) {
                $claimedToday = $chuchemonReward->claimed_at && $chuchemonReward->claimed_at->isToday();

                if (!$claimedToday) {
                    $currentId   = $chuchemonReward->chuchemon_id;
                    $alreadyOwns = $currentId && DB::table('user_chuchemons')
                        ->where('user_id', $user->id)
                        ->where('chuchemon_id', $currentId)
                        ->where('count', '>', 0)
                        ->exists();

                    if (!$currentId || $alreadyOwns) {
                        $newC = $this->getRandomUnownedChuchemon($user->id);
                        if ($newC) {
                            $chuchemonReward->update(['chuchemon_id' => $newC->id]);
                            $chuchemonReward->refresh();
                        }
                    }
                }
            }

            // Incluir configuración actual de horarios y cantidades
            $config = [
                'daily_xux_quantity' => GameSetting::getInt('daily_xux_quantity', 10),
                'daily_xux_hour' => GameSetting::getValue('daily_xux_hour', '08:00'),
                'daily_chuchemon_hour' => GameSetting::getValue('daily_chuchemon_hour', '08:00'),
            ];

            // No revelar el chuchemon antes de reclamarlo
            $chuchemonData = $chuchemonReward ? [
                'id'               => $chuchemonReward->id,
                'reward_type'      => $chuchemonReward->reward_type,
                'claimed_at'       => $chuchemonReward->claimed_at,
                'next_available_at'=> $chuchemonReward->next_available_at,
            ] : null;

            return response()->json([
                'xux'      => $xuxReward,
                'chuchemon'=> $chuchemonData,
                'config'   => $config,
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

            // Cargar objetos completos (items y vacunas) según el tipo de cada entrada
            $itemIds    = array_column(array_filter($itemsData, fn($d) => ($d['type'] ?? 'item') === 'item'), 'item_id');
            $vaccineIds = array_column(array_filter($itemsData, fn($d) => ($d['type'] ?? '') === 'vaccine'), 'vaccine_id');

            $itemsMap    = $itemIds    ? Item::whereIn('id', $itemIds)->get()->keyBy('id')       : collect();
            $vaccinesMap = $vaccineIds ? Vaccine::whereIn('id', $vaccineIds)->get()->keyBy('id') : collect();

            // Construir distribución unificada con objeto completo + tipo
            $distribution = [];
            foreach ($itemsData as $data) {
                $type = $data['type'] ?? 'item';
                if ($type === 'vaccine') {
                    $obj = $vaccinesMap[$data['vaccine_id']] ?? null;
                } else {
                    $obj = $itemsMap[$data['item_id']] ?? null;
                }
                if ($obj) {
                    $distribution[] = ['type' => $type, 'obj' => $obj, 'quantity' => $data['quantity']];
                }
            }

            // Preparar array para validación de espacio
            $itemsToAdd = array_map(function($dist) {
                if ($dist['type'] === 'vaccine') {
                    return ['type' => 'vaccine', 'vaccine_id' => $dist['obj']->id, 'quantity' => $dist['quantity']];
                }
                return ['type' => 'item', 'item_id' => $dist['obj']->id, 'quantity' => $dist['quantity']];
            }, $distribution);

            // Validar espacio ANTES de añadir
            $spaceCheck = $this->canFitItems($user->id, $itemsToAdd);
            if (!$spaceCheck['can_fit']) {
                return response()->json([
                    'message' => 'Tu mochila está llena. Libera espacio antes de reclamar las Chuches.',
                    'free_spaces'     => $spaceCheck['free_slots'],
                    'slots_needed'    => $spaceCheck['slots_needed'],
                    'currently_used'  => $spaceCheck['currently_used'],
                ], 400);
            }

            // Agregar a la mochila
            foreach ($distribution as $dist) {
                if ($dist['type'] === 'vaccine') {
                    // Vacunas NO son apilables: crear registros individuales con quantity = 1
                    for ($i = 0; $i < $dist['quantity']; $i++) {
                        MochilaXux::create([
                            'user_id' => $user->id,
                            'vaccine_id' => $dist['obj']->id,
                            'quantity' => 1, // 1 vacuna por registro (NO apilable)
                        ]);
                    }
                } else {
                    $row = MochilaXux::where('user_id', $user->id)
                        ->where('item_id', $dist['obj']->id)
                        ->whereNull('chuchemon_id')->whereNull('vaccine_id')->first();
                    if ($row) {
                        $row->increment('quantity', $dist['quantity']);
                    } else {
                        MochilaXux::create(['user_id' => $user->id, 'item_id' => $dist['obj']->id, 'quantity' => $dist['quantity']]);
                    }
                }
            }

            $nextAvailable = $this->nextAvailableAt('daily_xux_hour', '08:00', true);
            $reward->update([
                'claimed_at'        => now(),
                'next_available_at' => $nextAvailable,
                'items_data'        => null,
            ]);
            $reward->refresh();

            // Preparar respuesta
            $itemsSummary = array_map(fn($dist) => [
                'name'     => $dist['obj']->name,
                'quantity' => $dist['quantity'],
                'type'     => $dist['type'],
            ], $distribution);

            return response()->json([
                'message'        => 'Reward reclamado exitosamente',
                'items'          => $itemsSummary,
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

            // Guardar el chuchemon reclamado antes de cambiar el registro
            $claimedChuchemon = Chuchemon::find($reward->chuchemon_id);

            // Pre-asignar el siguiente Chuchemon (ahora el reclamado ya es del usuario, así no se repite)
            $nextChuchemon = $this->getRandomUnownedChuchemon($user->id);
            $nextAvailable = $this->nextAvailableAt('daily_chuchemon_hour', '08:00', true);
            $reward->update([
                'claimed_at'        => now(),
                'next_available_at' => $nextAvailable,
                'chuchemon_id'      => $nextChuchemon ? $nextChuchemon->id : $reward->chuchemon_id,
            ]);
            $reward->refresh();

            Log::info('Chuchemon reward claimed successfully', [
                'user_id' => $user->id,
                'chuchemon_id' => $reward->chuchemon_id,
                'next_available_at' => $reward->next_available_at,
            ]);

            return response()->json([
                'message'           => 'Reward de chuchemon reclamado exitosamente',
                'chuchemon'         => $claimedChuchemon,
                'chuchemon_id'      => $claimedChuchemon?->id,
                'was_new'           => !$existing,
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

    private function getRandomUnownedChuchemon(int $userId): ?Chuchemon
    {
        $ownedIds = DB::table('user_chuchemons')
            ->where('user_id', $userId)
            ->where('count', '>', 0)
            ->pluck('chuchemon_id')
            ->toArray();

        // Preferir Petit no poseídos
        $q = Chuchemon::where('mida', 'Petit');
        if (!empty($ownedIds)) {
            $q->whereNotIn('id', $ownedIds);
        }
        $chuchemon = $q->inRandomOrder()->first();

        // Si todos los Petit ya son poseídos, cualquier talla no poseída
        if (!$chuchemon && !empty($ownedIds)) {
            $chuchemon = Chuchemon::whereNotIn('id', $ownedIds)->inRandomOrder()->first();
        }

        // Fallback: cualquier Petit aunque ya lo tenga
        if (!$chuchemon) {
            $chuchemon = Chuchemon::where('mida', 'Petit')->inRandomOrder()->first();
        }

        return $chuchemon;
    }

    public function debugReset(): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        DailyReward::where('user_id', $user->id)->update([
            'next_available_at' => now(),
            'claimed_at'        => null,
        ]);
        return response()->json(['message' => 'Recompensas reseteadas']);
    }

    /**
     * Crea un daily reward de xuxes
     */
    private function createDailyXuxReward($userId): DailyReward
    {
        $item = Item::where('type', 'apilable')->where('name', 'like', 'Xux de %')->inRandomOrder()->first();
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
     * Genera una distribución aleatoria de items (2-3 tipos diferentes).
     * Mezcla chuches (Xux de X), Xux Exp y vacunas.
     */
    private function generateItemsDistribution(int $totalQuantity): array
    {
        // Combinar todos los items apilables y vacunas en un pool unificado
        $itemPool = Item::where('type', 'apilable')->get()->map(fn($i) => [
            'type'    => 'item',
            'id'      => $i->id,
            'name'    => $i->name,
        ]);
        $vaccinePool = Vaccine::all()->map(fn($v) => [
            'type'    => 'vaccine',
            'id'      => $v->id,
            'name'    => $v->name,
        ]);
        $available = $itemPool->merge($vaccinePool)->values();

        if ($available->count() < 2) {
            $first = $available->first();
            $key = $first['type'] === 'vaccine' ? 'vaccine_id' : 'item_id';
            return [['type' => $first['type'], $key => $first['id'], 'quantity' => $totalQuantity]];
        }

        // 2 o 3 tipos, con probabilidad igual para cada elemento del pool
        $numTypes = rand(2, min(3, $available->count()));
        $selected = $available->random($numTypes)->values();

        // Las vacunas van primero con 1 unidad fija; los items reciben el resto
        $distribution = [];
        $remaining = $totalQuantity;

        // Primer paso: asignar 1 unidad a cada vacuna seleccionada
        $itemEntries = [];
        foreach ($selected as $entry) {
            if ($entry['type'] === 'vaccine') {
                $distribution[] = ['type' => 'vaccine', 'vaccine_id' => $entry['id'], 'quantity' => 1];
                $remaining -= 1;
            } else {
                $itemEntries[] = $entry;
            }
        }

        // Segundo paso: repartir las unidades restantes entre los items apilables
        $itemCount = count($itemEntries);
        foreach ($itemEntries as $idx => $entry) {
            if ($idx === $itemCount - 1 || $itemCount === 0) {
                $quantity = $remaining;
            } else {
                $quantity = rand(1, $remaining - ($itemCount - $idx - 1));
                $remaining -= $quantity;
            }
            $distribution[] = ['type' => 'item', 'item_id' => $entry['id'], 'quantity' => $quantity];
        }

        // Si todos los seleccionados eran vacunas, dar el resto a un item aleatorio
        if ($itemCount === 0 && $remaining > 0) {
            $fallback = Item::where('type', 'apilable')->inRandomOrder()->first();
            if ($fallback) {
                $distribution[] = ['type' => 'item', 'item_id' => $fallback->id, 'quantity' => $remaining];
            }
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
