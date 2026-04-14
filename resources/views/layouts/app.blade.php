<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>L&A Flow</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Esconde a scrollbar da sidebar para um visual mais limpo */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="bg-slate-50 antialiased text-slate-900" x-data="{ sidebarOpen: false }">
    
    <div class="flex min-h-screen overflow-hidden">

        {{-- Overlay escuro no mobile quando o menu está aberto --}}
        <div x-show="sidebarOpen" 
             x-transition.opacity
             @click="sidebarOpen = false"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 md:hidden" 
             style="display: none;"></div>

        {{-- BARRA LATERAL (SIDEBAR) --}}
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 transform transition-transform duration-300 ease-in-out md:translate-x-0 md:static md:flex md:flex-col shrink-0 h-screen">
            
            {{-- Logo --}}
            <div class="p-6 flex items-center gap-3 shrink-0">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center shadow-lg shadow-indigo-200">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <span class="text-xl font-extrabold text-slate-900 tracking-tighter uppercase">L&A FLOW</span>
                
                {{-- Botão fechar no mobile --}}
                <button @click="sidebarOpen = false" class="md:hidden ml-auto text-slate-400 hover:text-slate-600 p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            {{-- Links de Navegação --}}
            <nav class="flex-1 px-4 space-y-1.5 overflow-y-auto no-scrollbar">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 font-semibold' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>

                <a href="{{ route('clientes.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all {{ request()->routeIs('clientes.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 font-semibold' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 005.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Clientes
                </a>

                <a href="{{ route('processos.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all {{ request()->routeIs('processos.*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 font-semibold' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Processos
                </a>

                @can('gerir-equipe')
                    <a href="{{ route('users.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all {{ request()->routeIs('users.index') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 font-semibold' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Equipe
                    </a>
                @endcan
            </nav>

            {{-- Perfil do Usuário no rodapé da Sidebar --}}
            <div class="p-4 border-t border-slate-200 shrink-0">
                <div class="flex items-center gap-3 px-2">
                    <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200 flex items-center justify-center font-bold text-sm uppercase shrink-0">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="text-sm font-bold text-slate-900 truncate">{{ Auth::user()->name }}</p>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-[10px] font-black text-rose-500 uppercase tracking-widest hover:text-rose-700 transition">Sair do Sistema</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        {{-- CONTEÚDO PRINCIPAL --}}
        <main class="flex-1 flex flex-col min-w-0 overflow-y-auto h-screen">
            
            {{-- HEADER MOBILE (Só aparece em telas pequenas) --}}
            <header class="md:hidden bg-white border-b border-slate-200 sticky top-0 z-30 flex items-center justify-between p-4 shadow-sm">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 bg-indigo-600 rounded flex items-center justify-center shadow-md">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <span class="text-lg font-black text-slate-900 tracking-tighter">L&A FLOW</span>
                </div>
                
                {{-- Botão Hambúrguer --}}
                <button @click="sidebarOpen = true" class="text-slate-500 hover:text-indigo-600 focus:outline-none p-1">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </header>

            {{-- Renderiza o conteúdo do Livewire/Página --}}
            <div class="flex-1 w-full relative">
                {{ $slot }}
            </div>
            
        </main>
    </div>
</body>
</html>