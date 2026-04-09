<?php

namespace App\Http\Controllers;

use App\Models\GameSetting;
use App\Models\Malaltia;
use App\Models\MochilaXux;
use App\Models\UserInfection;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class LevelingController extends Controller
{
    public static function normalizeMalaltiaName(?string $name): string
    {
        return str_replace(
            ['á', 'à', 'é', 'è', 'í', 'ì', 'ó', 'ò', 'ú', 'ù'],
            ['a', 'a', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u'],
            mb_strtolower((string) $name)
        );
    }

    public static function mapActiveInfections(int $userId, array $chuchemonIds): Collection
    {
        if (empty($chuchemonIds)) {
            return collect();
        }

        return DB::table('user_infections')
            ->join('malalties', 'user_infections.malaltia_id', '=', 'malalties.id')
            ->where('user_infections.user_id', $userId)
            ->where('user_infections.is_active', true)
            ->whereIn('user_infections.chuchemon_id', $chuchemonIds)
            ->select(
                'user_infections.chuchemon_id',
                'user_infections.infection_percentage',
                'malalties.id as malaltia_id',
                'malalties.name',
                'malalties.type',
                'malalties.severity'
            )
            ->get()
            ->groupBy('chuchemon_id')
            ->map(function ($infections) {
                return $infections->map(function ($infection) {
                    return [
                        'id' => $infection->malaltia_id,
                        'name' => $infection->name,
                        'type' => $infection->type,
                        'severity' => $infection->severity,
                        'infection_percentage' => $infection->infection_percentage,
                    ];
                })->values();
            });
    }

    private static function hasAtraconInfection(Collection $infections): bool
    {
        return $infections->contains(function ($infection) {
            return self::normalizeMalaltiaName($infection['name'] ?? null) === 'atracon';
        });
    }

    private static function hasBajonDeAzucar(Collection $infections): bool
    {
        return $infections->contains(function ($infection) {
            $name = self::normalizeMalaltiaName($infection['name'] ?? null);
            return $name === 'bajon de azucar' || $name === 'bajo de azucar';
        });
    }

    private static function maybeApplyRandomInfection(int $userId, int $chuchemonId): ?array
    {
        $taxaInfeccio = GameSetting::getInt('taxa_infeccio', 12);

        if ($taxaInfeccio <= 0 || rand(1, 100) > $taxaInfeccio) {
            return null;
        }

        $activeMalaltiaIds = UserInfection::query()
            ->where('user_id', $userId)
            ->where('chuchemon_id', $chuchemonId)
            ->where('is_active', true)
            ->pluck('malaltia_id');

        $malaltia = Malaltia::query()
            ->when($activeMalaltiaIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $activeMalaltiaIds))
            ->inRandomOrder()
            ->first();

        if (!$malaltia) {
            return null;
        }

        $infection = UserInfection::create([
            'user_id' => $userId,
            'chuchemon_id' => $chuchemonId,
            'malaltia_id' => $malaltia->id,
            'infection_percentage' => rand(10, 50),
            'is_active' => true,
            'infected_at' => now(),
        ]);

        return [
            'id' => $malaltia->id,
            'name' => $malaltia->name,
            'type' => $malaltia->type,
            'severity' => $malaltia->severity,
            'infection_percentage' => $infection->infection_percentage,
        ];
    }

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

            $activeInfections = self::mapActiveInfections($user->id, [$chuchemonId])->get($chuchemonId, collect());
            $cannotEat = self::hasAtraconInfection($activeInfections);

            return response()->json([
                'level'                   => $uc->level,
                'experience'              => $uc->experience,
                'experience_for_next_level' => $uc->experience_for_next_level,
                'experience_progress'     => round(($uc->experience / $uc->experience_for_next_level) * 100, 2),
                'current_hp'              => $uc->current_hp ?? $uc->max_hp,
                'max_hp'                  => $uc->max_hp ?? 105,
                'active_infections'       => $activeInfections->values()->all(),
                'has_active_infections'   => $activeInfections->isNotEmpty(),
                'cannot_eat'              => $cannotEat,
                'cannot_eat_reason'       => $cannotEat ? 'Atracón activo: este Xuxemon no puede comer más Xuxes por ahora.' : null,
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
                    'user_chuchemons.attack_boost',
                    'user_chuchemons.defense_boost',
                )
                ->get();

            $userId = $user->id;
            $infectionMap = self::mapActiveInfections($userId, $chuchemons->pluck('id')->all());
            $chuchemons = $chuchemons->map(function ($c) use ($userId) {
                    $baseAtk = $c->attack ?? 50;
                    $baseDef = $c->defense ?? 50;
                    $mida    = $c->current_mida ?? 'Petit';
                    $maxHp   = $c->max_hp   ?? self::computeMaxHp($baseDef, $c->level, $mida);
                    $currHp  = $c->current_hp ?? $maxHp;

                    $c->experience_progress   = round(($c->experience / $c->experience_for_next_level) * 100, 2);
                    $atkBoost = ($c->attack_boost ?? 0) / 100;
                    $defBoost = ($c->defense_boost ?? 0) / 100;
                    $c->effective_attack      = round(self::effectiveAttack($baseAtk, $mida) * (1 + $atkBoost), 1);
                    $c->effective_defense     = round(self::effectiveDefense($baseDef, $mida) * (1 + $defBoost), 1);
                    $c->max_hp                = $maxHp;
                    $c->current_hp            = min($currHp, $maxHp);
                    $c->hp_percent            = round(($c->current_hp / $maxHp) * 100, 1);

                    // Per-type xux quantities
                    $c->xuxes_maduixa = MochilaXux::where('user_id', $userId)->where('item_id', 1)->sum('quantity');
                    $c->xuxes_llimona = MochilaXux::where('user_id', $userId)->where('item_id', 2)->sum('quantity');
                    $c->xuxes_cola    = MochilaXux::where('user_id', $userId)->where('item_id', 3)->sum('quantity');
                    $c->xuxes_exp     = MochilaXux::where('user_id', $userId)->where('item_id', 6)->sum('quantity');
                    $c->xuxes_qty     = $c->xuxes_maduixa + $c->xuxes_llimona + $c->xuxes_cola + $c->xuxes_exp;

                    return $c;
                })->map(function ($c) use ($infectionMap) {
                    $activeInfections = $infectionMap->get($c->id, collect());
                    $cannotEat = self::hasAtraconInfection($activeInfections);
                    $hasBajon  = self::hasBajonDeAzucar($activeInfections);

                    $c->active_infections = $activeInfections->values()->all();
                    $c->has_active_infections = $activeInfections->isNotEmpty();
                    $c->cannot_eat = $cannotEat;
                    $c->cannot_eat_reason = $cannotEat
                        ? 'Atracón activo: este Xuxemon no puede alimentarse.'
                        : null;
                    $c->has_bajon = $hasBajon;
                    $c->evolve_cost_extra = $hasBajon ? 2 : 0;

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
            if ($qty < 1) return response()->json(['message' => 'Cantidad no válida'], 422);

            $activeInfections = self::mapActiveInfections($user->id, [$chuchemonId])->get($chuchemonId, collect());
            if (self::hasAtraconInfection($activeInfections)) {
                return response()->json([
                    'message' => 'Atracón activo: este Xuxemon no puede comer más Xuxes por ahora.',
                    'active_infections' => $activeInfections->values()->all(),
                ], 422);
            }

            // Check the user has enough Xux Exp (item_id=6)
            $mochilaXux = MochilaXux::where('user_id', $user->id)
                ->where('item_id', 6)
                ->first();

            if (!$mochilaXux || $mochilaXux->quantity < $qty) {
                return response()->json([
                    'message' => 'No tienes suficientes Xux Exp.',
                    'have'    => $mochilaXux?->quantity ?? 0,
                    'need'    => $qty,
                ], 422);
            }

            // Consume xuxes
            $mochilaXux->quantity -= $qty;
            $mochilaXux->quantity <= 0 ? $mochilaXux->delete() : $mochilaXux->save();

            // Add experience (20 XP per xux)
            $xpToAdd = $qty * 20;

            $response = $this->addExperience($chuchemonId, $xpToAdd);
            $payload = $response->getData(true);

            if ($response->getStatusCode() >= 400) {
                return $response;
            }

            $newInfection = self::maybeApplyRandomInfection($user->id, $chuchemonId);
            if ($newInfection) {
                $payload['infection_triggered'] = $newInfection;
                $payload['message'] = ($payload['message'] ?? 'Experiencia añadida') . ' Además, el Xuxemon ha contraído una enfermedad.';
            }

            return response()->json($payload, $response->getStatusCode());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cura el Xuxemon gastant Xux de Maduixa del inventari (+20 HP per Xux, fins a max_hp)
     * POST /api/user/chuchemons/{id}/heal  — body: { quantity: N }
     */
    public function healChuchemon(Request $request, int $chuchemonId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['message' => 'Usuario no autenticado'], 401);

            $qty = (int) ($request->input('quantity', 1));
            if ($qty < 1) return response()->json(['message' => 'Cantidad no válida'], 422);

            // Atracón blocks feeding
            $activeInfections = self::mapActiveInfections($user->id, [$chuchemonId])->get($chuchemonId, collect());
            if (self::hasAtraconInfection($activeInfections)) {
                return response()->json([
                    'message' => 'Atracón activo: este Xuxemon no puede alimentarse.',
                    'active_infections' => $activeInfections->values()->all(),
                ], 422);
            }

            // Only Xux de Maduixa (item_id=1) heals HP
            $maduixaRow = MochilaXux::where('user_id', $user->id)
                ->where('item_id', 1)
                ->first();

            if (!$maduixaRow || $maduixaRow->quantity < $qty) {
                return response()->json([
                    'message' => 'No tienes suficientes Xux de Maduixa para curar.',
                    'have'    => $maduixaRow?->quantity ?? 0,
                    'need'    => $qty,
                ], 422);
            }

            $uc = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$uc) return response()->json(['message' => 'Xuxemon no encontrado en tu colección'], 404);

            $maxHp  = $uc->max_hp  ?? 105;
            $currHp = $uc->current_hp ?? $maxHp;

            if ($currHp >= $maxHp) {
                return response()->json([
                    'message'    => '¡El Xuxemon ya tiene la vida llena!',
                    'current_hp' => $currHp,
                    'max_hp'     => $maxHp,
                ], 422);
            }

            $healAmount = $qty * 20;
            $newHp      = min($currHp + $healAmount, $maxHp);
            $actualHeal = $newHp - $currHp;

            // Only consume xuxes needed (don't waste if already near full)
            $xuxesUsed = (int) ceil($actualHeal / 20);
            $maduixaRow->quantity -= $xuxesUsed;
            $maduixaRow->quantity <= 0 ? $maduixaRow->delete() : $maduixaRow->save();

            DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->update(['current_hp' => $newHp]);

            return response()->json([
                'message'      => "¡Has curado {$actualHeal} PS al Xuxemon!",
                'healed'       => $actualHeal,
                'current_hp'   => $newHp,
                'max_hp'       => $maxHp,
                'xuxes_used'   => $xuxesUsed,
                'xuxes_left'   => $maduixaRow->exists ? $maduixaRow->quantity : 0,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Aplica Xux de Llimona: +10% atac temporal (guarda boost a user_chuchemons)
     * POST /api/user/chuchemons/{id}/boost-attack  — body: { quantity: N }
     */
    public function boostAttack(Request $request, int $chuchemonId): JsonResponse
    {
        return $this->applyBuff($request, $chuchemonId, 'attack', 2, 'Xux de Llimona');
    }

    /**
     * Aplica Xux de Cola: +10% defensa temporal (guarda boost a user_chuchemons)
     * POST /api/user/chuchemons/{id}/boost-defense  — body: { quantity: N }
     */
    public function boostDefense(Request $request, int $chuchemonId): JsonResponse
    {
        return $this->applyBuff($request, $chuchemonId, 'defense', 3, 'Xux de Cola');
    }

    private function applyBuff(Request $request, int $chuchemonId, string $stat, int $itemId, string $itemName): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['message' => 'Usuario no autenticado'], 401);

            $qty = (int) ($request->input('quantity', 1));
            if ($qty < 1) return response()->json(['message' => 'Cantidad no válida'], 422);

            // Atracón blocks feeding
            $activeInfections = self::mapActiveInfections($user->id, [$chuchemonId])->get($chuchemonId, collect());
            if (self::hasAtraconInfection($activeInfections)) {
                return response()->json([
                    'message' => 'Atracón activo: este Xuxemon no puede alimentarse.',
                    'active_infections' => $activeInfections->values()->all(),
                ], 422);
            }

            $row = MochilaXux::where('user_id', $user->id)
                ->where('item_id', $itemId)
                ->first();

            if (!$row || $row->quantity < $qty) {
                return response()->json([
                    'message' => "No tienes suficientes {$itemName}.",
                    'have'    => $row?->quantity ?? 0,
                    'need'    => $qty,
                ], 422);
            }

            $uc = DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if (!$uc) return response()->json(['message' => 'Xuxemon no encontrado en tu colección'], 404);

            // Each xux gives +10% boost (stacks up to max 50%)
            $boostColumn = $stat === 'attack' ? 'attack_boost' : 'defense_boost';
            $currentBoost = $uc->$boostColumn ?? 0;
            $addBoost = $qty * 10;
            $newBoost = min($currentBoost + $addBoost, 50);
            $actualBoost = $newBoost - $currentBoost;

            $xuxesUsed = (int) ceil($actualBoost / 10);
            $row->quantity -= $xuxesUsed;
            $row->quantity <= 0 ? $row->delete() : $row->save();

            DB::table('user_chuchemons')
                ->where('user_id', $user->id)
                ->where('chuchemon_id', $chuchemonId)
                ->update([$boostColumn => $newBoost]);

            $statLabel = $stat === 'attack' ? 'ataque' : 'defensa';

            return response()->json([
                'message'    => "¡+{$actualBoost}% de {$statLabel} temporal para el Xuxemon!",
                'boost'      => $newBoost,
                'stat'       => $stat,
                'xuxes_used' => $xuxesUsed,
                'xuxes_left' => $row->exists ? $row->quantity : 0,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
