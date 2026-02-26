<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // ─── REGISTRO ─────────────────────────────────────────
    public function register(Request $request)
    {
        \Log::info('Registro - Datos recibidos:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'nombre'            => 'required|string|max:255',
            'apellidos'         => 'required|string|max:255',
            'email'             => 'required|email|unique:users',
            'password'          => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            \Log::error('Registro - Errores de validación:', $validator->errors()->toArray());
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
                'nombre'    => $request->nombre,
                'apellidos' => $request->apellidos,
                'email'     => $request->email,
                'password'  => Hash::make($request->password),
                'player_id' => $playerId,
                'is_admin'  => $isAdmin,
            ]);

            $token = JWTAuth::fromUser($user);
            
            \Log::info('Registro exitoso - Usuario creado:', ['id' => $user->id, 'player_id' => $user->player_id]);

            return response()->json([
                'message' => 'Usuario registrado correctamente',
                'user'    => $user,
                'token'   => $token,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Registro - Error al crear usuario:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al crear el usuario: ' . $e->getMessage()], 500);
        }
    }

    // ─── LOGIN ────────────────────────────────────────────
    public function login(Request $request)
    {
        \Log::info('Login - Intento de login con player_id:', ['player_id' => $request->player_id]);
        
        $validator = Validator::make($request->all(), [
            'player_id' => 'required|string',
            'password'  => 'required|string',
        ]);

        if ($validator->fails()) {
            \Log::error('Login - Errores de validación:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('player_id', $request->player_id)->first();

        if (!$user) {
            \Log::warning('Login - Usuario no encontrado:', ['player_id' => $request->player_id]);
            return response()->json(['message' => 'Usuario no encontrado'], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            \Log::warning('Login - Contraseña incorrecta:', ['player_id' => $request->player_id]);
            return response()->json(['message' => 'Contraseña incorrecta'], 401);
        }

        $token = JWTAuth::fromUser($user);
        
        \Log::info('Login exitoso:', ['player_id' => $user->player_id, 'id' => $user->id]);

        return response()->json([
            'message' => 'Login correcto',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    // ─── DATOS DEL USUARIO AUTENTICADO ───────────────────
    public function me()
    {
        return response()->json(auth()->user());
    }

    // ─── LOGOUT ──────────────────────────────────────────
    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}