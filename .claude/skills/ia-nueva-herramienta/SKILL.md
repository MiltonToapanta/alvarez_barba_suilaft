---
name: ia-nueva-herramienta
description: Agrega una nueva acción ejecutable ("herramienta"/function calling) al asistente de IA de SUIAUTO (Centro de Inteligencia, /ia), para que pueda hacer algo real en el sistema además de responder preguntas — por ejemplo crear un registro, cambiar un estado, generar un archivo. Úsala cuando el usuario pida "que el asistente también pueda...", "que la IA haga X cuando se lo pida por chat", o describa una acción que hoy solo se hace a mano desde un formulario y debería poder dispararse en lenguaje natural desde /ia. Sigue el patrón ya probado de enviar_invitacion_kyc: el modelo solo decide QUÉ ejecutar y con qué datos (function calling de DeepSeek, compatible con el formato de OpenAI); la ejecución real, la validación y el texto de confirmación los decide PHP de forma determinística, nunca el modelo, para que el chat nunca pueda "alucinar" que algo se hizo cuando no fue así.
---

# Agregar una herramienta ejecutable al asistente de IA

El chat de `/ia` (`ApiController::chat()`) no es solo texto: puede pedirle a DeepSeek que llame una "función" cuando el mensaje del usuario describe una acción concreta, y entonces el backend ejecuta esa acción de verdad. La primera herramienta de este tipo es `enviar_invitacion_kyc` (crea un expediente KYC y manda el correo de invitación) — úsala como plantilla.

## El principio que no se negocia: el modelo decide QUÉ, PHP decide SI

DeepSeek puede equivocarse, inventar datos o malinterpretar una frase ambigua. Por eso la arquitectura separa dos responsabilidades:

1. **El modelo** solo extrae la intención y los parámetros estructurados del mensaje (ej. "tipo=cli-pj, nombre=Tal Empresa S.A., correo=x@y.com"), usando el esquema de la herramienta (`function.parameters`, con `enum` donde haya una lista cerrada de valores válidos).
2. **PHP valida todo antes de actuar.** Si falta un dato, el tipo no es válido, el correo no pasa `filter_var(..., FILTER_VALIDATE_EMAIL)`, o el usuario no tiene el permiso de módulo necesario, **no se ejecuta nada** — se devuelve un mensaje pidiendo el dato que falta. El texto final que ve el usuario en el chat (éxito o error) lo arma PHP con `string` concatenado, nunca se le pide al modelo que redacte "confirmo que se envió" porque el modelo no tiene forma de saber si de verdad se envió.

Si tu herramienta nueva salta ese segundo paso y confía en que el modelo mandó datos correctos, estás construyendo el mismo bug que un formulario web sin validación del lado del servidor — no lo hagas.

## Paso a paso

### 1. Confirma que la acción merece ser una herramienta de chat

Buenas candidatas: acciones que un analista ya hace seguido desde un formulario, con pocos parámetros (2-4), donde los datos necesarios normalmente ya están en el mensaje en lenguaje natural ("cierra la alerta 42 como falso positivo", "genera el RESU de operaciones de agosto"). Si la acción tiene muchos campos opcionales o pasos intermedios (como llenar un KYC completo), mejor que el chat solo **cree el borrador y dé el link** al formulario real — no trates de replicar un formulario entero dentro del chat.

### 2. Define el esquema de la herramienta

En `app/controllers/ApiController.php`, junto a `herramientasChat()` (que hoy solo tiene `enviar_invitacion_kyc`), agrega un bloque nuevo al arreglo que retorna:

```php
[
    'type' => 'function',
    'function' => [
        'name' => 'tu_accion_nueva',
        'description' => 'Explica en una frase qué hace y cuándo usarla. Sé explícito sobre cuándo NO usarla (ej. "solo cuando el usuario ya dio el número de alerta y la resolución, si falta alguno pídelo en vez de inventarlo").',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'parametro_uno' => ['type' => 'string', 'enum' => [...], 'description' => '...'],  // usa 'enum' siempre que la lista de valores válidos sea cerrada y conocida de antemano
                'parametro_dos' => ['type' => 'string', 'description' => '...'],
            ],
            'required' => ['parametro_uno', 'parametro_dos'],
        ],
    ],
],
```

El `description` de la función y de cada parámetro es lo único que el modelo ve para decidir cuándo y cómo llamarla — sé tan específico como puedas, igual que escribirías el docstring de una función que otra persona va a usar sin leer tu código.

### 3. Escribe el método de ejecución

Sigue la forma exacta de `ejecutarHerramienta()`: recibe la llamada cruda del modelo, decide por `$llamada['function']['name']` a qué método delegar, decodifica `$llamada['function']['arguments']` (es un string JSON, no un arreglo), y **retorna un string** — ese string es literalmente lo que se guarda como mensaje del asistente y lo que ve el usuario.

```php
private function ejecutarTuAccion(array $args, $usuarioId)
{
    // 1. Valida cada dato. Si algo falla, retorna un string explicando
    //    qué falta o qué está mal — NO ejecutes nada todavía.
    if (/* dato inválido o ausente */) {
        return 'Necesito que me confirmes <dato>, no pude identificarlo en tu mensaje.';
    }

    // 2. Si la acción toca datos sensibles, exige el permiso de módulo
    //    correspondiente ademas del permiso de 'ia' que ya exige guard():
    if (empty($_SESSION['es_admin']) && !in_array('modulo_correspondiente', $_SESSION['modulos'] ?? [], true)) {
        return 'No tienes permiso sobre ese módulo para realizar esta acción.';
    }

    // 3. Ejecuta la acción real usando los MISMOS modelos/controladores que
    //    usa el formulario web equivalente — no dupliques esa lógica aquí.
    $resultado = $this->model('LoQueCorresponda')->accion(...);

    // 4. Deja rastro en auditoria, igual que cualquier alta/edición manual.
    auditoria_registrar('accion', 'ia', $tipoEntidad, $idEntidad, 'Descripción de qué hizo el asistente de IA y para quién.');

    // 5. Confirma con datos concretos del resultado real, no una frase vaga.
    return 'Listo. <qué se hizo exactamente, con los valores reales usados>.';
}
```

Luego conéctalo en `ejecutarHerramienta()`:

```php
if ($nombreFuncion === 'tu_accion_nueva') {
    return $this->ejecutarTuAccion($args, $usuarioId);
}
```

### 4. Actualiza el mensaje de sistema del chat (opcional, solo si hace falta)

El mensaje `system` en `chat()` ya le dice al modelo que use las herramientas cuando el usuario pida una acción explícita y que pida el dato que falte en vez de inventarlo — esa instrucción es genérica y cubre cualquier herramienta nueva. Solo edítalo si tu acción necesita una regla especial (ej. "nunca ejecutes esta acción si el usuario solo está preguntando hipotéticamente").

### 5. No hace falta tocar el frontend

`public/js/ia.js` ya envía cualquier mensaje de chat tal cual a `/api/ai/chat` y muestra lo que venga en `message` — no sabe ni le importa si la respuesta vino de una herramienta o de texto libre del modelo. No necesitas cambiar `ia.js` a menos que quieras una visualización especial del resultado (por ejemplo, una tarjeta en vez de texto plano); en ese caso, devuelve también un campo extra en el JSON y agrégalo al render en `agregarMensaje()`.

### 6. Despliega

Este proyecto se despliega por FTP — usa la skill **`despliegue-ftp-produccion`** para el patrón de subida, migración (si tu acción la necesita) y commit+push. Normalmente solo cambia `app/controllers/ApiController.php` (y quizás un modelo si la acción necesita un método nuevo ahí).

### 7. Verifica (checklist)

- [ ] `php -l app/controllers/ApiController.php` sin errores.
- [ ] Pide la acción con todos los datos claros en un solo mensaje → se ejecuta y el chat confirma con datos reales (no una frase genérica).
- [ ] Pide la acción con un dato faltante o ambiguo → el asistente lo pide en vez de adivinar, y **no se ejecuta nada** (confírmalo mirando la base de datos o el log de auditoría, no solo el texto del chat).
- [ ] Prueba con un usuario sin el permiso de módulo requerido → la acción se niega con un mensaje claro.
- [ ] Revisa `/auditoria` después de ejecutar la acción: debe aparecer la fila con `modulo = 'ia'` y la descripción correcta.
- [ ] Pregúntale al asistente algo que NO debería disparar la herramienta (una pregunta normal de consulta) y confirma que sigue respondiendo con texto libre como antes — agregar una herramienta no debe romper el chat normal.
