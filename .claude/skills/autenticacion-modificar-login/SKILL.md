---
name: autenticacion-modificar-login
description: Guía de seguridad para modificar el flujo de acceso de SUIAUTO (login, doble autenticación con Authenticator/TOTP, códigos de recuperación, activación de cuenta por correo). Úsala cada vez que toques algo relacionado con login, contraseñas, 2FA, sesión, invitaciones de cuenta nueva o cierre de sesión — incluso para un cambio que parezca pequeño (ej. "agrega un botón de recordarme", "cambia el mensaje de error de login"). A diferencia de las demás skills de este proyecto, esta NO es un motor config-driven para multiplicar — es una lista de invariantes de seguridad que no se pueden romper, porque este flujo ya fue construido con cuidado deliberado y un error aquí compromete la cuenta de cualquier usuario del sistema.
---

# Modificar el flujo de login con seguridad

`LoginController` implementa acceso en dos pasos (contraseña + código TOTP del Authenticator) con códigos de recuperación, bloqueo por intentos fallidos, y activación de cuenta nueva por enlace de correo de un solo uso. No es un "motor" para replicar como KYC o Matriz — es infraestructura de seguridad ya construida con cuidado. El trabajo aquí casi nunca es "agregar un tipo nuevo", es "tocar esto sin romper una garantía que ya existe".

## Los invariantes que no se pueden romper

1. **`$_SESSION['usuario_id']` solo se fija después del segundo factor.** Mientras la contraseña fue correcta pero el código TOTP no, la sesión solo tiene `sf_pendiente` (ver `LoginController::pendiente()`). `Router::dispatch()` y `Controller::requireAuth()` miran `usuario_id`, no `sf_pendiente` — si alguna vez fijas `usuario_id` antes de validar el segundo factor, acabas de desactivar el 2FA para todo el sistema sin darte cuenta.

2. **`session_regenerate_id(true)` en cada cambio de nivel de privilegio.** Se llama al validar la contraseña (línea ~29) y otra vez al completar el acceso (`completarAcceso()`, línea ~239). Esto existe para que un ID de sesión robado antes del login no sirva después. Si agregas un paso intermedio nuevo al flujo, regenera el ID ahí también.

3. **El rate limiting (`SegundoFactor::bloqueado($usuarioId, $ip)`) se revisa ANTES de verificar el código**, tanto en `configurar()` como en `codigo()`. No remuevas esa verificación ni la muevas después de intentar validar — es lo que evita que alguien pruebe códigos por fuerza bruta.

4. **Los códigos de recuperación se muestran una sola vez.** En la base solo queda su hash (`sf_generar_codigos_recuperacion()` + `guardarCodigosRecuperacion()`); no hay forma de volver a mostrarlos después de `configurar()`. Si agregas una pantalla nueva que necesite mostrarlos, probablemente estás a punto de romper esa garantía — para.

5. **La política de contraseña vive en `validarClaveNueva()`** (mínimo 10 caracteres, letras + números, no puede contener el nombre de usuario). Si la cambias, cámbiala solo ahí — no dupliques la validación en otro lugar donde pueda quedar desincronizada.

6. **Todo evento de autenticación se audita**: `login`, `login_fallido`, `logout`, `activar` (cuenta activada), todos con `modulo = 'auth'`. Si agregas un paso nuevo al flujo (ej. un tercer factor), audítalo también — `/auditoria` es la única forma de investigar un incidente de acceso después del hecho.

## El flujo completo, para orientarte

```
POST /login (usuario + clave)
  → password_verify() ok → session_regenerate_id() → $_SESSION['sf_pendiente'] = {...}
  → ¿tiene 2FA activo? → /login/codigo : /login/configurar

/login/configurar (primera vez)
  → genera secreto TOTP, muestra QR
  → usuario ingresa código del Authenticator → Totp::verificar()
  → si correcto: activa 2FA, genera códigos de recuperación (se muestran UNA vez),
    completarAcceso() → sesión completa

/login/codigo (accesos siguientes)
  → usuario ingresa código TOTP O un código de recuperación
  → si correcto: completarAcceso() → sesión completa

/login/activar/{token} (cuenta nueva, sin sesión)
  → token de invitación de un solo uso, vence a las 72 horas
  → la persona crea su propia contraseña (nunca la ve un admin: se crea
    con bin2hex(random_bytes(32)) en UsuariosController::formulario())
```

## Antes de tocar cualquier archivo de este flujo

- `app/controllers/LoginController.php` — el flujo completo.
- `app/models/SegundoFactor.php` — TOTP, rate limiting, códigos de recuperación.
- `app/models/Invitacion.php` — tokens de activación de cuenta.
- `app/lib/Totp.php` — implementación TOTP (RFC 6238), vendorizada.
- `app/core/auditoria_helpers.php` — funciones `sf_cifrar`/`sf_descifrar`/`sf_generar_codigos_recuperacion` (revisa dónde están exactamente antes de asumir).

Pregúntate: *¿este cambio puede hacer que `usuario_id` se fije antes de tiempo, que se salte el rate limiting, o que un código de recuperación quede visible dos veces?* Si la respuesta no es un "no" claro, no lo subas sin que el usuario lo revise explícitamente — este no es el lugar para "probar en producción y ver qué pasa".

## Qué SÍ puedes cambiar con confianza

- Textos de error, estilos visuales de las vistas `login/*.php` — no tocan la lógica de sesión.
- Duración de `SF_PENDIENTE_SEGUNDOS` / `SF_VENTANA_MINUTOS` (constantes de configuración, no lógica).
- Agregar más contexto a `auditoria_registrar()` en los eventos existentes.

Para desplegar cualquier cambio aquí, usa la skill **`despliegue-ftp-produccion`** — y considera probarlo primero con una cuenta de prueba, nunca con la única cuenta administradora real, por si el cambio deja a alguien fuera del sistema.

## Checklist antes de dar por terminado un cambio aquí

- [ ] Iniciar sesión completa (contraseña + 2FA) sigue funcionando de punta a punta.
- [ ] Un código TOTP incorrecto no avanza la sesión ni dice más de lo necesario sobre por qué falló.
- [ ] Tras varios intentos fallidos, el bloqueo por rate limiting se activa (pruébalo, no lo asumas).
- [ ] Un código de recuperación usado una vez no funciona una segunda vez.
- [ ] El enlace de activación de cuenta funciona una sola vez y expira a las 72 horas.
- [ ] `logout` realmente destruye la sesión (`$_SESSION = []; session_destroy();`), no solo redirige.
- [ ] Cada evento relevante sigue apareciendo en `/auditoria` con el módulo `auth`.
