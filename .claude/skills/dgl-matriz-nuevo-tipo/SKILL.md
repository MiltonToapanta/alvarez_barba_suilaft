---
name: dgl-matriz-nuevo-tipo
description: Construye (la primera vez) y amplía la matriz de riesgo individual por tipo de sujeto KYC de SUILAFT DGL (módulo "Prevención de riesgos – Matriz, controles y monitoreo"): factores, pesos, calificación 1–5 con SUGERENCIA AUTOMÁTICA de calificación a partir de los datos del KYC, nivel Bajo/Medio/Alto/Crítico y PDF. Úsala cuando el usuario entregue un documento de matriz de riesgo, pida "crear la matriz para socio/accionista o cliente", "agregar un factor de riesgo", o cuando se agregó un tipo KYC nuevo con kyc-nuevo-tipo y le falta su matriz. Si en cambio pide un mapa de riesgos con probabilidad × impacto, controles y riesgo residual (modelo VASERUM), usa la skill global mapa-riesgos-laft.
---

# Matriz de riesgo individual en DGL

## Estado (actualizado 5-nov-2026)

**El motor está construido** con la metodología DGL (documentos DGL-MRI-001…005 v0.1, «Propuesta para revisión»). Para un tipo nuevo o un cambio de factores, edita solo `config/matriz_tipos.php`. **La metodología DGL prevalece sobre los ejemplos genéricos de esta skill:**

| Aspecto | DGL |
|---|---|
| Escala | **1–3** (1 Menor · 2 Intermedio · 3 Mayor). Los criterios de cada puntaje son textos del documento (`criterios`). |
| Pesos | **Porcentajes enteros que suman 100** (no decimales). `Matriz::tipos()` lanza una excepción si no suman 100. |
| Fórmula | R = Σ(peso × puntaje)/100, calculado en **centésimas exactas** (`total_centesimas`) |
| Niveles | Bajo < 1,67 ≤ Medio < 2,34 ≤ Alto. Se clasifica con el valor completo (`Matriz::nivel()`). |
| Pisos | Condiciones `piso_alto` (PEP confirmada, indicios de simulación) → nivel final = máx(base, Alto) |
| Estado | completo / **pendiente** (lista sin resolver, dato crítico no verificable o algún factor provisional) / **restricción legal** (sanción confirmada; prevalece) |
| Provisional | Falta de dato crítico → factor en 3 marcado provisional y expediente PENDIENTE; requiere justificación |
| Revisión | Máximo 24 / 12 / 6 meses según el nivel final (`niveles` en la config); el servidor rechaza una fecha posterior al máximo |
| Historial | **Cada evaluación es una versión nueva** (`matriz_evaluaciones.version`); nunca se sobrescribe |
| Ficha | Por factor: dato observado (prellenado desde el KYC), documento o consulta, fecha y justificación. **Fuente y fecha obligatorias en PEP, jurisdicción y listas** (`requiere_fuente`). |

Archivos: `config/matriz_tipos.php` · `models/Matriz.php` · `models/PdfMatriz.php` (extiende `PdfKyc`) · `riesgos.php` · `views/riesgos.php` · `sql/07_matriz_riesgo.sql` (tablas `matriz_evaluaciones` y `matriz_factores`).

Precisiones de las matrices que las reglas deben respetar:
- No se puntúan sexo, estado civil ni afiliación política ordinaria (en socio, `pep_partido` = «miembro de algún partido» **no** se puntúa; «directiva de partido» sí).
- No se penaliza la nacionalidad por sí sola. Por decisión del usuario (5-nov-2026) **no hay listas GAFI en el sistema**: el factor de jurisdicción va sin sugerencia automática y el evaluador registra la fuente consultada.
- Ser funcionario público no equivale a PEP: se sugiere 2 (verificar), no 3.
- Campo vacío ≠ ausencia de riesgo: usa reglas `falta` o `todos_falta`, con `provisional` si el dato es crítico.
- No se sanciona a un proveedor por no tener políticas PLA si no es sujeto obligado.
- Factores que dependen solo de `[complemento]` llevan `sin_sugerencia` (el sistema dice qué consultar y no inventa un puntaje).

Condiciones de regla soportadas: `campo+igual`, `campos+alguno_igual`, `campo|campos+falta`, `campos+todos_falta`, `campo+contiene` (palabras clave normalizadas), `calculo+menor` (id de `calculos` del KYC), `restar+menor`, `tabla+vacia`, `tabla+con_filas`, `tablas_todas_vacias`.

Pruebas que se deben repetir al cambiar la config (script en el scratchpad de la sesión, `mtest.php`):
1. Toda clave de campo, tabla o cálculo usada en las reglas existe en `config/kyc_tipos.php`.
2. Los pesos suman 100 %.
3. El ejemplo de cálculo de la sección 5 de cada documento da el mismo R y nivel.
4. Los límites 1,66/1,67 y 2,33/2,34 clasifican bien.
5. Las sugerencias esperadas coinciden en casos diseñados para disparar y para no disparar cada regla.

## Requisito del usuario: la matriz SUGIERE la calificación a partir del KYC

Pedido expreso del usuario (5-nov-2026): **la matriz debe proponer cómo calificar cada factor según lo que el sujeto declaró en su formulario KYC.** No basta con mostrar la evidencia: hay que sugerir la calificación (1–3 en DGL) y explicar por qué. El oficial de cumplimiento decide al final.

Cómo funciona:

1. Cada factor tiene `reglas` en `config/matriz_tipos.php`. Cada regla lee datos del expediente KYC (campos de `datos` y filas de las tablas repetibles) y, si se cumple, propone una calificación y un motivo.
2. `Matriz::sugerir($tipo, $datosKyc, $tablasKyc)` evalúa en el servidor todas las reglas de cada factor y devuelve, por factor, `array('score'=>N, 'motivos'=>array(...))`.
   - Gana la regla que se cumple con la **calificación más alta** (criterio conservador).
   - Si no se cumple ninguna, se usa `base` del factor. Si tampoco hay `base`, el factor queda **sin sugerencia**: no se inventa.
   - Una regla `falta` (dato vacío) propone subir el riesgo por información incompleta y lo dice en el motivo.
3. **En pantalla**, junto a cada factor:
   - un chip «Sugerido: 4 · motivo» (por ejemplo «Funcionario público = Sí»);
   - si el expediente aún no está calificado, el selector 1–5 viene **precargado con la sugerencia**;
   - un botón «Aplicar todas las sugerencias»;
   - el total y el nivel se recalculan en vivo.
4. **Si el oficial elige una calificación distinta de la sugerida, la justificación es obligatoria** (validada en el servidor). Se guardan `score` y `score_sugerido` para auditoría.
5. Si el KYC cambió después de calificar (por ejemplo, porque el sujeto lo actualizó por enlace externo), el listado muestra «KYC actualizado: revisar calificación» cuando la sugerencia actual difiere de la guardada.
6. El PDF muestra, por factor: calificación, sugerencia y motivo, y si se apartó de la sugerencia, la justificación.

Tipos de regla (`'si'`) que debe soportar el motor:

| Regla | Ejemplo | Para qué |
|---|---|---|
| `igual` | `array('campo'=>'pep_publico','igual'=>'Sí','score'=>5,'motivo'=>'Es funcionario público')` | Preguntas Sí/No y opciones |
| `en` | `array('campo'=>'rep_tipo_id','en'=>array('Pasaporte'),'score'=>3,...)` | Listas de valores |
| `mayor_o_igual` | `array('campo'=>'ingresos','mayor_o_igual'=>10000,'score'=>4,...)` | Montos (umbrales) |
| `calculo_mayor_o_igual` | sobre un `calculos` del KYC (patrimonio, resultado mensual) | Totales calculados |
| `falta` | `array('campo'=>'actividad','falta'=>true,'score'=>4,'motivo'=>'No declaró actividad económica')` | Información incompleta |
| `tabla_filas` | `array('tabla'=>'beneficiarios_finales','min'=>1,...)` / `'max'=>0` | Existencia o ausencia de filas |
| `tabla_campo_en` / `tabla_campo_distinto` | accionista con `nacionalidad` distinta de «Ecuador», sexo, tipo de identificación | Datos dentro de las filas |
| `pais_en_lista` | `array('campo'=>'nacionalidad','lista'=>'paises_alto_riesgo','score'=>5,...)` | Jurisdicciones de mayor riesgo (lista en config) |

Reglas para escribir reglas:
- **Las claves de campo y de tabla son las exactas de `config/kyc_tipos.php`.** Compruébalas. Una clave mal escrita hace que la regla nunca se cumpla, sin ningún error.
- **Los umbrales, las listas de países y las calificaciones de cada regla salen del documento de la matriz o del manual de DGL.** Si el documento no los trae, propónlos al usuario como borrador marcado «por confirmar con el Oficial de Cumplimiento». Nunca los subas a producción como si fueran oficiales sin esa confirmación.
- Los valores Sí/No llevan tilde: `'Sí'`.
- Agrega un test por tipo en la QA: un expediente `PRUEBA` diseñado para disparar cada regla y otro que no dispare ninguna, y compara la sugerencia esperada con la obtenida.

## Construir el motor (una sola vez)

1. `config/matriz_tipos.php`, indexado por **el mismo código** de `config/kyc_tipos.php` (`socio-accionista`, ...).
2. Migración `sql/NN_matriz.sql`, una tabla compartida para todos los tipos:
   ```sql
   CREATE TABLE IF NOT EXISTS matriz_calificaciones (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(40) NOT NULL, registro_id INT NOT NULL,
    factor_key VARCHAR(60) NOT NULL, score TINYINT NOT NULL,
    score_sugerido TINYINT NULL, motivo_sugerencia VARCHAR(500) NULL,   -- sugerencia vigente al calificar
    evidencia TEXT NULL, justificacion TEXT NULL,                        -- justificación obligatoria si score <> score_sugerido
    calificado_por INT NOT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk (tipo, registro_id, factor_key)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```
   Guarda la puntuación total y el nivel solo si se necesita un histórico; si no, se calculan al mostrar.
3. `models/Matriz.php`: `tipos()`, `calcular($tipo, $scores)` (devuelve total y nivel), `nivel($total)`, `color($nivel)`, `guardar()` con `INSERT ... ON DUPLICATE KEY UPDATE`, `sugerirEvidencia($tipo, $datosKyc)` y **`sugerir($tipo, $datosKyc, $tablasKyc)`**, que evalúa las `reglas` (ver la sección de requisitos). Lee las tablas repetibles con `Kyc::tableRows()` y los totales con `Kyc::calculations()` y `Kyc::calculate()`, sin duplicar esa lógica.
4. `riesgos.php` + `views/riesgos.php`: tipos, después el listado de expedientes activos con nivel o "Sin calificar", después el formulario con los factores, cálculo en vivo en JS idéntico al de PHP (como las fórmulas económicas de `views/kyc.php` y `PdfKyc`) y CSRF. PDF con FPDF siguiendo el estilo de `models/PdfKyc.php`. Acceso con los mismos roles que `kyc.php`. Diseño: `diseno-dgl`.
5. Habilita la tarjeta `riesgos` en `views/dashboard.php`.
6. Audita `crear`/`editar`/`imprimir` con módulo `riesgos` (`auditoria-instrumentar`, si existe).

## Agregar o editar la matriz de un tipo

```php
'socio-accionista' => array(
 'matriz_code' => 'DGL-MR-00X',   // código oficial del documento; pídelo, no lo inventes
 'factores' => array(
  array('key'=>'pep', 'nombre'=>'Exposición política', 'peso'=>0.20,
        'evalua'=>'Condición PEP del socio, su representante y beneficiarios finales.',
        'kyc_campos'=>array('pep_publico','pep_partido','pep_relacionado','beneficiario_publico','beneficiario_partido','beneficiario_relacionado'),
        'base'=>1,                                   // sugerencia si no se cumple ninguna regla
        'reglas'=>array(
          array('si'=>array('campo'=>'pep_publico','igual'=>'Sí'),      'score'=>5, 'motivo'=>'Declaró ser funcionario público'),
          array('si'=>array('campo'=>'pep_relacionado','igual'=>'Sí'),  'score'=>4, 'motivo'=>'Relacionado con una PEP'),
          array('si'=>array('campo'=>'pep_publico','falta'=>true),      'score'=>3, 'motivo'=>'No respondió la pregunta PEP'),
        )),
  // ...
 ),
),
```

- **Los pesos suman exactamente 1.00.** Súmalos en voz alta en la respuesta y valida en `Matriz::tipos()` con `abs(array_sum - 1) < 0.0001`; si falla, lanza una excepción.
- `key` es única dentro del tipo, en snake_case y **nunca se renombra** si ya hay calificaciones guardadas.
- `kyc_campos` son claves **exactas** de `config/kyc_tipos.php` y alimentan la evidencia sugerida (por ejemplo "pep_publico: Sí"). Si no sabes cuáles son, omítela: no inventes claves.
- Extrae factores, pesos y descripciones **del documento fuente**; no los inventes.

## Niveles (fijos para todos los tipos, salvo que el manual de DGL diga otra cosa)

| Total | Nivel | Color |
|---|---|---|
| 1.00–1.99 | Bajo | verde |
| 2.00–2.99 | Medio | amarillo |
| 3.00–3.99 | Alto | naranja |
| 4.00–5.00 | Crítico | rojo |

Si el manual de cumplimiento de DGL define otros umbrales, usa los del manual y documéntalos aquí.

## Despliegue y verificación

`despliegue-ftp-produccion`. Agregar un tipo a un motor que ya existe es solo subir `config/matriz_tipos.php`, sin migración.

- [ ] Los pesos suman 1.00 y una config mal sumada produce un error claro.
- [ ] El listado muestra "Sin calificar" antes de calificar.
- [ ] Calcula a mano un caso: el total de JS, el de PHP y el del PDF coinciden.
- [ ] Recalificar actualiza las filas sin duplicarlas.
- [ ] La evidencia sugerida trae datos reales de un expediente `PRUEBA`.
- [ ] **Sugerencias:** con un expediente `PRUEBA` hecho para disparar cada regla, la calificación sugerida y su motivo coinciden con lo esperado. Sin datos, sale `base` o «sin sugerencia».
- [ ] Un expediente sin calificar abre con las sugerencias precargadas, y «Aplicar todas las sugerencias» funciona.
- [ ] Cambiar una calificación a un valor distinto del sugerido sin justificación se rechaza en el servidor; con justificación se guarda y queda `score_sugerido`.
- [ ] Al editar el KYC (por ejemplo, PEP pasa a «Sí»), el listado marca «KYC actualizado: revisar calificación».
- [ ] Un expediente desactivado no aparece en el listado de la matriz.
