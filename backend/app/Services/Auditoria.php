<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Bitácora `auditoria` (misma tabla y convenciones que SUIAUTO). */
class Auditoria
{
    public static function registrar(
        string $accion,
        string $modulo,
        ?string $entidadTipo = null,
        string|int|null $entidadId = null,
        ?string $descripcion = null,
        ?int $usuarioId = null,
        ?string $usuarioNombre = null,
    ): void {
        try {
            DB::table('auditoria')->insert([
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $usuarioNombre !== null ? mb_substr($usuarioNombre, 0, 150) : null,
                'modulo' => $modulo,
                'accion' => $accion,
                'entidad_tipo' => $entidadTipo,
                'entidad_id' => $entidadId !== null ? (string) $entidadId : null,
                'descripcion' => $descripcion !== null ? mb_substr($descripcion, 0, 500) : null,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe tumbar la acción principal, pero sí debe quedar rastro.
            Log::error('No se pudo registrar auditoría: '.$e->getMessage());
        }
    }
}
