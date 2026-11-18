---
name: vehiculos-operaciones-modulo
description: Referencia completa del módulo Vehículos y Operaciones de SUIAUTO — operaciones de compra/venta/arrendamiento con vehículos o maquinaria como bienes, base de datos, flujo, control de acceso y cómo alimenta el RESU. Úsala para construir, explicar, modificar o depurar el registro de operaciones vehiculares.
---

# Módulo Vehículos y Operaciones (referencia completa)

Esta skill documenta el módulo **Vehículos y Operaciones** de SUIAUTO tal como existe hoy en producción: qué hace, cómo está construido, su base de datos, su flujo de uso, el control de acceso, y cómo alimenta directamente la generación del RESU. Úsala cuando necesites explicar el módulo, modificarlo, depurar un problema en él, o entender de dónde sale exactamente cada dato que termina en un archivo del RESU.

No es un módulo de "vehículos" aparte de "operaciones": **es un solo módulo con un solo controlador** (`OperacionesController`), donde cada vehículo (o maquinaria) es un **bien** que cuelga de una **operación** (compra/venta/arrendamiento). No hay un listado de vehículos independiente de las operaciones — un vehículo sin operación asociada no existe en el sistema.

## Qué es y qué NO es

Vehículos y Operaciones es el módulo donde se registra, para cada sujeto KYC (cliente, proveedor, empleado, beneficiario final — los que define `kyc_tipos.php`), **todo lo que el RESU necesita reportar**:

1. **La operación** en sí (compra/venta/arrendamiento, financiamiento, valor total).
2. **El/los bien(es)** de esa operación (el o los vehículos/maquinarias, con chasis, placa, año, etc. — una operación puede tener varios bienes, ej. una venta con 2 vehículos).
3. **Las transacciones/pagos** de esa operación (uno o varios abonos, cada uno con sus instrumentos: débito, crédito, efectivo, cheque, tarjeta, título).
4. **La declaración de origen lícito de fondos** del sujeto (de dónde salió el dinero, con soporte documental y evaluación de coherencia).

Lo que este módulo **no hace**: no decide si una operación "aplica" al RESU (eso lo calcula `Resu.php` comparando el valor total contra el umbral configurado en Reportería) y no genera ningún archivo descargable — solo alimenta los datos que `reporteria-modulo` consume después. Para la lógica de armado del reporte y los 4 archivos, ver la skill **`reporteria-modulo`**.

## Arquitectura: los archivos que lo componen

| Capa | Archivo | Responsabilidad |
|---|---|---|
| Controlador | `app/controllers/OperacionesController.php` (~500 líneas) | Listado unificado de personas, CRUD de operación/bienes, CRUD de transacción, CRUD de origen lícito, PDF de origen lícito |
| Modelos | `app/models/Operacion.php`, `app/models/Transaccion.php`, `app/models/OrigenFondos.php` | Acceso a datos de cada una de las 3 entidades |
| Catálogos | `app/models/CatalogoResu.php` | Listas desplegables (tipo operación, tipo bien, provincia/cantón/parroquia en cascada, instrumentos permitidos por tipo de transacción) |
| Helpers | `app/core/personas_helpers.php` | `personas_grupos()`, `persona_grupo()`, `persona_doc()`, `persona_es_juridica()`, `persona_estado_etiqueta()` — también usados por Reportería |
| Vistas | `app/views/operaciones/{index,listados,operacion,transaccion,licito}.php` | UI |
| PDF | `app/core/PdfOrigenFondos.php` (vía FPDF) | Impresión de la declaración de origen lícito |

No hay archivo de configuración propio por tipo de sujeto — el módulo es **genérico sobre los 8 tipos de KYC** exactamente igual que Debida Diligencia (ver skill `debida-diligencia-modulo`): lee `kyc_todos_tipos()` en vivo, nunca una lista hardcodeada. Un tipo de KYC nuevo agregado con `kyc-nuevo-tipo` aparece solo en el listado de personas de este módulo.

## El concepto clave: `origen_tipo` + `origen_id`

Las tres tablas de datos (`operaciones`, `origen_fondos`, y por cascada `operacion_transacciones`/`operacion_bienes`) **no tienen una foreign key hacia una tabla de personas** — porque no existe una sola tabla de personas, existen 8 (una por tipo de KYC). En su lugar usan un patrón de referencia polimórfica:

- `origen_tipo` (varchar) = el código del tipo KYC (`cli-pn`, `pro-pj`, `emp`, etc.)
- `origen_id` (int) = el `id` dentro de la tabla de ese tipo

Para resolver "¿de quién es esta operación?" en una consulta conjunta, el modelo construye un `UNION ALL` de las 8 tablas al vuelo (`Operacion::sujetosSql()`, idéntico patrón en `Resu::cargarSujetos()`). Esto es deliberado — significa que **agregar un tipo de KYC nunca requiere tocar una FK aquí**, pero también significa que si alguna vez cambias el código de un tipo en `kyc_tipos.php` después de que ya existan operaciones con ese `origen_tipo` guardado, esas operaciones quedan huérfanas silenciosamente (no hay error, simplemente el UNION ya no las encuentra). No renombres códigos de tipo KYC existentes sin migrar `origen_tipo` en estas tablas.

## Flujo completo de uso

```
/operaciones
  → listado unificado de TODAS las personas de los 8 tipos de KYC (solo las
    activas), con filtro por tipo/texto/estado(completo-incompleto)/rango de fecha
  → columna "Avance operativo": calculada en vivo por fila (Operacion::resumenOperaciones())
    - ¿tiene operación registrada? ¿cuántas completas?
    - ¿tiene origen lícito? ¿validado?
    - ¿tiene transacciones?
  → 3 botones por fila: Registrar operación / Origen lícito / Transacción-pago

/operaciones/listados
  → vista de solo lectura: las últimas 150 operaciones, transacciones y
    declaraciones de origen lícito de TODO el sistema, cada una con enlace
    directo al formulario del sujeto (Operacion::listadosGlobales())

/operaciones/operacion/{codigo}/{id}/{opId?}
  → GET sin opId: formulario vacío para una operación nueva de ese sujeto,
    con el listado de operaciones previas del sujeto debajo
  → GET con opId: carga esa operación + sus bienes para editar
  → POST: guarda cabecera (numero_operacion, tipo_operacion, tipo_financiamiento,
    valores, fecha, estado) Y los bienes en la misma petición (filas dinámicas
    bien_tipo[]/bien_modelo[]/... del formulario) → guardarBienesDesdePost()
  → el numero_operacion se limpia con identificadorRESU() (solo A-Z0-9 mayúsculas,
    sin guiones) porque así lo exige el formato del RESU; si se deja vacío,
    se autogenera con siguienteNumero() → formato OP{año}{correlativo de 4 dígitos}

/operaciones/eliminarOperacion/{opId}
  → borra la operación (CASCADE borra sus bienes y transacciones también —
    ver FK en el esquema)

/operaciones/eliminarBien/{bienId}/{codigo}/{id}/{opId}
  → borra un bien suelto de una operación existente

/operaciones/transaccion/{codigo}/{id}/{operacionId?}
  → selector de operación del sujeto (si no se pasa operacionId por URL,
    se puede pasar por ?op=)
  → formulario de transacción: fecha, número, tipo de transacción (filtrado
    en el cliente según tipo de operación vía mapaOperacionTransaccion()),
    6 instrumentos de pago (débito/crédito/efectivo/cheque/tarjeta/título —
    el JS habilita solo los permitidos para ese tipo de transacción según
    instrumentosPorTransaccion()), campos BAD (umbrales de efectivo/cheque
    *50/100 salarios básicos*), pago/cobro con terceros (activa 4 campos
    adicionales solo si aplica)
  → POST ignora silenciosamente si operacion_id<=0 o tipo_transaccion vacío
    (guardarTransaccionDesdePost() simplemente "return" sin guardar ni avisar)

/operaciones/eliminarTransaccion/{transaccionId}
  → borra una transacción suelta

/operaciones/licito/{codigo}/{id}/{registroId?}
  → declaración de origen lícito de fondos: fecha, monto, moneda, fuente de
    fondos, forma de pago, entidad financiera/cuenta, provincia/cantón/
    parroquia (cascada DPA), descripción, soportes, evaluación de coherencia
    (si/parcial/no), declarante, estado (borrador/validado/observado)
  → puede asociarse opcionalmente a una operación específica (operacion_id nullable)

/operaciones/eliminarLicito/{registroId}
  → borra una declaración de origen lícito

/operaciones/imprimirLicito/{registroId}
  → genera el PDF de la declaración (FPDF, PdfOrigenFondos) y lo entrega inline
```

## Tabla de base de datos

`database/10_operaciones_transacciones.sql` — 4 tablas de datos + 1 de configuración (esa última es de Reportería, ver esa skill):

```sql
operaciones
  id, numero_operacion, origen_tipo, origen_id,          -- referencia polimórfica, ver sección anterior
  tipo_operacion (char3), tipo_financiamiento (char3, default 'NAP'),
  valor_financiamiento_directo, valor_financiamiento_externo, valor_total_operacion,  -- bigint unsigned
  fecha_operacion, estado enum('borrador','completo','anulado'),
  observaciones, creado_por, creado_en, actualizado_en

operacion_bienes                                          -- FK operacion_id ON DELETE CASCADE
  id, operacion_id, anio_fabricacion, tipo_bien (char3), modelo, marca,
  numero_chasis, cilindraje, nivel_blindaje (default 'NO'), condicion_bien (char1, default 'N'),
  numero_placa (default 'NO APLICA'), cod_provincia, cod_canton, cod_parroquia, valor

operacion_transacciones                                    -- FK operacion_id ON DELETE CASCADE
  id, operacion_id, fecha_transaccion, numero_transaccion, tipo_transaccion,
  valor_debito, valor_credito, valor_efectivo, valor_cheque, valor_tarjeta, valor_titulo, valor_total,
  cod_moneda (default 'USD'), cantidad_bad_50, monto_bad_50, cantidad_bad_100, monto_bad_100,
  cod_agencia, pago_cobro (char3, default 'PRS'), tipo_id_tercero, id_tercero,
  nombres_tercero, apellidos_tercero (default 'NO APLICA' los 3 últimos)

origen_fondos                                               -- SIN FK (operacion_id es nullable, sin constraint)
  id, origen_tipo, origen_id, operacion_id (nullable), fecha, monto, cod_moneda,
  fuente_id, forma_pago_id, entidad_financiera, cuenta,
  cod_provincia, cod_canton, cod_parroquia, descripcion, sustento,
  coherencia enum('si','parcial','no'), declarante,
  estado enum('borrador','validado','observado'), creado_por, creado_en, actualizado_en
```

Puntos importantes del modelo de datos:

- **`operaciones` y sus hijas sí tienen historial real** (a diferencia de Debida Diligencia): cada operación es una fila nueva, cada transacción es una fila nueva — no hay UPSERT aquí. Editar una operación existente hace `UPDATE`, pero nunca se sobreescribe una operación distinta.
- **Borrar es físico, no lógico.** `eliminarOperacion`/`eliminarTransaccion`/`eliminarLicito`/`eliminarBien` hacen `DELETE FROM` real. Para "anular" sin perder el registro, el estado correcto es poner `estado = 'anulado'` desde el formulario de operación — eso es lo que excluyen todas las consultas de reportería y resumen (`WHERE estado <> 'anulado'`). Si necesitas conservar evidencia de una operación que no debió existir, anúlala, no la borres.
- **`origen_fondos.operacion_id` es opcional y sin FK.** Una declaración de origen lícito puede existir suelta (sin ligarse a ninguna operación) o puede asociarse a una — pero si esa operación se borra después, la declaración queda con un `operacion_id` apuntando a nada (sin error, sin cascada). Es un gap de integridad referencial, no un bug activo, pero tenlo presente si tocas el borrado de operaciones.

## Control de acceso

Mismo mecanismo que el resto de módulos "controlados" (`Router::$modulosControlados` incluye `'operaciones'`):

1. `requireAuth()` en cada acción.
2. El Router verifica que `'operaciones'` esté en `$_SESSION['modulos']` (o que el usuario sea admin).
3. Nivel Ver/Editar: con nivel `"ver"` se bloquean todos los `POST` (registrar operación, guardar transacción, guardar origen lícito, cualquier `eliminar*`), pero los `GET` (listado, listados globales, formularios en modo lectura, impresión del PDF) pasan.
4. Roles de fábrica (`database/11_roles_usuarios.sql`): **todos** los roles no-administradores de este sistema (`Oficial de Cumplimiento`, `Analista`, `Consulta`) incluyen `operaciones` en su lista de módulos — es, junto con `kyc`, uno de los dos módulos que todo perfil operativo necesita.

**Nota de auditoría**: cada creación/edición/eliminación de operación, transacción y origen lícito, y cada impresión del PDF de origen lícito, se registra en `/auditoria` bajo el módulo `'operaciones'` — correcto y consistente en los 8 puntos del controlador que mutan datos.

## Cómo se conecta con el resto del sistema

- **KYC** (`kyc_todos_tipos()`, `kyc_tipo()`): este módulo no tiene catálogo de tipos propio — hereda los 8 tipos de KYC en vivo. Lee cada registro de persona solo para mostrar nombre/identificación y validar que el `{codigo}/{id}` de la URL exista (`personaOFallar()`); nunca lo edita.
- **RESU / Reportería** (`app/models/Resu.php`, ver skill `reporteria-modulo`): este es el consumidor principal. `Resu::armar($periodo)` vuelve a leer directamente `operaciones`, `operacion_bienes`, `operacion_transacciones` y `origen_fondos` con sus propias consultas (no reutiliza los modelos de este controlador) filtrando por el período pedido y el umbral configurado. Si cambias un nombre de columna aquí, **tienes que cambiarlo también en `Resu.php`** — no hay una sola fuente de verdad del esquema, hay dos lugares que conocen esta estructura.
- **Motor de Alertas** (`MotorAlertas.php`): no tiene (hoy) ningún detector que mire `operaciones`/`operacion_transacciones`/`origen_fondos` directamente — las alertas de este sistema se centran en KYC/listas/matriz. Si se necesita, por ejemplo, alertar sobre transacciones fraccionadas o montos BAD inusuales, es un detector nuevo (`alertas-nuevo-detector`), no algo que ya exista.
- **Dashboards**: no se confirmó en esta sesión si hay gráficos específicos de Operaciones en `/dashboards` — revisar `dashboards-nuevo-grafico` si se necesita uno nuevo sobre volumen operado/transaccionado.

## Rutas en producción

Base: `https://suilaft.tech/suiaut/`

| Ruta | Método | Qué hace |
|---|---|---|
| `/operaciones` | GET | Listado unificado de personas con filtros y avance operativo |
| `/operaciones/listados` | GET | Últimas 150 operaciones/transacciones/orígenes de todo el sistema |
| `/operaciones/operacion/{codigo}/{id}/{opId?}` | GET, POST | Ver/crear/editar una operación (con sus bienes) |
| `/operaciones/eliminarOperacion/{opId}` | GET (vía enlace) | Elimina una operación y en cascada sus bienes/transacciones |
| `/operaciones/eliminarBien/{bienId}/{codigo}/{id}/{opId}` | GET | Elimina un bien suelto |
| `/operaciones/transaccion/{codigo}/{id}/{operacionId?}` | GET, POST | Ver/crear/editar una transacción |
| `/operaciones/eliminarTransaccion/{transaccionId}` | GET | Elimina una transacción |
| `/operaciones/licito/{codigo}/{id}/{registroId?}` | GET, POST | Ver/crear/editar una declaración de origen lícito |
| `/operaciones/eliminarLicito/{registroId}` | GET | Elimina una declaración de origen lícito |
| `/operaciones/imprimirLicito/{registroId}` | GET | PDF de la declaración de origen lícito |

(Las acciones "eliminar" son enlaces `GET`, no formularios `POST` con confirmación — no llevan CSRF ni confirmación del lado del servidor; si el usuario hace clic por error, no hay deshacer porque el borrado es físico. Candidato real a mejora si se audita el módulo con rigor.)

## Qué SÍ puedes cambiar con confianza

- Agregar un campo nuevo a `operacion_bienes` (ej. "color del vehículo"): columna nueva + agregarlo a `guardarBienesDesdePost()` y al formulario — no afecta el RESU a menos que también lo agregues a `Resu::filaOperacion()` y a `resu_columnas.php` (el RESU tiene su propio formato fijo, no absorbe columnas nuevas solo por existir en la tabla).
- Agregar un filtro nuevo al listado de `/operaciones` (ej. por rango de valor): se agrega en `Operacion::personas()` siguiendo el mismo patrón de los filtros existentes.
- Textos, catálogos nuevos en `CatalogoResu::$mapa` si agregas una tabla `cat_*` nueva.

## Qué NO cambiar sin pensarlo dos veces

- **El nombre de las columnas que `Resu.php` lee directamente** (`origen_tipo`, `origen_id`, `valor_total_operacion`, `tipo_bien`, `numero_chasis`, los 6 instrumentos de transacción, etc.) — un cambio aquí sin el espejo en `Resu.php` rompe la generación del RESU de forma silenciosa (el campo simplemente sale vacío o con un error PHP de índice indefinido, no una excepción clara).
- **`identificadorRESU()`** (la limpieza de `numero_operacion`/`numero_transaccion` a solo A-Z0-9): el manual de la UAFE no admite guiones ni espacios en esos campos — no lo relajes aunque parezca más amigable para el usuario.
- **Cambiar borrado físico a borrado lógico sin revisar las 3 tablas hijas y el historial del RESU** — si decides que "eliminar" debería ser reversible, es un cambio de modelo de datos (columna `eliminado_en` o similar) en las 3 tablas a la vez, no solo en `operaciones`.
- **Los códigos de tipo (`origen_tipo`) de KYC existentes** — ver la sección "El concepto clave" arriba.

## Checklist antes de dar por terminado un cambio aquí

- [ ] `/operaciones` sigue mostrando el avance operativo correcto (chips verdes/ámbar) tras crear una operación, una transacción y un origen lícito de prueba.
- [ ] Guardar una operación con 2 bienes guarda realmente 2 filas en `operacion_bienes` (no solo la última).
- [ ] Editar una operación existente (con `opId`) actualiza, no duplica.
- [ ] Eliminar una operación efectivamente elimina sus bienes y transacciones (verificar en BD, no solo que la UI deje de mostrarla).
- [ ] El combo de tipo de transacción en `/operaciones/transaccion` se filtra según el tipo de operación elegida, y los instrumentos de pago habilitados cambian según el tipo de transacción.
- [ ] Un `numero_operacion`/`numero_transaccion` con guiones o espacios se limpia al guardar.
- [ ] El PDF de `/operaciones/imprimirLicito/{id}` se genera sin error con una declaración que tiene todos los campos opcionales vacíos.
- [ ] Cada creación/edición/eliminación sigue apareciendo en `/auditoria` bajo el módulo "operaciones".
- [ ] Un usuario con nivel "Ver" en Operaciones puede listar y leer, pero cualquier intento de guardar o eliminar lo redirige con `?acceso=solo_lectura`.
