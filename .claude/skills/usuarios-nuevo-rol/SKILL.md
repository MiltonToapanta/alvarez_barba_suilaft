---
name: usuarios-nuevo-rol
description: Agrega un rol nuevo (o cambia los permisos de uno existente) en SUIAUTO — qué módulos puede ver/usar cada tipo de usuario. Úsala cuando el usuario pida "crea un rol para...", "este tipo de usuario solo debería ver X e Y", o describa un perfil de acceso nuevo (ej. "un rol de solo lectura para auditores externos"). El control de acceso de SUIAUTO es por módulo completo (no por botón/acción dentro de un módulo) y vive en un solo lugar: la columna `modulos` de la tabla `roles`, que no tiene pantalla de administración propia — los roles se crean/editan por migración.
---

# Agregar o editar un rol

No hay `RolController` ni una pantalla para crear roles desde la interfaz — la tabla `roles` es un catálogo que se carga por migración (`database/11_roles_usuarios.sql` la creó, migraciones posteriores como `14_inteligencia_roles.sql` y `16_auditoria_roles.sql` la fueron actualizando). `UsuariosController` solo lee `Rol::listar()` para llenar el selector del formulario de usuario — no modifica roles.

## Cómo funciona el control de acceso (para no repetir el patrón a medias)

Es por **módulo completo**, en un solo punto: `Router::dispatch()`. Si el primer segmento de la URL es uno de los módulos controlados (`$modulosControlados` en `app/core/Router.php`: `kyc, matriz, institucional, debida, operaciones, reporteria, alertas, usuarios, ia, auditoria`) y el rol de la sesión no tiene esa clave en `modulos`, redirige a `?acceso=denegado`. `Controller::viewApp()` usa la misma lista de sesión para decidir qué tarjetas del panel y qué íconos del menú lateral se muestran — por eso un usuario sin permiso ni siquiera ve el enlace a lo que no puede abrir.

**Limitación conocida y a propósito**: el control es por módulo, no por acción dentro del módulo. Hoy no existe un "modo solo lectura" a nivel de botón — si un rol tiene acceso a `kyc`, puede crear/editar/desactivar, no solo ver. Si el usuario pide un rol de "solo consulta" real, dilo explícitamente: hoy no es posible sin cambios más profundos (habría que agregar un segundo nivel de permiso, fuera del alcance de esta skill).

## Paso a paso

### 1. Define qué módulos necesita el rol nuevo

Lista de claves válidas (coinciden con `ruta` en `app/config/modulos.php`): `kyc`, `matriz`, `institucional`, `debida`, `operaciones`, `reporteria`, `alertas`, `usuarios`, `ia`, `auditoria`. `dashboards` también es una ruta pero no está en `$modulosControlados` — **todo usuario logueado puede ver Dashboards sin importar su rol**, no hace falta agregarlo a `modulos`.

`usuarios` es especial y conviene no confundirse: aunque está en `$modulosControlados` de `Router`, `UsuariosController` tiene su propia compuerta (`soloAdmin()`) que **solo mira `$_SESSION['es_admin']`, no la lista `modulos`**. Es decir, agregar `usuarios` al CSV de un rol no administrador no le da acceso de verdad — `UsuariosController::soloAdmin()` lo bloquea en cada acción igual. Si necesitas un rol que gestione cuentas sin ser administrador total de todo lo demás, no alcanza con editar `roles.modulos`: hay que tocar `UsuariosController::soloAdmin()` para que revise permiso por módulo en vez de solo `es_admin` — eso es un cambio de código, no solo de datos, y queda fuera de esta skill (coméntaselo al usuario si lo pide).

### 2. Decide si es administrador

`es_admin = 1` da acceso a **todo**, incluido `usuarios`, sin importar lo que diga `modulos`. Resérvalo para el único rol que de verdad deba poder gestionar cuentas y ver todo el sistema. Los 4 roles actuales: Administrador (`es_admin=1`), Oficial de Cumplimiento, Analista, Consulta — ninguno de los últimos tres es admin.

### 3. Escribe la migración

```sql
-- database/NN_rol_<nombre_corto>.sql
INSERT INTO `roles` (`nombre`, `descripcion`, `modulos`, `es_admin`) VALUES
('Nombre del Rol', 'Descripción de para quién es y qué puede hacer.', 'kyc,matriz,alertas', 0)
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`), `modulos` = VALUES(`modulos`), `es_admin` = VALUES(`es_admin`);
```

`ON DUPLICATE KEY UPDATE` sobre `nombre` (que es `UNIQUE KEY`) hace que la migración sea segura de correr de nuevo si necesitas ajustar los módulos de un rol ya creado — no necesitas una migración nueva por cada ajuste de permisos, puedes volver a correr una variante de esta.

Para **agregar un módulo a un rol existente sin pisar los que ya tenía** (patrón usado en `14_inteligencia_roles.sql`):

```sql
UPDATE `roles`
SET `modulos` = CONCAT_WS(',', NULLIF(`modulos`, ''), 'nuevo_modulo')
WHERE `nombre` = 'Oficial de Cumplimiento'
  AND FIND_IN_SET('nuevo_modulo', `modulos`) = 0;
```

El `FIND_IN_SET(...) = 0` evita duplicar la clave si la migración se corre dos veces.

⚠️ Hay un bug real que ya ocurrió en este proyecto: una migración puso la clave `inteligencia` en vez de `ia` (el nombre real que usa `Router` y `modulos.php`), y ningún rol no-administrador pudo abrir ese módulo hasta corregirlo. **Verifica la clave exacta contra `app/config/modulos.php` (campo `ruta`) antes de escribir la migración**, no la adivines por el nombre del módulo.

### 4. Despliega

Usa la skill **`despliegue-ftp-produccion`** para correr la migración contra producción (el patrón del script temporal protegido por token). No hay código PHP que cambie — todo es la fila en `roles`.

### 5. Verifica (checklist)

- [ ] Crear o editar un usuario y asignarle el rol nuevo desde `/usuarios/formulario`.
- [ ] Iniciar sesión con esa cuenta: el menú lateral y el panel principal solo muestran los módulos que le diste.
- [ ] Intentar entrar por URL directa a un módulo que NO le diste (ej. `/auditoria` si no lo tiene) — debe redirigir a `?acceso=denegado`, no mostrar nada parcial.
- [ ] Si el rol no es administrador, confirmar que NO puede entrar a `/usuarios` aunque lo intente por URL directa — esto debe cumplirse siempre, sin importar si `usuarios` quedó en su lista de `modulos`.
