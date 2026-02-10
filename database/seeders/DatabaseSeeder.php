<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Criação do Utilizador Admin
        // Usamos firstOrCreate para evitar duplicados se rodares o seed várias vezes
        User::firstOrCreate(
            ['email' => 'admin@leaflow.com'], // Procura por este email
            [
                'name' => 'Administrador do Sistema',
                'password' => 'Yirq3008!', // A password será "password"
                'cpf' => '000.000.000-01', // CPF fictício para passar na validação
                'role' => 'admin', // Define como administrador
                'cargo' => 'Gerente',
            ]
        );

        // Opcional: Criar alguns utilizadores de teste (advogados)
        
        User::factory()->create([
            'name' => 'Dr. Advogado Teste',
            'email' => 'advogado@leaflow.com',
            'role' => 'advogado',
            'cpf' => '111.111.111-11',
        ]);
        
    }
}