<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el primer usuario administrador a partir de ADMIN_EMAIL y ADMIN_PASSWORD.
     * No hay contraseña por defecto: sin ADMIN_PASSWORD no se crea nada.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@agencia.test');
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($password === '') {
            $this->command?->warn('ADMIN_PASSWORD está vacío: no se creó el usuario administrador.');

            return;
        }

        // firstOrCreate: re-ejecutar el seeder no cambia la contraseña de un admin existente.
        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Administrador', 'password' => $password],
        );

        $admin->assignRole('admin');
    }
}
