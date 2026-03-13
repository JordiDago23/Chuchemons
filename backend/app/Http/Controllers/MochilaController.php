<?php

namespace App\Http\Controllers;

use App\Models\MochilaXux;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MochilaController extends Controller
{
    private const MAX_SPACES = 20;
    private const STACK_SIZE = 5;

    /**
     * Returns the current user's mochila xuxes with space stats.
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();

        $items = MochilaXux::with('chuchemon')
            ->where('user_id', $user->id)
            ->where('quantity', '>', 0)
            ->get();

        $usedSpaces = $items->sum(fn($i) => (int) ceil($i->quantity / self::STACK_SIZE));

        return response()->json([
            'items'       => $items,
            'used_spaces' => $usedSpaces,
            'max_spaces'  => self::MAX_SPACES,
            'free_spaces' => self::MAX_SPACES - $usedSpaces,
        ]);
    }

    /**
     * Admin adds a specific quantity of Xuxes (linked to a Chuchemon)
     * to the current user's mochila. Excess Xuxes are discarded when the
     * mochila is full.
     */
    public function addXux(Request $request): JsonResponse
    {
        if (!auth()->user()->is_admin) {
            return response()->json(['message' => 'No autoritzat'], 403);
        }

        $validator = Validator::make($request->all(), [
            'chuchemon_id' => 'required|integer|exists:chuchemons,id',
            'quantity'     => 'required|integer|min:1|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user        = auth()->user();
        $chuchemonId = (int) $request->chuchemon_id;
        $qtyToAdd    = (int) $request->quantity;

        // Current mochila state
        $items      = MochilaXux::where('user_id', $user->id)->get();
        $usedSpaces = $items->sum(fn($i) => (int) ceil($i->quantity / self::STACK_SIZE));
        $freeSpaces = self::MAX_SPACES - $usedSpaces;

        if ($freeSpaces <= 0) {
            return response()->json([
                'message'   => 'La mochila està plena. No s\'han afegit Xuxes.',
                'added'     => 0,
                'discarded' => $qtyToAdd,
            ], 422);
        }

        // Max addable without exceeding MAX_SPACES:
        // ceil((currentQty + actualAdded) / STACK_SIZE) <= currentSlots + freeSpaces
        //  → actualAdded <= (currentSlots + freeSpaces) * STACK_SIZE − currentQty
        $existingItem = $items->firstWhere('chuchemon_id', $chuchemonId);
        $currentQty   = $existingItem ? (int) $existingItem->quantity : 0;
        $currentSlots = $currentQty > 0 ? (int) ceil($currentQty / self::STACK_SIZE) : 0;

        $maxAddable  = ($currentSlots + $freeSpaces) * self::STACK_SIZE - $currentQty;
        $actualAdded = min($qtyToAdd, $maxAddable);
        $discarded   = $qtyToAdd - $actualAdded;

        if ($actualAdded <= 0) {
            return response()->json([
                'message'   => 'La mochila està plena. No s\'han afegit Xuxes.',
                'added'     => 0,
                'discarded' => $qtyToAdd,
            ], 422);
        }

        // Persist
        if ($existingItem) {
            $existingItem->quantity += $actualAdded;
            $existingItem->save();
            $item = $existingItem;
        } else {
            $item = MochilaXux::create([
                'user_id'      => $user->id,
                'chuchemon_id' => $chuchemonId,
                'quantity'     => $actualAdded,
            ]);
        }

        $item->load('chuchemon');

        // Recalculate after save
        $newItems      = MochilaXux::where('user_id', $user->id)->get();
        $newUsedSpaces = $newItems->sum(fn($i) => (int) ceil($i->quantity / self::STACK_SIZE));

        $message = $discarded > 0
            ? "S'han afegit {$actualAdded} Xuxes. {$discarded} descartades (mochila plena)."
            : "S'han afegit {$actualAdded} Xuxes correctament.";

        return response()->json([
            'message'     => $message,
            'added'       => $actualAdded,
            'discarded'   => $discarded,
            'item'        => $item,
            'used_spaces' => $newUsedSpaces,
            'free_spaces' => self::MAX_SPACES - $newUsedSpaces,
        ]);
    }
}
