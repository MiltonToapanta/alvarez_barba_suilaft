---
name: dgl-ia-nueva-herramienta
description: Construye (la primera vez) el asistente de IA de SUILAFT DGL y le agrega herramientas ejecutables (function calling) para que haga acciones reales — enviar una invitación KYC, buscar un expediente, resumir alertas — además de responder preguntas. Úsala cuando el usuario pida "que el asistente pueda...", "que la IA haga X por chat", o quiera un asistente de cumplimiento dentro del sistema. El modelo decide QUÉ ejecutar; PHP valida, ejecuta y redacta la confirmación.
---

# Asistente de IA y herramientas en DGL

## Estado y decisiones previas

Al 5-nov-2026 **no hay asistente** en DGL y el README lo excluye del flujo actual. Antes de construirlo, confirma con el usuario:

1. **Proveedor y modelo**. SUIAUTO usa DeepSeek con un esquema compatible con OpenAI. Si se elige Claude, carga antes la skill `claude-api`.
2. **Qué datos salen del servidor.** Los expedientes KYC/PEP son datos personales sensibles. Envía al modelo solo lo mínimo, sin identificaciones completas cuando no hagan falta, y que el usuario apruebe por escrito qué se envía.
3. **Clave API** en `config/ia.php` (fuera de Git, protegido por `config/.htaccess`), nunca en el código ni en el JS.

## Construir el motor (una sola vez)

- `ia.php` (raíz) con sesión, rol y CSRF; recibe POST JSON y responde JSON. `views/ia.php` es el chat. Llamada HTTP con `curl` de PHP 7.4, verificando TLS y con timeout.
- `models/Asistente.php`: `herramientas()` (esquemas), `ejecutar($llamada)` (despacho por `name`) y un método por herramienta.
- Historial mínimo en `ia_mensajes` si se requiere, sin guardar tokens ni enlaces de invitación.

## El principio que no se negocia

El **modelo** solo extrae la intención y los parámetros. **PHP valida todo** (tipo existe en `config/kyc_tipos.php`, `filter_var` del correo, expediente activo, rol con permiso del módulo) y, si algo falta, **no ejecuta nada** y pide el dato. El texto de confirmación lo arma PHP con los valores reales. El modelo nunca dice "listo, enviado".

## Agregar una herramienta

```php
array('type'=>'function','function'=>array(
 'name'=>'enviar_invitacion_kyc',
 'description'=>'Genera un enlace externo de alta para un tipo KYC y lo envía por correo. Úsala solo si el usuario dio el tipo y el correo; si falta alguno, pídelo.',
 'parameters'=>array('type'=>'object','properties'=>array(
   'tipo'=>array('type'=>'string','enum'=>array_keys(require __DIR__.'/../config/kyc_tipos.php')),
   'correo'=>array('type'=>'string','description'=>'Correo del destinatario')),
  'required'=>array('tipo','correo'))))
```

La ejecución reutiliza **los mismos modelos** que el formulario web (`Kyc::invitation`, `Mailer::send`, `EmailKyc::html`); no duplica lógica. Después audita con módulo `ia`. Buenas candidatas: acciones de 2 a 4 parámetros que ya se hacen a mano. Para algo largo, como llenar un KYC completo, la herramienta solo devuelve el enlace al formulario.

## Verificación

- [ ] Con los datos completos se ejecuta, y la confirmación muestra los valores reales.
- [ ] Con un dato faltante o ambiguo no se ejecuta nada (compruébalo en la base de datos o en la auditoría).
- [ ] Con un rol sin permiso, se niega.
- [ ] Una pregunta normal no dispara herramientas.
- [ ] En las pruebas de correo **no se envían mensajes reales**: usa `Mailer::compose` o un SMTP de pruebas.
