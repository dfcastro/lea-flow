<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lacerda & Associados | Advocacia e Consultoria Estratégica</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700;900&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .font-serif {
            font-family: 'Playfair Display', serif;
        }

        [x-cloak] {
            display: none !important;
        }

        .image-gray {
            filter: grayscale(100%) contrast(110%);
        }

        .image-gray:hover {
            filter: grayscale(0%);
            transition: 0.7s ease-in-out;
        }
    </style>
</head>

<body class="bg-white text-lacerda-dark antialiased selection:bg-lacerda-teal selection:text-white">

    {{-- BARRA DE NAVEGAÇÃO --}}
    <nav class="fixed top-0 w-full z-50 bg-white/95 backdrop-blur-md border-b border-slate-100 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-6 h-24 flex justify-between items-center">

            <div class="flex items-center gap-4">
                {{-- A sua nova Logo --}}
                <img src="{{ asset('img/logo.png') }}" alt="Logo L&A" class="w-12 h-12 object-contain">
                
                <div class="flex flex-col leading-tight">
                    <span class="text-lg font-black tracking-tight text-lacerda-dark uppercase">Lacerda <span
                            class="text-lacerda-gold font-light">&</span> Associados</span>
                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-[0.3em] mt-0.5">Advocacia e
                        Consultoria</span>
                </div>
            </div>

            <div class="flex items-center gap-10">
                <a href="#atuacao"
                    class="hidden md:block text-[11px] font-bold uppercase tracking-widest text-slate-500 hover:text-lacerda-teal transition border-b-2 border-transparent hover:border-lacerda-teal pb-1">Especialidades</a>

                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}"
                            class="text-[10px] font-bold uppercase tracking-widest px-6 py-2.5 bg-lacerda-dark text-white rounded hover:bg-lacerda-teal transition shadow-md">Painel
                            de Gestão</a>
                    @else
                        <a href="{{ route('login') }}"
                            class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-lacerda-teal transition flex items-center gap-2 group">
                            <svg class="w-3.5 h-3.5 opacity-50 group-hover:opacity-100 transition" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Sistema interno
                        </a>
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    {{-- SEÇÃO HERO (DESTAQUE) --}}
    <section class="relative pt-48 pb-24 px-6 bg-slate-50">
        <div class="absolute inset-0 opacity-[0.03]"
            style="background-image: radial-gradient(#1a2d2f 1px, transparent 1px); background-size: 32px 32px;"></div>

        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center gap-16 relative z-10 animate-fadeIn">
            <div class="md:w-3/5">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-px bg-lacerda-gold"></div>
                    <h2 class="text-lacerda-dark text-[10px] font-bold uppercase tracking-[0.4em]">Proteção Patrimonial e
                        Contencioso</h2>
                </div>

                <h1 class="text-5xl md:text-[4rem] font-black text-lacerda-dark tracking-tighter leading-[1.1] mb-8">
                    Sua segurança <br>jurídica em <span class="font-serif italic font-normal text-lacerda-gold">boas
                        mãos.</span>
                </h1>

                <p class="max-w-xl text-slate-600 text-lg leading-relaxed mb-10 border-l-4 border-lacerda-gold pl-6">
                    O escritório <strong class="font-bold text-lacerda-dark">Lacerda & Associados</strong> oferece soluções
                    estratégicas, unindo consultoria preventiva especializada e atuação contenciosa de alta performance.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="https://wa.me/SEUNUMERO" target="_blank"
                        class="px-10 py-4 bg-lacerda-teal text-white rounded font-bold uppercase text-[11px] tracking-[0.2em] hover:bg-lacerda-dark transition shadow-xl shadow-lacerda-teal/20 text-center flex items-center justify-center gap-3 group">
                        Falar com Especialista
                        <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            </div>

            <div class="md:w-2/5 hidden md:block">
                <div class="relative group">
                    <div
                        class="absolute -inset-4 bg-lacerda-gold/30 rounded-lg blur-2xl opacity-40 group-hover:opacity-60 transition duration-700">
                    </div>
                    <div
                        class="relative bg-white p-3 border border-slate-200 shadow-2xl rounded-sm transform group-hover:-translate-y-2 transition duration-700">
                        <img src="https://plus.unsplash.com/premium_photo-1695449439526-9cebdbfa1a2c?fm=jpg&q=60&w=3000&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D"
                            alt="Advocacia de Excelência"
                            class="image-gray w-full h-[500px] object-cover border border-slate-100">
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SEÇÃO DE EXPERTISE --}}
    <section id="atuacao" class="py-32 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-24">
                <h2 class="text-4xl font-black text-lacerda-dark mb-6 tracking-tighter uppercase">Nossa Expertise</h2>
                <div class="w-16 h-1 bg-lacerda-gold mx-auto mb-8"></div>
                <p class="text-slate-500 max-w-2xl mx-auto text-lg leading-relaxed">Da consultoria preventiva ao litígio
                    de alta complexidade, estruturamos soluções jurídicas sólidas para pessoas físicas e empresas.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-12 gap-y-16">
                {{-- Card 1 --}}
                <div class="border-t border-slate-200 pt-8 group hover:border-lacerda-teal transition-colors duration-300">
                    <div class="flex justify-between items-baseline mb-6">
                        <h3 class="text-xl font-bold text-lacerda-dark uppercase tracking-tight">Direito Civil</h3>
                        <span class="text-slate-200 text-3xl font-black font-serif italic group-hover:text-lacerda-gold transition-colors">01</span>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">Assessoria em contratos complexos,
                        responsabilidade civil e contencioso cível de alto valor estratégico.</p>
                </div>

                {{-- Card 2 --}}
                <div class="border-t border-slate-200 pt-8 group hover:border-lacerda-teal transition-colors duration-300">
                    <div class="flex justify-between items-baseline mb-6">
                        <h3 class="text-xl font-bold text-lacerda-dark uppercase tracking-tight">Direito do Trabalho</h3>
                        <span class="text-slate-200 text-3xl font-black font-serif italic group-hover:text-lacerda-gold transition-colors">02</span>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">Consultoria em compliance trabalhista para
                        empresas e defesa técnica rigorosa dos direitos do trabalhador.</p>
                </div>

                {{-- Card 3 --}}
                <div class="border-t border-slate-200 pt-8 group hover:border-lacerda-teal transition-colors duration-300">
                    <div class="flex justify-between items-baseline mb-6">
                        <h3 class="text-xl font-bold text-lacerda-dark uppercase tracking-tight">Previdenciário</h3>
                        <span class="text-slate-200 text-3xl font-black font-serif italic group-hover:text-lacerda-gold transition-colors">03</span>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">Planejamento previdenciário personalizado, atuação
                        em concessões e revisões de benefícios perante o INSS e via judicial.</p>
                </div>

                {{-- Card 4 --}}
                <div class="border-t border-slate-200 pt-8 group hover:border-lacerda-teal transition-colors duration-300">
                    <div class="flex justify-between items-baseline mb-6">
                        <h3 class="text-xl font-bold text-lacerda-dark uppercase tracking-tight">Direito de Família</h3>
                        <span class="text-slate-200 text-3xl font-black font-serif italic group-hover:text-lacerda-gold transition-colors">04</span>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">Atendimento sigiloso e humanizado em divórcios,
                        guardas, inventários e planejamento sucessório estruturado.</p>
                </div>

                {{-- Card 5 --}}
                <div class="border-t border-slate-200 pt-8 group hover:border-lacerda-teal transition-colors duration-300">
                    <div class="flex justify-between items-baseline mb-6">
                        <h3 class="text-xl font-bold text-lacerda-dark uppercase tracking-tight">Direito Empresarial</h3>
                        <span class="text-slate-200 text-3xl font-black font-serif italic group-hover:text-lacerda-gold transition-colors">05</span>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">Amparo jurídico para operações societárias,
                        estruturação de negócios e proteção jurídica contínua da atividade econômica.</p>
                </div>

                {{-- Card 6 --}}
                <div class="border-t border-slate-200 pt-8 group hover:border-lacerda-teal transition-colors duration-300">
                    <div class="flex justify-between items-baseline mb-6">
                        <h3 class="text-xl font-bold text-lacerda-dark uppercase tracking-tight">Administrativo</h3>
                        <span class="text-slate-200 text-3xl font-black font-serif italic group-hover:text-lacerda-gold transition-colors">06</span>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">Defesa dos seus interesses em licitações,
                        sindicâncias, processos disciplinares e contratos com a Administração Pública.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- RODAPÉ (FOOTER) --}}
    <footer class="bg-lacerda-dark text-white pt-24 pb-12 px-6">
        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-16 border-b border-white/10 pb-16 mb-8">

            <div class="md:col-span-5">
                <div class="flex items-center gap-3 mb-8">
                    {{-- Logo no Rodapé --}}
                    <img src="{{ asset('img/logo.png') }}" alt="Logo L&A" class="w-10 h-10 object-contain">
                    
                    <div class="flex flex-col leading-none">
                        <span class="text-sm font-black uppercase tracking-widest text-white">Lacerda <span
                                class="font-light text-lacerda-gold">&</span> Associados</span>
                    </div>
                </div>
                <p class="text-slate-300 text-sm leading-relaxed max-w-sm">
                    Nossa missão é descomplicar o direito, garantindo soluções seguras, ágeis e transparentes para cada
                    um dos nossos clientes.
                </p>
            </div>

            <div class="md:col-span-3 space-y-4">
                <h4 class="text-[10px] font-black uppercase tracking-[0.3em] text-lacerda-gold mb-6">Atendimento</h4>
                <p class="text-slate-300 text-sm hover:text-white transition cursor-pointer">
                    contato@lacerdaeassociados.com.br</p>
                <p class="text-slate-300 text-sm">+55 (00) 00000-0000</p>
                <p class="text-slate-400 text-xs mt-4">Edifício Corporate Center, Sala 1001<br>Centro - Sua Cidade/UF
                </p>
            </div>

            <div class="md:col-span-4 md:text-right">
                <h4 class="text-[10px] font-black uppercase tracking-[0.3em] text-lacerda-gold mb-6">Informações Legais
                </h4>
                <p class="text-slate-300 text-sm font-bold tracking-widest uppercase mb-2">OAB/UF nº 00.000</p>
                <p class="text-slate-400 text-xs leading-relaxed">CNPJ: 00.000.000/0001-00</p>
            </div>
        </div>

        <div
            class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center text-slate-400 text-[10px] font-bold uppercase tracking-widest">
            <span>&copy; {{ date('Y') }} Lacerda & Associados. Todos os direitos reservados.</span>
            <div class="flex gap-8 mt-4 md:mt-0">
                <a href="#" class="hover:text-lacerda-gold transition">Política de Privacidade</a>
                <a href="#" class="hover:text-lacerda-gold transition">Termos de Serviço</a>
            </div>
        </div>
    </footer>

</body>

</html>