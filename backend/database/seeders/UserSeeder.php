<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'nombre'    => 'Admin',
                'apellidos' => 'Chuchemons',
                'email'     => 'admin@chuchemons.com',
                'password'  => Hash::make('admin1234'),
                'player_id' => '#Admin0001',
                'is_admin'  => true,
                'bio'       => 'Administrador del sistema.',
            ],
            [
                'nombre'    => 'Jordi',
                'apellidos' => 'Dape',
                'email'     => 'jordi@chuchemons.com',
                'password'  => Hash::make('password1234'),
                'player_id' => '#Jordi0001',
                'is_admin'  => false,
                'bio'       => 'Entrenador de Chuchemons apasionado.',
            ],
            [
                'nombre'    => 'Maria',
                'apellidos' => 'García',
                'email'     => 'maria@chuchemons.com',
                'password'  => Hash::make('password1234'),
                'player_id' => '#Maria0002',
                'is_admin'  => false,
                'bio'       => 'Col·leccionista de Chuchemons de tipus Aire.',
            ],
            [
                'nombre'    => 'Pere',
                'apellidos' => 'Puig',
                'email'     => 'pere@chuchemons.com',
                'password'  => Hash::make('password1234'),
                'player_id' => '#Pere0003',
                'is_admin'  => false,
                'bio'       => 'Especialista en Chuchemons de Terra.',
            ],
            [
                'nombre'    => 'Anna',
                'apellidos' => 'López',
                'email'     => 'anna@chuchemons.com',
                'password'  => Hash::make('password1234'),
                'player_id' => '#Anna0004',
                'is_admin'  => false,
                'bio'       => 'Domadora de Chuchemons aquàtics.',
            ],
            [
                'nombre'    => 'Marc',
                'apellidos' => 'Roca',
                'email'     => 'marc@chuchemons.com',
                'password'  => Hash::make('password1234'),
                'player_id' => '#Marc0005',
                'is_admin'  => false,
                'bio'       => 'Sempre busca evolucionar els seus Chuchemons.',
            ],
            [
                'nombre'    => 'Laia',
                'apellidos' => 'Mas',
                'email'     => 'laia@chuchemons.com',
                'password'  => Hash::make('password1234'),
                'player_id' => '#Laia0006',
                'is_admin'  => false,
                'bio'       => 'Estratega de combats Chuchemon.',
            ],
            [
                'nombre'    => 'Pau',
                'apellidos' => 'Vidal',
                'email'     => 'pau@chuchemons.com',
                'password'  => Hash::make('password1234'),
                'player_id' => '#Pau0007',
                'is_admin'  => false,
                'bio'       => 'Nou entrenador, gran potencial.',
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(['email' => $data['email']], $data);
        }
    }
}
