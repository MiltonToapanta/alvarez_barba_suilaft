---
name: catalogos-uafe-construccion
description: Catálogos UAFE que usa SUILAFT para constructoras (cantones INEC, ciudades, provincias, países ISO alfa-3/alfa-2, actividad económica, moneda, tipo de bien, tipo de transacción del sector 91–103, formas de pago, origen de fondos) — cómo exportarlos a una instalación nueva, cómo verificarlos con SQL y cómo corregirlos sin romper datos (respaldo, migración idempotente, códigos INEC). Úsala al replicar el sistema para otra constructora, cuando el RESU marque un cantón/país/actividad inexistente o repetido, cuando la UAFE actualice un catálogo, o cuando el usuario pida revisar o corregir catálogos.
---

# Catálogos UAFE del sector construcción

## Qué catálogo alimenta qué

| Tabla | Clave → campo | Lo usa |
|---|---|---|
| `canton` (`cod_canton`, `canton`, `id_provincia`) | INEC 4 dígitos PPCC → CCC (cliente) y CCO (bien) | KYC (por nombre), operación (por **id**), RESU |
| `ciudad` (`cod_canton`, `ciudad`) | ciudad de la declaración y del pago | licitud, formularios |
| `provincias` | `id` = código INEC de provincia (25 = zonas no delimitadas, 26 = N/A) | KYC, operación |
| `paises` (`cod`, `pais`) | ISO **alfa-3** → NAC; alfa-2 vía `config/iso_paises.php` → PUB | KYC, operación, licitud |
| `actividad_economica` (`codigo`, `descripcion`) | CIIU de 1–8 caracteres → AEC | KYC («CÓDIGO — descripción») |
| `tipo_bien` (`COD_TIPO_BIEN`, `NOMBRE`) | 3 letras → TTB | operación |
| `tipo_transaccion` (`codigo`) | 91, 92, 94–99, 103 → TTR | licitud |
| `moneda` (`codigo`) | ISO 4217 → MND | licitud |
| `cat_forma_pago`, `cat_origen_fondos` | referencia | formularios |
| `cat_proyectos` (`nombre` único, `direccion`) | proyectos **de cada empresa** | operaciones (CRUD en Catálogos › Proyectos) |
| RPT (tabla «Pago») | `config/resu_catalogos.php`: 1 propios · 2 terceros | licitud, RESU |

## Defectos ya encontrados y corregidos en DGL (migración `sql/13_catalogo_cantones_rpt.sql`)

- Cantones de **Cañar** con códigos 0402–0907 (repetidos con Bolívar, Pangua, Chambo, Chilla, Atacames y Durán). Correctos INEC: Biblián 0302, Cañar 0303, La Troncal 0304, El Tambo 0305, Déleg 0306, Suscal 0307 (Azogues 0301 ya estaba bien).
- **Zonas no delimitadas** sin código: Las Golondrinas 9001, Manga del Cura 9003, El Piedrero 9004.
- 11 códigos con salto de línea (`'0102\n'`): se limpian con `TRIM(REPLACE(REPLACE(...)))`.
- Tabla `ciudad` con el mismo error de Cañar (y el nombre «DÉELEG» mal escrito; el nombre no se cambió porque los KYC guardan el texto).
- **RPT**: el código 3 no existe en el SISLAFT; 1 = propios, 2 = terceros (también beneficiario final).
- `tipo_bien` de producción tiene 22 tipos (BOD, CAR, CAS, CER, CCO, CDE, CED, CME, CRE, CTU, CON, DEP, EDI, FAB, FCA, HAC, HOS, HOT, LOC, OFI, PAC, TER) que **no** coinciden con la tabla del manual de 2017 (GAL, PAR, VIA…): se respeta el catálogo cargado (presumiblemente el vigente del SISLAFT).

## Instalación nueva (replicar)

1. Exporta de la base de DGL (phpMyAdmin › Exportar › personalizado › solo estas tablas, con datos): `actividad_economica`, `paises`, `provincias`, `canton`, `ciudad`, `parroquia`, `moneda`, `tipo_bien`, `tipo_transaccion`, `cat_forma_pago`, `cat_origen_fondos`. `cat_proyectos` **solo estructura**.
2. Importa en la base nueva antes de las migraciones 02–15.
3. Ejecuta `references/verificar_catalogos.sql`: las consultas 1–5 deben devolver 0 filas y los conteos (6) deben coincidir.

## Corregir un catálogo (procedimiento seguro)

1. **Respalda** las filas afectadas antes (el migrador de la migración 13 imprimió un JSON de respaldo que se guardó en el scratchpad).
2. Escribe una migración `sql/NN_….sql` **idempotente**: `UPDATE … WHERE id = X AND nombre = '…' AND cod = 'viejo'` (filtra por id **y** valor viejo; si ya se corrigió, afecta 0 filas).
3. No renombres textos que los KYC guardan literalmente (provincia, cantón, ciudad, país): el KYC busca por nombre normalizado.
4. Ejecuta con el migrador temporal (`despliegue-ftp-produccion`), revisa «filas afectadas», borra el migrador y comprueba 404.
5. Re-verifica con `verificar_catalogos.sql` y valida un RESU en la pantalla.

## Cómo se traducen los datos del KYC a códigos (en `Resu::armar()`)

- País: por nombre normalizado (sin tildes, mayúsculas) contra `paises.pais`, o si ya es alfa-3.
- Cantón: por nombre de provincia + nombre de cantón; si la provincia no coincide y el nombre es único, por nombre.
- Actividad: código antes de « — » (formato del selector del KYC) o el texto si ya es un código del catálogo.
Si no se puede traducir, el campo queda vacío y el **validador lo marca** con enlace al expediente para corregirlo.
