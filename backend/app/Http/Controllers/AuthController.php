<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Chuchemon;
use App\Models\UserTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // ─── REGISTRO ─────────────────────────────────────────
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [   
            'nombre'            => 'required|string|max:255',
            'apellidos'         => 'required|string|max:255',
            'email'             => 'required|email|unique:users',  //Validador de usuario existente
            'password'          => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Generar player_id único: #NomXXXX
            $nombreSinEspacios = str_replace(' ', '', $request->nombre);
            do {
                $numero   = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $playerId = '#' . $nombreSinEspacios . $numero;
            } while (User::where('player_id', $playerId)->exists());

            // El primer usuario registrado será admin
            $isAdmin = User::count() === 0;

            $user = User::create([
                'nombre'       => $request->nombre,
                'apellidos'    => $request->apellidos,
                'email'        => $request->email,
                'password'     => Hash::make($request->password),  //Encriptar contraseña 
                'player_id'    => $playerId,
                'is_admin'     => $isAdmin,
                'last_seen_at' => now(),
            ]);

            $token = JWTAuth::fromUser($user);

            // Give 3 random Petit chuchemons as starters
            $starters = Chuchemon::where('mida', 'Petit')->inRandomOrder()->limit(3)->get();
            $starterIds = [];
            foreach ($starters as $chuchemon) {
                DB::table('user_chuchemons')->insert([
                    'user_id'       => $user->id,
                    'chuchemon_id'  => $chuchemon->id,
                    'count'         => 1,
                    'current_mida'  => 'Petit',
                    'level'         => 1,
                    'experience'    => 0,
                    'experience_for_next_level' => LevelingController::experienceForMida('Petit'),
                    'current_hp'    => LevelingController::computeMaxHp($chuchemon->defense ?? 50, 1, 'Petit'),
                    'max_hp'        => LevelingController::computeMaxHp($chuchemon->defense ?? 50, 1, 'Petit'),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $starterIds[] = $chuchemon->id;
            }

            // Auto-equip starters to team
            UserTeam::create([
                'user_id'         => $user->id,
                'chuchemon_1_id'  => $starterIds[0] ?? null,
                'chuchemon_2_id'  => $starterIds[1] ?? null,
                'chuchemon_3_id'  => $starterIds[2] ?? null,
            ]);

            return response()->json([
                'message' => 'Usuario registrado correctamente',
                'user'    => $user,
                'token'   => $token,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el usuario: ' . $e->getMessage()], 500);
        }
    }

    // ─── LOGIN ────────────────────────────────────────────
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|string',
            'password'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('player_id', $request->player_id)->first();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Contraseña incorrecta'], 401);
        }

        $user->forceFill(['last_seen_at' => now()])->save();

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Login correcto',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    // ─── DATOS DEL USUARIO AUTENTICADO ───────────────────
    public function me()
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->forceFill(['last_seen_at' => now()])->save();

        return response()->json($user->fresh());
    }

    // ─── LOGOUT ──────────────────────────────────────────
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}