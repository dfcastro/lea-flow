<?php
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use function Livewire\Volt\{state, with, usesPagination};

usesPagination();

state([
    'name' => '', 'email' => '', 'cpf' => '', 'password' => '', 'role' => 'user', 'cargo' => 'Advogado',
    'formId' => 1, 'editingUserId' => null, 'isEditing' => false
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
            'name' => $this->name, 'email' => $this->email, 'cpf' => $this->cpf, 
            'role' => $this->role, 'cargo' => $this->cargo
        ];
        if (!empty($this->password)) $dados['password'] = Hash::make($this->password);
        $user->update($dados);
        session()->flash('message', 'Colaborador atualizado com sucesso!');
    } else {
        $this->validate(['password' => 'required|min:8']);
        User::create([
            'name' => $this->name, 'email' => $this->email, 'cpf' => $this->cpf,
            'password' => Hash::make($this->password), 'role' => $this->role, 'cargo' => $this->cargo
        ]);
        session()->flash('message', 'Novo membro adicionado à equipe!');
    }
    $this->cancelar();
};

$excluir = function ($id) {
    if ($id !== auth()->id()) {
        $user = User::find($id);
        if ($user) {
            $user->delete();
            session()->flash('message', 'Membro removido do sistema!');
        }
    }
};

with(fn () => ['usuarios' => User::latest()->paginate(10)]);
?>

<div class="space-y-8 animate-fadeIn text-left">
    
    <div class="bg-white rounded-2xl shadow-sm border {{ $isEditing ? 'border-indigo-400 ring-2 ring-indigo-50' : 'border-gray-100' }} transition-all duration-500">
        <div class="p-8">
            <div class="flex items-center justify-between mb-8 text-left">
                <div class="flex items-center gap-4 text-left">
                    <div class="p-3 rounded-xl {{ $isEditing ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight uppercase">{{ $isEditing ? 'Editar Perfil' : 'Gestão de Equipe' }}</h2>
                        <p class="text-sm text-gray-500 font-medium uppercase">{{ $isEditing ? 'Modificando acesso de ' . $name : 'Cadastre advogados e colaboradores' }}</p>
                    </div>
                </div>
                @if($isEditing)
                    <button wire:click="cancelar" class="text-xs font-bold text-red-500 uppercase tracking-widest hover:underline">CANCELAR</button>
                @endif
            </div>

            <form wire:submit.prevent="salvar" class="grid grid-cols-1 md:grid-cols-12 gap-x-6 gap-y-5 text-left" wire:key="form-{{ $formId }}">
                
                <div class="md:col-span-6">
                    <x-input-label value="NOME COMPLETO" class="font-semibold text-gray-700 uppercase text-[10px]" />
                    <x-text-input wire:model="name" type="text" class="w-full mt-1 bg-gray-50 border-none focus:ring-2 focus:ring-indigo-500 shadow-inner font-bold" placeholder="Ex: Dr. Roberto Silva" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="CPF" class="font-semibold text-gray-700 uppercase text-[10px]" />
                    <x-text-input wire:model="cpf" type="text" class="w-full mt-1 bg-gray-50 border-none shadow-inner font-bold" x-mask="999.999.999-99" />
                    <x-input-error :messages="$errors->get('cpf')" />
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="E-MAIL CORPORATIVO" class="font-semibold text-gray-700 uppercase text-[10px]" />
                    <x-text-input wire:model="email" type="email" class="w-full mt-1 bg-gray-50 border-none shadow-inner" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="{{ $isEditing ? 'NOVA SENHA' : 'SENHA INICIAL' }}" class="font-semibold text-gray-700 uppercase text-[10px]" />
                    <x-text-input wire:model="password" type="password" class="w-full mt-1 bg-gray-50 border-none shadow-inner" />
                    <x-input-error :messages="$errors->get('password')" />
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="CARGO / FUNÇÃO" class="font-semibold text-gray-700 uppercase text-[10px]" />
                    <select wire:model="cargo" class="w-full mt-1 border-none bg-gray-50 rounded-lg shadow-inner focus:ring-2 focus:ring-indigo-500 font-bold uppercase text-xs">
                        <option value="Sócio">Sócio</option>
                        <option value="Advogado">Advogado</option>
                        <option value="Secretária">Secretária</option>
                        <option value="Estagiário">Estagiário</option>
                    </select>
                </div>

                <div class="md:col-span-3 text-left">
                    <x-input-label value="PERMISSÃO DE ACESSO" class="font-semibold text-gray-700 uppercase text-[10px]" />
                    <select wire:model="role" class="w-full mt-1 border-none bg-gray-50 rounded-lg shadow-inner focus:ring-2 focus:ring-indigo-500 font-bold uppercase text-xs {{ $role === 'admin' ? 'text-indigo-600' : 'text-gray-600' }}">
                        <option value="user">👤 USUÁRIO COMUM</option>
                        <option value="admin">🔑 ADMINISTRADOR</option>
                    </select>
                </div>

                <div class="md:col-span-3 flex items-end">
                    <button type="submit" wire:loading.attr="disabled" 
                            class="w-full py-2.5 rounded-xl text-white font-bold shadow-lg transition-all transform hover:-translate-y-1 active:scale-95 uppercase text-xs tracking-widest {{ $isEditing ? 'bg-indigo-600 shadow-indigo-200' : 'bg-gray-900 shadow-gray-200' }}">
                        <span wire:loading.remove>{{ $isEditing ? 'SALVAR ALTERAÇÕES' : 'CADASTRAR NA EQUIPE' }}</span>
                        <span wire:loading>AGUARDE...</span>
                    </button>
                </div>
            </form>

            @if (session()->has('message'))
                <div x-data="{ show: true }" 
                     x-show="show" 
                     x-init="setTimeout(() => show = false, 5000)"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     class="mt-6 flex items-center p-4 border-l-4 border-emerald-500 bg-emerald-50 rounded-xl shadow-sm text-left">
                    
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    
                    <div class="ml-3">
                        <p class="text-xs font-black text-emerald-800 uppercase tracking-widest">
                            {{ session('message') }}
                        </p>
                    </div>

                    <div class="ml-auto pl-3">
                        <button @click="show = false" type="button" class="text-emerald-500 hover:bg-emerald-100 rounded-md p-1 transition">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden text-left">
        <table class="min-w-full divide-y divide-gray-100 text-left">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-left">COLABORADOR</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-left">ACESSO</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-right">AÇÕES</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 text-left">
                @foreach($usuarios as $user)
                    <tr class="group hover:bg-gray-50/50 transition-all text-left" wire:key="user-{{ $user->id }}">
                        <td class="px-6 py-4 text-left">
                            <div class="flex items-center gap-3 text-left">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 text-white flex items-center justify-center font-bold shadow-md shadow-indigo-100">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                                <div class="text-left">
                                    <div class="font-bold text-gray-900 tracking-tighter">{{ $user->name }}</div>
                                    <div class="text-[10px] text-gray-400 font-medium uppercase tracking-widest">{{ $user->cargo }} • {{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-left">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase {{ $user->role === 'admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $user->role === 'admin' ? 'ADMIN' : 'COMUM' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end items-center gap-4">
                                <button wire:click="editar({{ $user->id }})" class="text-indigo-600 hover:text-indigo-900 font-bold text-[10px] uppercase tracking-widest">EDITAR</button>
                                @if($user->id !== auth()->id())
                                    <button onclick="confirm('EXCLUIR ESTE MEMBRO?') || event.stopImmediatePropagation()" 
                                            wire:click="excluir({{ $user->id }})" class="text-gray-300 hover:text-red-500 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>