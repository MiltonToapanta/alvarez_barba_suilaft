<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Primer administrador. Si ADMIN_CLAVE está vacío, genera una y la muestra UNA vez. */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $usuario = env('ADMIN_USUARIO', 'admin');
        if (Usuario::where('usuario', $usuario)->exists()) {
            $this->command?->info("El usuario {$usuario} ya existe, se omite.");

            return;
        }

        $clave = env('ADMIN_CLAVE') ?: Str::password(16, symbols: false);
        $rol = Rol::where('es_admin', true)->orderBy('id')->first();

        Usuario::create([
            'usuario' => $usuario,
            'clave' => Hash::make($clave),
            'estado' => 'A',
            'cambio_clave' => '1',
            'nombres' => env('ADMIN_NOMBRES', 'Administrador SUILAFT'),
            'email' => env('ADMIN_EMAIL', ''),
            'rol' => $rol?->nombre ?? 'Administrador',
            'rol_id' => $rol?->id,
        ]);

        $this->command?->warn("Administrador creado → usuario: {$usuario}  clave: {$clave}");
        $this->command?->warn('Guárdala ahora: no se vuelve a mostrar. Al entrar se pedirá activar el Authenticator.');
    }
}
