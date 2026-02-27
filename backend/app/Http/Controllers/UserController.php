<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // ─── ACTUALIZAR PERFIL ────────────────────────────────
    public function update(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'nombre'    => 'sometimes|string|max:255',
            'apellidos' => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|unique:users,email,' . $user->id,
            'bio'       => 'sometimes|nullable|string|max:500',
            'password'  => 'sometimes|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('nombre'))    $user->nombre    = $request->nombre;
        if ($request->has('apellidos')) $user->apellidos = $request->apellidos;
        if ($request->has('email'))     $user->email     = $request->email;
        if ($request->has('bio'))       $user->bio       = $request->bio;
        if ($request->has('password'))  $user->password  = Hash::make($request->password);

        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user'    => $user,
        ]);
    }

    // ─── DARSE DE BAJA ────────────────────────────────────
    public function delete()
    {
        $user = auth()->user();
        auth()->logout();
        $user->delete();

        return response()->json(['message' => 'Cuenta eliminada correctamente']);
    }
}