<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Chuchemon;
use App\Models\GameSetting;
use App\Models\MochilaXux;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminController extends Controller
{
    private const MAX_SPACES = 20;
    private const STACK_SIZE = 5;

    private function settingsPayload(): array
    {
        return [
            'config' => [
                'xux_petit_mitja' => GameSetting::getInt('xux_petit_mitja', 3),
                'xux_mitja_gran' => GameSetting::getInt('xux_mitja_gran', 5),
            ],
            'infection' => [
                'taxa_infeccio' => GameSetting::getInt('taxa_infeccio', 12),
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
            'taxa_infeccio' => GameSetting::getInt('taxa_infeccio', 12),
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
        $validator = Validator::make($request->all(), [
            'taxa_infeccio' => 'required|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        GameSetting::setValue('taxa_infeccio', (int) $request->taxa_infeccio);

        return response()->json([
            'message' => 'Tasa de infección actualizada correctamente.',
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

        $qtyToAdd   = (int) $request->quantity;
        $items      = MochilaXux::where('user_id', $targetUser->id)->get();
        $usedSpaces = $items->sum(fn($i) => (int) ceil($i->quantity / self::STACK_SIZE));
        $freeSpaces = self::MAX_SPACES - $usedSpaces;

        if ($freeSpaces <= 0) {
            return response()->json([
                'message'   => 'La motxilla del jugador està plena.',
                'added'     => 0,
                'discarded' => $qtyToAdd,
            ], 422);
        }

        $existing     = $items->firstWhere('chuchemon_id', $firstChuch->id);
        $currentQty   = $existing ? (int) $existing->quantity : 0;
        $currentSlots = $currentQty > 0 ? (int) ceil($currentQty / self::STACK_SIZE) : 0;
        $maxAddable   = ($currentSlots + $freeSpaces) * self::STACK_SIZE - $currentQty;
        $actualAdded  = min($qtyToAdd, $maxAddable);
        $discarded    = $qtyToAdd - $actualAdded;

        if ($existing) {
            $existing->quantity += $actualAdded;
            $existing->save();
        } else {
            MochilaXux::create([
                'user_id'      => $targetUser->id,
                'chuchemon_id' => $firstChuch->id,
                'quantity'     => $actualAdded,
            ]);
        }

        return response()->json([
            'message'   => "S'han afegit {$actualAdded} Xuxes a {$targetUser->player_id}.",
            'added'     => $actualAdded,
            'discarded' => $discarded,
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
                'user_id'      => $targetUser->id,
                'chuchemon_id' => $chuchemon->id,
                'count'        => 1,
                'max_hp'       => $maxHp,
                'current_hp'   => $maxHp,
                'created_at'   => now(),
                'updated_at'   => now(),
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

        $mochilaItems = MochilaXux::where('user_id', $targetUser->id)->get();
        $usedSpaces = $mochilaItems->sum(function($i) {
            $itemType = $i->item ? $i->item->type : 'apilable';
            return $itemType === 'no_apilable' ? $i->quantity : (int) ceil($i->quantity / self::STACK_SIZE);
        });
        $freeSpaces = self::MAX_SPACES - $usedSpaces;

        $spacesNeeded = $item->type === 'no_apilable' ? $request->quantity : (int) ceil($request->quantity / self::STACK_SIZE);

        if ($freeSpaces < $spacesNeeded) {
            $discarded = $request->quantity - ($freeSpaces * ($item->type === 'no_apilable' ? 1 : self::STACK_SIZE));
            return response()->json([
                'message' => 'La motxilla del jugador no té prou espai.',
                'added' => 0,
                'discarded' => max(0, $discarded),
            ], 422);
        }

        $existing = $mochilaItems->firstWhere('item_id', $item->id);
        if ($existing) {
            $existing->quantity += $request->quantity;
            $existing->save();
        } else {
            MochilaXux::create([
                'user_id' => $targetUser->id,
                'item_id' => $item->id,
                'quantity' => $request->quantity,
            ]);
        }

        return response()->json([
            'message' => "S'han afegit {$request->quantity} {$item->name} a la motxilla de {$targetUser->player_id}.",
            'item' => $item,
            'added' => $request->quantity,
        ]);
    }
}
