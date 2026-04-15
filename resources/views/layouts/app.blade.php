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
        body {
            font-family: 'Inter', sans-serif;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="bg-slate-50 antialiased text-slate-900" x-data="{ sidebarOpen: false }">

    <div class="flex min-h-screen overflow-hidden">

        {{-- Overlay escuro no mobile quando o menu está aberto --}}
        <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
            class="fixed inset-0 bg-lacerda-dark/80 backdrop-blur-sm z-40 md:hidden" style="display: none;"></div>

        {{-- BARRA LATERAL (SIDEBAR) --}}
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 transform transition-transform duration-300 ease-in-out md:translate-x-0 md:static md:flex md:flex-col shrink-0 h-screen">

            {{-- Logo --}}
            <div class="p-6 flex items-center gap-3 shrink-0">
                {{-- A sua Imagem Logo substituindo o ícone roxo --}}
                <div class="w-9 h-9 flex items-center justify-center">
                    <img src="{{ asset('img/logo.png') }}" alt="L&A" class="w-full h-full object-contain drop-shadow-sm">
                </div>
                <span class="text-xl font-black text-lacerda-dark tracking-tighter uppercase">L&A FLOW</span>

                {{-- Botão fechar no mobile --}}
                <button @click="sidebarOpen = false" class="md:hidden ml-auto text-slate-400 hover:text-lacerda-teal p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            {{-- Links de Navegação (Sidebar Desktop) --}}
            <nav class="flex-1 px-4 space-y-1.5 overflow-y-auto no-scrollbar">
                
                <a href="{{ route('dashboard') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all {{ request()->routeIs('dashboard') ? 'bg-lacerda-teal text-white font-bold shadow-md shadow-lacerda-teal/20' : 'text-slate-500 hover:bg-slate-50 hover:text-lacerda-teal font-semibold' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                        </path>
                    </svg>
                    Dashboard
                </a>

                <a href="{{ route('clientes.index') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all {{ request()->routeIs('clientes.*') ? 'bg-lacerda-teal text-white font-bold shadow-md shadow-lacerda-teal/20' : 'text-slate-500 hover:bg-slate-50 hover:text-lacerda-teal font-semibold' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 005.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                    Clientes
                </a>

                <a href="{{ route('processos.index') }}"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all {{ request()->routeIs('processos.*') ? 'bg-lacerda-teal text-white font-bold shadow-md shadow-lacerda-teal/20' : 'text-slate-500 hover:bg-slate-50 hover:text-lacerda-teal font-semibold' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    Processos
                </a>

                {{-- Link Financeiro (Destacado em Dourado/Escuro) --}}
                @can('acesso-financeiro')
                    <a href="{{ route('financeiro.index') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all {{ request()->routeIs('financeiro.*') ? 'bg-lacerda-dark text-lacerda-gold font-bold shadow-md shadow-lacerda-dark/20' : 'text-slate-500 hover:bg-slate-50 hover:text-lacerda-gold font-semibold' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                            </path>
                        </svg>
                        Financeiro
                    </a>
                @endcan

                {{-- Link Equipe --}}
                @can('gerir-equipe')
                    <a href="{{ route('users.index') }}"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-all {{ request()->routeIs('users.index') ? 'bg-lacerda-teal text-white font-bold shadow-md shadow-lacerda-teal/20' : 'text-slate-500 hover:bg-slate-50 hover:text-lacerda-teal font-semibold' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                            </path>
                        </svg>
                        Equipe
                    </a>
                @endcan
            </nav>

            {{-- Perfil do Usuário Sidebar --}}
            <div class="p-4 border-t border-slate-200 shrink-0">
                <div class="flex items-center gap-3 px-2">
                    {{-- Ícone do Utilizador (Escuro e Dourado) --}}
                    <div
                        class="w-9 h-9 rounded-full bg-lacerda-dark text-lacerda-gold border border-lacerda-gold/30 flex items-center justify-center font-bold text-sm uppercase shrink-0">
                        {{ mb_substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="text-sm font-bold text-lacerda-dark truncate">{{ Auth::user()->name }}</p>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-rose-500 transition">Sair
                                do Sistema</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        {{-- CONTEÚDO PRINCIPAL --}}
        <main class="flex-1 flex flex-col min-w-0 overflow-y-auto h-screen">

            {{-- HEADER MOBILE --}}
            <header
                class="md:hidden bg-white border-b border-slate-200 sticky top-0 z-30 flex items-center justify-between p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    {{-- Logo no Mobile --}}
                    <div class="w-8 h-8 flex items-center justify-center">
                        <img src="{{ asset('img/logo.png') }}" alt="L&A" class="w-full h-full object-contain">
                    </div>
                    <span class="text-lg font-black text-lacerda-dark tracking-tighter uppercase">L&A FLOW</span>
                </div>

                <button @click="sidebarOpen = true" class="text-slate-500 hover:text-lacerda-teal focus:outline-none p-1">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </header>

            {{-- Slot de Conteúdo (Renderiza o Financeiro, Clientes, etc) --}}
            <div class="flex-1 w-full relative">
                {{ $slot }}
            </div>

        </main>
    </div>
</body>

</html>