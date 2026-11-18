---
name: reporteria-resu-adaptar
description: Adapta el generador de reportes regulatorios RESU de SUIAUTO a un manual de la UAFE distinto al del sector vehicular (ej. para replicar SUILAFT en otro sector regulado — inmobiliario, joyas/minería, casinos). Úsala cuando el usuario entregue un manual de "Generación de Estructuras y Contenidos para el Reporte... (RESU)" de un sector distinto, o pida adaptar/clonar el reportero RESU para otro tipo de negocio. A diferencia de las demás skills de este proyecto, esto NO es editar un solo archivo de configuración: el motor que arma los archivos (Excel/XML) es genérico y reutilizable, pero el mapeo de cada campo del reporte a los datos reales (Resu::filaCliente/filaOperacion/filaTransaccion) es específico del sector vehicular y hay que reescribirlo contra el manual nuevo.
---

# Adaptar el generador RESU a otro sector regulado

El RESU (Reporte de Operaciones y Transacciones que Igualen o Superen el Umbral) lo exige la UAFE a distintos "sujetos obligados" — cada sector (vehicular, inmobiliario, joyas, casinos...) tiene su propio manual con sus propios campos, aunque la forma general (CABECERA + DETALLE con Cliente/Operación/Transacción, en Excel y XML) es la misma estructura regulatoria. Esta skill explica qué parte de SUIAUTO ya es reutilizable tal cual y qué parte hay que reescribir a mano contra el manual del sector nuevo.

## Qué es genérico y reutilizable sin cambios

- **`app/core/ResuExcel.php`** — arma cualquier hoja Excel a partir de una lista de columnas + filas de datos. No sabe nada de vehículos.
- **`app/core/ResuXml.php`** — arma los XML de CABECERA y DETALLE contra cualquier XSD, de la misma forma genérica.
- **`ReporteriaController`** — el flujo de "Generar reporte", historial de generaciones, descarga — todo genérico sobre un `$tipo` (CABECERA/CLIENTE/OPERACION/TRANSACCION).
- **`app/views/reporteria/index.php`** — la pantalla, sin cambios.
- El patrón de `resu_tipos_reporte()` (qué botones aparecen) — ver la skill que agregó el botón de Cabecera como ejemplo de cómo extender esto.

## Qué es específico del sector vehicular y hay que reescribir

- **`app/config/resu_columnas.php`** — los encabezados de columna de cada hoja, copiados literalmente de las plantillas Excel oficiales del sector. **Reemplázalos por los del manual nuevo**, columna por columna, en el mismo orden que trae la plantilla oficial — un orden incorrecto hace que el archivo se rechace en el portal de la UAFE aunque los datos sean correctos.
- **`app/models/Resu.php`**, en particular:
  - `filaCliente()` — arma cada fila del archivo de clientes. Lee nombres de columna específicos del KYC vehicular (`cedula_pasaporte`, `ruc`, `ingresos_mensuales`, `actividad_economica_id`...). En un sector nuevo, estos nombres de campo KYC serán distintos (o el KYC mismo necesitará campos distintos — considera usar primero la skill `kyc-nuevo-tipo` para los tipos de sujeto del sector nuevo).
  - `filaOperacion()` / `filaTransaccion()` — lo mismo pero para los archivos de operación y transacción. Los campos de "operación" en vehicular son vehículo/maquinaria (`VTVHM`, `MVHM`, placas...); en otro sector serán completamente distintos (ej. inmobiliario: predio, metraje, registro de propiedad).
  - `filaCabecera()` — los totales de control. La fórmula de sumar/contar es genérica, pero los NOMBRES de columna de `resu_columnas.php['CABECERA']` deben coincidir.
  - `armar($periodo)` — orquesta todo; su esqueleto (traer operaciones del período, agrupar por sujeto, calcular totales) probablemente se mantiene, pero cada pieza que arma una fila cambia.
- **Las reglas de umbral y "INCOMPLETA"** (quién entra al reporte, qué campos faltantes bloquean a un sujeto) están descritas en el manual de cada sector — normalmente el umbral de USD 10.000 / 30 días es el mismo (es una regla general de la ley, no del sector), pero qué campos son obligatorios por sujeto sí puede variar.
- **`database/09_catalogos_resu.sql`** (catálogos `cat_*`) — están armados para el catálogo oficial del sector vehicular (`CATALOGO_ESTRUCTURA_COMERCIALIZACION_VEH_O_MAQ`). Un sector nuevo trae su propio catálogo oficial (tipos de operación, de transacción, de bien, etc.) — es una migración nueva, no una edición de la existente (no mezcles catálogos de dos sectores en las mismas tablas `cat_*` si ambos sectores van a coexistir en la misma base; si es una instancia nueva de SUILAFT para un solo sector, sí puedes reemplazar el contenido).

## Paso a paso

### 1. Lee el manual del sector nuevo completo antes de tocar código

Específicamente las secciones "Estructura de archivos XML y Excel" (tablas de columnas de Cabecera y Detalle) y las reglas de cada campo (longitud mínima/máxima, formato, catálogo que usa). Es el mismo tipo de documento que `MANUAL (3).pdf` en este proyecto, solo que para otro sector — trátalo con el mismo nivel de literalidad: estos son campos regulatorios, no sugerencias.

### 2. Decide el alcance: ¿sector nuevo en el mismo SUIAUTO, o una instancia nueva de SUILAFT?

- Si es un módulo adicional dentro del mismo sistema (poco común, la UAFE normalmente asigna un solo sector por sujeto obligado): necesitas distinguir `resu_columnas.php` y `Resu.php` por sector, lo cual es un cambio de arquitectura mayor, no cubierto por esta skill tal cual.
- Si es una instancia nueva (lo más probable, dado el objetivo de "replicar SUILAFT para otro negocio"): clona el repositorio completo, y dentro de esa copia reemplaza `resu_columnas.php`, reescribe `Resu.php`, reemplaza `database/09_catalogos_resu.sql` por el catálogo del sector nuevo, y usa `kyc-nuevo-tipo` / `matriz-nuevo-tipo` para los tipos de sujeto de ese sector (reemplazando o complementando los 6 vehiculares, según si ese sector tiene sujetos equivalentes).

### 3. Reescribe `resu_columnas.php` columna por columna

Copia los encabezados exactos de la plantilla Excel oficial del sector nuevo (la UAFE casi siempre adjunta un archivo tipo `ESTRUCTURAS` con las plantillas), en el mismo orden.

### 4. Reescribe las funciones `filaX()` de `Resu.php`

Para cada columna de `resu_columnas.php`, identifica de qué campo real del sistema sale el valor (KYC, operación, transacción, catálogo) y escribe la extracción — sigue el estilo defensivo del código actual: cada campo opcional/dudoso se intenta extraer, y si falta, se agrega a un arreglo `$faltantes` que alimenta la validación `INCOMPLETA` en vez de fallar silenciosamente con un valor vacío.

### 5. Normalización de texto — esto SÍ es genérico, reutilízalo

Mayúsculas, sin tildes, sin caracteres especiales en identificadores — son reglas del manual de la UAFE en general, no del sector. Las funciones helper ya existentes (busca `resu_texto()`, `resu_entero()` en `app/core/personas_helpers.php` o donde estén definidas) probablemente sirven tal cual.

### 6. Despliega

Usa la skill **`despliegue-ftp-produccion`**. Esto normalmente involucra migración nueva (catálogos del sector) + varios archivos PHP.

### 7. Verifica (checklist) — esto es lo más importante de toda esta skill

- [ ] Genera los 4 archivos (Cabecera, Cliente, Operación, Transacción) con datos de prueba reales y **ábrelos** para contar columnas contra la plantilla oficial — una columna de más/menos o en el orden equivocado invalida el archivo completo en el portal de la UAFE, aunque el sistema no muestre ningún error.
- [ ] Compara el número de columnas exacto contra el manual (ej. en el sector vehicular son 15/12/21/24 — el número correcto depende del manual del sector nuevo).
- [ ] Los dos XML (`CABECERA.xml`, `DETALLE.xml`) validan contra los `.xsd` oficiales del sector nuevo (no asumas que el XSD vehicular sirve para otro sector).
- [ ] Prueba un caso con un sujeto que SÍ alcanza el umbral y uno que no, y confirma que `RESU_APLICA`/`NO_ALCANZA`/`INCOMPLETA` se calculan igual de bien que en el sector vehicular.
- [ ] Todos los textos salen en mayúsculas y sin tildes, los identificadores sin caracteres especiales — igual que en el reporte vehicular.
