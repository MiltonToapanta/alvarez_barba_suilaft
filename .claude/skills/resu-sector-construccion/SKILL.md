---
name: resu-sector-construccion
description: Conocimiento y mantenimiento del RESU (Reporte de operaciones y transacciones que igualan o superan el umbral) para el sector de inversión e intermediación inmobiliaria y construcción ante la UAFE de Ecuador, tal como está construido en SUILAFT DGL — plantillas UAFI CLI (10 col.) / OPR (12) / TRA (24), reglas del manual RESU v2.0 (Res. UAFE-DG-SO-2017-0002), normalización para el SISLAFT, umbral USD 10.000 individual o acumulado en 30 días, códigos RPT/TID/PUB y el validador SISLAFT. Úsala cuando haya que generar, corregir o validar un RESU de una constructora, cuando la UAFE cambie columnas o reglas, cuando el SISLAFT rechace un archivo, o cuando se replique el sistema para otra constructora.
---

# RESU del sector construcción e inmobiliario

## Dónde está

| Pieza | Archivo |
|---|---|
| Pantalla: configuración (CDR, identificación, umbral, oficina), generar, historial, validación del período, validador de archivos | `resu.php`, `views/resu.php` |
| Armado de CLI/OPR/TRA desde KYC + `pago_operaciones` + `pago_transacciones` | `models/Resu.php` (`armar()`) |
| Reglas del manual (una sola fuente para lo generado y lo subido) | `models/ResuValidador.php` (`COLS`, `validar()`) |
| Normalización SISLAFT (mayúsculas, sin tildes ni Ñ, sin especiales, enteros truncados, fechas AAAAMMDD) | `models/ResuNorma.php` |
| Excel sin dependencias (lectura y escritura, texto para ceros iniciales) | `models/Xlsx.php` |
| Catálogos del archivo TRANSACCIÓN: instrumentos, instrumentos por tipo de transacción, RPT, TID | `config/resu_catalogos.php` |
| ISO 3166 alfa-3 → alfa-2 (generado de ICU; NAC usa alfa-3, PUB usa alfa-2) | `config/iso_paises.php` |
| Plantillas oficiales de referencia (UAFI, 2019) | `resu/CLI-AL-2019.xlsx`, `OPR-…`, `TRA-…` (no se suben al servidor) |

Columnas exactas y reglas campo por campo: `references/estructura-resu.md`.

## Reglas de negocio del sector (no cambiarlas sin la norma)

- **Quién se reporta** (Art. 2 b): operación del cliente ≥ umbral, **o** transacciones del cliente que suman ≥ umbral en 30 días (ventana: 29 días antes del inicio del mes + el mes). Si no alcanza → `NO_ALCANZA` y no va en los archivos.
- **Qué se reporta**: la operación por su **valor total** (contrato) y **todas** sus transacciones del mes, superen o no el umbral (manual, «Información sujeta a reporte»). No se reportan intereses, comisiones, créditos bancarios de la empresa, aportes de socios, pagos a proveedores, impuestos ni servicios básicos.
- **Interruptor «RESU aplica»** (listado de transacciones): solo entran las transacciones con el interruptor activo.
- **Período**: transacciones con FTR dentro del mes; operaciones con transacciones en el mes o con fecha de operación en el mes. Fecha de corte = último día del mes.
- **IMT**: PN = ingreso mensual del KYC; PJ = ventas anuales (el KYC guarda mensual → se reporta × 12 y se avisa); sin dato = 0 (aviso).
- **RPT** (tabla «Pago»): `1` recursos propios (TIT/IDT/NRT = `0`), `2` recursos de terceros (incluye beneficiario final; TIT/IDT/NRT del tercero). **No existe el 3.**
- **CAT** = CDR + oficina (8–9 dígitos). **VVT** = suma de los instrumentos ya truncados (así cuadra la validación del SISLAFT).

## Cómo trabaja la pantalla

1. Configuración guardada en `resu_configuracion` (sin CDR no se genera).
2. «Ver validación del período» arma los 3 archivos en memoria y los pasa por `ResuValidador`. Cada error enlaza a «Corregir en» (KYC, edición de operación o de declaración).
3. «Generar y descargar» **solo descarga si hay 0 errores**; los avisos no bloquean. Queda en `resu_generaciones` (con el archivo) y en Auditoría.
4. «Validador SISLAFT de archivos»: sube 1–3 .xlsx (de este u otro sistema) y aplica las mismas reglas; con los 3 cruza clientes ↔ operaciones ↔ transacciones.

## Errores típicos del SISLAFT y su causa

| Síntoma | Causa | Solución |
|---|---|---|
| Cédula de 9 / RUC de 12 dígitos | Excel guardó la identificación como número y perdió el 0 | Celda de texto (el generador ya lo hace); en archivos ajenos, el validador lo marca |
| Cantón de 3 dígitos | Igual: 0101 → 101 | Texto |
| CCO/CCC repetido o inexistente | Catálogo `canton` mal codificado | `catalogos-uafe-construccion` |
| AEC «ABOGADO», «ARQ» | Actividad escrita en vez del código | El KYC debe usar el catálogo («CÓDIGO — descripción») |
| D1 no coincide | Conteo de la fila 1 desactualizado | El generador lo calcula; el validador lo compara |
| Tildes, Ñ, `#`, `/`, `,`, espacios al final | Texto sin normalizar | `ResuNorma::texto()` + filtro de caracteres; el validador lo marca |
| VVT ≠ suma | Decimales redondeados por instrumento | Enteros truncados y VVT = suma de truncados |

## Cambiar columnas o reglas (si la UAFE publica otra versión)

1. Actualiza `ResuValidador::COLS` y `Resu::armar()` **juntos** (mismo orden de columnas).
2. Ajusta tipos de celda en `armar()`: `'s'` para todo lo que pueda llevar ceros iniciales o letras; `'n'` para montos, fechas y códigos numéricos.
3. Valida contra las plantillas nuevas: sube las plantillas oficiales al validador y compara.
4. Prueba un mes con datos `PRUEBA` completos (0 errores) y otro incompleto (descarga bloqueada).
5. Despliega con `despliegue-ftp-produccion` y repite la prueba en producción.

## XML directo

El sistema genera las plantillas Excel que la aplicación UAFI convierte al XML oficial. El XML del sector (cabecera con CLAVE Triple DES + detalle) **no** se genera porque sus etiquetas/XSD no son públicos (manual reservado desde la Res. UAFE-DG-2021-0362). Si el usuario entrega los XSD, se puede agregar sobre `Resu::armar()` (los totales de cabecera TVO, TDE…TVT ya se calculan en `$rep['totales']`).

## Verificación

- [ ] Plantillas de referencia en el validador: detecta sus problemas (en DGL: 36 errores).
- [ ] Archivos generados en el validador: «Sin errores».
- [ ] ZIP con 3 .xlsx, hoja «Hoja1», fila 1 = TIPO | CDR | FCT | n.º de registros.
- [ ] Identificaciones y cantones como texto (ceros iniciales intactos).
- [ ] Un cliente bajo el umbral aparece como «No alcanza» y no va en los archivos.

## Normalizador y reglas de archivo (17-nov-2026)

- `models/ResuNormalizador.php` + botón «Normalizar y validar» en Generación RESU: limpia archivos CLI/OPR/TRA subidos (ocultos de copiar y pegar, mayúsculas sin tildes/Ñ, cédula/RUC solo dígitos con cero inicial recuperado, montos sin $ ni miles y truncados, fechas AAAAMMDD, cantón 3→4 dígitos, instrumentos vacíos → 0, VVT vacío = suma, filas en blanco fuera, D1 recalculado) y devuelve los archivos con nombre seguro. No inventa datos de catálogo.
- `ResuNorma::ocultos()`, `limpiarCelda()`, `montoEntero()`, `problemasNombreArchivo()`, `nombreArchivoSeguro()`.
- El validador marca caracteres ocultos en todas las celdas (incluida la fila 1), identificaciones con puntos/guiones/espacios, montos con $ o separadores de miles y, para archivos subidos, el nombre del archivo.
- Nombres generados: `CLI_RESU_13208_202611.xlsx` (sin tildes, espacios ni signos; palabras con _; un solo punto).
