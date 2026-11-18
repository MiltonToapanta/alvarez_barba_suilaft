# Estructura del RESU inmobiliario/constructor (plantillas UAFI)

Fuente: Manual RESU v2.0 (Res. UAFE-DG-SO-2017-0002, RO Supl. 57, 15-ago-2017) y plantillas UAFI `CLI/OPR/TRA-AL-2019.xlsx`.
Una hoja «Hoja1». Fila 1 = cabecera: `TIPO | CDR | FCT | N.º de registros de detalle`. Detalle desde la fila 2, sin filas en blanco.

## CLI (10 columnas)

| Col | Campo | Regla |
|---|---|---|
| A | TID | C cédula · P pasaporte · R RUC · A análogo |
| B | IDE | C = 10 dígitos · R = 13 · P/A = 3–20 alfanumérico. Texto (ceros iniciales) |
| C | NRS | 5–150, MAYÚSCULAS, sin tildes/Ñ/especiales, sin abreviaturas |
| D | AEC | código de actividad del catálogo, 3–10 caracteres |
| E | IMT | entero ≥ 0 (PN mensual, PJ ventas anuales; 0 si se desconoce) |
| F | NAC | país ISO alfa-3 (ECU) |
| G | DIR | 8–150: calle principal, numeración y transversal |
| H | CCC | cantón 4 dígitos (texto) |
| I | CDR | código de registro (= fila 1) |
| J | FCT | fecha de corte AAAAMMDD (= fila 1, último día del mes) |

## OPR (12 columnas)

| Col | Campo | Regla |
|---|---|---|
| A–B | TID, IDE | del cliente (debe existir en CLI) |
| C–D | CDR, FCT | = fila 1 |
| E | NOP | 1–50, sin espacios, A-Z 0-9 y guion (sub-operación 100-1); único por cliente |
| F | VTO | entero > 0, máx. 8 dígitos (valor total del bien/contrato, truncado) |
| G | FDO | AAAAMMDD, ≤ FCT |
| H | TTB | tipo de bien (catálogo, 3 letras); N/A solo para remodelación (TTR 101) |
| I | ADB | área m², entero (0 si se desconoce) |
| J | DRB | dirección del bien 8–150 |
| K | CCO | cantón del bien 4 dígitos; 0000 si está fuera del país |
| L | PUB | país del bien ISO **alfa-2** (EC) |

## TRA (24 columnas)

| Col | Campo | Regla |
|---|---|---|
| A–B | TID, IDE | del cliente (en CLI) |
| C–D | CDR, FCT | = fila 1 |
| E | NOP | operación existente en OPR para ese cliente |
| F | FTR | AAAAMMDD dentro del mes de FCT |
| G | NFA | factura/documento 1–40, sin espacios |
| H–P | VDE VCR VEF VCH VBB VSR VTC VFC TCV | enteros ≥ 0 (débito, crédito, efectivo, cheque, bienes, servicios, tarjeta, financiamiento, títulos valores) |
| Q | VVT | = suma de H–P, ≥ 1 |
| R | MND | moneda 3 letras (USD) |
| S | CAT | CDR + oficina, 8–9 dígitos |
| T | TTR | tipo de transacción del catálogo (91–99, 103) |
| U | RPT | 1 propios · 2 terceros |
| V–X | TIT IDT NRT | con RPT 1 → `0 0 0`; con RPT 2 → datos del tercero (TID/IDE/nombre) |

## Tipos de transacción del sector (tabla `tipo_transaccion`) e instrumentos permitidos

| TTR | Descripción | Instrumentos |
|---|---|---|
| 91 | Compra de bienes inmuebles | VDE VEF VCH VBB VSR VTC VFC TCV |
| 92 | Venta de bienes inmuebles | VCR VEF VCH VBB VSR TCV |
| 94 | Permutas | todos |
| 95 | Aporte inicial a la compra | VDE VEF VCH VBB VSR VTC VFC TCV |
| 96 | Aportes a la compra | VDE VEF VCH VBB VSR VTC VFC TCV |
| 97 | Reserva de bienes | VDE VEF VCH VBB VSR VTC TCV |
| 98 | Resciliación / devolución | VCR VEF VCH VBB VSR TCV |
| 99 | Cesión de derechos | VCR VEF VCH VBB TCV |
| 103 | Obras de ingeniería civil | VDE VEF VCH VBB VSR VTC VFC TCV |

(Configurado en `config/resu_catalogos.php`; tomado del sistema de referencia del usuario.)

## Normalización SISLAFT aplicada

MAYÚSCULAS · sin tildes ni diéresis · Ñ→N · Ç→C · sin espacios al inicio/fin ni dobles · coma → espacio · solo `A-Z 0-9 espacio . -` en textos (NOP/NFA: `A-Z 0-9 -`, sin espacios) · enteros truncados (100000,60 → 100000) · fechas AAAAMMDD.
