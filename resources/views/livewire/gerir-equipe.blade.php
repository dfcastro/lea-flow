<?php
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use function Livewire\Volt\{state, with, usesPagination};

usesPagination();

state([
    'name' => '',
    'email' => '',
    'cpf' => '',
    'password' => '',
    'role' => 'user',
    'cargo' => 'Advogado',
    'formId' => 1,
    'editingUserId' => null,
    'isEditing' => false
]);

$editar = function ($id) {
    $user = User::find($id);
    if ($user) {
        $this->isEditing = true;
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->cpf = $user->cpf;
        $this->role = $user->role;
        $this->cargo = $user->cargo;
        $this->password = '';
        $this->formId++;
    }
};

$cancelar = function () {
    $this->reset(['name', 'email', 'cpf', 'password', 'role', 'cargo', 'isEditing', 'editingUserId']);
    $this->formId++;
};

$salvar = function () {
    $this->validate([
        'name' => 'required|min:3',
        'email' => 'required|email|unique:users,email,' . ($this->editingUserId ?? 'NULL'),
        'cpf' => 'required|unique:users,cpf,' . ($this->editingUserId ?? 'NULL'),
        'cargo' => 'required',
        'role' => 'required',
    ]);

    if ($this->isEditing) {
        $user = User::find($this->editingUserId);
        $dados = [
            'name' => $this->name,
            'email' => $this->email,
            'cpf' => $this->cpf,
            'role' => $this->role,
            'cargo' => $this->cargo
        ];
        if (!empty($this->password))
            $dados['password'] = Hash::make($this->password);
        $user->update($dados);
        session()->flash('message', 'COLABORADOR ATUALIZADO COM SUCESSO!');
    } else {
        $this->validate(['password' => 'required|min:8']);
        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'cpf' => $this->cpf,
            'password' => Hash::make($this->password),
            'role' => $this->role,
            'cargo' => $this->cargo
        ]);
        session()->flash('message', 'NOVO MEMBRO ADICIONADO À EQUIPE!');
    }
    $this->cancelar();
};

$excluir = function ($id) {
    if ($id !== auth()->id()) {
        $user = User::find($id);
        if ($user) {
            $user->delete();
            session()->flash('message', 'MEMBRO REMOVIDO DO SISTEMA!');
        }
    }
};

with(fn() => ['usuarios' => User::latest()->paginate(10)]);
?>

<div class="space-y-8 animate-fadeIn text-left">
    <div
        class="bg-white rounded-2xl shadow-sm border {{ $isEditing ? 'border-indigo-400 ring-2 ring-indigo-50' : 'border-gray-100' }} transition-all duration-500">
        <div class="p-8">
            <div class="flex items-center justify-between mb-8 text-left">
                <div class="flex items-center gap-4 text-left">
                    <div class="p-3 rounded-xl bg-gray-900 text-white shadow-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                            </path>
                        </svg>
                    </div>
                    <div class="text-left">
                        <h2 class="text-2xl font-black text-gray-900 tracking-tighter uppercase">
                            {{ $isEditing ? 'Editar Perfil' : 'Gestão de Equipe' }}</h2>
                        <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest text-left">Controle de
                            Colaboradores • L&A Flow</p>
                    </div>
                </div>
                @if($isEditing)
                    <button wire:click="cancelar"
                        class="text-xs font-black text-red-500 uppercase tracking-widest hover:underline">CANCELAR</button>
                @endif
            </div>

            <form wire:submit.prevent="salvar" class="grid grid-cols-1 md:grid-cols-12 gap-x-6 gap-y-5 text-left"
                wire:key="form-equipe-{{ $formId }}">

                <div class="md:col-span-6 text-left">
                    <x-input-label value="NOME COMPLETO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="name" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold uppercase" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="CPF" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="cpf" type="text"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold" x-mask="999.999.999-99" />
                    <x-input-error :messages="$errors->get('cpf')" class="mt-1" />
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="E-MAIL CORPORATIVO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="email" type="email"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner font-medium" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="SENHA" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <x-text-input wire:model="password" type="password"
                        class="w-full mt-1 bg-gray-50 border-none shadow-inner" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="CARGO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <select wire:model="cargo"
                        class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner focus:ring-2 focus:ring-indigo-500 font-bold text-xs uppercase">
                        <option value="Sócio">Sócio</option>
                        <option value="Advogado">Advogado</option>
                        <option value="Secretária">Secretária</option>
                        <option value="Estagiário">Estagiário</option>
                        <option value="Financeiro">Financeiro</option>
                    </select>
                    <x-input-error :messages="$errors->get('cargo')" class="mt-1" />
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="PERMISSÃO" class="text-[10px] font-bold text-gray-400 uppercase" />
                    <select wire:model="role"
                        class="w-full mt-1 border-none bg-gray-50 rounded-xl shadow-inner focus:ring-2 focus:ring-indigo-500 font-bold text-xs uppercase">
                        <option value="user">Comum</option>
                        <option value="admin">Admin</option>
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                </div>

                <div class="md:col-span-3 flex items-end">
                    <button type="submit"
                        class="w-full py-3 bg-gray-900 text-white rounded-xl font-black shadow-xl hover:bg-indigo-600 transition-all uppercase text-[10px] tracking-widest">
                        {{ $isEditing ? 'Salvar' : 'Cadastrar' }}
                    </button>
                </div>
            </form>

            @if (session()->has('message'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                    class="mt-6 flex items-center p-4 border-l-4 border-emerald-500 bg-emerald-50 rounded-xl shadow-sm">
                    <div class="ml-3 font-black text-emerald-800 uppercase tracking-widest text-[10px]">
                        {{ session('message') }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden text-left">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-left">
                        Colaborador</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-left">
                        Acesso</th>
                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">
                        Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($usuarios as $user)
                    <tr class="hover:bg-gray-50/50 transition text-left" wire:key="user-{{ $user->id }}">
                        <td class="px-8 py-4 text-left">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-12 h-12 rounded-full bg-gray-900 text-white flex items-center justify-center font-black uppercase text-lg shadow-md">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                                <div class="text-left">
                                    <div class="font-black text-gray-900 tracking-tighter uppercase">{{ $user->name }}</div>
                                    <div class="text-[10px] text-gray-400 font-bold tracking-widest uppercase">
                                        {{ $user->cargo }} • {{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-4 text-left">
                            <span
                                class="px-2.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest {{ $user->role === 'admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $user->role === 'admin' ? 'Admin' : 'Comum' }}
                            </span>
                        </td>
                        <td class="px-8 py-4 text-right">
                            <div class="flex justify-end items-center gap-6">
                                <button wire:click="editar({{ $user->id }})"
                                    class="text-indigo-600 font-black text-[10px] uppercase tracking-widest hover:underline">Editar</button>
                                @if($user->id !== auth()->id())
                                    <button onclick="confirm('Excluir membro?') || event.stopImmediatePropagation()"
                                        wire:click="excluir({{ $user->id }})"
                                        class="text-gray-300 hover:text-red-500 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 bg-gray-50/50">{{ $usuarios->links() }}</div>
    </div>
</div>