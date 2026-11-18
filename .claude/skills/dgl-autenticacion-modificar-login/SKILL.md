---
name: dgl-autenticacion-modificar-login
description: Guía de seguridad para modificar el acceso de SUILAFT DGL (login de index.php, contraseñas, sesión, CSRF, logout, enlaces externos por token de publico.php) y para añadir lo que falta — límite de intentos, recuperación de contraseña, cambio de clave, segundo factor TOTP, alta de usuarios. Úsala cada vez que toques algo relacionado con login, contraseñas, sesión, tokens o cierre de sesión, aunque el cambio parezca pequeño ("cambia el mensaje de error", "agrega recordarme").
---

# Modificar el acceso de DGL sin romper garantías

No es un motor para multiplicar: es una lista de invariantes. Un error aquí expone expedientes KYC/PEP.

## Lo que ya existe y no se puede romper

1. **Login** (`index.php` + `User::authenticate`): consulta preparada, `estado = 'A'` y `password_verify`. El mensaje de error es genérico ("Usuario o contraseña incorrectos.") y no distingue entre usuario inexistente y clave incorrecta.
2. **`session_regenerate_id(true)`** al autenticar, y regeneración del `csrf_token`. Todo paso nuevo que eleve privilegios (2FA, cambio de rol) también regenera.
3. **Cookies** con `httponly`, `samesite=Lax` y `secure` cuando hay HTTPS: el mismo bloque `session_set_cookie_params` al inicio de **cada** punto de entrada. Un punto de entrada nuevo lo copia tal cual.
4. **CSRF** con `hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])` en todo POST, incluido el logout.
5. **Logout** = `$_SESSION = array(); session_destroy();`, por POST con CSRF.
6. **`$_SESSION['user']` solo se fija con la autenticación completa.** Si agregas 2FA, la contraseña correcta deja únicamente `$_SESSION['sf_pendiente']`; ninguna pantalla debe aceptar eso como sesión.
7. **Tokens de invitación** (`Kyc::invitation`, `publico.php`): 32 bytes aleatorios, solo se guarda su SHA-256, vencen en 7 días, se marcan como usados dentro de la transacción con `usado_en IS NULL AND vence_en>NOW()` y la cabecera es `Referrer-Policy: no-referrer`. No los registres en logs, auditoría ni capturas.
8. Errores de base de datos o SMTP al visitante: mensaje general; el detalle va a `error_log`.

## Al añadir funciones que faltan

- **Límite de intentos**: tabla `login_intentos` (usuario, ip, creado_en). Comprueba **antes** de `password_verify`, por usuario y por IP (por ejemplo, 5 intentos en 15 minutos). Responde con el mismo mensaje genérico.
- **Segundo factor TOTP**: usa la skill `segundo-factor` (`~/Downloads/segundo-factor`, versión para PHP puro). Guarda el secreto cifrado con `openssl` y una clave fuera del webroot. Los códigos de recuperación se guardan como hash y se muestran **una sola vez**. El límite de intentos se aplica también a los códigos.
- **Recuperación o alta de cuenta**: token de un solo uso con hash y vencimiento corto (por ejemplo 1 h o 72 h), siguiendo el patrón de `kyc_invitaciones`. La persona define su propia contraseña; nadie la ve ni la envía por correo.
- **Política de contraseñas** en una sola función (por ejemplo `User::validarClave()`): mínimo 10 caracteres, letras y números, y que no contenga el usuario. `password_hash(PASSWORD_DEFAULT)` y `password_needs_rehash` al iniciar sesión.
- **Auditoría**: `login`, `login_fallido`, `logout` y `activar` con módulo `auth` (skill `auditoria-instrumentar`). Nunca registres la contraseña.

## Antes de subir

Pregúntate: ¿puede fijarse `$_SESSION['user']` antes de tiempo? ¿Se salta el límite de intentos? ¿Queda un token o secreto visible o en un log? Si la respuesta no es un "no" claro, que el usuario lo revise antes de desplegar. Prueba con un **usuario de prueba**, nunca con la única cuenta administradora, y ten preparado un `UPDATE` de rescate por si alguien queda fuera.

## Verificación

- [ ] El login correcto, el incorrecto y el de un usuario inactivo dan el mismo mensaje genérico.
- [ ] Tras varios fallos se bloquea (pruébalo de verdad).
- [ ] El ID de sesión cambia tras iniciar sesión (compara la cookie).
- [ ] Un POST sin CSRF responde 403.
- [ ] El logout invalida la sesión: volver atrás no da acceso.
- [ ] Un enlace externo usado o vencido responde 410.
