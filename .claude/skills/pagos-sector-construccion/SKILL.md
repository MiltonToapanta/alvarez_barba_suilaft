---
name: pagos-sector-construccion
description: Módulo «Registro de pagos» de SUILAFT para constructoras e inmobiliarias — catálogo de Proyectos → Operación (contrato del bien: NOP, valor, tipo de bien, ubicación) → Transacción con Declaración de origen lícito de recursos (instrumentos de pago, tipo de transacción 91–103, origen de fondos, recursos propios o de terceros, aceptación), listados con edición, interruptor «RESU aplica» e impresión del lícito en PDF. Úsala para modificar formularios de operaciones o transacciones, agregar campos, cambiar reglas de pago, depurar por qué una operación/transacción no sale o sale mal en el RESU, o replicar el módulo para otra constructora.
---

# Registro de pagos del sector construcción

Flujo: **Proyecto** (catálogo) → **Operación** del cliente sobre ese proyecto → **Transacciones** (cada pago, con su declaración de origen lícito) → **RESU**.

## Piezas

| Vista | URL | Archivos |
|---|---|---|
| Inicio del módulo (3 tarjetas) | `pagos.php` | `views/pagos.php` (`$vistas` en `pagos.php`) |
| Sujetos: clientes PN, PJ y proveedores con KYC activo | `?vista=registro` | `views/pagos.php` |
| Registrar / editar operación | `?vista=operacion&tipo=…&id=…` · `?vista=operacion&op=ID` | `models/Operacion.php`, `views/pagos_operacion.php` |
| Listado de operaciones | `?vista=operaciones` | `views/pagos_operaciones.php` |
| Declaración de origen lícito (crear / editar) | `?vista=licitud&tipo=…&id=…[&op=ID]` · `?vista=licitud&tx=ID` | `models/Transaccion.php`, `views/pagos_licitud.php` |
| Listado de transacciones + «RESU aplica» + imprimir | `?vista=transacciones` · `?vista=imprimir&tx=ID` | `views/pagos_transacciones.php`, `models/PdfLicitud.php` |
| Proyectos | `catalogos.php?catalogo=cat_proyectos` | `models/Proyecto.php` |

Tablas: `cat_proyectos`, `pago_operaciones` (`sql/09`), `pago_transacciones` (`sql/10`, + `resu_aplica` en `sql/12`). Catálogos de la transacción: `config/resu_catalogos.php`.

## Reglas que garantizan un RESU limpio (no relajarlas)

- **Doble normalización**: el JavaScript limpia mientras se escribe (aviso «Se quitó …: no lo admite el SISLAFT») y el servidor vuelve a normalizar y **rechaza** lo que quede fuera de norma. Usa siempre `ResuNorma::texto()` + `ResuNorma::invalidos()`; nunca guardes texto del POST sin pasar por ahí.
- **Operación**: NOP sin espacios, único (`UNIQUE`), sub-operación con guion (100-1); valor ≥ 1 y ≤ 8 dígitos enteros; fecha no futura; tipo de bien y país de los catálogos; en Ecuador provincia + cantón (el cantón se elige por **`canton.id`** y el código sale del registro, porque los códigos pueden repetirse entre provincias); fuera de Ecuador cantón `0000`.
- **Edición de operación**: no permite una fecha posterior a su primer pago ni un NOP de otra operación.
- **Transacción**: atada a una operación del mismo sujeto; fecha no futura ni anterior a la operación; documento sin espacios y único por operación; instrumentos habilitados según el tipo de transacción (`instrumentos_por_tipo`, también en el servidor); total = suma; recursos de terceros exigen identificación del tercero (C 10 dígitos, R 13, P/A 3–20); las dos declaraciones (entrega y recepción) aceptadas.
- **Declarante**: se copia del KYC al guardar (nombre, documento, representante legal en PJ/proveedor). Al editar, se vuelve a copiar del KYC vigente.
- **«RESU aplica»**: desactivar solo con sustento; queda en Auditoría.

## Agregar un campo

- **A la operación**: columna nueva en `pago_operaciones` (migración idempotente) → `Operacion::validar()` (normaliza) → `crear()`/`actualizar()` (revisa la cadena de tipos de `bind_param`) → `formDesde()` → `views/pagos_operacion.php`. Si va al RESU, también `Resu::armar()` y `ResuValidador::COLS`.
- **A la transacción**: igual con `Transaccion::validar()`, `crear()` (45 parámetros) y `actualizar()` (41). **Verifica la alineación** de columnas, tipos y variables con un script (ya hubo dos desajustes: moneda guardada como entero). Si se imprime, `PdfLicitud::render()`.
- **Instrumentos por tipo de transacción** o **RPT**: solo `config/resu_catalogos.php` (pantalla, servidor y RESU leen de ahí).

## Integraciones

- **RESU** (`resu-sector-construccion`): lee operaciones y transacciones activas con `resu_aplica = 1`.
- **Alertas** (`alertas-nuevo-detector`): transacción ≥ umbral, efectivo, terceros, pago del exterior, no acorde al ingreso del KYC, fraccionamiento en 30 días, pagos mayores al valor de la operación.
- **Auditoría**: crear/editar operación y declaración, «RESU aplica», imprimir.
- **Permisos**: módulo `pagos` (`Acceso::exigirSegunMetodo('pagos')`; con «Solo ver» se bloquean los POST).

## Verificación (en producción, datos `PRUEBA`)

- [ ] Operación con textos sucios («  ct-2026 /001ñ ») se guarda normalizada (`CT-2026001N`).
- [ ] Transacción con tipo 95 deshabilita crédito (VCR) y el servidor rechaza un VCR enviado a mano.
- [ ] Recursos de terceros con cédula de 3 dígitos → rechazado; con 10 → guardado y visible en el PDF.
- [ ] Documento repetido en la misma operación → rechazado.
- [ ] Apagar «RESU aplica» reduce el conteo de TRA del período en Generación RESU; encenderlo lo restaura.
- [ ] El PDF del lícito tiene la marca de la empresa, los valores, el tercero y las firmas.
