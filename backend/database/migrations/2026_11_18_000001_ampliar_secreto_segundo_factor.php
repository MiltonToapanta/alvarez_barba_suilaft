<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * En SUIAUTO el secreto TOTP se cifraba con sodium (~84 bytes). Aquí se cifra con
 * Crypt de Laravel (AES-256-CBC + MAC, ~260 bytes), así que la columna se amplía.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `usuario_segundo_factor` MODIFY `secreto_cifrado` VARBINARY(512) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `usuario_segundo_factor` MODIFY `secreto_cifrado` VARBINARY(255) NOT NULL');
    }
};
