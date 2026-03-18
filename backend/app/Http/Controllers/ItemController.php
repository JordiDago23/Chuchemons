<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index()
    {
        return response()->json(Item::all());
    }

    public function show($id)
    {
        $item = Item::find($id);
        
        if (!$item) {
            return response()->json(['message' => 'Item no trobat'], 404);
        }

        return response()->json($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:items',
            'description' => 'nullable|string',
            'type' => 'required|in:apilable,no_apilable',
            'image' => 'nullable|string',
        ]);

        $item = Item::create($validated);

        return response()->json($item, 201);
    }

    public function update(Request $request, $id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json(['message' => 'Item no trobat'], 404);
        }

        $validated = $request->validate([
            'name' => 'string|unique:items,name,' . $id,
            'description' => 'nullable|string',
            'type' => 'in:apilable,no_apilable',
            'image' => 'nullable|string',
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json(['message' => 'Item no trobat'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Item eliminat correctament']);
    }
}
