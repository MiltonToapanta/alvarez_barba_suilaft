---
name: alertas-nuevo-detector
description: Agrega un nuevo detector (regla de alerta automática) al Motor de Alertas de SUIAUTO, o extiende uno existente (ej. detectar coincidencias en listas para un tipo de KYC nuevo). Úsala cuando el usuario pida "detecta cuando pase X", "agrega una alerta para...", describa un patrón de riesgo/transacción/vencimiento que el sistema debería vigilar solo, o después de usar la skill kyc-nuevo-tipo si el tipo nuevo necesita entrar también a la detección de PEP/listas restrictivas. Sigue el patrón establecido de MotorAlertas.php: idempotente por clave_dedupe, nunca reabre una alerta que un analista ya cerró o descartó, y la descripción siempre explica el motivo con cifras concretas, nunca un título genérico.
---

# Agregar un detector al Motor de Alertas

`app/models/MotorAlertas.php` no es una lista de reglas mágicas: es una clase con un método privado `escanearX()` por categoría, cada uno devolviendo un arreglo de alertas candidatas, y `ejecutar()` las junta y las pasa por `Alerta::upsert()`. Agregar una regla nueva es **escribir un método privado nuevo y agregarlo al `array_merge()` de `ejecutar()`** — no se toca nada más del sistema.

## Las tres reglas que no se negocian

1. **`clave_dedupe` determinística y única por hallazgo.** Formato `tipo|identificador(es)`, ej. `transaccion_fraccionamiento|cli-pn|42` o `riesgo|cli-pj|7`. Debe ser la MISMA cada vez que el motor corra sobre el mismo hallazgo real — si usas `time()` o un valor aleatorio ahí, cada corrida duplicará la alerta.
2. **Nunca reabras algo que un analista ya cerró.** No llames `Alerta::upsert()` tú mismo con lógica distinta — siempre pasa por `upsert()`, que ya tiene la regla: si `estado` es `cerrada` o `descartada`, no se toca. Tu detector no decide esto, no lo repitas.
3. **La descripción explica el motivo con cifras, no es un título.** Mira cualquier `escanearX()` existente: la descripción siempre dice *qué pasó, cuánto, cuándo y por qué importa* — nunca algo genérico como "Transacción sospechosa". El listado de `/alertas` muestra la primera línea como resumen (`alerta_primera_linea()`) y el detalle completo se ve en `/alertas/ver/{id}`.

## Paso a paso

### 1. Decide la categoría y el `tipo`

Las categorías existentes en la columna `alertas.tipo` son: `riesgo`, `lista`, `transaccion`, `vencimiento`, `perfil`. Si tu detector encaja en una de estas, úsala — el frontend (donut/barras por categoría en `/alertas`) ya sabe agruparlas. Si de verdad es un concepto nuevo, puedes usar un `tipo` nuevo, pero entonces revisa `app/views/alertas/index.php` y `app/core/alertas_helpers.php` para que el gráfico por categoría lo muestre con una etiqueta legible.

### 2. Escribe el método `escanearTuDetector()`

Usa esta forma (tomada de `escanearTransaccionesUmbral()`, el detector más simple del archivo):

```php
private function escanearTuDetector()
{
    $alertas = [];
    $tiposKyc = kyc_todos_tipos();   // si tu detector es por sujeto KYC

    foreach ($this->tusCandidatos() as $fila) {
        if (/* condición que descarta el caso */) {
            continue;
        }

        $alertas[] = [
            'tipo' => 'transaccion',          // una de las categorías de arriba
            'severidad' => 'alta',             // 'baja' | 'media' | 'alta' | 'critica'
            'origen_tipo' => $fila['origen_tipo'],   // opcional: para vincular a un sujeto
            'origen_id' => (int) $fila['origen_id'], // opcional
            'titulo' => 'Título corto y específico - ' . $nombre,
            'descripcion' => "Motivo: <qué regla se disparó>.\n"
                . 'Detalle con cifras: monto, fecha, números de referencia reales.',
            'clave_dedupe' => 'tu_detector|' . $fila['id'],
        ];
    }

    return $alertas;
}
```

Si el detector necesita agrupar transacciones por persona dentro de una ventana de tiempo (como fraccionamiento), copia el patrón completo de `escanearFraccionamiento()` — ya resuelve el `foreach ($porPersona as $clave => $transacciones)` (nota el `=>`, un bug real de este archivo en una versión anterior: sin la flecha, PHP ignora las claves y el agrupamiento por persona se rompe silenciosamente).

### 3. Regístralo en `ejecutar()`

```php
foreach (array_merge(
    $this->escanearRiesgo(),
    $this->escanearListas(),
    $this->escanearTransaccionesUmbral(),
    $this->escanearFraccionamiento(),
    $this->escanearEfectivoDominante(),
    $this->escanearFrecuenciaInusual(),
    $this->escanearVencimientos(),
    $this->escanearCambiosPerfil(),
    $this->escanearTuDetector()     // <- agrégalo aquí
) as $alerta) {
```

### Caso especial: extender la categoría "lista" para un tipo de KYC nuevo

A diferencia de `escanearRiesgo()` (que itera `matriz_todos_tipos()` genéricamente y por eso un tipo nuevo entra solo), `escanearListas()` depende de `$camposListas`, un mapa **hardcodeado por tipo** porque cada formulario KYC nombra distinto sus campos de PEP/listas (`es_pep` en Cliente PN, `rl_es_pep` en Cliente PJ, `pep_propiedad_admin` en Proveedor PJ...). Si agregaste un tipo de KYC nuevo con la skill `kyc-nuevo-tipo` y tiene campos de PEP/listas restrictivas, **tienes que agregar una entrada a mano**:

```php
private $camposListas = [
    'cli-pn' => ['es_pep' => ['Si'], 'familiar_pep' => ['Si'], 'coincidencias_listas' => ['Potencial', 'Confirmada']],
    // ...
    'tu-codigo-nuevo' => ['tu_campo_pep' => ['Si'], 'tu_campo_listas' => ['Potencial', 'Confirmada']],
];
```

Y si el campo tiene un nombre que no es autoexplicativo, agrégalo también a `$etiquetasCampo` (se usa para armar la descripción legible: "¿Es PEP?: Si" en vez de "es_pep: Si").

### 4. Despliega

Este proyecto se despliega por FTP — usa la skill **`despliegue-ftp-produccion`** para el patrón de subida y de commit+push. Aquí solo tocas `app/models/MotorAlertas.php` (un archivo PHP, sin migración, salvo que tu detector necesite una columna/tabla nueva).

### 5. Verifica (checklist)

- [ ] `php -l app/models/MotorAlertas.php` sin errores de sintaxis.
- [ ] Crea datos de prueba reales que disparen el detector (no inventes solo el caso feliz — prueba también el caso que NO debería disparar, para confirmar que el `continue` filtra bien).
- [ ] Corre el motor dos veces seguidas (botón "Actualizar alertas") — la segunda corrida no debe duplicar la alerta ni cambiar su severidad si no cambió el hallazgo real.
- [ ] Cierra o descarta la alerta manualmente, corre el motor de nuevo — debe seguir cerrada/descartada, no reabrirse.
- [ ] Si subes la severidad del hallazgo real (ej. el monto sube), corre el motor — la severidad debe escalar, nunca bajar sola.
- [ ] Lee la descripción completa en `/alertas/ver/{id}`: debe explicar el motivo con cifras reales, no un texto genérico.
- [ ] Los gráficos de `/alertas` (donut por severidad, barras por categoría) reflejan la alerta nueva sin tocar esas vistas.
