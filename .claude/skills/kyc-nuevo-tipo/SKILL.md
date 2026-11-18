---
name: kyc-nuevo-tipo
description: Agrega un nuevo tipo de sujeto al Registro de formularios (KYC) de SUILAFT Centerauto (ej. "Conozca su accionista", "Cliente persona natural", "Cliente persona jurídica", "Proveedor", "Empleado") a partir del PDF, Word o imagen del formulario oficial. Úsala siempre que el usuario entregue un formulario de conocimiento, pida "crear el formulario de X", "agregar un tipo de sujeto/persona", o describa secciones, campos o adjuntos de un sujeto que Centerauto todavía no tiene, aunque no diga "KYC". El motor es config-driven: una entrada en app/config/kyc_tipos.php + una migración dan tarjeta, listado con indicadores, formulario interno y externo, adjuntos, PDF con FPDF, correo, desactivación y auditoría.
---

# Agregar un tipo de formulario KYC en Centerauto

Producción: https://suilaft.tech/centerauto_suilaft/ · Módulo: `/kyc`.

**No escribas controladores, vistas ni rutas nuevas.** Todo se arma leyendo la entrada del tipo en `app/config/kyc_tipos.php`. Si crees que falta código, lo que falta es una clave de configuración o un tipo de campo (ver §3).

## Mapa del motor

| Pieza | Archivo | Qué lee de la config |
|---|---|---|
| Tarjetas de tipos `/kyc` | `app/views/kyc/tipos.php` | `nombre`, `descripcion`, `icono`, `kyc_code`, conteo de secciones y campos |
| Listado `/kyc/tipo/{codigo}` | `app/views/kyc/listado.php` | `plural`, `kyc_code`, `version`, `nombre_listado`, campos obligatorios (completitud), `identificacion`, `correo` |
| Formulario interno y externo | `app/views/kyc/formulario.php` + `_campos.php` | `secciones`, `tablas`, `notas` (web), `calculos`, `introduccion` |
| Validación | `app/core/Kyc.php::validar()` / `recogerTablas()` | tipo de campo, obligatorio, opciones, catálogos |
| PDF (FPDF) | `app/core/PdfKyc.php` | `titulo`, `kyc_code`, `version`, `fecha_version`, `notas` (pdf), `calculos`, `tablas`, `firma`, `archivo_pdf`, `titulo_anexos` |
| Correo | `app/core/Correo.php::htmlSolicitudKyc()` | `destinatario`, `titulo`, `kyc_code` |
| Persistencia | `app/models/KycRegistro.php` | `tabla` |
| Controladores | `KycController` (interno) · `PublicoController` (`/publico/formulario/{token}`) | genéricos |

Almacenamiento: cada tipo tiene su tabla `kyc_<codigo>` con `datos` (JSON) y `tablas` (JSON). **Un campo nuevo no necesita columna SQL.** `kyc_invitaciones` (enlaces de un solo uso, 7 días) y `kyc_adjuntos` (archivos en `storage/kyc/<tipo>/<id>/`) son compartidas.

Referencia viva y completa: la entrada `accionista` (formulario CACCIONISTA-01) en `app/config/kyc_tipos.php`.

## Paso a paso

### 1. Lee el formulario fuente
Extrae: título exacto, código oficial y versión, secciones **con su numeración**, cada campo, cuáles son obligatorios, tablas repetibles (historial laboral, beneficiarios, referencias), fórmulas (totales de activo/pasivo/patrimonio), textos legales (PEP, declaración, autorización), firmas y documentos a adjuntar.

- **No inventes** códigos, textos legales ni fórmulas. Si faltan, pregúntalos.
- Los formularios suelen venir de otra empresa: si el texto legal nombra a otra compañía (p. ej. "DISTRIAUTEC S.A.S.", "Mirasol S. A."), reemplázala por `$RAZON_SOCIAL` (definida arriba en `kyc_tipos.php`) y **avísale al usuario** para que confirme la razón social.
- Ignora los datos de ejemplo escritos en el PDF (nombres, fechas, cargos de prueba).
- Si la numeración del PDF tiene errores (dos secciones "8.", saltos), corrígela solo lo necesario para que los prefijos no choquen y dilo en el resumen.

### 2. Elige el código
Minúsculas con guion: `accionista`, `cliente-pn`, `cliente-pj`, `proveedor`, `empleado`. Se guarda en `kyc_invitaciones.tipo` y `kyc_adjuntos.tipo` (`VARCHAR(40)`): **no lo cambies después** ni renombres claves de campos con expedientes guardados.

### 3. Mapea cada campo
Formato: `'clave' => array('Etiqueta', 'tipo', obligatorio, [opciones])`. El 3.º elemento marca lo que exige el estado **«Completo»** y el formulario externo; el interno deja guardar incompleto (solo exige `nombre_listado` + `identificacion`, o la lista `minimos`).

Tipos (`Kyc::TIPOS_CAMPO`):

| Tipo | Uso |
|---|---|
| `text`, `email`, `telefono`, `numero`, `date`, `textarea` | básicos (textarea ocupa todo el ancho) |
| `dinero` | valores en USD; alimenta `calculos` |
| `si_no` | botones Sí / No (PEP, preguntas cerradas) |
| `identificacion` | Cédula / Pasaporte / RUC |
| `opciones` | lista cerrada en el 4.º elemento; ≤3 opciones = botones, más = selector. Ya existen `$SEXO`, `$ESTADO_CIVIL` |
| `catalogo_paises`, `catalogo_ciudades`, `catalogo_actividades` | buscador sobre catálogos oficiales (tablas `paises`, `ciudad`, `actividad_economica`) |
| `ubicacion_provincias`, `ubicacion_cantones`, `ubicacion_parroquias` | selectores en cascada (tablas `provincias`, `canton`, `parroquia`); un solo juego por formulario |

Claves con significado especial: `nombre` (o las de `nombre_listado`), `identificacion`, `correo`: alimentan el listado, la búsqueda, el avatar y el correo. Úsalas con ese sentido.

**Tipo de campo nuevo** = cuatro cambios: render en `kyc_campo()` (`_campos.php`), validación en `Kyc::validar()`, catálogo en `Catalogo::cargar()` + `Kyc::catalogoDeTipo()` si aplica, y formato en `Kyc::formatoValor()` si se imprime distinto. Avísale al usuario antes.

### 4. Escribe la entrada en `app/config/kyc_tipos.php`

```php
'cliente-pn' => array(
    'nombre' => 'Cliente persona natural', 'plural' => 'Clientes persona natural',
    'descripcion' => 'Datos personales, cónyuge, información económica y PEP.',
    'icono' => 'id',                       // íconos en app/core/icons.php
    'tabla' => 'kyc_cliente_pn',
    'kyc_code' => 'CCLIENTE-01', 'version' => '1.0', 'fecha_version' => '2026-11-12',
    'titulo' => 'Formulario «Conozca a su cliente» · Persona natural',
    'archivo_pdf' => 'CCLIENTE-01-Cliente-PN',
    'destinatario' => 'cliente',
    'introduccion' => 'Texto introductorio del formulario (se muestra arriba).',
    'secciones' => array(
        '1. Identificación' => array(
            'identificacion' => array('Número de cédula', 'text', true),
            'nombre'         => array('Apellidos y nombres', 'text', true),
            'nacionalidad'   => array('Nacionalidad', 'catalogo_paises', true),
            'estado_civil'   => array('Estado civil', 'opciones', true, $ESTADO_CIVIL),
        ),
    ),
    'tablas' => array(
        'historial' => array('titulo' => '4. Historial laboral', 'antes_de' => '6.', 'item' => 'Actividad', 'maximo' => 4,
            'campos' => array('tipo' => array('Tipo de actividad', 'opciones', array('Principal', 'Secundaria 1')),
                              'empresa' => array('Empresa'), 'ciudad' => array('Ciudad', 'ciudad'),
                              'remuneracion' => array('Remuneración', 'dinero'))),
    ),
    'notas' => array('8. Declaración PEP' => array(array('estilo' => 'legal', 'web' => '…', 'pdf' => '…'))),
    'calculos' => array('7. Patrimonio' => array(
        array('etiqueta' => 'Total activo (A)', 'suma' => array('efectivo', 'inversiones'), 'resta' => array()))),
    'firma' => array('seccion' => '9. Declaración', 'etiqueta' => array('Firma del cliente', 'Oficial de Cumplimiento')),
    'adjuntos' => array('formulario_firmado', 'cedula', 'otro'),
),
```

Claves y reglas:
- `tablas`: columnas `'clave' => array('Etiqueta', tipo, [opciones])`; tipos `text`, `porcentaje`, `pais`, `ciudad`, `dinero`, `email`, `date`, `opciones`, `identificacion`. `antes_de` = prefijo de la sección delante de la cual va; sin él, al final. Se guardan en JSON `{clave: [filas]}`.
- `notas`: `estilo` `hint` (ayuda, va arriba) o `legal` (texto legal, va al final de la sección); `web` y/o `pdf`. La clave es el **prefijo** de la sección (`'8.'`) o su nombre completo si dos secciones comparten prefijo.
- `calculos`: `suma`/`resta` con claves de campos `dinero`/`numero`; se recalculan en vivo y salen en el PDF.
- `firma.etiqueta`: texto o arreglo (varias firmas en paralelo, p. ej. titular y Oficial de Cumplimiento).
- `adjuntos`: categorías de `Kyc::CATEGORIAS_ADJUNTO` que se ofrecen en «Documentos adjuntos» (se ofrecen en «Documentos adjuntos» interno y en la etapa 3 del enlace externo: con el mismo enlace el titular 1) llena el formulario una sola vez, 2) descarga el PDF para imprimirlo y firmarlo, 3) sube el formulario firmado y sus documentos hasta que el enlace vence). Para una categoría nueva, agrégala en `CATEGORIAS_ADJUNTO`.
- `minimos`: (opcional) claves obligatorias para poder guardar en el formulario interno.
- **Campos condicionales** (formularios unificados, p. ej. cliente persona natural o jurídica): 5.º elemento del campo = condición `array('tipo_cliente' => array('Persona jurídica'))`; para campos con 4.º elemento vacío usa `array()`. Incluye `''` en la lista para que se muestre también mientras el campo de control está vacío (y en expedientes antiguos). Un campo que no aplica se oculta en pantalla, no se exige para «Completo» ni en el enlace externo, no sale en el PDF ni en los datos de la matriz.
  - Tablas: clave `'si' => condición`. Secciones completas: `'secciones_si' => array('2.' => condición)`.
  - Referencia viva: `cliente-pn` (variables `$SI_PN` y `$SI_PJ` en `kyc_tipos.php`).

### 5. Migración `database/NN_kyc_<codigo>.sql`
Siguiente número: `ls database/ | sort -V | tail -3`.

```sql
CREATE TABLE IF NOT EXISTS kyc_cliente_pn (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  datos LONGTEXT NOT NULL,
  tablas LONGTEXT NOT NULL,
  estado VARCHAR(12) NOT NULL DEFAULT 'activo',
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6. Prueba local (sin base de datos)
- `php -l app/config/kyc_tipos.php` y `php -r` que haga `require` de la config y recorra `Kyc::campos()`, `Kyc::tablas()` y `Kyc::calculos()`.
- PDF por CLI: `new PdfKyc(); ->generar($tipo, $datos_de_prueba, $tablas, [])` y `Output('F', ...)`; revisa visualmente las páginas.
- Vista previa del formulario con `php -S` y un router en el scratchpad que haga `require app/views/kyc/formulario.php` con datos ficticios; captura con Chrome headless a 1920 px y 600 px.

### 7. Despliega (skill `despliegue-ftp-produccion`)
Orden: **primero la migración** (script temporal con token, borrarlo enseguida), **después** `app/config/kyc_tipos.php`. Si la config sube antes que la tabla, la tarjeta aparece y el listado responde 503. El usuario pidió subir por FTP siempre al terminar.

### 8. Verifica en producción (datos `PRUEBA - …`, luego desactívalos)
- [ ] La tarjeta aparece en `/kyc` con código, secciones y campos.
- [ ] Alta con datos mínimos → queda «Incompleto · n/m» con lo que falta.
- [ ] Edición: los valores persisten; los cálculos coinciden en pantalla y en el PDF.
- [ ] PDF (`/kyc/imprimir/...`): título, código, secciones, tablas, notas legales y firmas.
- [ ] Enlace externo: abre sin sesión, exige obligatorios y guarda una sola vez; luego el mismo enlace muestra «Descargar PDF» y la subida de documentos por categoría.
- [ ] Adjuntos: sube un PDF y lo descarga.
- [ ] `/auditoria` (bitácora `auditoria`) registra crear, editar, imprimir, enlace, adjunto.
- [ ] Correo: solo si `app/config/correo.php` existe; si no, el listado lo avisa. **No envíes correos reales a terceros.**

## Fuera de alcance
Matriz de riesgo del tipo → `matriz-nuevo-tipo` · Detección PEP/listas → `alertas-nuevo-detector` · Bitácora → `auditoria-instrumentar` · RESU → `reporteria-resu-adaptar`.
