<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteController;

// 1. Página Inicial (Pública)
Route::get('/', function () {
    return view('welcome');
});
Route::get('/debug-role', function () {
    if (auth()->check()) {
        return 'O teu cargo atual no banco de dados é: ' . auth()->user()->role;
    }
    return 'Tu não estás logado! Por favor, faz login primeiro.';
})->middleware('auth');
// 2. Dashboard (Logado)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// 3. Rotas de Perfil do Utilizador (Acessível a TODOS os logados)
Route::middleware('auth')->group(function () {

    // Rotas de Clientes
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    //rotas perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// 4. ÁREA ADMINISTRATIVA L&A FLOW (Apenas Advogados)
// 4. ÁREA ADMINISTRATIVA L&A FLOW (Apenas Administradores)
Route::middleware(['auth', 'can:gerir-equipe'])->group(function () {

    // Esta rota única agora servirá para ver a lista e o formulário (via Volt)
    Route::get('/equipe', function () {
        return view('equipe.index');
    })->name('users.index');

    // Nota: Como estamos a usar Volt, não precisamos mais do UserController@store aqui
});

require __DIR__ . '/auth.php';