<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\VoltServiceProvider::class,     // O seu (para montar as pastas)
    Livewire\Volt\VoltServiceProvider::class,    // O da Biblioteca (para os comandos do terminal)
];