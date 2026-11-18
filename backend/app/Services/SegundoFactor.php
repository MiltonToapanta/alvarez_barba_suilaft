<?php

namespace App\Services;

use App\Models\Usuario;
use App\Models\UsuarioSegundoFactor;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (Authenticator), bloqueo por intentos y códigos de recuperación.
 * Tablas heredadas de SUIAUTO: usuario_segundo_factor, segundo_factor_intentos,
 * usuario_codigo_recuperacion.
 */
class SegundoFactor
{
    public function __construct(private Google2FA $g2fa = new Google2FA) {}

    public function obtener(int $usuarioId): ?UsuarioSegundoFactor
    {
        return UsuarioSegundoFactor::find($usuarioId);
    }

    /** Devuelve el secreto pendiente (lo crea si no existe) para que recargar no invalide un QR ya escaneado. */
    public function secretoPendiente(int $usuarioId): string
    {
        $sf = $this->obtener($usuarioId);
        if ($sf && ! $sf->activo) {
            return Crypt::decryptString($sf->secreto_cifrado);
        }
        $secreto = $this->g2fa->generateSecretKey(32);
        UsuarioSegundoFactor::updateOrCreate(
            ['usuario_id' => $usuarioId],
            ['secreto_cifrado' => Crypt::encryptString($secreto), 'activo' => false, 'ultimo_paso_usado' => null, 'activado_en' => null],
        );

        return $secreto;
    }

    public function uri(Usuario $u, string $secreto): string
    {
        return $this->g2fa->getQRCodeUrl(config('suilaft.sf_emisor'), $u->usuario, $secreto);
    }

    /** ¿Demasiados fallos recientes para este usuario o esta IP? Revisar ANTES de verificar. */
    public function bloqueado(int $usuarioId, ?string $ip): bool
    {
        $desde = now()->subMinutes((int) config('suilaft.sf_ventana_minutos'));
        $max = (int) config('suilaft.sf_max_intentos');

        $porUsuario = DB::table('segundo_factor_intentos')
            ->where('usuario_id', $usuarioId)->where('exito', 0)->where('creado_en', '>=', $desde)->count();
        $porIp = $ip ? DB::table('segundo_factor_intentos')
            ->where('ip', $ip)->where('exito', 0)->where('creado_en', '>=', $desde)->count() : 0;

        return $porUsuario >= $max || $porIp >= $max * 4;
    }

    public function registrarIntento(int $usuarioId, ?string $ip, bool $exito): void
    {
        DB::table('segundo_factor_intentos')->insert(['usuario_id' => $usuarioId, 'ip' => $ip, 'exito' => $exito ? 1 : 0]);
    }

    /**
     * Verifica un código TOTP. Rechaza la reutilización del mismo código
     * (ultimo_paso_usado) y guarda el paso usado. Ventana ±1 paso (30 s).
     */
    public function verificarTotp(UsuarioSegundoFactor $sf, string $codigo): bool
    {
        if (! preg_match('/^\d{6}$/', $codigo)) {
            return false;
        }
        $secreto = Crypt::decryptString($sf->secreto_cifrado);
        $paso = $this->g2fa->verifyKeyNewer($secreto, $codigo, $sf->ultimo_paso_usado, 1);
        if ($paso === false) {
            return false;
        }
        // verifyKeyNewer devuelve true cuando no hay paso previo: calculamos el actual.
        $sf->ultimo_paso_usado = $paso === true ? $this->g2fa->getTimestamp() : $paso;
        $sf->save();

        return true;
    }

    public function activar(UsuarioSegundoFactor $sf): void
    {
        $sf->activo = true;
        $sf->activado_en = now();
        $sf->save();
    }

    /** Genera 10 códigos XXXX-XXXX; en la base solo queda su hash. Se devuelven una única vez. */
    public function generarCodigosRecuperacion(int $usuarioId): array
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigos = [];
        for ($i = 0; $i < 10; $i++) {
            $c = '';
            for ($j = 0; $j < 8; $j++) {
                $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $codigos[] = substr($c, 0, 4).'-'.substr($c, 4);
        }

        DB::transaction(function () use ($usuarioId, $codigos) {
            DB::table('usuario_codigo_recuperacion')->where('usuario_id', $usuarioId)->delete();
            DB::table('usuario_codigo_recuperacion')->insert(array_map(
                fn ($c) => ['usuario_id' => $usuarioId, 'codigo_hash' => Hash::make($c)],
                $codigos,
            ));
        });

        return $codigos;
    }

    /** Consume un código de recuperación (un solo uso). */
    public function usarCodigoRecuperacion(int $usuarioId, string $codigo): bool
    {
        $codigo = Str::upper(trim($codigo));
        if (! preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $codigo)) {
            return false;
        }
        $filas = DB::table('usuario_codigo_recuperacion')
            ->where('usuario_id', $usuarioId)->whereNull('usado_en')->get(['id', 'codigo_hash']);

        foreach ($filas as $f) {
            if (Hash::check($codigo, $f->codigo_hash)) {
                // update condicionado: si dos peticiones llegan a la vez, solo una lo consume.
                return DB::table('usuario_codigo_recuperacion')
                    ->where('id', $f->id)->whereNull('usado_en')
                    ->update(['usado_en' => now()]) === 1;
            }
        }

        return false;
    }
}
