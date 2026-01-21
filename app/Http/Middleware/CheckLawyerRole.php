<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLawyerRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Se o utilizador estiver logado e for advogado, ele passa
        if (auth()->check() && auth()->user()->role === 'advogado') {
            return $next($request);
        }

        // Se não for, mandamos de volta para o dashboard com um aviso
        return redirect('/dashboard')->with('error', 'Acesso restrito a advogados.');
    }
}
