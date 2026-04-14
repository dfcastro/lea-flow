<nav x-data="{ open: false }" class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 bg-indigo-600 rounded flex items-center justify-center text-white font-black text-xl shadow-sm">
                            L
                        </div>
                        <span class="font-black text-xl tracking-tighter text-slate-900 hidden sm:block">
                            L&A <span class="text-indigo-600">Flow</span>
                        </span>
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')"
                        class="font-bold text-xs uppercase tracking-widest text-slate-600 hover:text-indigo-600 transition-colors">
                        Dashboard
                    </x-nav-link>
                    <x-nav-link :href="route('processos.index')" :active="request()->routeIs('processos.*')"
                        class="font-bold text-xs uppercase tracking-widest text-slate-600 hover:text-indigo-600 transition-colors">
                        Processos
                    </x-nav-link>
                    <x-nav-link :href="route('clientes.index')" :active="request()->routeIs('clientes.*')"
                        class="font-bold text-xs uppercase tracking-widest text-slate-600 hover:text-indigo-600 transition-colors">
                        Clientes
                    </x-nav-link>

                    {{-- LINK FINANCEIRO - Visível para Admin e Advogado --}}

                    <x-nav-link :href="route('financeiro.index')" :active="request()->routeIs('financeiro.*')"
                        class="font-bold text-xs uppercase tracking-widest text-emerald-600 hover:text-emerald-700 transition-colors">
                        Financeiro
                    </x-nav-link>

                    @can('gerir-equipe')
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.index')"
                            class="font-bold text-xs uppercase tracking-widest text-slate-600 hover:text-indigo-600 transition-colors">
                            Equipe
                        </x-nav-link>
                    @endcan
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 border border-slate-200 text-xs font-bold uppercase tracking-widest leading-4 rounded-md text-slate-600 bg-white hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 focus:outline-none transition ease-in-out duration-150">
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-slate-600">
                                    {{ mb_substr(Auth::user()->name, 0, 1) }}
                                </div>
                                <div class="hidden md:block">{{ explode(' ', Auth::user()->name)[0] }}</div>
                            </div>
                            <div class="ms-2">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')"
                            class="text-xs font-bold text-slate-600 uppercase tracking-widest">
                            Meu Perfil
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();"
                                class="text-xs font-bold text-rose-600 uppercase tracking-widest">
                                Sair do Sistema
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Menu Mobile --}}
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-slate-50 border-b border-slate-200">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')"
                class="text-xs font-bold uppercase tracking-widest">
                Dashboard
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('processos.index')" :active="request()->routeIs('processos.*')"
                class="text-xs font-bold uppercase tracking-widest">
                Processos
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('clientes.index')" :active="request()->routeIs('clientes.*')"
                class="text-xs font-bold uppercase tracking-widest">
                Clientes
            </x-responsive-nav-link>

            {{-- LINK FINANCEIRO MOBILE --}}
            @can('acesso-financeiro')
                <x-responsive-nav-link :href="route('financeiro.index')" :active="request()->routeIs('financeiro.*')"
                    class="text-xs font-bold uppercase tracking-widest text-emerald-600">
                    Financeiro
                </x-responsive-nav-link>
            @endcan

            @can('gerir-equipe')
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.index')"
                    class="text-xs font-bold uppercase tracking-widest text-indigo-600">
                    Equipe
                </x-responsive-nav-link>
            @endcan
        </div>

        <div class="pt-4 pb-4 border-t border-slate-200 bg-white">
            <div class="px-4 flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 font-bold text-lg">
                    {{ mb_substr(Auth::user()->name, 0, 1) }}
                </div>
                <div>
                    <div class="font-bold text-sm text-slate-900 leading-tight">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-xs text-slate-500">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="mt-4 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')"
                    class="text-xs font-bold uppercase tracking-widest">
                    Meu Perfil
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();"
                        class="text-xs font-bold uppercase tracking-widest text-rose-600">
                        Sair do Sistema
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>