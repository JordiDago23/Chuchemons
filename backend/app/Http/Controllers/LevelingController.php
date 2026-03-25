<?php

namespace App\Http\Controllers;

use App\Models\MochilaXux;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class LevelingController extends Controller
{
    // ─── HP helper ────────────────────────────────────────────────────────────

    /**
     * max_hp = 50 + base_defense + (level × 5) + mida_bonus
     * Petit=0 · Mitjà=+25 · Gran=+50
     */
    public static function computeMaxHp(int $baseDefense, int $level, string $currentMida): int
    {
        $midaBonus = match ($currentMida) {
            'Mitjà' => 25,
            'Gran'  => 50,
            default => 0,
        };
        return 50 + $baseDefense + ($level * 5) + $midaBonus;
    }

    /**
     * Effective attack/defense applying mida multiplier:
     * Petit ×1.00 · Mitjà ×1.02 · Gran ×1.02×1.05
     */
    public static function effectiveAttack(int $base, string $currentMida): float
    {
        return match ($currentMida) {
            'Mitjà' => round($base * 1.02, 1),
            'Gran'  => round($base * 1.02 * 1.05, 1),
            default => (float) $base,
        };
    }

    public static function effectiveDefense(int $base, string $currentMida): float
    {
        return self::effectiveAttack($base, $currentMida); // same multipliers
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    public function getChuchemonLevel(int $chuchemonId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['message' => 'Usuario no autenticado'], 401);

            $uc = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$uc) return response()->json(['message' => 'Chuchemon no encontrado en tu colección'], 404);

            return response()->json([
                'level'                   => $uc->level,
                'experience'              => $uc->experience,
                'experience_for_next_level' => $uc->experience_for_next_level,
                'experience_progress'     => round(($uc->experience / $uc->experience_for_next_level) * 100, 2),
                'current_hp'              => $uc->current_hp ?? $uc->max_hp,
                'max_hp'                  => $uc->max_hp ?? 105,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene todos los chuchemons del usuario con sus niveles, HP y stats efectivos
     */
    public function getAllChuchemonsWithLevels(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['message' => 'Usuario no autenticado'], 401);

            $chuchemons = DB::table('user_chuchemons')
                ->join('chuchemons', 'user_chuchemons.chuchemon_id', '=', 'chuchemons.id')
                ->where('user_chuchemons.user_id', $user->id)
                ->select(
                    'chuchemons.id',
                    'chuchemons.name',
                    'chuchemons.element',
                    'chuchemons.image',
                    'chuchemons.attack',
                    'chuchemons.defense',
                    'chuchemons.speed',
                    'user_chuchemons.level',
                    'user_chuchemons.experience',
                    'user_chuchemons.experience_for_next_level',
                    'user_chuchemons.count',
                    'user_chuchemons.current_mida',
                    'user_chuchemons.current_hp',
                    'user_chuchemons.max_hp',
                )
                ->get();

            $userId = $user->id;
            $chuchemons = $chuchemons->map(function ($c) use ($userId) {
                    $baseAtk = $c->attack ?? 50;
                    $baseDef = $c->defense ?? 50;
                    $mida    = $c->current_mida ?? 'Petit';
                    $maxHp   = $c->max_hp   ?? self::computeMaxHp($baseDef, $c->level, $mida);
                    $currHp  = $c->current_hp ?? $maxHp;

                    $c->experience_progress   = round(($c->experience / $c->experience_for_next_level) * 100, 2);
                    $c->effective_attack      = self::effectiveAttack($baseAtk, $mida);
                    $c->effective_defense     = self::effectiveDefense($baseDef, $mida);
                    $c->max_hp                = $maxHp;
                    $c->current_hp            = min($currHp, $maxHp);
                    $c->hp_percent            = round(($c->current_hp / $maxHp) * 100, 1);

                    // Xuxes disponibles (chuchemon-specific candies in the mochila)
                    $c->xuxes_qty = MochilaXux::where('user_id', $userId)
                        ->where('chuchemon_id', $c->id)
                        ->sum('quantity');

                    return $c;
                });

            return response()->json($chuchemons, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─── Mutations ────────────────────────────────────────────────────────────

    /**
     * Agrega experiencia y verifica si sube de nivel (actualiza max_hp al subir)
     */
    public function addExperience(int $chuchemonId, int $experienceAmount): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['message' => 'Usuario no autenticado'], 401);

            $uc = DB::table('user_chuchemons')
                ->join('chuchemons', 'user_chuchemons.chuchemon_id', '=', 'chuchemons.id')
                ->where('user_chuchemons.user_id', $user->id)
                ->where('user_chuchemons.chuchemon_id', $chuchemonId)
                ->select('user_chuchemons.*', 'chuchemons.defense', 'chuchemons.attack')
                ->first();

            if (!$uc) return response()->json(['message' => 'Chuchemon no encontrado en tu colección'], 404);

            $newXp     = $uc->experience + $experienceAmount;
            $level     = $uc->level;
            $xpForNext = $uc->experience_for_next_level;
            $leveledUp = false;

            while ($newXp >= $xpForNext) {
                $newXp    -= $xpForNext;
                $level++;
                $xpForNext = 100 + ($level * 50);
                $leveledUp = true;
            }

            $mida   = $uc->current_mida ?? 'Petit';
            $maxHp  = self::computeMaxHp($uc->defense ?? 50, $level, $mida);
            $currHp = $uc->current_hp ?? $maxHp;

            // On level up, restore HP proportionally (not to full, just gain bonus)
            if ($leveledUp) {
                $currHp = min($currHp + 10, $maxHp);
            }

            DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->update([
                    'experience'              => $newXp,
                    'level'                   => $level,
                    'experience_for_next_level' => $xpForNext,
                    'max_hp'                  => $maxHp,
                    'current_hp'              => $currHp,
                ]);

            return response()->json([
                'message'                 => $leveledUp ? 'Chuchemon ha pujat de nivell!' : 'Experiència afegida',
                'level'                   => $level,
                'experience'              => $newXp,
                'experience_for_next_level' => $xpForNext,
                'level_up'                => $leveledUp,
                'current_hp'              => $currHp,
                'max_hp'                  => $maxHp,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Gasta N Xuxes del inventari per donar +20 XP cada un
     * POST /api/user/chuchemons/{id}/use-xux  — body: { quantity: N }
     */
    public function useXuxForExperience(Request $request, int $chuchemonId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['message' => 'Usuario no autenticado'], 401);

            $qty = (int) ($request->input('quantity', 1));
            if ($qty < 1) return response()->json(['message' => 'Quantitat no vàlida'], 422);

            // Check the user has enough xuxes for this chuchemon
            $mochilaXux = MochilaXux::where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$mochilaXux || $mochilaXux->quantity < $qty) {
                return response()->json([
                    'message' => 'No tens prou Xuxes per a aquest Xuxemon.',
                    'have'    => $mochilaXux?->quantity ?? 0,
                    'need'    => $qty,
                ], 422);
            }

            // Consume xuxes
            $mochilaXux->quantity -= $qty;
            $mochilaXux->quantity <= 0 ? $mochilaXux->delete() : $mochilaXux->save();

            // Add experience (20 XP per xux)
            $xpToAdd = $qty * 20;

            return $this->addExperience($chuchemonId, $xpToAdd);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cura el Xuxemon gastant Xuxes del inventari (+20 HP per Xux, fins a max_hp)
     * POST /api/user/chuchemons/{id}/heal  — body: { quantity: N }
     */
    public function healChuchemon(Request $request, int $chuchemonId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['message' => 'Usuario no autenticado'], 401);

            $qty = (int) ($request->input('quantity', 1));
            if ($qty < 1) return response()->json(['message' => 'Quantitat no vàlida'], 422);

            // Check xuxes
            $mochilaXux = MochilaXux::where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$mochilaXux || $mochilaXux->quantity < $qty) {
                return response()->json([
                    'message' => 'No tens prou Xuxes per curar aquest Xuxemon.',
                    'have'    => $mochilaXux?->quantity ?? 0,
                    'need'    => $qty,
                ], 422);
            }

            $uc = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$uc) return response()->json(['message' => 'Xuxemon no trobat a la col·lecció'], 404);

            $maxHp  = $uc->max_hp  ?? 105;
            $currHp = $uc->current_hp ?? $maxHp;

            if ($currHp >= $maxHp) {
                return response()->json([
                    'message'    => 'El Xuxemon ja té la vida plena!',
                    'current_hp' => $currHp,
                    'max_hp'     => $maxHp,
                ], 422);
            }

            $healAmount = $qty * 20;
            $newHp      = min($currHp + $healAmount, $maxHp);
            $actualHeal = $newHp - $currHp;

            // Only consume xuxes needed (don't waste if already near full)
            $xuxesUsed = (int) ceil($actualHeal / 20);
            $mochilaXux->quantity -= $xuxesUsed;
            $mochilaXux->quantity <= 0 ? $mochilaXux->delete() : $mochilaXux->save();

            DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->update(['current_hp' => $newHp]);

            return response()->json([
                'message'      => "Has curat {$actualHeal} PS al Xuxemon!",
                'healed'       => $actualHeal,
                'current_hp'   => $newHp,
                'max_hp'       => $maxHp,
                'xuxes_used'   => $xuxesUsed,
                'xuxes_left'   => $mochilaXux->exists ? $mochilaXux->quantity : 0,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
