<?php
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use function Livewire\Volt\{state, with, usesPagination};

usesPagination();

state([
    'name' => '',
    'username' => '',
    'email' => '',
    'cpf' => '',
    'password' => '',
    'role' => 'user',
    'cargo' => 'Advogado',
    'formId' => 1,
    'editingUserId' => null,
    'isEditing' => false,
    'showModal' => false,
    'search' => ''
]);

$abrirModal = function () {
    $this->reset(['name', 'username', 'email', 'cpf', 'password', 'role', 'cargo', 'editingUserId', 'isEditing']);
    $this->role = 'user';
    $this->cargo = 'Advogado';
    $this->showModal = true;
};

$fecharModal = function () {
    $this->showModal = false;
    $this->formId++;
};

$editar = function ($id) {
    $user = User::find($id);
    if ($user) {
        $this->isEditing = true;
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->cpf = $user->cpf;
        $this->role = $user->role;
        $this->cargo = $user->cargo;
        $this->password = '';
        $this->showModal = true;
        $this->formId++;
    }
};

$salvar = function () {
    $this->validate([
        'name' => 'required|min:3',
        'username' => 'required|string|min:3|unique:users,username,' . ($this->editingUserId ?? 'NULL'),
        'email' => 'nullable|email|unique:users,email,' . ($this->editingUserId ?? 'NULL'),
        'cpf' => 'required|unique:users,cpf,' . ($this->editingUserId ?? 'NULL'),
        'cargo' => 'required',
        'role' => 'required',
    ]);

    $dados = [
        'name' => $this->name,
        'username' => strtolower(trim($this->username)),
        'email' => $this->email ?: null,
        'cpf' => $this->cpf,
        'role' => $this->role,
        'cargo' => $this->cargo
    ];

    if ($this->isEditing) {
        $user = User::find($this->editingUserId);
        if (!empty($this->password)) {
            $dados['password'] = Hash::make($this->password);
        }
        $user->update($dados);
        session()->flash('message', 'Colaborador atualizado com sucesso!');
    } else {
        $this->validate(['password' => 'required|min:6']);
        $dados['password'] = Hash::make($this->password);
        User::create($dados);
        session()->flash('message', 'Novo colaborador adicionado com sucesso!');
    }
    $this->fecharModal();
};

$excluir = function ($id) {
    if ($id !== auth()->id()) {
        $user = User::find($id);
        if ($user) {
            $user->delete();
            session()->flash('message', 'Colaborador removido do sistema.');
        }
    }
};

with(function () {
    return [
        'usuarios' => User::where('name', 'like', "%{$this->search}%")
            ->orWhere('username', 'like', "%{$this->search}%")
            ->orWhere('email', 'like', "%{$this->search}%")
            ->orWhere('cpf', 'like', "%{$this->search}%")
            ->latest()
            ->paginate(10)
    ];
});
?>

{{-- Repare: Removi todos os fundos cinzas (bg-slate-50) e margins, deixando apenas a div crua --}}
<div class="w-full font-sans antialiased text-slate-900">

    {{-- Notificações Flash --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
            class="fixed top-5 right-5 z-[999999] flex items-center p-4 border-l-4 border-emerald-500 bg-white rounded-xl shadow-lg">
            <svg class="w-5 h-5 text-emerald-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <div class="font-bold text-emerald-800 uppercase tracking-wider text-[11px]">{{ session('message') }}</div>
        </div>
    @endif

    {{-- Cabeçalho da Tela Integrado --}}
    <div
        class="flex flex-col md:flex-row justify-between items-start md:items-end border-b border-slate-100 pb-5 mb-6 gap-5">
        <div class="w-full md:w-auto">
            <div class="flex items-center gap-2">
                <div class="w-1 h-7 bg-indigo-600 rounded-sm"></div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Equipe</h1>
            </div>
            <p class="mt-1 text-sm text-slate-500 pl-3">Gestão de colaboradores e acessos.</p>
        </div>

        <div class="flex flex-col sm:flex-row w-full md:w-auto gap-3 items-center">
            {{-- Busca --}}
            <div class="relative w-full sm:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar colaborador..."
                    class="w-full pl-10 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" />
            </div>

            {{-- Botão Novo --}}
            <button wire:click="abrirModal"
                class="w-full sm:w-auto px-6 py-2.5 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center justify-center gap-2 shrink-0 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Novo Membro
            </button>
        </div>
    </div>

    {{-- VISUALIZAÇÃO MOBILE (CARDS) --}}
    <div class="block md:hidden space-y-4">
        @forelse($usuarios as $user)
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm relative">
                <div class="absolute top-4 right-4">
                    <span
                        class="px-2 py-1 rounded text-[9px] font-bold uppercase tracking-wider {{ $user->role === 'admin' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                        {{ $user->role === 'admin' ? 'Admin' : 'Comum' }}
                    </span>
                </div>

                <div class="flex items-center gap-3 mb-4 pr-16">
                    <div
                        class="w-10 h-10 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-center font-bold text-base text-slate-600 shrink-0">
                        {{ mb_substr($user->name, 0, 1) }}
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-900 leading-tight">{{ $user->name }}</div>
                        <div class="text-[10px] font-semibold text-slate-500 uppercase mt-0.5">{{ $user->cargo }}</div>
                    </div>
                </div>

                <div class="space-y-1 border-t border-slate-100 pt-3 mb-4">
                    <div class="text-xs text-slate-600 flex justify-between items-center">
                        <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Usuário (Login)</span>
                        <span class="font-mono font-bold text-indigo-600">{{ '@' . strtolower($user->username) }}</span>
                    </div>
                    <div class="text-xs text-slate-600 flex justify-between items-center mt-1">
                        <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">CPF</span>
                        <span class="font-mono">{{ $user->cpf }}</span>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button wire:click="editar({{ $user->id }})"
                        class="flex-1 py-2 text-[11px] font-bold text-slate-600 bg-slate-50 border border-slate-200 hover:bg-slate-100 rounded-lg transition text-center uppercase tracking-wide">
                        Editar
                    </button>
                    @if($user->id !== auth()->id())
                        <button
                            onclick="confirm('Excluir este membro do sistema permanentemente?') || event.stopImmediatePropagation()"
                            wire:click="excluir({{ $user->id }})"
                            class="flex-1 py-2 text-[11px] font-bold text-rose-600 bg-rose-50 border border-rose-200 hover:bg-rose-100 rounded-lg transition text-center uppercase tracking-wide">
                            Excluir
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-10 px-4 text-sm text-slate-500 border border-slate-100 rounded-xl">
                Nenhum colaborador encontrado.
            </div>
        @endforelse
    </div>

    {{-- VISUALIZAÇÃO DESKTOP (TABELA LIMPA E DIRETA) --}}
    <div class="hidden md:block overflow-x-auto border border-slate-200 rounded-xl">
        <table class="w-full text-left border-collapse whitespace-nowrap">
            <thead>
                <tr
                    class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                    <th class="px-6 py-4">Colaborador</th>
                    <th class="px-6 py-4">Login / Documento</th>
                    <th class="px-6 py-4 text-center">Acesso</th>
                    <th class="px-6 py-4 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($usuarios as $user)
                    <tr class="hover:bg-slate-50 transition" wire:key="user-{{ $user->id }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-9 h-9 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-center font-bold text-sm text-slate-600 shrink-0">
                                    {{ mb_substr($user->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $user->name }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 uppercase tracking-wide">
                                        <span class="font-bold">{{ $user->cargo }}</span>
                                        @if($user->email) • {{ strtolower($user->email) }} @endif
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-indigo-600">{{ '@' . strtolower($user->username) }}</div>
                            <div class="font-mono text-[10px] text-slate-500 mt-0.5">CPF: {{ $user->cpf }}</div>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <span
                                class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wider {{ $user->role === 'admin' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                {{ $user->role === 'admin' ? 'Admin' : 'Comum' }}
                            </span>
                        </td>

                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end items-center gap-2">
                                <button wire:click="editar({{ $user->id }})"
                                    class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-md transition"
                                    title="Editar Usuário">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>

                                @if($user->id !== auth()->id())
                                    <button
                                        onclick="confirm('Excluir este membro do sistema permanentemente?') || event.stopImmediatePropagation()"
                                        wire:click="excluir({{ $user->id }})"
                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition"
                                        title="Excluir Usuário">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @else
                                    <div class="w-7 h-7"></div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-sm text-slate-500">
                            Nenhum colaborador encontrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($usuarios->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-white">
                {{ $usuarios->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL DE CADASTRO/EDIÇÃO --}}
    @if($showModal)
        @teleport('body')
        <div
            class="fixed inset-0 z-[99999] flex items-start sm:items-center justify-center p-4 pt-10 sm:p-6 overflow-y-auto">

            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm transition-opacity" wire:click="fecharModal"></div>

            <form wire:submit.prevent="salvar"
                class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden"
                wire:key="form-equipe-{{ $formId }}">

                <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center shrink-0">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">
                            {{ $isEditing ? 'Editar Colaborador' : 'Novo Colaborador' }}
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">Configure o acesso ao sistema e as funções.</p>
                    </div>
                    <button type="button" wire:click="fecharModal"
                        class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 p-2 rounded-full transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                <div class="p-8 overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-6">

                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-2">Nome
                                Completo</label>
                            <input type="text" wire:model="name"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                            @error('name') <span
                            class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-indigo-600 uppercase tracking-wider mb-1">Nome de
                                Usuário</label>
                            <span class="block text-[9px] text-slate-400 mb-2">Usado para fazer login no sistema</span>
                            <input type="text" wire:model="username" placeholder="ex: joao.silva"
                                class="w-full bg-indigo-50/30 border border-indigo-200 rounded-xl px-4 py-3 text-sm text-indigo-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition lowercase">
                            @error('username') <span
                            class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-indigo-600 uppercase tracking-wider mb-1">Senha
                                de Acesso</label>
                            <span
                                class="block text-[9px] text-slate-400 mb-2">{{ $isEditing ? 'Deixe em branco para manter a atual' : 'Mínimo de 6 caracteres' }}</span>
                            <input type="password" wire:model="password"
                                class="w-full bg-indigo-50/30 border border-indigo-200 rounded-xl px-4 py-3 text-sm text-indigo-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                            @error('password') <span
                            class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-2">CPF</label>
                            <input type="text" wire:model="cpf" x-mask="999.999.999-99"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                            @error('cpf') <span
                            class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-2">Cargo</label>
                            <select wire:model="cargo"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition cursor-pointer">
                                <option value="Advogado">Advogado</option>
                                <option value="Secretária">Secretária</option>
                                <option value="Estagiário">Estagiário</option>
                            </select>
                            @error('cargo') <span
                            class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label
                                class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-2">E-mail
                                Corporativo <span class="text-slate-400 normal-case font-normal">(Opcional)</span></label>
                            <input type="email" wire:model="email"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:bg-white focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                            @error('email') <span
                            class="text-rose-500 text-[10px] mt-1.5 block font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2 pt-4 border-t border-slate-100 mt-2">
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-3">Nível de
                                Acesso ao Sistema</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label
                                    class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition {{ $role === 'user' ? 'bg-indigo-50 border-indigo-300 ring-1 ring-indigo-300' : 'border-slate-200 hover:bg-slate-50' }}">
                                    <input type="radio" wire:model="role" value="user"
                                        class="mt-0.5 w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                    <div>
                                        <span class="block text-sm font-bold text-slate-900">Usuário Comum</span>
                                        <span class="block text-[10px] text-slate-500 mt-1 leading-snug">Visualiza processos
                                            delegados e agenda pessoal. Restrito de configurações.</span>
                                    </div>
                                </label>
                                <label
                                    class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition {{ $role === 'admin' ? 'bg-indigo-50 border-indigo-300 ring-1 ring-indigo-300' : 'border-slate-200 hover:bg-slate-50' }}">
                                    <input type="radio" wire:model="role" value="admin"
                                        class="mt-0.5 w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                    <div>
                                        <span class="block text-sm font-bold text-slate-900">Administrador</span>
                                        <span class="block text-[10px] text-slate-500 mt-1 leading-snug">Acesso total. Pode
                                            gerir toda a equipe, processos, e visualizar todos os dados.</span>
                                    </div>
                                </label>
                            </div>
                            @error('role') <span
                            class="text-rose-500 text-[10px] mt-2 block font-semibold">{{ $message }}</span> @enderror
                        </div>

                    </div>
                </div>

                <div class="px-8 py-5 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 shrink-0">
                    <button type="button" wire:click="fecharModal"
                        class="px-6 py-2.5 text-slate-500 rounded-xl text-sm font-bold hover:bg-slate-200 hover:text-slate-800 transition">Cancelar</button>
                    <button type="submit"
                        class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition">
                        {{ $isEditing ? 'Atualizar Membro' : 'Salvar Novo Membro' }}
                    </button>
                </div>
            </form>

        </div>
        @endteleport
    @endif
</div>