---
name: dgl-alertas-nuevo-detector
description: Construye (la primera vez) y amplía el Motor de Alertas de SUILAFT DGL (monitoreo dentro de "Prevención de riesgos" / "Debida diligencia"): reglas automáticas que detectan PEP, coincidencias en listas, riesgo alto, pagos que superan umbral, fraccionamiento, efectivo, vencimientos de documentos o de enlaces. Úsala cuando el usuario pida "detecta cuando pase X", "agrega una alerta para...", describa un patrón que el sistema debería vigilar solo, o después de kyc-nuevo-tipo si el tipo nuevo tiene preguntas PEP/listas.
---

# Motor de alertas en DGL

## Estado

**Existe desde el 6-nov-2026**: `models/MotorAlertas.php` (detectores `escanearPep`, `escanearRiesgo`, `escanearTransacciones`), `alertas.php`, tabla `alertas` (con columna `enlace` al origen). Agrega un detector como método privado y regístralo en `ejecutar()`. Sigue sin haber consulta automática a listas restrictivas. Hoy solo hay datos para alertas de **PEP declarada** (formulario socio-accionista) y de vencimiento de invitaciones. Las reglas transaccionales (umbral, fraccionamiento, efectivo) dependen de que exista el módulo **Registro de pagos**: no las construyas sin esos datos.

## Tipologías del sector construcción ya implementadas (`models/MotorAlertas.php`)

| Detector | Severidad | Clave de deduplicación |
|---|---|---|
| PEP declarada en el KYC (`pep_es`, `pep_publico`, `pep_partido`, `pep_relacionado`, `beneficiario_*` = «Sí») | alta (titular) / media | `pep|tipo|id` |
| Coincidencia en listas registrada en Debida diligencia | crítica (confirmada) / alta (potencial) | `lista|tipo|id|resultado` |
| PEP confirmada en Debida diligencia | alta | `pepdd|tipo|id` |
| Matriz con nivel final ALTO / revisión vencida | alta / media | `riesgo|tipo|id|vN` · `vencimiento|matriz|tipo|id|fecha` |
| Transacción ≥ umbral RESU | media | `umbral|tx|id` |
| Efectivo ≥ USD 5.000 (`UMBRAL_EFECTIVO`, por confirmar) | media / alta si ≥ umbral | `efectivo|tx|id` |
| Recursos de terceros (RPT 2) | media | `tercero|tx|id` |
| Pago desde el exterior | media | `exterior|tx|id` |
| Transacción > 3 × ingreso mensual del KYC (`VECES_INGRESO`, por confirmar) | media / alta si > 12× | `perfil|tx|id` |
| Fraccionamiento: ≥ 2 transacciones < umbral que suman ≥ umbral en 30 días | alta | `fracc|tipo:id|tx` |
| Pagos mayores al valor de la operación | media | `sobrepago|op|id` |

Al replicar para otra constructora, confirma con su Oficial de Cumplimiento los dos umbrales internos.

## Construir el motor (una sola vez)

1. Migración `sql/NN_alertas.sql`:
   ```sql
   CREATE TABLE IF NOT EXISTS alertas (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(20) NOT NULL,            -- pep | lista | riesgo | transaccion | vencimiento | perfil
    severidad VARCHAR(10) NOT NULL,            -- baja | media | alta | critica
    origen_tipo VARCHAR(40) NULL, origen_id INT NULL,
    titulo VARCHAR(255) NOT NULL, descripcion TEXT NOT NULL,
    clave_dedupe VARCHAR(191) NOT NULL UNIQUE,
    estado VARCHAR(12) NOT NULL DEFAULT 'abierta',  -- abierta | en_revision | cerrada | descartada
    resolucion TEXT NULL, resuelto_por INT NULL, resuelto_en DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```
2. `models/MotorAlertas.php`: `ejecutar($db)` une los resultados de los métodos privados `escanearX()` y pasa cada candidata por `upsert()`.
3. `upsert()` aplica una sola regla: si la `clave_dedupe` existe en estado `cerrada` o `descartada`, **no se toca**. Si está abierta, actualiza la descripción y solo **sube** la severidad, nunca la baja.
4. Vista de listado, detalle y resolución (cerrar o descartar con justificación obligatoria) y un botón "Actualizar alertas" con CSRF. No hay cron en el hosting: el motor corre al pulsarlo, salvo que el usuario configure un cron en el panel.
5. Auditoría: `escanear` y `cambiar_estado`, módulo `riesgos`.

## Las tres reglas que no se negocian

1. **`clave_dedupe` determinista**: `categoria|tipo|id[|detalle]`, por ejemplo `pep|socio-accionista|12`. Nunca `time()` ni valores aleatorios.
2. **Nunca reabrir** lo que un analista cerró o descartó. Pasa siempre por `upsert()`.
3. **La descripción explica el motivo con datos concretos**: qué regla se disparó, qué campo, qué valor, monto y fecha. Nunca "Transacción sospechosa" a secas.

## Mapa PEP/listas por tipo KYC

Cada formulario nombra distinto sus campos, así que el mapa es explícito:

```php
private static $camposPep = array(
 'socio-accionista' => array(
   'pep_publico'=>array('Sí'), 'pep_partido'=>array('Sí'), 'pep_relacionado'=>array('Sí'),
   'beneficiario_publico'=>array('Sí'), 'beneficiario_partido'=>array('Sí'), 'beneficiario_relacionado'=>array('Sí'),
 ),
 // 'nuevo-tipo' => array(...)  // agrégalo al crear un tipo con kyc-nuevo-tipo
);
```

Los valores son exactamente `'Sí'` con tilde, como los guarda `Kyc::fields()`. Las etiquetas legibles salen de `config/kyc_tipos.php`; no hace falta duplicarlas. Lee el JSON con `json_decode(..., true)` de la tabla del tipo y recorre solo los expedientes `estado='activo'`.

## Agregar un detector

```php
private static function escanearX($db) {
  $alertas = array();
  foreach (/* candidatos */ as $fila) {
    if (/* no aplica */) continue;
    $alertas[] = array('categoria'=>'...', 'severidad'=>'alta', 'origen_tipo'=>$codigo, 'origen_id'=>(int)$fila['id'],
      'titulo'=>'Título específico - '.$nombre,
      'descripcion'=>"Motivo: ...\nDetalle: ...",
      'clave_dedupe'=>'x|'.$codigo.'|'.$fila['id']);
  }
  return $alertas;
}
```

Regístralo en `ejecutar()`. Si agrupas por persona, usa `foreach ($porPersona as $clave => $filas)` (con `=>`; sin él se pierde la clave sin ningún error visible). Cualquier umbral (por ejemplo USD 10.000 en 30 días) va en una constante con su fuente normativa en un comentario, y lo confirma el usuario.

## Verificación

- [ ] `php -l models/MotorAlertas.php`.
- [ ] Prueba con un caso `PRUEBA` que dispara la regla y otro que no la dispara.
- [ ] Al ejecutarlo dos veces no se duplica nada.
- [ ] Una alerta cerrada o descartada sigue igual tras otra ejecución.
- [ ] La severidad sube con el hallazgo y nunca baja sola.
- [ ] La descripción se entiende sin abrir el expediente.
