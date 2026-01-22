<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>L&A Flow</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">

    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
</head>
<body class="bg-gray-100 antialiased flex items-center justify-center min-h-screen">
    
    <div class="bg-white p-10 rounded-3xl shadow-xl border border-gray-100 text-center max-w-md animate-fadeIn">
        <div class="w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-indigo-200">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
        </div>
        
        <h1 class="text-3xl font-black text-gray-900 tracking-tighter uppercase mb-2">L&A Flow</h1>
        <p class="text-gray-500 font-medium mb-8 uppercase text-[10px] tracking-widest">Gestão Jurídica Inteligente</p>

        @if (Route::has('login'))
            <div class="space-y-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="block w-full py-4 bg-gray-900 text-white rounded-xl font-bold uppercase text-xs tracking-widest hover:bg-indigo-600 transition-all">Entrar no Painel</a>
                @else
                    <a href="{{ route('login') }}" class="block w-full py-4 bg-gray-900 text-white rounded-xl font-bold uppercase text-xs tracking-widest hover:bg-indigo-600 transition-all">Acessar Sistema</a>
                @endauth
            </div>
        @endif
    </div>

</body>
</html>