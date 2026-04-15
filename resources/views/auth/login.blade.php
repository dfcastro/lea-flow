<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - Lacerda e Associados</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body
    class="bg-lacerda-dark antialiased flex items-center justify-center min-h-screen selection:bg-lacerda-teal selection:text-white p-4">

    {{-- Cartão Principal do Login --}}
    <div
        class="bg-white p-8 sm:p-10 rounded-3xl shadow-2xl shadow-black/40 border border-slate-100 w-full max-w-md transform transition-all animate-fadeIn">

        {{-- Cabeçalho do Cartão (Logo L&A e Títulos) --}}
        <div class="text-center mb-8">
            {{-- A sua nova Logo --}}
            <div class="flex justify-center mb-6">
                <img src="{{ asset('img/logo.png') }}" alt="Lacerda e Associados" class="h-24 w-auto drop-shadow-md">
            </div>
            
            <h1 class="text-2xl font-black text-lacerda-dark tracking-tight uppercase">Bem-vindo de volta</h1>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Insira as suas credenciais
                para continuar</p>
        </div>

        {{-- Status da Sessão (Exibe mensagens de erro/sucesso do Laravel) --}}
        <x-auth-session-status
            class="mb-4 text-sm font-bold text-emerald-600 bg-emerald-50 p-3 rounded-xl border border-emerald-200"
            :status="session('status')" />

        {{-- Formulário de Login --}}
        <form method="POST" action="{{ route('login') }}" class="space-y-6">
            @csrf

            {{-- Campo: Usuário --}}
            <div>
                <label for="username"
                    class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-2">Nome de
                    Usuário</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
                    autocomplete="username" placeholder="ex: joao.silva"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-lacerda-teal focus:ring-2 focus:ring-lacerda-teal/30 transition lowercase">
                <x-input-error :messages="$errors->get('username')" class="mt-2 text-[10px] font-bold text-rose-500" />
            </div>

            {{-- Campo: Senha --}}
            <div>
                <div class="flex justify-between items-center mb-2">
                    <label for="password"
                        class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider">Senha</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                            class="text-[10px] font-bold text-lacerda-gold hover:text-yellow-600 transition">Esqueceu a
                            senha?</a>
                    @endif
                </div>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-lacerda-teal focus:ring-2 focus:ring-lacerda-teal/30 transition">
                <x-input-error :messages="$errors->get('password')" class="mt-2 text-[10px] font-bold text-rose-500" />
            </div>

            {{-- Campo: Lembrar-me --}}
            <div class="flex items-center pt-1">
                <label for="remember_me" class="flex items-center cursor-pointer group">
                    <input id="remember_me" type="checkbox" name="remember"
                        class="w-4 h-4 rounded border-slate-300 text-lacerda-teal focus:ring-lacerda-teal transition cursor-pointer">
                    <span
                        class="ml-2 text-xs font-semibold text-slate-500 group-hover:text-lacerda-dark transition">Lembrar
                        o meu acesso</span>
                </label>
            </div>

            {{-- Botão de Submissão --}}
            <div class="pt-2">
                <button type="submit"
                    class="w-full py-3.5 bg-lacerda-teal text-white rounded-xl font-bold uppercase text-[11px] tracking-widest hover:bg-lacerda-dark hover:shadow-lg hover:shadow-lacerda-dark/30 transition-all active:scale-95 flex items-center justify-center gap-2">
                    Entrar no Sistema
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </button>
            </div>
        </form>

        {{-- Rodapé: Voltar ao Início --}}
        <div class="mt-8 text-center pt-6 border-t border-slate-100">
            <a href="/"
                class="text-[10px] font-bold text-slate-400 hover:text-lacerda-teal uppercase tracking-widest transition flex items-center justify-center gap-1.5 w-fit mx-auto">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar para o ecrã inicial
            </a>
        </div>
    </div>

</body>

</html>