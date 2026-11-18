---
name: auditoria-instrumentar
description: Conecta un controlador nuevo (o una acción nueva en uno existente) de SUIAUTO a la bitácora de Auditoría, para que quede trazado quién hizo qué, cuándo y sobre qué expediente. Úsala cada vez que agregues un alta, edición, baja, descarga, envío de correo, cambio de estado o aprobación nueva al sistema — incluyendo cuando uses las skills kyc-nuevo-tipo, matriz-nuevo-tipo o ia-nueva-herramienta y quieras que esa acción nueva también quede en /auditoria. También úsala si el usuario pregunta "¿por qué esta acción no aparece en la bitácora?" o pide "que esto también quede registrado/auditado".
---

# Instrumentar una acción con `auditoria_registrar()`

Cada alta, edición, baja, descarga, correo, aprobación o cambio de estado que ya existe en SUIAUTO llama a una sola función, `auditoria_registrar()` (`app/core/auditoria_helpers.php`), justo después de que la acción real ya tuvo éxito. No hay nada más que montar: la tabla `auditoria` y la pantalla `/auditoria` ya existen y leen cualquier fila nueva automáticamente.

## Las tres reglas de esta función

1. **Se llama DESPUÉS de que la acción ya ocurrió, nunca antes.** Si registras la auditoría y luego la acción real falla (ej. el `INSERT` del formulario lanza una excepción), quedaría una entrada de auditoría mintiendo sobre algo que no pasó. Mira cualquier controlador existente: el patrón es siempre `$modelo->accion(...); auditoria_registrar(...);` en ese orden.
2. **Nunca bloquea la acción que audita.** La función ya envuelve su propio `INSERT` en un `try/catch` que traga cualquier error y lo manda a `error_log()`. Si el log de auditoría falla (ej. la tabla no existe, o hay un problema de conexión), el alta/edición/lo que sea igual se completa para el usuario. No agregues tu propio try/catch alrededor de la llamada — ya está cubierto.
3. **`$usuarioId`/`$usuarioNombre` son opcionales** y por defecto toman la sesión actual. Solo pásalos explícitos cuando la acción NO viene de un usuario logueado — por ejemplo el formulario público (`PublicoController`), donde se usa `auditoria_registrar(..., null, 'Cliente (formulario público)')`.

## Paso a paso

### 1. Localiza el punto exacto después de la acción real

```php
public function crear($codigo)
{
    // ... validaciones y extracción de datos ...
    $nuevoId = $this->model('TuModelo')->crear($datos);

    // Aquí, inmediatamente después de que la acción tuvo éxito:
    auditoria_registrar('crear', 'tu_modulo', $codigo, $nuevoId, 'Descripción legible de qué se creó y para quién/qué.');

    header('Location: ...');
    exit;
}
```

### 2. Elige una `accion` de la lista existente si aplica

`crear`, `editar`, `eliminar`, `desactivar`, `activar`, `descargar`, `enviar_correo`, `cambiar_estado`, `aprobar`, `generar_reporte`, `login`, `login_fallido`, `logout`, `escanear`. Reutilizar estas evita que `/auditoria` muestre una mezcla de sinónimos (`crear` vs `crear_nuevo` vs `alta`). Si tu acción no encaja en ninguna, puedes usar una nueva — se verá con `ucfirst(str_replace('_', ' ', $accion))` como fallback, pero si crees que se va a repetir, agrégala también a `auditoria_accion_label()` en `app/core/auditoria_helpers.php` con una etiqueta en español.

### 3. Usa la clave de módulo correcta

Las claves existentes (`auditoria_modulo_label()`) son: `kyc`, `matriz`, `institucional`, `debida`, `operaciones`, `reporteria`, `alertas`, `usuarios`, `ia`, `auth`, `publico`. Si tu controlador pertenece a un módulo nuevo, agrega su clave y etiqueta ahí también — si no, el filtro de módulo en `/auditoria` lo mostrará con `ucfirst($modulo)`, funciona pero se ve menos cuidado.

### 4. Escribe una descripción que un humano pueda leer sin abrir el expediente

Mal: `'Actualizado'`. Bien: `'Expediente de Cliente Persona Jurídica editado (razón social: Tal Empresa S.A.)'`. La descripción se trunca a 500 caracteres (`mb_substr(..., 0, 500)`), así que no hace falta que seas exhaustivo, pero sí específico — incluye el nombre/identificador real del sujeto u objeto afectado cuando lo tengas a mano en ese punto del código.

### 5. ⚠️ La trampa real de `Controller::viewApp()` — solo si tu controlador tiene una vista nueva

Esto no es sobre `auditoria_registrar()` en sí, pero es el bug real más caro que se encontró al construir el módulo de Auditoría, y vuelve a morder a cualquiera que agregue una vista nueva: `Controller::viewApp($vista, $datos, $activo)` comparte **una sola tabla de variables** entre `app_header.php`, tu vista y `app_footer.php` (usa `require`, no `include` aislado), y hace `extract($datos)` **después** de fijar `$modulos` (el arreglo de tarjetas del sidebar). Si tu vista nueva necesita pasar un dato llamado `modulos`, `activo`, `titulo` o cualquier otro nombre que ya use el layout compartido, `extract()` lo sobrescribe **silenciosamente** y rompe el sidebar en **todas las páginas**, no solo la tuya — porque el layout es compartido. Usa un nombre de clave distinto (ej. `modulosLista` en vez de `modulos`) para cualquier dato de tu vista que pueda chocar.

### 6. Despliega

Este proyecto se despliega por FTP — usa la skill **`despliegue-ftp-produccion`** para el patrón de subida y commit+push. Aquí solo cambia el controlador que editaste (y `auditoria_helpers.php` si agregaste una etiqueta nueva), sin migración.

### 7. Verifica (checklist)

- [ ] Ejecuta la acción real y confirma en `/auditoria` que aparece la fila nueva con el usuario, módulo, acción y descripción correctos.
- [ ] Si la acción puede fallar (ej. una validación del formulario), confirma que **no** queda una fila de auditoría de algo que no pasó.
- [ ] Prueba también el filtro por módulo/acción/usuario/texto en `/auditoria` con tu fila nueva — debe aparecer y desaparecer correctamente según el filtro.
- [ ] Si tu controlador agregó una vista nueva, fetch el HTML crudo de esa página autenticada y revisa que no haya `Warning:` de PHP en la respuesta (no confíes solo en que "se ve bien" — un `Warning: Illegal string offset` no siempre rompe visualmente a simple vista, pero sí contamina la salida).
