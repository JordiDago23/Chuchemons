<?php

namespace App\Http\Controllers;

use App\Models\GameSetting;
use App\Models\Item;
use App\Models\Malaltia;
use App\Models\MochilaXux;
use App\Models\UserInfection;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Facades\JWTAuth;

class LevelingController extends Controller
{
    private static array $itemIdsByName = [];

    public const XP_PER_CANDY = 50;

    public static function normalizeMalaltiaName(?string $name): string
    {
        return str_replace(
            ['á', 'à', 'é', 'è', 'í', 'ì', 'ó', 'ò', 'ú', 'ù'],
            ['a', 'a', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u'],
            mb_strtolower((string) $name)
        );
    }

    private static function itemId(string $itemName): ?int
    {
        if (!array_key_exists($itemName, self::$itemIdsByName)) {
            self::$itemIdsByName[$itemName] = Item::idByName($itemName);
        }

        return self::$itemIdsByName[$itemName];
    }

    private static function itemQuantity(int $userId, string $itemName): int
    {
        $itemId = self::itemId($itemName);
        if (!$itemId) {
            return 0;
        }

        return (int) MochilaXux::where('user_id', $userId)->where('item_id', $itemId)->sum('quantity');
    }

    public static function experienceForMida(string $currentMida): int
    {
        return match ($currentMida) {
            'Mitjà' => GameSetting::getInt('xp_mitja_gran', 250),
            'Gran' => 0, // Gran no puede evolucionar más
            default => GameSetting::getInt('xp_petit_mitja', 150),
        };
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
                'malalties.id as malaltia_id',
                'malalties.name',
                'malalties.type'
            )
            ->get()
            ->groupBy('chuchemon_id')
            ->map(function ($infections) {
                return $infections->map(function ($infection) {
                    return [
                        'id' => $infection->malaltia_id,
                        'name' => $infection->name,
                        'type' => $infection->type,
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
        // Get active infections to avoid duplicates
        $activeMalaltiaIds = UserInfection::query()
            ->where('user_id', $userId)
            ->where('chuchemon_id', $chuchemonId)
            ->where('is_active', true)
            ->pluck('malaltia_id');

        // Get candidate diseases (not already infected)
        $candidates = Malaltia::query()
            ->when($activeMalaltiaIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $activeMalaltiaIds))
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Apply infection with default rate
        $defaultRate = GameSetting::getInt('taxa_infeccio', 12);
        
        // Roll for each candidate disease individually
        $infected = null;
        foreach ($candidates as $malaltia) {
            if (rand(1, 100) <= $defaultRate) {
                $infected = $malaltia;
                break; // Only one infection per action
            }
        }

        if (!$infected) {
            return null;
        }

        // If Sobredosis de sucre → downgrade mida
        $originalMida = null;
        $normalizedName = self::normalizeMalaltiaName($infected->name);
        if ($normalizedName === 'sobredosis de sucre') {
            $uc = DB::table('user_chuchemons')
                ->where('user_id', $userId)
                ->where('chuchemon_id', $chuchemonId)
                ->first();

            if ($uc) {
                $originalMida = $uc->current_mida;
                $newMida = match ($uc->current_mida) {
                    'Gran'  => 'Mitjà',
                    'Mitjà' => 'Petit',
                    default => 'Petit',
                };

                if ($newMida !== $uc->current_mida) {
                    $chuchemon = \App\Models\Chuchemon::find($chuchemonId);
                    $baseDefense = $chuchemon->defense ?? 50;
                    $newMaxHp = self::computeMaxHp($baseDefense, $uc->level, $newMida);
                    $newCurrentHp = min($uc->current_hp, $newMaxHp);

                    DB::table('user_chuchemons')
                        ->where('user_id', $userId)
                        ->where('chuchemon_id', $chuchemonId)
                        ->update([
                            'current_mida' => $newMida,
                            'max_hp'       => $newMaxHp,
                            'current_hp'   => $newCurrentHp,
                            'updated_at'   => now(),
                        ]);
                }
            }
        }

        $infection = UserInfection::create([
            'user_id' => $userId,
            'chuchemon_id' => $chuchemonId,
            'malaltia_id' => $infected->id,
            'original_mida' => $originalMida,
            'is_active' => true,
            'infected_at' => now(),
        ]);

        return [
            'id' => $infected->id,
            'name' => $infected->name,
            'type' => $infected->type,
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
                'experience_progress'     => $uc->experience_for_next_level > 0
                    ? round(($uc->experience / $uc->experience_for_next_level) * 100, 2)
                    : 0,
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

            // Solo mostrar los Chuchemons que están en el equipo activo del usuario
            $team = DB::table('user_teams')->where('user_id', $user->id)->first();
            $teamIds = $team
                ? array_filter([
                    $team->chuchemon_1_id,
                    $team->chuchemon_2_id,
                    $team->chuchemon_3_id,
                ], fn($id) => !is_null($id))
                : [];

            $chuchemons = DB::table('user_chuchemons')
                ->join('chuchemons', 'user_chuchemons.chuchemon_id', '=', 'chuchemons.id')
                ->where('user_chuchemons.user_id', $user->id)
                ->when(count($teamIds) > 0, fn($q) => $q->whereIn('chuchemons.id', $teamIds))
                ->when(count($teamIds) === 0, fn($q) => $q->whereRaw('1=0')) // sin equipo → lista vacía
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

                    $c->experience_progress   = $c->experience_for_next_level > 0
                        ? round(($c->experience / $c->experience_for_next_level) * 100, 2)
                        : 0;
                    $atkBoost = ($c->attack_boost ?? 0) / 100;
                    $defBoost = ($c->defense_boost ?? 0) / 100;
                    $c->effective_attack      = round(self::effectiveAttack($baseAtk, $mida) * (1 + $atkBoost), 1);
                    $c->effective_defense     = round(self::effectiveDefense($baseDef, $mida) * (1 + $defBoost), 1);
                    $c->max_hp                = $maxHp;
                    $c->current_hp            = min($currHp, $maxHp);
                    $c->hp_percent            = round(($c->current_hp / $maxHp) * 100, 1);

                    // Per-type xux quantities
                    $c->xuxes_maduixa = self::itemQuantity($userId, Item::NAME_XUX_MADUIXA);
                    $c->xuxes_llimona = self::itemQuantity($userId, Item::NAME_XUX_LLIMONA);
                    $c->xuxes_cola    = self::itemQuantity($userId, Item::NAME_XUX_COLA);
                    $c->xuxes_exp     = self::itemQuantity($userId, Item::NAME_XUX_EXP);
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

            // Incluir configuración de costos de evolución
            $config = [
                'xux_petit_mitja' => GameSetting::getInt('xux_petit_mitja', 3),
                'xux_mitja_gran' => GameSetting::getInt('xux_mitja_gran', 5),
            ];

            return response()->json([
                'chuchemons' => $chuchemons,
                'config' => $config
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─── Mutations ────────────────────────────────────────────────────────────

    /**
     * Agrega experiencia y verifica si sube de nivel (actualiza max_hp al subir)
     * Además, verifica si tiene suficiente XP para evolucionar de tamaño y lo hace automáticamente
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

            $oldMida   = $uc->current_mida ?? 'Petit';
            $newXp     = $uc->experience + $experienceAmount;
            $level     = $uc->level;
            $xpForNext = $uc->experience_for_next_level ?: self::experienceForMida($oldMida);
            $leveledUp = false;
            $evolved   = false;
            $currentMida = $oldMida;

            // Check if should auto-evolve based on XP threshold BEFORE level-up
            $xpForEvolution = self::experienceForMida($oldMida);
            $activeInfections = self::mapActiveInfections($user->id, [$chuchemonId])->get($chuchemonId, collect());
            $hasAtracon = $activeInfections->contains(fn ($inf) => self::normalizeMalaltiaName($inf['name'] ?? null) === 'atracon');

            if (!$hasAtracon && $newXp >= $xpForEvolution && $xpForEvolution > 0 && $oldMida !== 'Gran') {
                // Determine next size
                $nextMida = match ($oldMida) {
                    'Petit' => 'Mitjà',
                    'Mitjà' => 'Gran',
                    default => $oldMida,
                };

                if ($nextMida !== $oldMida) {
                    // Reset experience for new size
                    $newXp = 0;
                    $xpForNext = self::experienceForMida($nextMida);
                    $currentMida = $nextMida;
                    $evolved = true;

                    // Update size in database immediately
                    DB::table('user_chuchemons')
                        ->where('user_id', $user->id)
                        ->where('chuchemon_id', $chuchemonId)
                        ->update([
                            'current_mida'   => $nextMida,
                            'evolution_count' => DB::raw('evolution_count + 1'),
                            'experience_for_next_level' => $xpForNext,
                        ]);
                }
            } else {
                // No evolution, check for level up
                while ($newXp >= $xpForNext && $xpForNext > 0) {
                    $newXp    -= $xpForNext;
                    $level++;
                    $xpForNext = self::experienceForMida($currentMida);
                    $leveledUp = true;
                }
            }

            // Calculate HP (use new mida if evolved)
            $maxHp  = self::computeMaxHp($uc->defense ?? 50, $level, $currentMida);
            $currHp = $uc->current_hp ?? $maxHp;

            // On level up, restore HP bonus
            if ($leveledUp) {
                $currHp = min($currHp + 10, $maxHp);
            }

            // On evolution, scale HP proportionally
            if ($evolved) {
                $oldMaxHp = self::computeMaxHp($uc->defense ?? 50, $level, $oldMida);
                $oldCurrHp = $currHp;
                $newMaxHp = $maxHp;
                $currHp = (int) round(($oldCurrHp / max($oldMaxHp, 1)) * $newMaxHp);
                $currHp = max(1, min($currHp, $newMaxHp));
            }

            // Update all values
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

            // Generate appropriate message
            $message = '¡Experiencia añadida!';
            if ($evolved) {
                $message = "¡Tu Xuxemon ha evolucionado a {$currentMida}!";
            } elseif ($leveledUp) {
                $message = '¡Xuxemon ha subido de nivel!';
            }

            return response()->json([
                'message'                 => $message,
                'level'                   => $level,
                'experience'              => $newXp,
                'experience_for_next_level' => $xpForNext,
                'level_up'                => $leveledUp,
                'evolved'                 => $evolved,
                'current_mida'            => $currentMida,
                'current_hp'              => $currHp,
                'max_hp'                  => $maxHp,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
    * Gasta N Xuxes del inventari per donar +50 XP cada un
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

            $xuxExpItemId = self::itemId(Item::NAME_XUX_EXP);
            if (!$xuxExpItemId) {
                return response()->json(['message' => 'No existe el item Xux Exp en la base de datos.'], 409);
            }

            // Check the user has enough Xux Exp
            $mochilaXux = MochilaXux::where('user_id', $user->id)
                ->where('item_id', $xuxExpItemId)
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

            // Add experience (+50 XP per candy)
            $xpToAdd = $qty * self::XP_PER_CANDY;

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

            $payload['xp_gained'] = $xpToAdd;

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

            $maduixaItemId = self::itemId(Item::NAME_XUX_MADUIXA);
            if (!$maduixaItemId) {
                return response()->json(['message' => 'No existe el item Xux de Maduixa en la base de datos.'], 409);
            }

            // Only Xux de Maduixa heals HP
            $maduixaRow = MochilaXux::where('user_id', $user->id)
                ->where('item_id', $maduixaItemId)
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
        return $this->applyBuff($request, $chuchemonId, 'attack', Item::NAME_XUX_LLIMONA);
    }

    /**
     * Aplica Xux de Cola: +10% defensa temporal (guarda boost a user_chuchemons)
     * POST /api/user/chuchemons/{id}/boost-defense  — body: { quantity: N }
     */
    public function boostDefense(Request $request, int $chuchemonId): JsonResponse
    {
        return $this->applyBuff($request, $chuchemonId, 'defense', Item::NAME_XUX_COLA);
    }

    private function applyBuff(Request $request, int $chuchemonId, string $stat, string $itemName): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) return response()->json(['message' => 'Usuario no autenticado'], 401);

            $itemId = self::itemId($itemName);
            if (!$itemId) {
                return response()->json(['message' => "No existe {$itemName} en la base de datos."], 409);
            }

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
