---
name: institucional-nuevo-factor
description: Agrega, edita o reordena los factores de la Matriz de Riesgo Institucional de SUIAUTO (MRI-INST-01) a partir de un documento fuente. Úsala cuando el usuario entregue el documento de la matriz institucional, pida "agregar un factor institucional nuevo", o describa un riesgo a nivel de toda la organización (no de un sujeto individual) que debería calificarse en las evaluaciones periódicas. Es la contraparte a nivel institucional de matriz-nuevo-tipo (que es por sujeto): aquí hay UNA sola matriz para toda la organización, versionada en el tiempo con flujo de aprobación, no una por tipo de KYC.
---

# Agregar un factor a la Matriz Institucional

A diferencia de la matriz individual (una por cada tipo de sujeto KYC), la Matriz Institucional es **una sola lista de factores** que evalúa el riesgo de la organización completa, y se versiona en el tiempo (cada evaluación es una "versión" con estado borrador/aprobada). `InstitucionalController` y `institucional_helpers.php` leen la lista de `app/config/matriz_institucional.php` — agregar un factor es editar ese único archivo.

## Paso a paso

### 1. Lee el documento fuente (MRI-INST-01 o equivalente)

Extrae, por cada factor: nombre, peso, y una descripción de qué datos lo alimentan (columna "Datos" del documento original).

### 2. Verifica que los pesos sumen 1.00

Igual regla que la matriz individual: la suma de `peso` de **todos** los factores debe dar exactamente 1.00. Hoy son 9 factores (0.15+0.10+0.15+0.20+0.10+0.10+0.05+0.05+0.10 = 1.00) — si agregas uno nuevo, tienes que redistribuir los pesos existentes para que la suma se mantenga en 1.00, no solo sumar el nuevo peso encima.

### 3. Decide si el factor tiene una fuente de datos automática en SUIAUTO

Este es el detalle que distingue a esta matriz: algunos factores pueden **sugerir** su calificación de riesgo inherente a partir de datos reales ya calificados en las matrices individuales (`tipos_kyc`). Por ejemplo, el factor "Clientes / contrapartes" sugiere su nivel mirando la distribución real de scores de **todos** los tipos de cliente activos (hoy `cli-pn`, `cli-pj`, `cli-pn-sp`, `cli-pj-sp`). Si tu factor nuevo tiene una fuente de datos real en el sistema (una tabla, un tipo de KYC calificado), ponla en `tipos_kyc`; si no la tiene (como "Geografía" o "Canales/intermediarios" hoy, que se califican 100% a mano porque el documento original así lo pide), deja `tipos_kyc => []`.

`Institucional::sugerirInherente()` ya sabe leer **ambos motores** de matriz individual sin que tengas que tocar nada: para un tipo con motor `simple` lee `matriz_scores` + `matriz_nivel_de()` (escala 1-5), y para un tipo con motor `avanzado` lee directamente el `nivel` ya calculado en `matriz_avanzada_decision` (escala 0-100, Muy bajo..Muy alto). Si agregas un tipo de cliente/proveedor/BF/empleado nuevo con `matriz-nuevo-tipo` y quieres que alimente un factor institucional existente, solo agrega su código al arreglo `tipos_kyc` correspondiente — no hay que decidir ni codificar el motor aquí.

**Importante:** cuando agregues un tipo de KYC nuevo que sea una variante de un sujeto ya cubierto por un factor (ej. un nuevo tipo de cliente), acuérdate de sumarlo también al `tipos_kyc` del factor institucional correspondiente — no es automático, porque el documento original lista códigos de matriz concretos, no categorías. Si lo olvidas, el factor institucional simplemente ignora esos registros al sugerir (no falla, pero subestima el riesgo real).

### 4. Escribe la entrada en `app/config/matriz_institucional.php`

```php
['key' => 'tu_factor', 'nombre' => 'Nombre visible', 'peso' => 0.10,
 'datos' => 'Descripción de una línea de qué información alimenta este factor.',
 'tipos_kyc' => ['cli-pn', 'cli-pj']],  // o [] si se califica manualmente
```

No hay migración de base de datos: `matriz_institucional_factor` ya guarda una fila por `factor_key` genérica (igual que `matriz_scores` para las individuales), así que un `key` nuevo simplemente empieza a aparecer sin tocar el esquema.

### 5. Despliega

Usa la skill **`despliegue-ftp-produccion`**. Solo sube `app/config/matriz_institucional.php` — sin migración.

### 6. Verifica (checklist)

- [ ] Los pesos de **todos** los factores (viejos y el nuevo) siguen sumando 1.00.
- [ ] `/institucional` → "Nueva evaluación" muestra el factor nuevo en el formulario, con su peso visible.
- [ ] Si pusiste `tipos_kyc`, la sugerencia automática de riesgo inherente aparece al abrir una evaluación nueva (compárala contra la distribución real de esos tipos en `/matriz`).
- [ ] Calificar todos los factores (incluyendo el nuevo) da un total y nivel coherente con `matriz_nivel_de()` (mismos umbrales que la matriz individual: 1–1.99 Bajo, 2–2.99 Medio, 3–3.99 Alto, 4–5 Crítico).
- [ ] El flujo de aprobación (`/institucional/formulario/{id}` → aprobar) sigue bloqueando la edición de una versión ya aprobada, incluyendo el factor nuevo.
- [ ] El PDF (`/institucional/imprimir/{id}`) incluye el factor nuevo en la tabla y, si tiene plan de tratamiento pendiente, en esa sección también.
- [ ] Una versión aprobada con nivel Alto/Crítico sigue generando su alerta correspondiente en el Motor de Alertas (categoría "vencimiento" cuando se acerca `proxima_revision") sin que tengas que tocar `MotorAlertas.php`.
