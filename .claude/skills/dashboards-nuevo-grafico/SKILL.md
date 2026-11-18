---
name: dashboards-nuevo-grafico
description: Agrega un gráfico nuevo al explorador de Dashboards de SUIAUTO (/dashboards, los 56 gráficos con Chart.js). Úsala cuando el usuario pida "agrega un gráfico que muestre...", "quiero ver X en el dashboard", o describa una métrica/distribución que debería visualizarse — el motor de dashboards es config-driven igual que KYC y Matriz: un gráfico nuevo es una línea de código en un solo archivo, sin tocar HTML/CSS/JS.
---

# Agregar un gráfico al explorador de Dashboards

`public/js/dashboards.js` no sabe nada de gráficos específicos: lee un arreglo `charts` que viene de `Dashboard::datos()` (`app/models/Dashboard.php`) y renderiza cada uno según su `type` usando Chart.js. Agregar un gráfico es **una sola línea nueva** dentro de `Dashboard::datos()` — no se toca el frontend.

## El patrón: una llamada a `$add(...)`

Dentro de `datos()` hay un closure local:

```php
$charts = [];
$add = function ($id, $group, $title, $explain, $type, $values, $unit = 'registros', $extra = []) use (&$charts) {
    $charts[] = $this->chart($id, $group, $title, $explain, $type, $values, $unit, $extra);
};
```

Y al final del método, decenas de llamadas como esta (una por gráfico existente):

```php
$add('alert-severidad', 'Alertas', 'Severidad de alertas', 'Distribución por baja, media, alta y crítica.', 'polarArea', $alertSeveridad);
```

Para agregar un gráfico nuevo, calculas tu propio arreglo asociativo `label => valor_numérico` en algún punto de `datos()` (siguiendo el estilo del resto del método: una consulta SQL + un `foreach` que va sumando con el helper `tally()`), y agregas una llamada `$add(...)` más, **antes del `return` final**.

## Los 7 `type` soportados (y cuándo usar cada uno)

| `type` | Para qué | Nota |
|---|---|---|
| `bar` | comparar categorías (conteos, montos) | el más genérico |
| `horizontal` | igual que `bar` pero con barras horizontales | mejor cuando las etiquetas son largas (ej. nombres de marcas, roles) |
| `line` | evolución en el tiempo (series por mes) | combínalo con el helper `months($n)` para el eje de meses |
| `area` | igual que `line` pero relleno (para montos acumulados/flujo) | |
| `pie` | proporción de un total, pocas categorías (2-5) | |
| `doughnut` | igual que `pie`, visualmente más compacto | úsalo para estados (activo/inactivo, abierta/cerrada) |
| `polarArea` | proporción con más categorías que `pie` (5-8) | bueno para severidades, niveles |

## Paso a paso

### 1. Decide el grupo

El parámetro `group` (ej. `'Alertas'`, `'Institucional'`, `'Inteligencia Artificial'`) es el que agrupa visualmente los gráficos en el explorador — reutiliza uno existente si tu gráfico pertenece a un módulo que ya tiene sección, o crea uno nuevo si es un módulo sin gráficos todavía (ej. si agregaste un módulo nuevo con `kyc-nuevo-tipo`, sus datos probablemente ya caen dentro del grupo "KYC" existente sin que hagas nada).

### 2. Escribe la consulta y arma el arreglo de valores

Sigue el estilo del resto del método — helpers disponibles dentro de la clase:
- `$this->rows($sql, $params)` — consulta obligatoria (falla si la tabla no existe).
- `$this->optionalRows($sql, $params)` — igual pero devuelve `[]` si la tabla no existe (útil si tu gráfico depende de datos que pueden no estar migrados todavía en algún entorno).
- `$this->tally(&$map, $key, $cantidad = 1)` — acumula conteos/sumas en un arreglo asociativo, tratando `null`/`''` como `'Sin definir'`.
- `$this->months($n)` — arreglo de los últimos `$n` meses en `Y-m => 0`, para series de tiempo.
- `$this->named($values, $names)` — traduce claves (ej. códigos) a nombres legibles antes de graficar.

Respeta el filtro de período (`$dias`/`$desde`) y el filtro de tipo de sujeto (`$filtroTipo`) que ya recibe `datos()`, si tu gráfico tiene sentido filtrado por esos dos ejes — así el usuario puede acotarlo igual que al resto.

### 3. Agrega la llamada `$add(...)`

```php
$add('tu-id-unico', 'TuGrupo', 'Título visible', 'Explicación de una línea de qué mide (aparece como tooltip del gráfico).', 'bar', $tusValores, 'registros');
```

`id` debe ser único entre los ~56 existentes (revisa con `grep -n "\$add('" app/models/Dashboard.php` antes de elegir uno). El sexto parámetro (`unit`) es el texto que acompaña los valores (`'registros'` por defecto; usa `'USD'`, `'%'`, `'puntos'`, etc. si corresponde).

### 4. No toques `public/js/dashboards.js` ni las vistas

El renderizado es completamente genérico sobre `type` — si tu gráfico usa uno de los 7 tipos soportados, aparece automáticamente con leyenda, tooltip, zoom y tabla de datos exacta, igual que los demás. Solo edita `dashboards.js` si necesitas un `type` que hoy no existe (eso sí sería un cambio de frontend, fuera del alcance normal de esta skill).

### 5. Despliega

Usa la skill **`despliegue-ftp-produccion`**. Solo cambia `app/models/Dashboard.php` — sin migración, sin tocar frontend.

### 6. Verifica (checklist)

- [ ] El gráfico aparece en `/dashboards`, en el grupo correcto, con el tipo visual esperado.
- [ ] El tooltip al pasar el cursor muestra el valor exacto por categoría.
- [ ] La leyenda permite activar/desactivar categorías (para gráficos circulares/multi-serie).
- [ ] La tabla de datos exactos (botón para expandir) coincide con lo que ves en el gráfico.
- [ ] Si tu gráfico depende del filtro de período, cambiar entre Historial/30/90/365 días lo actualiza correctamente.
- [ ] Si depende del filtro de tipo de sujeto, cambiarlo filtra el gráfico (o, si no debería filtrarse — como Usuarios o Institucional, que son totales globales — confirma que efectivamente lo ignora).
- [ ] `GET /dashboards/datos?dias=30&tipo=cli-pn` (la fuente JSON) incluye tu gráfico nuevo en la respuesta.
