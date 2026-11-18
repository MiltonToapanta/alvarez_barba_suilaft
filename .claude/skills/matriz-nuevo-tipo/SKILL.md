---
name: matriz-nuevo-tipo
description: Carga o actualiza la matriz de riesgo individual de un tipo de sujeto KYC en SUILAFT Centerauto (accionista, cliente-pn, empleado, proveedor…) a partir del documento «SIUAUTO / Metodología de evaluación individual» de cada perfil (PDF, Word, Excel o imagen) — factores, pesos, campo o respaldo y criterios 1 Bajo · 2 Medio · 3 Alto. Úsala cuando el usuario entregue una «Matriz de riesgo», pida "crear/cargar la matriz de X", "agregar un factor", "cambiar pesos", o cuando se agregó un tipo KYC nuevo y le falta su matriz. El motor es config-driven: una entrada en app/config/matriz_tipos.php da tarjeta, listado por nivel, pantalla de evaluación 1–3 con N/A, pendientes y elevación, cálculo en vivo, evidencia sugerida desde el KYC y PDF.
---

# Cargar una matriz de riesgo individual en Centerauto

Producción: https://suilaft.tech/centerauto_suilaft/matriz

**No escribas código por tipo.** `MatrizController` es genérico: todo sale de la entrada en `app/config/matriz_tipos.php`. No hay migración por tipo: la tabla `matriz_calificaciones` (una calificación vigente por `tipo` + `registro_id`, con `puntajes` en JSON) sirve para todas.

## Mapa del motor

| Pieza | Archivo |
|---|---|
| Config de matrices | `app/config/matriz_tipos.php` |
| Cálculo, niveles, evidencia sugerida | `app/core/Matriz.php` |
| Persistencia | `app/models/MatrizCalificacion.php` |
| Rutas `/matriz`, `/matriz/tipo/{c}`, `/matriz/calificar/{c}/{id}`, `/matriz/imprimir/{c}/{id}` | `app/controllers/MatrizController.php` |
| Vistas | `app/views/matriz/{tipos,listado,calificar}.php` |
| PDF (FPDF, hereda encabezado de `PdfKyc`) | `app/core/PdfMatriz.php` |
| Cálculo en vivo | `public/js/matriz.js` · estilos `public/css/matriz.css` |

## Metodología común (app/config/matriz_metodologia.php — no se toca por tipo)

Fuente: «SIUAUTO / Metodología de evaluación individual / Propuesta v1.0» (página 2 de cada documento por perfil).

- Nota por factor: **1 Bajo · 2 Medio · 3 Alto**. Sin nota = **Pendiente** (la evaluación queda en **borrador**). **N/A** solo con motivo documentado en la evidencia; se excluye del cálculo.
- **Puntaje = Σ (peso × nota) ÷ Σ pesos aplicables**, comparado **sin redondear**.
- Niveles: **Bajo desde 1,00 y < 1,67 · Medio desde 1,67 y < 2,34 · Alto desde 2,34 hasta 3,00**, con su medida (simplificada / ordinaria / reforzada + Cumplimiento).
- **Elevación por regla interna**: PEP o vinculación PEP confirmada aplicable, o inconsistencia material de fondos / tercero pagador / titularidad → nivel final **Alto** aunque el calculado sea menor. Se guardan nivel calculado, nivel final y motivo.
- Si un documento nuevo trae **otra escala, otros cortes u otras elevaciones**, no cambies la metodología en silencio: afecta a todas las matrices. Pregunta al usuario.

## Paso a paso

### 1. Lee el documento
Extrae: título y perfil (p. ej. «SIUAUTO / CENTERAUTO / ACC»), nota introductoria, y por cada fila: **Factor, Peso, Campo o respaldo, criterio 1 · Bajo, 2 · Medio, 3 · Alto**. Verifica el «Ejemplo de calificación» del documento contra el motor (paso 5). **No inventes** factores, pesos ni criterios: si faltan, pregunta.

### 2. Confirma el tipo KYC
La clave de la matriz es **el mismo código** del tipo en `app/config/kyc_tipos.php`: `accionista`, `cliente-pn`, `empleado`, `proveedor`. Si el tipo no existe todavía, primero usa la skill `kyc-nuevo-tipo`.

### 3. Verifica que los pesos sumen 1.00
Suma a mano y con `php -r 'require "app/core/Matriz.php"; $m=require "app/config/matriz_tipos.php"; echo Matriz::sumaPesos($m["CODIGO"]);'`. Si el documento da porcentajes, conviértelos (15 % → 0.15). Si no suman 100 %, **detente y pregunta**: el total quedaría mal escalado.

### 4. Escribe la entrada

```php
'cliente-pn' => array(
    'matriz_code'   => 'CENTERAUTO-CLI',
    'version'       => '1.0',
    'fecha_version' => '2026-11-12',
    'titulo'        => 'Matriz de riesgo individual · Cliente persona natural',
    'factores'      => array(
        array(
            'key'    => 'pep_listas',                     // único, snake_case; NO cambiarlo con calificaciones guardadas
            'nombre' => 'PEP, listas y reputación',
            'peso'   => 0.20,
            'respaldo'  => 'KYC declaración PEP y relacionados. Expediente: consultas y contraste de cargos y períodos.',
            'criterios' => array(1 => 'Verificación sin hallazgos relevantes.', 2 => 'Hallazgo corroborado de exposición moderada.', 3 => 'PEP aplicable confirmada o hallazgo grave corroborado.'),
            'kyc_secciones' => array('8.'),               // prefijos de sección del KYC para la evidencia sugerida
        ),
        // … un bloque por factor
    ),
),
```

- `kyc_secciones`: prefijos (`'1.'`, `'5.'`) de las secciones del formulario KYC de ese tipo. Al calificar, el botón «Usar datos del KYC» pega «Etiqueta: valor» de esas secciones como evidencia. Revisa los nombres reales de sección en `kyc_tipos.php`; si no sabes cuál corresponde, omite la clave (no rompe nada).
- `kyc_campos`: lista de claves de campos sueltos del KYC (p. ej. `array('ingresos','egresos')`) cuando no conviene mostrar la sección completa.
- `kyc_tablas`: claves de tablas repetibles del KYC sin número en su título (p. ej. `array('parientes_pep')`); las tablas con título numerado («4. Historial…») ya entran por `kyc_secciones`.
- Un factor sin `kyc_secciones` / `kyc_campos` / `kyc_tablas` muestra «se respalda con documentación del expediente» (p. ej. cargo y accesos, participación y control).
- `criterios`: texto de cada nota (1, 2, 3); se muestra dentro de cada opción y en el PDF bajo la nota elegida.
- `nota` (de la matriz): texto introductorio del documento (p. ej. cómo usarla para PJ).

### 5. Prueba local
- `php -l app/config/matriz_tipos.php` y la suma de pesos (paso 3).
- Reproduce el **«Ejemplo de calificación»** del documento con `Matriz::calcular($matriz, $post)` (las notas en orden): el puntaje y el nivel deben coincidir exactamente (accionista: 1,1,2,1,3,1 → 1,65 Bajo; con elevación PEP → Alto).
- PDF por CLI: `PdfMatriz::generarMatriz($kyc, $matriz, $registro, $cal)`.
- Vista previa de `matriz/calificar` con `php -S` y un router en el scratchpad (como en `kyc-nuevo-tipo`).

### 6. Despliega
Solo sube `app/config/matriz_tipos.php` por FTP (skill `despliegue-ftp-produccion`). La tabla `matriz_calificaciones` ya existe (migración `09_matriz_riesgo.sql`). El usuario pidió subir siempre al terminar.

### 7. Verifica en producción
- [ ] `/matriz` muestra la tarjeta del tipo con su código, n.º de factores y conteo por nivel.
- [ ] `/matriz/tipo/{codigo}` lista los expedientes KYC activos como «Sin calificar».
- [ ] Evaluar un expediente de prueba con las notas del ejemplo del documento: pantalla, listado y PDF dan el mismo puntaje y nivel.
- [ ] Guardar con factores pendientes deja «Borrador · n pendiente(s)»; N/A sin motivo vuelve a pendiente.
- [ ] Marcar una elevación sube el nivel final a Alto y el listado muestra «Calculado: … · elevado».
- [ ] «Usar datos del KYC» trae texto real del expediente en los factores con `kyc_secciones`.
- [ ] Desde el KYC, menú ⋯ → «Matriz de riesgo» abre la calificación de ese expediente.
- [ ] La bitácora `auditoria` registra `calificar` e `imprimir` con módulo `matriz`.

## Fuera de alcance
Formulario KYC del tipo → `kyc-nuevo-tipo` · Alertas por nivel Alto/Crítico → `alertas-nuevo-detector` · Mapa de riesgos institucional → `mapa-riesgos-laft` / `institucional-nuevo-factor`.
