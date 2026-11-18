---
name: dgl-usuarios-nuevo-rol
description: Agrega un rol nuevo, cambia qué módulos ve cada rol, o construye la gestión de usuarios/roles de SUILAFT DGL. Úsala cuando el usuario pida "crea un rol para...", "este usuario solo debería ver X", "un rol de consulta/auditor externo", "crear usuarios desde el sistema", o al habilitar un módulo nuevo del dashboard y decidir quién puede abrirlo.
---

# Roles y permisos en DGL

## Estado (6-nov-2026)

**Ya existe** la gestión desde la interfaz: `usuarios.php` (usuarios y roles), tabla `roles` (permisos JSON por módulo: `ver` | `editar`), `models/Acceso.php` (`puede`, `exigir`, `exigirSegunMetodo`) y `models/Usuarios.php`. Los roles se crean y editan en pantalla, no en `config/roles.php`. Para un módulo nuevo: añade su clave en `Acceso::modulos()`, en `config/modulos.php` y llama `Acceso::exigirSegunMetodo('clave')` en su punto de entrada. Lo que sigue describe el estado anterior.

## Cómo estaba (diferente de SUIAUTO)

- No hay tabla `roles`. El rol es **texto libre** en `usuarios.rol` (`varchar(45)`) y se guarda en `$_SESSION['user']['rol']` al iniciar sesión (`models/User.php`, `index.php`).
- El control está **repetido en cada punto de entrada**. Por ejemplo, `kyc.php` compara `mb_strtolower(trim(rol))` contra `array('super administrador','oficial de cumplimiento')`. `catalogos.php` solo exige sesión.
- El dashboard (`views/dashboard.php`, `$modules`) muestra todas las tarjetas a todo usuario logueado.
- No hay pantalla de usuarios: se crean por SQL con hash `password_hash()` (`sql/crear_usuarios.sql`).

## Paso 1 (una sola vez): centralizar los permisos

Antes de agregar un segundo rol o un módulo nuevo, crea `config/roles.php`:

```php
<?php
return array(
 'super administrador'      => array('*'),
 'oficial de cumplimiento'  => array('formularios','pagos','diligencia','riesgos','resu','auditoria','catalogos','manual'),
 // 'analista'              => array('formularios','diligencia','catalogos'),
 // 'consulta'              => array('catalogos','manual'),
);
```

Y `models/Acceso.php` con `Acceso::puede($modulo)` (normaliza el rol con `mb_strtolower(trim())`; `'*'` da acceso a todo) y `Acceso::exigir($modulo)` (redirige a `index.php` sin sesión y responde 403 sin permiso). Sustituye la comprobación fija de `kyc.php` por `Acceso::exigir('formularios')` **sin cambiar quién tiene acceso hoy**. Las claves son las de `$modules` en `views/dashboard.php`. El dashboard oculta o desactiva las tarjetas sin permiso usando `Acceso::puede()`.

## Agregar o editar un rol

1. Añade la entrada en `config/roles.php` con el nombre **en minúsculas, tal como estará en `usuarios.rol`**.
2. Asigna el rol al usuario con un `UPDATE usuarios SET rol=? WHERE usuario=?` por migración o desde phpMyAdmin, siempre con respaldo previo.
3. Comprueba la clave del módulo contra `$modules`; no la adivines por el nombre visible. Una clave mal escrita deja al rol sin acceso sin ningún error.

## Limitaciones a decir en voz alta

- El permiso es por módulo, no por botón. Un "solo lectura" real exige un segundo nivel (por ejemplo `formularios:ver`) y revisar cada `operation` POST de `kyc.php`. Es un cambio de código, no de configuración.
- `publico.php` no usa roles: el acceso lo da el token de invitación. No lo metas en `Acceso`.
- Gestión de usuarios desde la interfaz (alta, contraseña inicial, desactivar): requiere la skill `autenticacion-modificar-login`, porque toca contraseñas.

## Verificación

- [ ] Los usuarios actuales conservan exactamente el acceso que tenían.
- [ ] Un usuario de prueba con el rol nuevo solo ve y abre sus módulos; por URL directa a otro recibe 403.
- [ ] Un rol con mayúsculas o espacios distintos en la base de datos se reconoce igual.
- [ ] Un rol desconocido no tiene acceso a nada salvo el dashboard.
- [ ] Si existe la auditoría, los cambios de rol quedan registrados.
