---
name: dgl-dashboards-nuevo-grafico
description: Construye (la primera vez) y amplía el explorador de gráficos/indicadores de SUILAFT DGL (reportes y estadísticas de cumplimiento con Chart.js). Úsala cuando el usuario pida "agrega un gráfico que muestre...", "quiero ver X en el dashboard/estadísticas", indicadores de expedientes, PEP, matriz, alertas, pagos o auditoría. El motor es config-driven: cada gráfico nuevo es una llamada $add(...) en un solo modelo, sin tocar HTML/CSS/JS.
---

# Gráficos e indicadores en DGL

## Estado

Al 5-nov-2026 **no hay gráficos**. `views/dashboard.php` es un panel de tarjetas de módulos y **no** debe convertirse en el explorador de gráficos. Si `models/Estadisticas.php` no existe, constrúyelo primero. Antes de diseñar, carga también la skill `dataviz`: paleta, tipo de gráfico, accesibilidad y modo oscuro.

## Construir el motor (una sola vez)

1. `models/Estadisticas.php` con `datos($db, $dias, $tipo)`, que devuelve `array('charts'=>[...])`, y estos helpers:
   - `rows($db,$sql,$types,$params)`: consulta preparada.
   - `optionalRows(...)`: devuelve `array()` si la tabla aún no existe, para módulos no migrados.
   - `tally(&$map,$key,$n=1)`: acumula; vacío → `'Sin definir'`.
   - `months($n)`: últimos `$n` meses `Y-m => 0`.
   - Un closure `$add($id,$grupo,$titulo,$explica,$tipo,$valores,$unidad='registros')`.
2. `estadisticas.php` (raíz): con `?formato=json` devuelve JSON (`Content-Type: application/json`); sin él, la vista.
3. `views/estadisticas.php` + JS en línea genérico: recorre `charts` y dibuja según `type` (`bar`, `horizontal`, `line`, `area`, `pie`, `doughnut`, `polarArea`), con tooltip, leyenda y una tabla de datos exactos debajo. Chart.js desde cdnjs o jsdelivr **con la versión fija**. Diseño: `diseno-dgl`.
4. Filtros comunes: período (todo, 30, 90 o 365 días) y tipo KYC (de `config/kyc_tipos.php`).
5. Acceso con sesión y los roles de cumplimiento. Ubícalo en la tarjeta `resu` ("Reportes y exportaciones") o como tarjeta nueva, según decida el usuario.

## Agregar un gráfico

1. Elige el grupo (`Formularios`, `Riesgos`, `Alertas`, `Pagos`, `Auditoría`).
2. Calcula `label => número`. Ten en cuenta que los expedientes KYC guardan los datos en JSON (`datos`): o bien decodificas en PHP recorriendo los activos de cada tabla `kyc_*` de `config/kyc_tipos.php`, o usas `JSON_UNQUOTE(JSON_EXTRACT(datos,'$.clave'))` si la versión de MySQL/MariaDB lo soporta (compruébalo antes).
3. `$add('formularios-pep', 'Formularios', 'Sujetos con condición PEP', 'Expedientes activos que declararon alguna pregunta PEP con Sí.', 'doughnut', $valores);`
4. El `id` es único: `grep -n "\$add('" models/Estadisticas.php`.

No toques el JS para un gráfico de un tipo ya soportado.

## Verificación

- [ ] Aparece en su grupo y con el tipo correcto; el tooltip, la leyenda y la tabla coinciden.
- [ ] Los filtros de período y tipo lo afectan, o no lo afectan si es global.
- [ ] `estadisticas.php?formato=json` lo incluye y no hay `Warning` en la salida.
- [ ] Sin datos, muestra "Sin datos", no un gráfico vacío roto.
- [ ] Ningún gráfico expone nombres ni identificaciones de personas: solo agregados.
