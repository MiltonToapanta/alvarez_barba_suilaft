<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga los catálogos replicados de SUIAUTO (UAFE, DPA, países, monedas, roles,
 * actividad económica...). Idempotente: solo carga si `roles` está vacía.
 * Se regeneran con tools/extraer_schema.py a partir de un volcado de SUIAUTO.
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('roles')->exists()) {
            $this->command?->info('Catálogos ya cargados, se omite.');

            return;
        }

        DB::unprepared(file_get_contents(database_path('seeders/sql/catalogos.sql')));

        // Configuración RESU propia de Alvarez Barba (completar con los datos de la UAFE).
        DB::table('resu_config')->updateOrInsert(['id' => 1], [
            'cdr' => '', 'identificacion_reporte' => 'RESU', 'umbral_usd' => 10000,
            'usr' => '', 'cod_agencia' => '', 'direccion_default' => '',
        ]);

        $this->command?->info('Catálogos cargados: '.DB::table('actividad_economica')->count().' actividades, '
            .DB::table('cat_dpa_parroquia')->count().' parroquias, '.DB::table('roles')->count().' roles.');
    }
}
