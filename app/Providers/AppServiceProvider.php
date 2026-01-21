<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; // CERTIFICA-TE QUE É ESTE FACADE
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // O nome aqui tem de ser 'admin-access' (com hífen)
        Gate::define('gerir-equipe', function (User $user) {
            return $user->role === 'admin';
        });
        
    }
}