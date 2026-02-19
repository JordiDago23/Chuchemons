<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

// Ruta de salud para verificar que Laravel funciona
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $dbConnected = true;
        $message = 'Laravel está funcionando correctamente y conectado a MySQL';
    } catch (\Exception $e) {
        $dbConnected = false;
        $message = 'Error de conexión a la base de datos: ' . $e->getMessage();
    }

    return response()->json([
        'status' => 'OK',
        'database_connected' => $dbConnected,
        'message' => $message,
        'database_host' => config('database.connections.mysql.host'),
        'database_name' => config('database.connections.mysql.database'),
    ]);
});

// Ruta para probar la base de datos
Route::get('/test-db', function () {
    try {
        // Intentar obtener la lista de tablas
        $tables = DB::select('SHOW TABLES');
        
        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Conexión exitosa a MySQL',
            'database' => config('database.connections.mysql.database'),
            'tables_count' => count($tables),
            'tables' => $tables
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
});
