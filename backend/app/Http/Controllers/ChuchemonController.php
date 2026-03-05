<?php

namespace App\Http\Controllers;

use App\Models\Chuchemon;
use Illuminate\Http\JsonResponse;

class ChuchemonController extends Controller
{
    /**
     * Obtiene todos los Chuchemons
     */
    public function index(): JsonResponse
    {
        $chuchemons = Chuchemon::all();
        return response()->json($chuchemons);
    }

    /**
     * Obtiene un Chuchemon por ID
     */
    public function show(int $id): JsonResponse
    {
        $chuchemon = Chuchemon::find($id);

        if (!$chuchemon) {
            return response()->json(['message' => 'Chuchemon not found'], 404);
        }

        return response()->json($chuchemon);
    }

    /**
     * Filtra Chuchemons por elemento
     */
    public function filterByElement(string $element): JsonResponse
    {
        $chuchemons = Chuchemon::where('element', $element)->get();
        return response()->json($chuchemons);
    }

    /**
     * Busca Chuchemons por nombre
     */
    public function search(string $query): JsonResponse
    {
        $chuchemons = Chuchemon::where('name', 'like', "%{$query}%")->get();
        return response()->json($chuchemons);
    }
}
