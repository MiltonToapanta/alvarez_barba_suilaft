<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\SegundoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Acceso en dos pasos: contraseña y luego código del Authenticator (TOTP).
 *
 * INVARIANTE: tras la contraseña solo queda en sesión 'sf_pendiente'. El login
 * real (Auth::login, lo que mira auth:sanctum) se hace recién en completarAcceso(),
 * después de validar el segundo factor. Ver skill autenticacion-modificar-login.
 */
class AccesoController extends Controller
{
    public function __construct(private SegundoFactor $sf) {}

    /** Estado del flujo para el frontend. */
    public function estado(Request $request): JsonResponse
    {
        if ($u = Auth::guard('web')->user()) {
            return response()->json(['estado' => 'autenticado', 'usuario' => $u->resumen()]);
        }
        if ($u = $this->pendiente($request)) {
            $sf = $this->sf->obtener($u->id_usuarios);

            return response()->json(['estado' => $sf && $sf->activo ? 'codigo' : 'configurar']);
        }

        return response()->json(['estado' => 'anonimo']);
    }

    /** Paso 1: usuario + contraseña. */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string', 'max:50'],
            'clave' => ['required', 'string', 'max:255'],
        ]);
        $usuarioTxt = trim($datos['usuario']);
        $clave = Str::lower($usuarioTxt).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts('login:'.$clave, 5)) {
            $seg = RateLimiter::availableIn('login:'.$clave);

            return response()->json(['message' => "Demasiados intentos. Intenta de nuevo en {$seg} segundos."], 429);
        }

        $u = Usuario::where('usuario', $usuarioTxt)->first();
        // Hash::check contra un hash ficticio si no existe: mismo tiempo de respuesta (no revela usuarios).
        $ok = Hash::check($datos['clave'], $u?->clave ?? '$2y$12$abcdefghijklmnopqrstuuLxw9zCqJk2eW7b0N6Y2u8L3mJx4xXe6');

        if (! $u || ! $ok || ! $u->activo()) {
            RateLimiter::hit('login:'.$clave, 60);
            Auditoria::registrar('login_fallido', 'auth', 'usuario', $u?->id_usuarios,
                'Intento fallido con usuario "'.mb_substr($usuarioTxt, 0, 50).'"'.($u && $ok ? ' (usuario inactivo).' : '.'),
                null, $usuarioTxt);

            return response()->json(['message' => 'Usuario o contraseña incorrectos.'], 422);
        }

        RateLimiter::clear('login:'.$clave);

        // Cambio de nivel de privilegio: sesión nueva, sin nada de la anterior.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('sf_pendiente', ['id' => $u->id_usuarios, 'desde' => time()]);

        $sf = $this->sf->obtener($u->id_usuarios);

        return response()->json(['estado' => $sf && $sf->activo ? 'codigo' : 'configurar']);
    }

    /** Primer acceso: datos para el QR. */
    public function datosConfigurar(Request $request): JsonResponse
    {
        $u = $this->pendienteOFallar($request);
        $sf = $this->sf->obtener($u->id_usuarios);
        if ($sf && $sf->activo) {
            return response()->json(['message' => 'La doble autenticación ya está activa.', 'estado' => 'codigo'], 409);
        }

        $secreto = $this->sf->secretoPendiente($u->id_usuarios);

        return response()->json(['secreto' => $secreto, 'uri' => $this->sf->uri($u, $secreto)]);
    }

    /** Primer acceso: confirma el primer código y activa el 2FA. */
    public function configurar(Request $request): JsonResponse
    {
        $u = $this->pendienteOFallar($request);
        $codigo = (string) $request->validate(['codigo' => ['required', 'string', 'max:20']])['codigo'];
        $sf = $this->sf->obtener($u->id_usuarios);

        if (! $sf || $sf->activo) {
            return response()->json(['message' => 'Vuelve a iniciar sesión.'], 409);
        }
        if ($this->sf->bloqueado($u->id_usuarios, $request->ip())) {
            return response()->json(['message' => 'Demasiados intentos fallidos. Espera unos minutos.'], 429);
        }
        if (! $this->sf->verificarTotp($sf, trim($codigo))) {
            $this->sf->registrarIntento($u->id_usuarios, $request->ip(), false);
            Auditoria::registrar('login_fallido', 'auth', 'usuario', $u->id_usuarios,
                'Código de activación del Authenticator incorrecto.', $u->id_usuarios, $u->nombres);

            return response()->json(['message' => 'Código incorrecto. Revisa la hora del móvil e inténtalo de nuevo.'], 422);
        }

        $this->sf->registrarIntento($u->id_usuarios, $request->ip(), true);
        $this->sf->activar($sf);
        $codigos = $this->sf->generarCodigosRecuperacion($u->id_usuarios);
        Auditoria::registrar('activar', 'auth', 'usuario', $u->id_usuarios,
            'Doble autenticación (Authenticator) activada.', $u->id_usuarios, $u->nombres);

        $this->completarAcceso($request, $u, 'Authenticator, primer registro');

        // Única vez que salen los códigos en claro.
        return response()->json(['usuario' => $u->resumen(), 'codigos_recuperacion' => $codigos]);
    }

    /** Accesos siguientes: código TOTP o código de recuperación. */
    public function codigo(Request $request): JsonResponse
    {
        $u = $this->pendienteOFallar($request);
        $codigo = trim((string) $request->validate(['codigo' => ['required', 'string', 'max:20']])['codigo']);
        $sf = $this->sf->obtener($u->id_usuarios);

        if (! $sf || ! $sf->activo) {
            return response()->json(['message' => 'Vuelve a iniciar sesión.'], 409);
        }
        if ($this->sf->bloqueado($u->id_usuarios, $request->ip())) {
            return response()->json(['message' => 'Demasiados intentos fallidos. Espera unos minutos.'], 429);
        }

        $esRecuperacion = str_contains($codigo, '-');
        $ok = $esRecuperacion
            ? $this->sf->usarCodigoRecuperacion($u->id_usuarios, $codigo)
            : $this->sf->verificarTotp($sf, $codigo);

        $this->sf->registrarIntento($u->id_usuarios, $request->ip(), $ok);

        if (! $ok) {
            Auditoria::registrar('login_fallido', 'auth', 'usuario', $u->id_usuarios,
                'Código de segundo factor incorrecto o ya usado.', $u->id_usuarios, $u->nombres);

            return response()->json(['message' => 'Código incorrecto o ya usado.'], 422);
        }

        $this->completarAcceso($request, $u, $esRecuperacion ? 'código de recuperación' : 'Authenticator');

        return response()->json(['usuario' => $u->resumen()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $u = Auth::guard('web')->user();
        if ($u) {
            Auditoria::registrar('logout', 'auth', 'usuario', $u->id_usuarios, 'Cierre de sesión.', $u->id_usuarios, $u->nombres);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['estado' => 'anonimo']);
    }

    // ------------------------------------------------------------------

    private function completarAcceso(Request $request, Usuario $u, string $metodo): void
    {
        $request->session()->forget('sf_pendiente');
        Auth::guard('web')->login($u);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        Auditoria::registrar('login', 'auth', 'usuario', $u->id_usuarios,
            "Inicio de sesión de {$u->nombres} con doble autenticación ({$metodo}).", $u->id_usuarios, $u->nombres);
    }

    /** Usuario con contraseña ya validada y segundo factor pendiente (si no venció). */
    private function pendiente(Request $request): ?Usuario
    {
        $p = $request->session()->get('sf_pendiente');
        if (! $p) {
            return null;
        }
        if (time() - (int) $p['desde'] > config('suilaft.sf_pendiente_segundos')) {
            $request->session()->forget('sf_pendiente');

            return null;
        }
        $u = Usuario::find($p['id']);

        return $u && $u->activo() ? $u : null;
    }

    private function pendienteOFallar(Request $request): Usuario
    {
        $u = $this->pendiente($request);
        abort_unless($u, 401, 'La sesión de acceso venció. Vuelve a iniciar sesión.');

        return $u;
    }
}
