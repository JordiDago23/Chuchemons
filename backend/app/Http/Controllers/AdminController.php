<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Chuchemon;
use App\Models\GameSetting;
use App\Models\MochilaXux;
use App\Models\Item;
use App\Models\Vaccine;
use App\Models\Malaltia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminController extends Controller
{
    private const MAX_SPACES = 20;
    private const STACK_SIZE = 5;

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
     * Calcula cuántos items puedo añadir realmente dado el espacio disponible.
     * 
     * @param int $userId
     * @param string $type 'xux', 'item', 'vaccine'
     * @param int $id chuchemon_id, item_id, o vaccine_id
     * @param int $requestedQty Cantidad solicitada
     * @param bool $isStackable Si el item es apilable (false para vacunas y chocolatinas no_apilable)
     * @return array ['can_add' => int, 'discarded' => int]
     */
    private function calculateMaxAddableQuantity(int $userId, string $type, int $id, int $requestedQty, bool $isStackable): array
    {
        // Obtener items actuales
        $currentItems = MochilaXux::with('item')->where('user_id', $userId)->where('quantity', '>', 0)->get();
        $currentlyUsedSlots = $currentItems->sum(fn($item) => self::calculateItemSlots($item));
        $freeSlots = self::MAX_SPACES - $currentlyUsedSlots;
        
        // Buscar si ya existe un registro del mismo tipo
        $existingRow = null;
        foreach ($currentItems as $item) {
            if ($type === 'xux' && $item->chuchemon_id === $id && !$item->vaccine_id && !$item->item_id) {
                $existingRow = $item;
                break;
            }
            if ($type === 'item' && $item->item_id === $id && !$item->chuchemon_id && !$item->vaccine_id) {
                $existingRow = $item;
                break;
            }
            if ($type === 'vaccine' && $item->vaccine_id === $id && !$item->chuchemon_id && !$item->item_id) {
                $existingRow = $item;
                break;
            }
        }
        
        if ($isStackable) {
            // Items apilables (5 por slot)
            if ($existingRow) {
                // Calcular espacio en el slot actual parcialmente lleno
                $currentQty = $existingRow->quantity;
                $spaceInCurrentSlot = self::STACK_SIZE - ($currentQty % self::STACK_SIZE);
                if ($spaceInCurrentSlot === self::STACK_SIZE) $spaceInCurrentSlot = 0; // El slot está completo
                
                // Primero llenar el slot actual
                $canAddToCurrentSlot = min($requestedQty, $spaceInCurrentSlot);
                $remaining = $requestedQty - $canAddToCurrentSlot;
                
                // Luego usar slots libres (si quedan items y hay slots disponibles)
                $canAddInFreeSlots = 0;
                if ($remaining > 0 && $freeSlots > 0) {
                    $canAddInFreeSlots = min($remaining, $freeSlots * self::STACK_SIZE);
                }
                
                $totalCanAdd = $canAddToCurrentSlot + $canAddInFreeSlots;
                
                return [
                    'can_add' => $totalCanAdd,
                    'discarded' => $requestedQty - $totalCanAdd,
                ];
            } else {
                // No existe - necesita crear nuevo registro
                if ($freeSlots <= 0) {
                    return ['can_add' => 0, 'discarded' => $requestedQty];
                }
                
                $canAdd = min($requestedQty, $freeSlots * self::STACK_SIZE);
                return [
                    'can_add' => $canAdd,
                    'discarded' => $requestedQty - $canAdd,
                ];
            }
        } else {
            // Items NO apilables (1 por slot) - vacunas
            if ($freeSlots <= 0) {
                return ['can_add' => 0, 'discarded' => $requestedQty];
            }
            
            $canAdd = min($requestedQty, $freeSlots);
            return [
                'can_add' => $canAdd,
                'discarded' => $requestedQty - $canAdd,
            ];
        }
    }

    /**
     * Valida si hay espacio en la mochila para añadir items,
     * considerando que items apilables pueden ir a slots parcialmente llenos.
     * 
     * @param int $userId
     * @param array $itemsToAdd Ejemplo: [['type' => 'xux'|'vaccine', 'chuchemon_id' => 1, 'quantity' => 7], ...]
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
                // Xuxes from admin use chuchemon_id
                if ($newItem['type'] === 'xux' && 
                    isset($newItem['chuchemon_id']) && 
                    $item->chuchemon_id === $newItem['chuchemon_id'] && 
                    !$item->vaccine_id && 
                    !$item->item_id) {
                    $existingRow = $item;
                    break;
                }
                
                // Items use item_id
                if ($newItem['type'] === 'item' && 
                    isset($newItem['item_id']) && 
                    $item->item_id === $newItem['item_id'] && 
                    !$item->chuchemon_id && 
                    !$item->vaccine_id) {
                    $existingRow = $item;
                    break;
                }
                
                // Vaccines use vaccine_id
                if ($newItem['type'] === 'vaccine' && 
                    isset($newItem['vaccine_id']) && 
                    $item->vaccine_id === $newItem['vaccine_id'] && 
                    !$item->chuchemon_id && 
                    !$item->item_id) {
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
        
        return [
            'can_fit' => $slotsNeeded <= $freeSlots,
            'free_slots' => $freeSlots,
            'slots_needed' => $slotsNeeded,
            'currently_used' => $currentlyUsedSlots,
        ];
    }

    private function settingsPayload(): array
    {
        $defaultRate = GameSetting::getInt('taxa_infeccio', 12);
        $diseases = Malaltia::query()->select('id', 'name')->get()->map(function ($disease) use ($defaultRate) {
            $disease->infection_rate = Schema::hasColumn('malalties', 'infection_rate')
                ? (int) ($disease->getAttribute('infection_rate') ?? $defaultRate)
                : $defaultRate;

            return $disease;
        });

        if (Schema::hasColumn('malalties', 'infection_rate')) {
            $diseases = Malaltia::query()->select('id', 'name', 'infection_rate')->get();
        }

        return [
            'config' => [
                'xux_petit_mitja' => GameSetting::getInt('xux_petit_mitja', 3),
                'xux_mitja_gran' => GameSetting::getInt('xux_mitja_gran', 5),
            ],
            'infection' => [
                'diseases' => $diseases,
            ],
            'schedules' => [
                'daily_xux_hour' => GameSetting::getValue('daily_xux_hour', '06:00'),
                'daily_xux_quantity' => GameSetting::getInt('daily_xux_quantity', 10),
                'daily_chuchemon_hour' => GameSetting::getValue('daily_chuchemon_hour', '08:00'),
            ],
        ];
    }

    // ── GET /api/admin/stats ──────────────────────────────────────────────────
    public function stats(): JsonResponse
    {
        return response()->json([
            'jugadors'      => User::where('is_admin', false)->count(),
            'total_usuaris' => User::count(),
            'xuemons'       => Chuchemon::count(),
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json($this->settingsPayload());
    }

    public function updateEvolutionConfig(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'xux_petit_mitja' => 'required|integer|min:1|max:99',
            'xux_mitja_gran' => 'required|integer|min:1|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        GameSetting::setValue('xux_petit_mitja', (int) $request->xux_petit_mitja);
        GameSetting::setValue('xux_mitja_gran', (int) $request->xux_mitja_gran);

        return response()->json([
            'message' => 'Configuración de evolución guardada correctamente.',
            'settings' => $this->settingsPayload()['config'],
        ]);
    }

    public function updateInfectionRate(Request $request): JsonResponse
    {
        if (!Schema::hasColumn('malalties', 'infection_rate')) {
            return response()->json([
                'message' => 'Falta la migración de infection_rate en malalties. Ejecuta las migraciones del backend.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'diseases' => 'required|array',
            'diseases.*.id' => 'required|exists:malalties,id',
            'diseases.*.infection_rate' => 'required|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->diseases as $d) {
            Malaltia::where('id', $d['id'])->update(['infection_rate' => $d['infection_rate']]);
        }

        return response()->json([
            'message' => 'Tasas de infección actualizadas correctamente.',
            'settings' => $this->settingsPayload()['infection'],
        ]);
    }

    public function updateDailyXuxSchedule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hour' => ['required', 'date_format:H:i'],
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        GameSetting::setValue('daily_xux_hour', $request->hour);
        GameSetting::setValue('daily_xux_quantity', (int) $request->quantity);

        return response()->json([
            'message' => 'Horario de Xuxes actualizado correctamente.',
            'settings' => [
                'daily_xux_hour' => GameSetting::getValue('daily_xux_hour', '06:00'),
                'daily_xux_quantity' => GameSetting::getInt('daily_xux_quantity', 10),
            ],
        ]);
    }

    public function updateDailyChuchemonSchedule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hour' => ['required', 'date_format:H:i'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        GameSetting::setValue('daily_chuchemon_hour', $request->hour);

        return response()->json([
            'message' => 'Horario de Xuxemon actualizado correctamente.',
            'settings' => [
                'daily_chuchemon_hour' => GameSetting::getValue('daily_chuchemon_hour', '08:00'),
            ],
        ]);
    }

    // ── GET /api/admin/users ──────────────────────────────────────────────────
    public function listUsers(): JsonResponse
    {
        $users = User::all()->map(function ($user) {
            $xuxCount = MochilaXux::where('user_id', $user->id)->sum('quantity');
            return [
                'id'        => $user->id,
                'nombre'    => $user->nombre,
                'player_id' => $user->player_id,
                'email'     => $user->email,
                'is_admin'  => $user->is_admin,
                'xux_count' => (int) $xuxCount,
                'level'     => 1,
                'wins'      => 0,
            ];
        });

        return response()->json(['users' => $users]);
    }

    // ── POST /api/admin/users/{id}/add-xux ───────────────────────────────────
    public function addXuxToUser(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Jugador no trobat.'], 404);
        }

        // Use the first chuchemon as the generic "Xux" carrier
        $firstChuch = Chuchemon::first();
        if (!$firstChuch) {
            return response()->json(['message' => "No hi ha Xuxemons a la base de dades."], 404);
        }

        $qtyRequested = (int) $request->quantity;
        
        // Calcular cuántos items REALMENTE caben (añadir parcialmente si es necesario)
        $calculation = $this->calculateMaxAddableQuantity($targetUser->id, 'xux', $firstChuch->id, $qtyRequested, true);
        $qtyToAdd = $calculation['can_add'];
        $qtyDiscarded = $calculation['discarded'];
        
        if ($qtyToAdd === 0) {
            return response()->json([
                'message' => 'La mochila del jugador está llena. No se ha añadido nada.',
                'added' => 0,
                'discarded' => $qtyRequested,
            ], 422);
        }

        // Añadir items a la mochila
        $existing = MochilaXux::where('user_id', $targetUser->id)
            ->where('chuchemon_id', $firstChuch->id)
            ->first();

        if ($existing) {
            $existing->quantity += $qtyToAdd;
            $existing->save();
        } else {
            MochilaXux::create([
                'user_id'      => $targetUser->id,
                'chuchemon_id' => $firstChuch->id,
                'quantity'     => $qtyToAdd,
            ]);
        }

        $message = $qtyDiscarded > 0
            ? "S'han afegit {$qtyToAdd} Xuxes a {$targetUser->player_id}. {$qtyDiscarded} Xuxes descartades per falta d'espai."
            : "S'han afegit {$qtyToAdd} Xuxes a {$targetUser->player_id}.";

        return response()->json([
            'message' => $message,
            'added' => $qtyToAdd,
            'discarded' => $qtyDiscarded,
        ]);
    }

    // ── POST /api/admin/users/{id}/add-random-chuchemon ──────────────────────
    public function addRandomChuchemon(int $id): JsonResponse
    {
        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Jugador no trobat.'], 404);
        }

        $chuchemon = Chuchemon::inRandomOrder()->first();
        if (!$chuchemon) {
            return response()->json(['message' => 'No hi ha Xuxemons a la base de dades.'], 404);
        }

        // Add to user_chuchemons (captured chuchemons), not to mochila_xuxes (inventory)
        $existingCapture = DB::table('user_chuchemons')
            ->where('user_id', $targetUser->id)
            ->where('chuchemon_id', $chuchemon->id)
            ->first();

        if ($existingCapture) {
            // Increment count if already captured
            DB::table('user_chuchemons')
                ->where('user_id', $targetUser->id)
                ->where('chuchemon_id', $chuchemon->id)
                ->increment('count');
        } else {
            // Insert new captured chuchemon — initialize HP
            $maxHp = LevelingController::computeMaxHp($chuchemon->defense ?? 50, 1, 'Petit');
            DB::table('user_chuchemons')->insert([
                'user_id'                    => $targetUser->id,
                'chuchemon_id'               => $chuchemon->id,
                'count'                      => 1,
                'current_mida'               => 'Petit',
                'level'                      => 1,
                'experience'                 => 0,
                'experience_for_next_level'  => LevelingController::experienceForMida('Petit'),
                'max_hp'                     => $maxHp,
                'current_hp'                 => $maxHp,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);
        }

        return response()->json([
            'message'   => "S'ha desbloqueado 1 {$chuchemon->name} a {$targetUser->player_id}.",
            'chuchemon' => $chuchemon,
        ]);
    }

    // ── POST /api/admin/users/{id}/add-item ───────────────────────────────────
    public function addItemToUser(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Jugador no trobat.'], 404);
        }

        $item = Item::find($request->item_id);
        if (!$item) {
            return response()->json(['message' => 'Item no trobat.'], 404);
        }

        $qtyRequested = (int) $request->quantity;
        
        // Determinar si el item es apilable
        $isStackable = ($item->type !== 'no_apilable');
        
        // Calcular cuántos items REALMENTE caben (añadir parcialmente si es necesario)
        $calculation = $this->calculateMaxAddableQuantity($targetUser->id, 'item', $item->id, $qtyRequested, $isStackable);
        $qtyToAdd = $calculation['can_add'];
        $qtyDiscarded = $calculation['discarded'];
        
        if ($qtyToAdd === 0) {
            return response()->json([
                'message' => 'La mochila del jugador está llena. No se ha añadido nada.',
                'added' => 0,
                'discarded' => $qtyRequested,
            ], 422);
        }

        // Añadir items a la mochila
        $existing = MochilaXux::where('user_id', $targetUser->id)
            ->where('item_id', $item->id)
            ->first();
            
        if ($existing) {
            $existing->quantity += $qtyToAdd;
            $existing->save();
        } else {
            MochilaXux::create([
                'user_id' => $targetUser->id,
                'item_id' => $item->id,
                'quantity' => $qtyToAdd,
            ]);
        }

        $message = $qtyDiscarded > 0
            ? "S'han afegit {$qtyToAdd} {$item->name} a la mochila de {$targetUser->player_id}. {$qtyDiscarded} descartats per falta d'espai."
            : "S'han afegit {$qtyToAdd} {$item->name} a la mochila de {$targetUser->player_id}.";

        return response()->json([
            'message' => $message,
            'item' => $item,
            'added' => $qtyToAdd,
            'discarded' => $qtyDiscarded,
        ]);
    }

    // ── POST /api/admin/users/{id}/add-vaccine ────────────────────────────────
    public function addVaccineToUser(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vaccine_id' => 'required|exists:vaccines,id',
            'quantity' => 'required|integer|min:1|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Jugador no encontrado.'], 404);
        }

        $vaccine = Vaccine::find($request->vaccine_id);
        if (!$vaccine) {
            return response()->json(['message' => 'Vacuna no encontrada.'], 404);
        }

        $qtyRequested = (int) $request->quantity;
        
        // Calcular cuántas vacunas REALMENTE caben (añadir parcialmente si es necesario)
        // Vacunas NO son apilables (1 vacuna = 1 slot)
        $calculation = $this->calculateMaxAddableQuantity($targetUser->id, 'vaccine', $vaccine->id, $qtyRequested, false);
        $qtyToAdd = $calculation['can_add'];
        $qtyDiscarded = $calculation['discarded'];
        
        if ($qtyToAdd === 0) {
            return response()->json([
                'message' => 'La mochila del jugador está llena. No se ha añadido nada.',
                'added' => 0,
                'discarded' => $qtyRequested,
            ], 422);
        }

        // Añadir vacunas a la mochila
        // Vacunas NO son apilables: cada vacuna debe ser un registro separado con quantity = 1
        for ($i = 0; $i < $qtyToAdd; $i++) {
            MochilaXux::create([
                'user_id'    => $targetUser->id,
                'vaccine_id' => $vaccine->id,
                'quantity'   => 1, // 1 vacuna por registro (NO apilable)
            ]);
        }

        $message = $qtyDiscarded > 0
            ? "Se han añadido {$qtyToAdd} {$vaccine->name} a la mochila de {$targetUser->player_id}. {$qtyDiscarded} descartadas por falta de espacio."
            : "Se han añadido {$qtyToAdd} {$vaccine->name} a la mochila de {$targetUser->player_id}.";

        return response()->json([
            'message' => $message,
            'vaccine' => $vaccine,
            'added' => $qtyToAdd,
            'discarded' => $qtyDiscarded,
        ]);
    }
}
