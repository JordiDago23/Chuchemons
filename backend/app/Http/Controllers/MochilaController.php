<?php

namespace App\Http\Controllers;

use App\Models\MochilaXux;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class MochilaController extends Controller
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
     * Valida si hay espacio en la mochila para añadir items,
     * considerando que items apilables pueden ir a slots parcialmente llenos.
     * 
     * @param int $userId
     * @param array $itemsToAdd Ejemplo: [['type' => 'xux'|'item'|'vaccine', 'chuchemon_id' => 1, 'quantity' => 7], ...]
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
                // Xux type (chuchemon_id)
                if ($newItem['type'] === 'xux' && 
                    isset($newItem['chuchemon_id']) &&
                    $item->chuchemon_id === $newItem['chuchemon_id'] && 
                    !$item->vaccine_id &&
                    !$item->item_id) {
                    $existingRow = $item;
                    break;
                }
                
                // Item type (item_id)
                if ($newItem['type'] === 'item' && 
                    isset($newItem['item_id']) &&
                    $item->item_id === $newItem['item_id'] && 
                    !$item->chuchemon_id && 
                    !$item->vaccine_id) {
                    $existingRow = $item;
                    break;
                }
                
                // Vaccine type (vaccine_id)
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

    /**
     * Returns the current user's mochila xuxes with space stats.
     */
    public function index(): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        if (!$user) {
            return response()->json(['message' => 'No autoritzat'], 401);
        }

        $items = MochilaXux::with(['chuchemon', 'item', 'vaccine'])
            ->where('user_id', $user->id)
            ->where('quantity', '>', 0)
            ->get();

        $usedSpaces = $items->sum(fn($i) => self::calculateItemSlots($i));

        return response()->json([
            'items'       => $items,
            'used_spaces' => $usedSpaces,
            'max_spaces'  => self::MAX_SPACES,
            'free_spaces' => self::MAX_SPACES - $usedSpaces,
        ]);
    }

    /**
     * Admin adds a specific quantity of Xuxes (linked to a Chuchemon)
     * to the current user's mochila.
     * Uses canFitItems() to validate space before adding.
     */
    public function addXux(Request $request): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        if (!$user) {
            return response()->json(['message' => 'No autoritzat'], 401);
        }
        
        if (!$user->is_admin) {
            return response()->json(['message' => 'No autoritzat'], 403);
        }

        $validator = Validator::make($request->all(), [
            'chuchemon_id' => 'required|integer|exists:chuchemons,id',
            'quantity'     => 'required|integer|min:1|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chuchemonId = (int) $request->chuchemon_id;
        $qtyToAdd    = (int) $request->quantity;

        // Validar espacio ANTES de añadir usando canFitItems()
        $itemsToAdd = [
            ['type' => 'xux', 'chuchemon_id' => $chuchemonId, 'quantity' => $qtyToAdd],
        ];
        
        $spaceCheck = $this->canFitItems($user->id, $itemsToAdd);
        
        if (!$spaceCheck['can_fit']) {
            return response()->json([
                'message' => 'La mochila no té prou espai per afegir aquests Xuxes.',
                'added' => 0,
                'discarded' => $qtyToAdd,
                'free_spaces' => $spaceCheck['free_slots'],
                'slots_needed' => $spaceCheck['slots_needed'],
                'currently_used' => $spaceCheck['currently_used'],
            ], 422);
        }

        // Añadir items a la mochila
        $existingItem = MochilaXux::where('user_id', $user->id)
            ->where('chuchemon_id', $chuchemonId)
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $qtyToAdd;
            $existingItem->save();
            $item = $existingItem;
        } else {
            $item = MochilaXux::create([
                'user_id'      => $user->id,
                'chuchemon_id' => $chuchemonId,
                'quantity'     => $qtyToAdd,
            ]);
        }

        $item->load('chuchemon');

        // Recalcular espacios después de guardar
        $newItems      = MochilaXux::with('item')->where('user_id', $user->id)->where('quantity', '>', 0)->get();
        $newUsedSpaces = $newItems->sum(fn($i) => self::calculateItemSlots($i));

        return response()->json([
            'message'     => "S'han afegit {$qtyToAdd} Xuxes correctament.",
            'added'       => $qtyToAdd,
            'discarded'   => 0,
            'item'        => $item,
            'used_spaces' => $newUsedSpaces,
            'free_spaces' => self::MAX_SPACES - $newUsedSpaces,
        ]);
    }

    /**
     * Modifica la quantitat d'un item de la mochila de l'usuari.
     * Si quantity = 0, elimina l'item.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        if (!$user) {
            return response()->json(['message' => 'No autoritzat'], 401);
        }

        $item = MochilaXux::with('item')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Item no trobat'], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $newQty = (int) $request->quantity;

        if ($newQty === 0) {
            $item->delete();
            return response()->json(['message' => 'Item eliminat de la mochila']);
        }

        // Check space constraints: slots used by other items + new slots for this item
        $others = MochilaXux::with('item')->where('user_id', $user->id)->where('id', '!=', $id)->get();
        $usedByOthers = $others->sum(fn($i) => self::calculateItemSlots($i));
        
        // Calcular slots para la nueva cantidad según el tipo
        if ($item->vaccine_id) {
            $slotsForNewQty = $newQty; // Vacunas no apilables
        } elseif ($item->item_id && $item->item && $item->item->type === 'no_apilable') {
            $slotsForNewQty = $newQty; // Items no apilables
        } else {
            $slotsForNewQty = (int) ceil($newQty / self::STACK_SIZE); // Apilables
        }

        if ($usedByOthers + $slotsForNewQty > self::MAX_SPACES) {
            return response()->json(['message' => 'La mochila no té prou espai per a aquesta quantitat'], 422);
        }

        $item->quantity = $newQty;
        $item->save();
        $item->load('chuchemon');

        $allItems = MochilaXux::with('item')->where('user_id', $user->id)->get();
        $newUsedSpaces = $allItems->sum(fn($i) => self::calculateItemSlots($i));

        return response()->json([
            'message'     => 'Quantitat actualitzada correctament',
            'item'        => $item,
            'used_spaces' => $newUsedSpaces,
            'free_spaces' => self::MAX_SPACES - $newUsedSpaces,
        ]);
    }

    /**
     * Elimina un item de la mochila de l'usuari.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        if (!$user) {
            return response()->json(['message' => 'No autoritzat'], 401);
        }

        $item = MochilaXux::with(['item', 'vaccine'])->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Item no trobat'], 404);
        }

        $itemName = $item->item ? $item->item->name : ($item->vaccine ? $item->vaccine->name : 'Xux');
        
        // Para todos los items (incluidas vacunas): decrementar o eliminar
        if ($item->quantity > 1) {
            $item->quantity -= 1;
            $item->save();
            $message = "Se ha eliminado 1 {$itemName} de la mochila (quedan {$item->quantity})";
        } else {
            $item->delete();
            $message = "Se ha eliminado {$itemName} de la mochila";
        }

        $allItems = MochilaXux::with(['item', 'vaccine'])->where('user_id', $user->id)->get();
        $newUsedSpaces = $allItems->sum(fn($i) => self::calculateItemSlots($i));

        return response()->json([
            'message'     => $message,
            'used_spaces' => $newUsedSpaces,
            'free_spaces' => self::MAX_SPACES - $newUsedSpaces,
        ]);
    }

    /**
     * Add a generic Item (xux) to the user's mochila.
     * For items marked as 'apilable', the system will stack up to STACK_SIZE per space.
     */
    public function addItem(Request $request): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        if (!$user) {
            return response()->json(['message' => 'No autoritzat'], 401);
        }

        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer|exists:items,id',
            'quantity' => 'required|integer|min:1|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $itemId = (int) $request->item_id;
        $qtyToAdd = (int) $request->quantity;

        $item = \App\Models\Item::find($itemId);
        if (!$item) {
            return response()->json(['message' => 'Item no trobat'], 404);
        }

        // Validar espacio ANTES de añadir items (considerando slots parcialmente llenos)
        $itemsToAdd = [
            ['type' => 'item', 'item_id' => $itemId, 'quantity' => $qtyToAdd],
        ];
        
        $spaceCheck = $this->canFitItems($user->id, $itemsToAdd);
        
        if (!$spaceCheck['can_fit']) {
            return response()->json([
                'message' => 'La mochila no té prou espai.',
                'added' => 0,
                'discarded' => $qtyToAdd,
                'free_spaces' => $spaceCheck['free_slots'],
                'slots_needed' => $spaceCheck['slots_needed'],
            ], 422);
        }

        // Check for existing item
        $existingMochilaItem = MochilaXux::where('user_id', $user->id)
            ->where('item_id', $itemId)
            ->first();

        if ($existingMochilaItem) {
            $existingMochilaItem->quantity += $qtyToAdd;
            $existingMochilaItem->save();
        } else {
            $existingMochilaItem = MochilaXux::create([
                'user_id' => $user->id,
                'item_id' => $itemId,
                'quantity' => $qtyToAdd,
            ]);
        }

        $existingMochilaItem->load('item');

        // Recalculate
        $newMochilaItems = MochilaXux::with('item')->where('user_id', $user->id)->get();
        $newUsedSpaces = $newMochilaItems->sum(fn($i) => self::calculateItemSlots($i));

        return response()->json([
            'message' => "S'han afegit {$qtyToAdd} items correctament.",
            'added' => $qtyToAdd,
            'item' => $existingMochilaItem,
            'used_spaces' => $newUsedSpaces,
            'free_spaces' => self::MAX_SPACES - $newUsedSpaces,
        ]);
    }
}
