---
name: reporteria-modulo
description: Referencia completa del módulo de Reportería / RESU de SUIAUTO (sector comercialización de vehículos ante la UAFE) — de dónde sale cada columna del RESU, cómo se arma y entrega el archivo, cómo depurar por qué un sujeto no aparece. Úsala para explicar, mantener o replicar el RESU vehicular. Para portarlo a otro sector usa reporteria-resu-adaptar.
---

# Módulo de Reportería / RESU (referencia completa)

Esta skill documenta el módulo de **Reportería** de SUIAUTO tal como existe hoy en producción: el motor que arma y entrega el **RESU** (Reporte de Operaciones y Transacciones Económicas que Igualen o Superen el Umbral legal) exigido por la UAFE al sector de comercialización de vehículos. Úsala cuando necesites explicar el módulo, depurar por qué un sujeto no sale en un archivo generado, o entender exactamente de dónde sale cada columna del RESU.

**No confundir con la skill `reporteria-resu-adaptar`**: esa skill es para **portar este motor a un manual de la UAFE distinto** (otro sector regulado). Esta skill de aquí es para **entender y mantener el motor tal como está hoy**, configurado para el sector vehicular. Si lo que necesitas es cambiar a qué sector aplica, usa esa; si necesitas depurar, ajustar un campo o explicar cómo funciona el RESU actual, usa esta.

## Qué es y qué NO es

Reportería **no registra ningún dato nuevo** — es una capa de **extracción, validación y empaquetado** sobre datos que ya existen en otro módulo:

- Las operaciones, bienes (vehículos), transacciones y declaraciones de origen lícito se registran en **Vehículos y Operaciones** (`OperacionesController`/`Operacion.php`/`Transaccion.php`/`OrigenFondos.php` — ver skill **`vehiculos-operaciones-modulo`**).
- Los sujetos (clientes, proveedores, empleados, beneficiarios finales) vienen de **KYC** (`kyc_todos_tipos()`).
- Reportería solo **lee** todo eso para un período (`YYYY-MM`), decide qué sujetos/operaciones "aplican" al RESU según un umbral configurable, da formato a cada campo exactamente como exige el manual de la UAFE, y genera el archivo (Excel/CSV o XML) para descargar.

Por eso casi ningún error real de "el RESU salió mal" se arregla tocando este módulo — casi siempre el dato de origen está mal capturado en Operaciones o en el expediente KYC. Este módulo es el lugar correcto para depurar **el formato de salida**, no los datos de entrada.

## Arquitectura: los archivos que lo componen

| Capa | Archivo | Responsabilidad |
|---|---|---|
| Controlador | `app/controllers/ReporteriaController.php` | Panel, guardar configuración, generar y entregar un archivo, re-descargar del historial |
| Modelo (motor) | `app/models/Resu.php` | Extrae datos del período, aplica el umbral, construye las filas de los 4 archivos y la cabecera |
| Generador Excel | `app/core/ResuExcel.php` | `.xlsx` sin dependencias (ZipArchive con las partes mínimas OOXML); si `ZipArchive` no está disponible, cae a CSV |
| Generador XML | `app/core/ResuXml.php` | Los 2 archivos XML (`CABECERA` y `DETALLE`) según el XSD oficial de la UAFE — **ver la observación importante más abajo: hoy no es alcanzable desde la UI** |
| Encabezados oficiales | `app/config/resu_columnas.php` | Nombres de columna exactos de las plantillas Excel de la UAFE, literales, para los 4 archivos |
| Helpers | `app/core/personas_helpers.php` | `resu_texto()` (normaliza tildes/mayúsculas), `resu_entero()`, `resu_fecha()`, `resu_tipo_identificacion()`, `resu_nombres_apellidos()`, `resu_direccion()`, `resu_tipos_reporte()` |
| Vista | `app/views/reporteria/index.php` | Panel único: configuración, generación, historial |

## El umbral y la clasificación de "aplica"

El RESU no reporta todas las operaciones, solo las que **igualan o superan un umbral en USD** configurado en `resu_config.umbral_usd` (por defecto 10000). `Resu::armar()` suma el `valor_total_operacion` de todas las operaciones de un sujeto en el período y:

- si el total ≥ umbral → el sujeto **aplica** (se incluye en los 4 archivos)
- si el total > 0 pero < umbral → **no alcanza** (se cuenta pero no se incluye)
- si el sujeto no tiene expediente KYC cargado → **incompleta** con motivo `'Sin formulario KYC asociado'`
- si el sujeto aplica pero le faltan campos obligatorios del RESU (identificación, nombres, dirección, provincia/cantón/parroquia del origen lícito, actividad económica) → también **incompleta**, con el motivo exacto por campo (`filaCliente()` acumula `_faltantes`)

Estas 3 categorías (`aplica`/`no_alcanza`/`incompleta`) se muestran en el panel como `RESU_APLICA:n;NO_ALCANZA:n;INCOMPLETA:n` en la columna "Validación" del historial — es la primera cosa a mirar cuando un sujeto que debería salir en el reporte no aparece.

**Importante**: la clasificación se hace por **operaciones**, no por transacciones. Una transacción solo se incluye en el archivo `TRANSACCION` si su operación ya calificó como `aplica` — una transacción grande sobre una operación pequeña no "arrastra" por sí sola la inclusión.

## Los 4 archivos (+ 2 XML sin salida en la UI)

`resu_tipos_reporte()` define 4 tipos, cada uno con su archivo `.xlsx`/`.csv`:

| Tipo | Prefijo de archivo | Contenido | Una fila por... |
|---|---|---|---|
| `CABECERA` | `CABECERA{CDR}{AAAA}{MM}` | Totales de control (conteos y sumas) que validan los otros 3 | Todo el período (1 fila) |
| `CLIENTE` | `DETALLECLIENTE{CDR}{AAAA}{MM}` | Datos del sujeto | Sujeto que aplica |
| `OPERACION` | `DETALLEOPERACION{CDR}{AAAA}{MM}` | Datos de la operación + el bien | **Bien** (un vehículo), no por operación — una operación con 2 autos genera 2 filas |
| `TRANSACCION` | `DETALLETRANSACCION{CDR}{AAAA}{MM}` | Pago/cobro | Transacción |

**Observación real sobre el código**: `ReporteriaController::entregar()` y `ResuXml.php` ya tienen implementada la generación de los **2 archivos XML oficiales** (`CABECERA` y `DETALLE`, respetando el XSD de la UAFE) para los tipos `XML_CABECERA`/`XML_DETALLE` — pero **`resu_tipos_reporte()` (la función que alimenta tanto el panel como la validación de `generar()`) solo declara los 4 tipos Excel/CSV de la tabla de arriba**. Esto significa que:
- el botón para generar XML **no existe en la UI** (`/reporteria` solo itera `resu_tipos_reporte()`), y
- si alguien intentara llamar a `/reporteria/generar` con `tipo_reporte=XML_CABECERA` manualmente, `generar()` lo rechazaría igual (`!array_key_exists($tipo, resu_tipos_reporte())` → `?error=parametros`).

Es decir: **el código XML es funcionalmente inalcanzable hoy**, probablemente una funcionalidad dejada a medio exponer. Si el usuario pide "quiero descargar el XML del RESU", la solución no es escribir el generador (ya existe) — es agregar `XML_CABECERA`/`XML_DETALLE` a `resu_tipos_reporte()` y al switch de `nombreArchivo()`/`cantidadRegistros()` del controlador, y probar que el XML resultante valida contra el XSD real de la UAFE antes de darlo por bueno.

## Flujo completo de uso

```
/reporteria
  → 2 gráficos de barras simples (reportes generados por tipo, volumen operado
    por mes) calculados por Resu::conteoPorTipo() / Resu::resumenMensual()
  → formulario de configuración (fila única, id=1 en resu_config):
      CDR (código de registro, máx 5), identificación del reporte,
      umbral USD, USR (código de usuario), código de agencia, dirección por defecto
  → formulario de generación: período (mes) + 4 tarjetas-botón, una por tipo
    de archivo (CABECERA/CLIENTE/OPERACION/TRANSACCION)
  → al enviar: POST a /reporteria/generar, descarga inmediata del archivo
  → debajo: historial de todas las generaciones previas (fecha, tipo, período,
    registros, validación, quién lo generó), cada una con enlace de re-descarga

/reporteria/guardarConfig   (POST)
  → actualiza la fila única de resu_config

/reporteria/generar   (POST)
  → valida período (regex YYYY-MM) y tipo (debe estar en resu_tipos_reporte())
  → Resu::armar($periodo) construye el reporte completo
  → registra la generación en resu_historial (auditoría + trazabilidad)
  → entrega el archivo (Excel si ZipArchive existe, si no CSV)

/reporteria/descargar/{id}   (GET)
  → re-genera el MISMO reporte a partir del registro de resu_historial
    (no guarda el binario, lo reconstruye desde los datos vivos — si los
    datos de origen cambiaron después de la generación original, la
    "re-descarga" no es un archivo histórico congelado, es una versión
    recalculada con el estado actual de la base)
```

**Esto último es importante**: `resu_historial` no guarda el archivo generado, solo sus metadatos (tipo, período, cantidad de registros, texto de validación). Si necesitas el archivo *exacto* que se envió a la UAFE en una fecha pasada y alguien editó una operación de ese período después, la re-descarga ya no coincide bit a bit con lo que se envió. Si eso es un requisito de cumplimiento (guardar evidencia inmutable de lo enviado), es un cambio de diseño (guardar el binario o un hash) que hoy no existe.

## Base de datos propia de este módulo

De `database/10_operaciones_transacciones.sql` (comparte migración con Vehículos y Operaciones):

```sql
resu_config                               -- fila única, id siempre = 1
  id, cdr, identificacion_reporte, umbral_usd (default 10000),
  usr, cod_agencia, direccion_default, actualizado_en

resu_historial
  id, tipo_reporte, periodo (char7 'YYYY-MM'), archivo, registros,
  validacion, generado_por, creado_en
```

Todo lo demás (`operaciones`, `operacion_bienes`, `operacion_transacciones`, `origen_fondos`) pertenece a Vehículos y Operaciones — Reportería solo las lee.

## De dónde sale cada campo (mapa rápido para depurar)

Cuando un campo del Excel/XML sale vacío o mal formateado, el camino de depuración es siempre el mismo: `Resu::fila*()` → columna de origen en Operaciones/KYC:

- **`filaCliente()`**: identificación (`ruc` si es persona jurídica — `persona_es_juridica()` mira si el código termina en `pj` —, `cedula_pasaporte` si no), nombres/apellidos (`resu_nombres_apellidos()`, que para personas naturales **parte un solo campo `nombres_completos`** con una heurística de orden español — ver el TODO explícito en el código: esto es una heurística, no un dato estructurado, y puede partir mal un nombre con un orden atípico), dirección (primer campo no vacío entre `direccion`/`domicilio_principal`/`domicilio`/`direccion_residencia`/`residencia` según la tabla KYC del tipo), provincia/cantón/parroquia (**no vienen del KYC, vienen de la última declaración de origen lícito del sujeto** — `cargarOrigenFondos()`), actividad económica (vía `actividad_economica_id` del KYC, mapeado a su código con `mapaActividades()`), ingreso (primer campo no vacío entre `ingresos_mensuales`/`ingresos_ventas_anuales`/`remuneracion` — distinto según el tipo de sujeto).
- **`filaOperacion()`**: todo sale directo de `operaciones` + `operacion_bienes`, sin transformación salvo normalización de texto (`resu_texto()`) y limpieza de cilindraje (si dice "NO APLICA" se deja así, si no se le quitan todos los caracteres no numéricos).
- **`filaTransaccion()`**: sale directo de `operacion_transacciones`; los 4 campos de terceros (`ID_TERCEROS`, nombres/apellidos de terceros) se fuerzan a `'NO APLICA'` si `pago_cobro` no es `'DTC'` (cobro/pago a través de terceros), **incluso si la fila de la BD tiene otro valor guardado** — es una regla de negocio deliberada, no un bug.
- **`resu_texto()`**: reemplaza tildes/Ñ por su equivalente sin tilde, pasa a mayúsculas, y elimina cualquier carácter que no sea `A-Z0-9` y espacio (o también `.` si se pasa `$permitirPunto = true`, usado solo en el modelo del vehículo). Si un campo del RESU sale "raro" (con espacios dobles o vacío donde no debería), casi siempre es por este normalizador actuando sobre un dato que no es lo que se esperaba en origen, no un bug del normalizador en sí.

## Control de acceso

Mismo mecanismo que los demás módulos controlados (`Router::$modulosControlados` incluye `'reporteria'`):

1. `requireAuth()` + verificación de que `'reporteria'` esté en `$_SESSION['modulos']`.
2. Nivel Ver/Editar: con nivel `"ver"` se bloquea `guardarConfig` y `generar` (ambos POST), pero no la descarga del historial ni el panel (son GET).
3. **Roles de fábrica — a diferencia de Vehículos y Operaciones, `reporteria` NO está en todos los roles**: `Administrador`, `Oficial de Cumplimiento` y `Consulta` sí lo tienen; **`Analista` no** (`database/11_roles_usuarios.sql`). Es deliberado: la generación del reporte regulatorio es una función de oficial de cumplimiento/consulta, no de analista operativo — si un usuario Analista necesita generar el RESU, es una decisión de negocio que cambiar, no un bug de permisos.

Cada generación y cada re-descarga quedan en `/auditoria` bajo el módulo `'reporteria'`, con el tipo y período en la descripción — suficiente para reconstruir quién generó qué y cuándo si la UAFE pide evidencia.

## Cómo se conecta con el resto del sistema

- **Vehículos y Operaciones**: fuente de el 100% de los datos transaccionales (ver esa skill). `Resu.php` **no reutiliza los modelos `Operacion`/`Transaccion`/`OrigenFondos`** de ese módulo — tiene sus propias consultas SQL directas a las mismas tablas. Esto significa que un cambio de esquema en esas tablas hay que replicarlo en ambos lugares (ver advertencia en la skill de Operaciones).
- **KYC**: fuente de los datos del sujeto (`kyc_todos_tipos()`, y lectura directa de la tabla de cada tipo dentro de `cargarSujetos()`).
- **`actividad_economica`**: catálogo compartido con KYC, usado solo para traducir el id guardado en el expediente a su código RESU.
- **`reporteria-resu-adaptar`**: si en el futuro se necesita generar un reporte equivalente para un manual de la UAFE de otro sector (no vehicular), esa skill explica cómo reescribir `Resu::fila*()` y `resu_columnas.php` contra el nuevo manual, reutilizando el motor genérico (`ReporteriaController`, `ResuExcel`, `ResuXml`).

## Qué SÍ puedes cambiar con confianza

- El umbral, CDR, USR y demás campos de `resu_config` — son configuración pura, sin código que tocar (`/reporteria`, formulario de configuración).
- Agregar una columna más a `resu_columnas.php` para un tipo existente, siempre que también agregues de dónde sale el valor en el `fila*()` correspondiente y la marques como numérica en `ReporteriaController::numericas()` si aplica.
- Exponer `XML_CABECERA`/`XML_DETALLE` en la UI (agregarlos a `resu_tipos_reporte()` y a los switches de `ReporteriaController`) — el generador ya existe, es trabajo de "conectar", no de escribir desde cero. Pero valida el XML resultante contra el XSD oficial antes de darlo por terminado, no asumas que porque el PHP corre sin error el XML es válido.

## Qué NO cambiar sin pensarlo dos veces

- **El orden y nombre exacto de las columnas en `resu_columnas.php`** — son literales de la plantilla oficial de la UAFE, incluyendo inconsistencias aparentes como el espacio en `'COD_TIPO_ OPERACION'` (con espacio antes de OPERACION) — **eso no es un typo que corregir, es el nombre de columna que la plantilla oficial realmente usa**. "Arreglarlo" rompe la carga en el sistema de la UAFE.
- **`resu_entero()`** (trunca decimales, nunca redondea) — así lo exige el manual; cambiarlo a redondeo altera los totales de la cabecera de forma que ya no cuadran con la suma real de las filas de detalle.
- **La heurística de `resu_partir_nombre()`** sin antes confirmar que no hay una forma mejor de obtener nombres/apellidos ya separados del KYC — es un parche documentado (`TODO` explícito en el código) sobre una limitación real del formulario KYC, no una elección de diseño a defender.
- **El criterio de "aplica" por suma de operaciones del período, no por transacción individual** — cambiarlo altera qué sujetos salen reportados, con implicaciones de cumplimiento regulatorio reales, no solo técnicas.

## Checklist antes de dar por terminado un cambio aquí

- [ ] Generar cada uno de los 4 tipos para un período con datos reales no lanza error PHP ni descarga un archivo vacío.
- [ ] El archivo `CABECERA` generado tiene totales que efectivamente cuadran con la suma de las filas de `CLIENTE`/`OPERACION`/`TRANSACCION` del mismo período (verificar a mano, no solo visualmente).
- [ ] Un sujeto sin expediente KYC asociado aparece en `INCOMPLETA`, no se omite silenciosamente sin explicación.
- [ ] Un sujeto cuyo total de operaciones del período no alcanza el umbral queda fuera de los 3 archivos de detalle pero se refleja en `NO_ALCANZA` del texto de validación.
- [ ] Re-descargar una generación del historial (`/reporteria/descargar/{id}`) no lanza error aunque hayan pasado meses desde que se generó.
- [ ] Si `ZipArchive` no está disponible en el entorno, la descarga cae a CSV sin romperse (`ResuExcel::disponible()`).
- [ ] Cada generación y re-descarga sigue apareciendo en `/auditoria` bajo el módulo "reporteria".
- [ ] Un usuario del rol `Analista` no ve el módulo de Reportería; uno de `Consulta` lo ve pero no puede generar ni guardar configuración (nivel "ver").
