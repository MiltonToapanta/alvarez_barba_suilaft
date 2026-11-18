---
name: dgl-auditoria-instrumentar
description: Construye (la primera vez) y usa la bitácora de Auditoría de SUILAFT DGL para dejar trazado quién hizo qué, cuándo y sobre qué expediente. Úsala al crear el módulo "Auditoría" del dashboard, y cada vez que agregues o modifiques un alta, edición, desactivación, descarga de adjunto, envío de correo, generación de enlace, PDF, login/logout, cambio de estado o aprobación; también si preguntan "¿por qué esto no aparece en la bitácora?" o piden "que esto quede registrado/auditado".
---

# Bitácora de auditoría en DGL

## Estado

**Existe desde el 6-nov-2026**: `models/Auditoria.php` (`registrar()` admite `$db = null` y un 9.º parámetro `usuarioLogin`), `auditoria.php`, tabla `auditoria`. Ya instrumentados: auth, formularios, publico, pagos, catalogos, riesgos, institucional, resu, usuarios y alertas. Para una acción nueva, usa el vocabulario de `Auditoria::acciones()` (incluye además `eliminar`, `validar_archivo`, `escanear`, `cambiar_clave`) y sigue "Las tres reglas". Lo que sigue es el diseño original.

## Construir el motor (una sola vez)

1. Migración `sql/NN_auditoria.sql`:
   ```sql
   CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    usuario_nombre VARCHAR(250) NOT NULL,
    modulo VARCHAR(40) NOT NULL,
    accion VARCHAR(40) NOT NULL,
    entidad_tipo VARCHAR(40) NULL,
    entidad_id INT NULL,
    descripcion VARCHAR(500) NOT NULL,
    ip VARCHAR(45) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (modulo, accion), INDEX (entidad_tipo, entidad_id), INDEX (creado_en)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```
   Solo inserciones: la app nunca hace `UPDATE` ni `DELETE` sobre `auditoria`.
2. `models/Auditoria.php` con `Auditoria::registrar($db, $accion, $modulo, $entidadTipo, $entidadId, $descripcion, $usuarioId = null, $usuarioNombre = null)`:
   - Por defecto toma `$_SESSION['user']['id']` y `['nombres']`.
   - Recorta la descripción a 500 caracteres con `mb_substr`.
   - Envuelve su `INSERT` en `try/catch (Throwable)` + `error_log`: **nunca** bloquea la acción auditada.
   - Etiquetas en `Auditoria::acciones()` y `Auditoria::modulos()`.
3. `auditoria.php` (raíz) + `views/auditoria.php`: listado de solo lectura con filtros (módulo, acción, usuario, texto, rango de fechas) y paginación de 50 (como `catalogos.php`). Acceso: roles `super administrador` y `oficial de cumplimiento`, el mismo patrón que `kyc.php`. Diseño: skill `diseno-dgl`.
4. Habilita la tarjeta `auditoria` en `views/dashboard.php` (las condiciones `in_array($module[0], array('formularios','catalogos'))` y el `if/elseif` de las tarjetas).
5. Instrumenta lo que ya existe (lista abajo).

## Las tres reglas

1. **Registrar después de que la acción tuvo éxito**, nunca antes. En `publico.php`, dentro de la transacción y antes del `commit()`, o justo después: no dejes registrada una acción revertida.
2. **No bloquear**: no añadas otro try/catch alrededor; ya lo trae `registrar()`.
3. **Usuario explícito solo sin sesión**: en `publico.php` usa `null, 'Titular (enlace externo)'`.

## Vocabulario fijo

Acciones: `crear`, `editar`, `desactivar`, `activar`, `descargar`, `subir_adjunto`, `imprimir`, `generar_enlace`, `enviar_correo`, `envio_externo`, `cambiar_estado`, `aprobar`, `generar_reporte`, `login`, `login_fallido`, `logout`.
Módulos (coinciden con las claves de `$modules` del dashboard): `formularios`, `pagos`, `diligencia`, `riesgos`, `resu`, `auditoria`, `catalogos`, `manual`, `auth`, `publico`.

Reutiliza estos valores; no crees sinónimos (`alta` frente a `crear`).

## Puntos a instrumentar hoy

| Archivo | Lugar | Acción |
|---|---|---|
| `index.php` | login correcto, fallido y logout | `login` / `login_fallido` (con el usuario escrito, **nunca la contraseña**) / `logout`, módulo `auth` |
| `kyc.php` | `Kyc::save()` sin id / con id | `crear` / `editar` |
| `kyc.php` | `operation=deactivate` (si `affected_rows`=1) | `desactivar` |
| `kyc.php` | `operation=invite` / `email` | `generar_enlace` / `enviar_correo` (**sin el token ni la URL**) |
| `kyc.php` | `operation=upload`, `accion=descargar`, `accion=imprimir` | `subir_adjunto` / `descargar` / `imprimir` |
| `publico.php` | envío correcto | `envio_externo`, módulo `publico` |

## Descripción

Debe ser específica y entenderse sin abrir el expediente: `Expediente Socio o accionista #12 editado (PRUEBA - Ejemplo, TEST-001)`. Nunca incluyas contraseñas, tokens, enlaces de invitación, contenido de adjuntos ni datos PEP o económicos detallados.

## Despliegue y verificación

Usa `despliegue-ftp-produccion` (migración primero, después el modelo y por último los puntos de entrada).

- [ ] Cada acción deja exactamente una fila con usuario, módulo, acción y descripción correctos.
- [ ] Una validación fallida o un conflicto de edición no deja fila.
- [ ] Si la tabla `auditoria` no existe, la acción sigue funcionando (error en el log PHP).
- [ ] Los filtros y la paginación de `auditoria.php` funcionan; un rol no autorizado recibe 403.
- [ ] En la bitácora no aparece ningún token ni enlace.
