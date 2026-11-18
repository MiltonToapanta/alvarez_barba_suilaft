---
name: debida-diligencia-modulo
description: Referencia completa del módulo de Debida Diligencia de SUIAUTO (genérico para los 8 tipos de sujeto KYC) — controlador, modelo, tabla, flujo, control de acceso, enlace a listas restrictivas y conexión con KYC, Motor de Alertas y Auditoría. Úsala para explicar, modificar o depurar Debida Diligencia.
---

# Módulo de Debida Diligencia (referencia completa)

Esta skill documenta el módulo de **Debida Diligencia** de SUIAUTO tal como existe hoy en producción: qué hace, cómo está construido, su base de datos, su flujo de uso, el control de acceso, el enlace al software externo de listas restrictivas, y cómo se conecta con el resto del sistema (KYC, Motor de Alertas, Auditoría). Úsala cuando necesites explicar el módulo, modificarlo, depurar un problema en él, o entender por qué aparece automáticamente para cada tipo de KYC nuevo.

No es una skill de "agregar un tipo nuevo" (no hay nada que multiplicar aquí): Debida Diligencia es **genérica para los 8 tipos de sujeto KYC** por diseño — un solo controlador, un solo modelo, una sola tabla con una columna `tipo`. Si se agrega un tipo de KYC nuevo con la skill `kyc-nuevo-tipo`, Debida Diligencia lo soporta automáticamente sin tocar ningún archivo de este módulo.

## Qué es y qué NO es

Debida Diligencia **no realiza, por sí sola, la verificación contra listas restrictivas ni la consulta a fuentes externas de PEP**. Es un módulo de **registro y evidencia documental** de un proceso de debida diligencia que se ejecuta en otro lugar:

- La verificación real contra listas (OFAC, ONU, UAFE, etc.) y PEP se hace con un **software externo ya contratado por la entidad para ese fin específico** — accesible desde el propio módulo mediante el botón "↗ Acceder al software de Debida Diligencia" en `/debida`, que enlaza a `https://dd.suilaft.tech/login` (se abre en pestaña nueva).
- Lo que hace SUIAUTO es: (a) dejar constancia del **resultado** de esa verificación externa (sin coincidencias / potencial / confirmada; PEP / no PEP), (b) guardar la **conclusión y recomendación** del analista, y (c) archivar los **dos PDF de evidencia** (el expediente de debida diligencia y el informe) por cada sujeto.

Esta separación de responsabilidades (verificación externa, registro interno) fue una decisión explícita: la entidad ya tenía licenciado un software de listas restrictivas y no tenía sentido duplicar esa funcionalidad dentro de SUIAUTO.

## Arquitectura: los 4 archivos que lo componen

| Capa | Archivo | Responsabilidad |
|---|---|---|
| Controlador | `app/controllers/DebidaController.php` (80 líneas) | 6 acciones: `index`, `listado`, `formulario`, `archivo`, `informe`, `descargar` |
| Modelo | `app/models/DebidaDiligencia.php` (58 líneas) | CRUD sobre `debida_diligencia` y `debida_diligencia_archivos` |
| Vistas | `app/views/debida/{index,listado,formulario,informe,_etiquetas}.php` | UI + helper de etiquetas/colores |
| Config (compartida con KYC) | `app/config/kyc_tipos.php` vía `kyc_todos_tipos()` / `kyc_tipo()` | De aquí sale la lista de los 8 tipos de sujeto; Debida Diligencia no tiene su propio catálogo de tipos |

No hay nada que configurar en `app/config/` específico de Debida Diligencia — por eso un tipo de KYC nuevo "hereda" el módulo automáticamente (ver sección final).

## Flujo completo de uso

```
/debida
  → tarjeta por cada uno de los 8 tipos de sujeto (Cliente PN, Cliente PJ, Proveedor PN,
    Proveedor PJ, Empleado, Beneficiario Final, Cliente PN/PJ Sector Público)
  → botón superior: ↗ Acceder al software de Debida Diligencia (https://dd.suilaft.tech/login)

/debida/listado/{codigo}
  → tabla de todos los expedientes KYC activos de ese tipo, con 4 columnas de estado
    (Estado, Listas, PEP, Documentos) como "chips" de color
  → "Revisar →" por cada fila

/debida/formulario/{codigo}/{id}
  → si el sujeto no tiene registro de debida diligencia todavía, se crea uno vacío
    en el primer GET (estado=pendiente, resultado_pep=pendiente, validacion_documental=no_validada)
  → formulario con 4 selects (Estado / Resultado de listas / Resultado PEP / Validación
    documental) + 3 textarea (Observaciones / Conclusión / Recomendación)
  → POST guarda todo de una vez (UPSERT por la clave única tipo+kyc_registro_id)
  → debajo: 2 tarjetas de carga de PDF (una por categoría) + enlace a "Ver resumen imprimible"

/debida/archivo/{codigo}/{id}  (POST, multipart/form-data)
  → sube UN pdf a la vez, a una de las 2 categorías fijas: "debida" o "informe"
  → valida: mime real por contenido (finfo, no por extensión), máx. 10 MB, solo
    application/pdf
  → nombre de archivo en disco: aleatorio (bin2hex(random_bytes(16)).pdf), nunca el
    nombre original — el nombre original solo se conserva en la base de datos para mostrarlo
  → ruta en disco: storage/kyc/{codigo}/{id}/debida/{aleatorio}.pdf
  → subir un segundo archivo a la misma categoría REEMPLAZA al anterior (UNIQUE KEY
    debida_id+categoria) — el archivo físico viejo queda huérfano en disco, no se borra

/debida/informe/{codigo}/{id}  (GET)
  → vista HTML standalone (sin el layout/menú de la app — $this->view(..., false)),
    pensada para imprimir/exportar a PDF desde el navegador (window.print() automático
    al cargar), con el logo de SUIAUTO y los mismos datos del formulario en modo lectura

/debida/descargar/{codigo}/{id}/{categoria}  (GET)
  → sirve el PDF subido (categoria = "debida" o "informe") inline, con su nombre original
```

## Tabla de base de datos

`database/07_debida_diligencia.sql` (+ `08_debida_categorias.sql` que agrega la columna `categoria`):

```sql
debida_diligencia
  id, tipo, kyc_registro_id,                         -- UNIQUE KEY (tipo, kyc_registro_id): un solo registro por sujeto
  estado                 enum('pendiente','en_revision','aprobada','rechazada')
  resultado_listas       enum('sin_coincidencias','potencial','confirmada')      -- NULL = pendiente
  resultado_pep          enum('no_pep','pep','pendiente')
  validacion_documental  enum('completa','incompleta','no_validada')
  observaciones, conclusion, recomendacion            -- text, libres
  revisado_por, revisado_en                           -- se sobreescriben en CADA guardado (no hay historial)
  creado_en, actualizado_en

debida_diligencia_archivos
  id, debida_id (FK → debida_diligencia, ON DELETE CASCADE),
  categoria               enum('debida','informe')    -- UNIQUE KEY (debida_id, categoria): máx. 2 archivos por sujeto
  nombre_ruta, nombre_original, tipo_mime, tamano,
  subido_por, subido_en
```

Puntos importantes del modelo de datos:

- **No hay historial de revisiones.** Cada `guardar()` es un `INSERT ... ON DUPLICATE KEY UPDATE`: la fila se sobreescribe completa, incluido quién la revisó por última vez. Si se necesita saber quién aprobó una debida diligencia en una fecha pasada y luego otro analista la editó, esa información ya no está disponible — solo queda el último estado. Si el negocio pide trazabilidad de versiones, es un cambio de modelo de datos (tabla de historial aparte), no algo que ya exista.
- **Máximo 2 archivos por sujeto, siempre.** No es una galería de adjuntos como en KYC — es exactamente una categoría "debida" y una "informe", y subir uno nuevo reemplaza al anterior en la base (aunque el archivo físico viejo sigue en disco, sin referencia).

## Control de acceso

Mismo mecanismo que el resto de módulos "controlados" del sistema (`Router::$modulosControlados`, que incluye `'debida'`):

1. `requireAuth()` en cada acción del controlador — sin sesión completa (usuario + segundo factor), redirige a login.
2. `Router::dispatch()` verifica que el módulo `debida` esté en la lista de módulos permitidos del usuario (`$_SESSION['modulos']`), salvo administradores.
3. **Nivel de permiso (Ver / Editar) por usuario y por módulo**: si el usuario tiene `debida` en nivel `"ver"`, el Router bloquea cualquier `POST` hacia `/debida/*` (guardar formulario, subir archivo) devolviendo `?acceso=solo_lectura` — pero deja pasar los `GET` (listado, formulario en modo lectura, informe, descarga), porque son de solo lectura por naturaleza.
4. Los roles de fábrica (`Oficial de Cumplimiento`, `Analista`, `Consulta`) ya incluyen `debida` en su lista de módulos (ver `database/11_roles_usuarios.sql`); `Administrador` tiene acceso total por `es_admin=1`.

**Observación de seguridad** (compartida con el resto del sistema, no exclusiva de este módulo): los formularios de `/debida/formulario/*` y `/debida/archivo/*` no llevan token CSRF — la protección CSRF del proyecto hoy solo está implementada en Firma Electrónica y en KYC. No es una vulnerabilidad explotable trivialmente (requiere sesión activa de la víctima), pero es una brecha real si se audita con rigor; ya quedó documentada como recomendación transversal en el informe `SUIAUT_Medidas_Control_Seguridad.pdf`.

## El enlace al software externo de listas restrictivas

- **URL**: `https://dd.suilaft.tech/login`
- **Dónde aparece**: botón destacado en la cabecera de `/debida` (`app/views/debida/index.php`, línea 1), `target="_blank"`.
- **Por qué es un enlace externo y no un cruce automático dentro de SUIAUTO**: la entidad ya tiene licenciado ese software específicamente para la consulta de listas restrictivas (OFAC, ONU, UAFE) y verificación PEP; duplicar esa funcionalidad en SUIAUTO sería redundante y, más importante, perdería la vigencia/actualización de listas que sí mantiene el proveedor externo. SUIAUTO se limita a **registrar el resultado** que el analista obtuvo en ese sistema externo.
- Si en algún momento se requiere hacer esa integración más estrecha (ej. traer el resultado automáticamente por API en vez de que el analista lo transcriba a mano), eso es trabajo nuevo de integración — no existe hoy ningún cliente HTTP hacia `dd.suilaft.tech` en el código (a diferencia de `NexusFirma.php`/`SuiFirmaCliente.php`, que sí son clientes API reales para la firma electrónica).

## Cómo se conecta con el resto del sistema

- **KYC** (`app/config/kyc_tipos.php`, `KycRegistro`): Debida Diligencia lee el expediente KYC del sujeto (`$this->model('KycRegistro')->obtener($tipo['tabla'], $id)`) para mostrar nombre e identificación, pero nunca lo edita. La relación es de solo lectura hacia KYC.
- **Motor de Alertas** (`app/models/MotorAlertas.php`, detector `escanearListas()`): este detector es **independiente** de los resultados guardados en `debida_diligencia` — cruza directamente los campos de PEP/listas autodeclarados dentro de cada formulario KYC (ej. `es_pep`, `rl_es_pep`, según el tipo), no el `resultado_pep`/`resultado_listas` que el analista guarda aquí. Es decir: hay dos señales de PEP/listas en el sistema que no están unificadas — la autodeclaración en el KYC (que sí dispara alertas automáticas) y el resultado de la verificación externa en Debida Diligencia (que es un registro manual, sin alerta automática asociada). Si se quiere que un `resultado_listas = 'confirmada'` guardado aquí también dispare una alerta automática, eso es un detector nuevo para `MotorAlertas.php` (usar la skill `alertas-nuevo-detector`).
- **Auditoría** (`app/core/auditoria_helpers.php`): instrumentado en 3 puntos — guardar la revisión (`'editar'`), subir un archivo (`'editar'`) y descargar un archivo (`'descargar'`), todos bajo el módulo `'debida'`.
- **Tipos de KYC nuevos**: como el controlador y el modelo no tienen ningún tipo hardcodeado (todo viene de `kyc_tipo($codigo)` / `kyc_todos_tipos()`), un tipo de KYC agregado con la skill `kyc-nuevo-tipo` aparece automáticamente en `/debida` sin editar ningún archivo de este módulo — es uno de los "módulos que cambian solos" documentados en esa skill.

## Rutas en producción

Base: `https://suilaft.tech/suiaut/`

| Ruta | Método | Qué hace |
|---|---|---|
| `/debida` | GET | Hub con las 8 tarjetas de tipo + enlace al software externo |
| `/debida/listado/{codigo}` | GET | Tabla de expedientes de un tipo |
| `/debida/formulario/{codigo}/{id}` | GET, POST | Ver/editar la revisión de un sujeto |
| `/debida/archivo/{codigo}/{id}` | POST | Subir/reemplazar un PDF (categoría `debida` o `informe`) |
| `/debida/informe/{codigo}/{id}` | GET | Resumen imprimible (HTML standalone, imprime automático) |
| `/debida/descargar/{codigo}/{id}/{categoria}` | GET | Descarga/visualiza el PDF subido |

## Qué SÍ puedes cambiar con confianza

- Textos, estilos y chips de `_etiquetas.php` (colores de estado) — no tocan datos ni lógica de acceso.
- Agregar un campo nuevo al formulario (ej. "fecha de próxima revisión"): columna nueva en `debida_diligencia`, agregarla a `guardar()`/`obtener()` y al formulario — patrón directo, sin efectos en cascada.
- Agregar una tercera categoría de archivo (ej. "soporte adicional"): ampliar el `enum('debida','informe')` de la columna `categoria` y la lista `['debida','informe']` validada en `DebidaController::archivo()`.

## Qué NO cambiar sin pensarlo dos veces

- El `UNIQUE KEY (tipo, kyc_registro_id)` de `debida_diligencia`: si se necesita historial de revisiones, no se logra quitando esta restricción a la ligera — hay que diseñar una tabla de historial aparte y decidir qué sigue siendo "el estado vigente" para `listado.php` y para el cruce con Motor de Alertas.
- El guardado de `nombre_ruta` como nombre aleatorio (no el nombre original): es deliberado, evita path traversal y colisiones; no lo cambies a `$f['name']` directamente.

## Checklist antes de dar por terminado un cambio aquí

- [ ] `/debida/listado/{codigo}` sigue mostrando los chips de color correctos para los 4 campos de estado.
- [ ] Guardar el formulario hace UPSERT correcto (probar sobre un sujeto que ya tenía revisión, no solo uno nuevo).
- [ ] Subir un PDF a una categoría que ya tenía archivo lo reemplaza (no duplica fila).
- [ ] Subir un archivo que no es PDF, o mayor a 10 MB, es rechazado.
- [ ] `/debida/informe/{codigo}/{id}` renderiza sin el menú de la app y dispara impresión automática.
- [ ] Cada guardado, subida y descarga sigue apareciendo en `/auditoria` bajo el módulo "debida".
- [ ] Un usuario con nivel "Ver" en Debida Diligencia puede listar y leer, pero no puede guardar ni subir archivos.
- [ ] El botón "↗ Acceder al software de Debida Diligencia" sigue apuntando a `https://dd.suilaft.tech/login` y abre en pestaña nueva.
