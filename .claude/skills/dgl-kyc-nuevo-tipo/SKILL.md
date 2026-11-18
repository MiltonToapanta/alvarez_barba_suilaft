---
name: dgl-kyc-nuevo-tipo
description: Agrega un nuevo tipo de sujeto KYC a SUILAFT DGL (ej. "Cliente persona natural", "Cliente persona jurídica", "Proveedor", "Empleado", "Comprador de inmueble") a partir de un formulario fuente (PDF, Word, imagen o descripción). Úsala siempre que el usuario entregue un formulario de conocimiento nuevo, pida "crear un tipo de sujeto/persona nuevo", "agregar el formulario de X", o describa campos, secciones o adjuntos de un sujeto que DGL todavía no tiene, aunque no use la palabra KYC. El motor es config-driven: una entrada en config/kyc_tipos.php + una migración dan listado, formulario interno y externo, adjuntos, PDF, correo y desactivación.
---

# Agregar un nuevo tipo de sujeto KYC en DGL

## Cómo funciona (estado al 5-nov-2026)

Todo lee la entrada del tipo en `config/kyc_tipos.php`. **No escribas vistas, controladores ni rutas nuevas**: si crees que hace falta, lo que falta es una clave de configuración.

| Pieza | Archivo | Qué lee de la config |
|---|---|---|
| Selector de tipos | `views/kyc_types.php` | `nombre`, `descripcion`, `icono`, `kyc_code`, `secciones` |
| Listado y formulario (interno y externo) | `kyc.php`, `publico.php`, `views/kyc.php` | todo; notas, cálculos y beneficiarios vía `Kyc::notes()`, `Kyc::calculations()`, `Kyc::beneficiaryConfig()` |
| Validación y guardado | `models/Kyc.php` (`fields`, `beneficiaries`, `save`) | `secciones` (tipo de campo y obligatorio) |
| Adjuntos | `models/AdjuntoKyc.php::categorias($type)`, `views/kyc_attachments.php`, `publico.php` | `adjuntos_categorias` |
| PDF | `models/PdfKyc.php` | `titulo`, `kyc_code`, `version`, `fecha_version`, `notas[*].pdf`, `calculos`, `beneficiarios`, `firma`, `titulo_anexos`, `adjuntos` |
| Correo | `models/EmailKyc.php::html($nombre, $url, $type)` | `destinatario`, `kyc_code` |
| Nombre del PDF | `kyc.php` | `archivo_pdf` |

Almacenamiento: cada tipo tiene su tabla `kyc_<codigo>` con `datos` (JSON en `LONGTEXT`) y `beneficiarios` (JSON). Un campo nuevo **no** necesita columna SQL. `kyc_invitaciones` y `kyc_adjuntos` son compartidas por todos los tipos.

Referencia completa y comentada: la entrada `socio-accionista` de `config/kyc_tipos.php`.

## Paso a paso

### 1. Lee el documento fuente

Extrae: nombre, título exacto del formulario, código oficial (`DGL-FOR-0XX`), versión y fecha, secciones **tal como aparecen** (con su numeración romana), campos, obligatorios, beneficiarios finales sí o no, fórmulas (ingresos netos, patrimonio), textos legales (PEP, autorización, declaración), firma y anexos. **No inventes** el código, los textos legales ni las fórmulas: si faltan, pídelos.

### 2. Elige el código

Minúsculas con guion: `cliente-pn`, `cliente-pj`, `proveedor`, `empleado`. Compruébalo con `grep -n "=> *array(" config/kyc_tipos.php`. El código se guarda en `kyc_invitaciones.tipo` y `kyc_adjuntos.tipo` (`VARCHAR(40)`): **no lo cambies después**.

### 3. Mapea cada campo

Formato: `'clave' => array('Etiqueta', 'tipo', true_si_obligatorio)`. Tipos soportados (render en `views/kyc.php::kyc_input_field`, validación en `Kyc::fields`):

`text`, `email`, `date`, `si_no` (`''|Sí|No`), `identificacion` (`''|Cédula|Pasaporte`), `opciones`, `dinero`, `textarea`, `catalogo_ciudades`, `catalogo_paises`, `catalogo_actividades`, `ubicacion_provincias`, `ubicacion_cantones`, `ubicacion_parroquias`.

- **`opciones`** es una lista cerrada propia: `array('Estado civil','opciones',false,array('Soltero/a','Casado/a',...))`. Con 3 opciones o menos se muestra como botones segmentados y con más, como selector con búsqueda. El servidor valida que el valor esté en la lista. Úsala para sexo, estado civil, tipos de identificación distintos de Cédula/Pasaporte (Cédula/RUC, Cédula de identidad/ciudadanía/Pasaporte), etc. En `config/kyc_tipos.php` ya existen `$ESTADO_CIVIL` y `$SEXO`.

- Las claves de ubicación deben llamarse **`provincia`, `canton` y `parroquia`**: el JS dependiente busca esos `id`. Solo puede haber un juego de ubicación por formulario.
- Las etiquetas de más de 70 caracteres y los `textarea` ocupan el ancho completo automáticamente.
- Las claves `nombre`, `identificacion`, `correo` y `ciudad` alimentan el listado (columnas, avatar y búsqueda) y el correo. Úsalas con ese significado.
- **Un tipo de campo nuevo** (RUC con validación, otro catálogo como `cat_origen_fondos`, `cat_forma_pago`, `cat_proyectos`, `moneda` o `tipo_bien`, o una selección cerrada distinta) requiere cuatro cambios: render en `kyc_input_field`, validación en `Kyc::fields`, carga en `Catalog::options` si es catálogo, e impresión en `PdfKyc::renderRecord` si se formatea distinto. Dilo al usuario antes de hacerlo.
- No reutilices ni renombres claves de un tipo con expedientes guardados.

### 4. Escribe la entrada

```php
'cliente-pn' => array(
 'nombre'=>'Cliente persona natural', 'tabla'=>'kyc_cliente_pn', 'kyc_code'=>'DGL-FOR-00X',
 'plural'=>'Clientes persona natural',
 'titulo'=>'Conozca a su cliente persona natural',          // cabecera del PDF y del formulario externo
 'descripcion'=>'Identificación, PEP, actividad e información económica.',
 'icono'=>'bi-person',                                         // Bootstrap Icons 1.11
 'version'=>'1.0', 'fecha_version'=>'dd/mm/aaaa',
 'destinatario'=>'cliente',                                    // "Estimado/a cliente" en el correo
 'archivo_pdf'=>'DGL-Cliente-PN',                              // solo [A-Za-z0-9_-]
 'titulo_anexos'=>'X. Anexos',
 'adjuntos_categorias'=>array('formulario_firmado','identidad','otro'), // claves de AdjuntoKyc::tipos()
 'beneficiarios'=>null,  // o array('titulo'=>'III. Socios...','antes_de'=>'IV.','item'=>'Socio o accionista','descripcion'=>'...',
                         //          'campos'=>array('nombre'=>'Nombre','porcentaje'=>'Participación (%)','tipo_identificacion'=>'Tipo de identificación','identificacion'=>'No. de identificación','nacionalidad'=>'Nacionalidad'))
 'calculos'=>array('VII.'=>array(array('id'=>'neto','etiqueta'=>'Ingresos netos (ingresos - gastos)','suma'=>array('salario'),'resta'=>array('gastos')))),
 'firma'=>array('seccion'=>'VIII.','etiqueta'=>'Firma del cliente','nota'=>'La firma debe obtenerse por separado.'),
 'notas'=>array('IV.'=>array(array('estilo'=>'hint','web'=>'Texto corto en pantalla.','pdf'=>'Texto legal completo en el PDF.'))),
 'secciones'=>array(
  'I. Información general'=>array('nombre'=>array('Nombres completos','text',true),'identificacion'=>array('No. de identificación','text',true) /* ... */),
 ),
 'adjuntos'=>array('Copia de cédula.' /* ... */),
),
```

- `tabla` cumple `^kyc_[a-z0-9_]+$` (lo exige `Kyc::table()`).
- Las claves de `notas`, `calculos`, `beneficiarios.antes_de` y `firma.seccion` son el **título exacto** de una sección o un **prefijo** (`'IV.'`). Si no coinciden con ninguna sección, no se muestran y no se produce ningún error: compruébalo.
- Notas: `estilo` puede ser `hint` (recuadro informativo) o `legal` (texto legal). `web` y `pdf` son independientes; omite el que no aplique.
- Cálculos: `suma` y `resta` son claves de campos `dinero`. El mismo cálculo se usa en pantalla (JS), en `Kyc::calculate` y en el PDF.
- **Varias tablas repetibles** (referencias bancarias y comerciales, accionistas, beneficiarios finales, actividad adicional…): usa `'tablas'=>array('clave'=>array('titulo','antes_de','item','descripcion','campos'))`. Cada columna es `'clave'=>array('Etiqueta','tipo',[opciones])` con tipo `text` (por defecto), `porcentaje`, `pais`, `dinero`, `email` u `opciones`. Se guardan como objeto JSON `{clave:[filas]}` en la columna `beneficiarios` y llegan por POST como `tablas[clave][i][campo]`. Las filas nuevas se clonan de un `<template>` que genera el servidor. Varias tablas pueden compartir `antes_de` y salen en el orden de la config; sin `antes_de`, van antes de los anexos. Para referencias bancarias y comerciales hay helpers: `$refBancarias(titulo, antes_de)` y `$refComerciales(...)`. **No mezcles** `tablas` y `beneficiarios` en un mismo tipo.
- `beneficiarios` (formato antiguo, una sola tabla guardada como lista) es la **tabla repetible** del formulario (beneficiarios finales, socios desde el 10 %, etc.). `item` es el nombre de cada fila y `campos` sus columnas (por defecto, las de socio o accionista). Hay tres claves de columna con control especial: `porcentaje` (0–100), `nacionalidad` (sugerencias de países) y `tipo_identificacion` (selector Cédula/Pasaporte, validado en el servidor). Si se omite `beneficiarios`, el tipo no tiene tabla repetible y guarda `[]`.
- `firma.etiqueta` puede ser un arreglo para varias firmas en paralelo, por ejemplo `array('Firma del cliente y sello','Nombre y firma de quien recepta la información')`.
- `nombre_listado` (por ejemplo `array('nombre','apellidos')`) arma el nombre visible del listado, los adjuntos y el correo cuando el formulario separa nombres y apellidos.
- Las secciones pueden numerarse con romanos (`IV.`) o arábigos (`4.`). Los prefijos de `notas`, `calculos`, `antes_de` y `firma` usan el mismo formato.
- Para los textos legales comunes (PEP, cláusula de datos, autorización, declaración) usa `$notasComunes('VI.','VII.','VIII.','DGL-FOR-0XX')`, que ya trae los textos oficiales.
- Categorías de adjunto disponibles: `formulario_firmado`, `ruc`, `nombramiento`, `identidad` (del representante legal), `cedula`, `certificado_uafe`, `lista_accionistas`, `otro`.
- Una sección solo con firma puede tener `array()` como campos. Ponle una nota `web` para que no quede vacía en pantalla.
- Los IDs `#sec-N` del formulario cuentan también la tabla repetible y la sección de anexos. En las pruebas, busca las secciones por su título `h2` y no por el número.
- Para una categoría de adjunto nueva, añádela en `AdjuntoKyc::tipos()`: es el catálogo global de etiquetas.

### 5. Migración

Archivo `sql/NN_kyc_<codigo>.sql` (`ls sql/ | sort -V | tail -3`; al 6-nov-2026 el siguiente es `16`, tras `15_debida_diligencia.sql`):

```sql
CREATE TABLE IF NOT EXISTS kyc_cliente_pn (
 id INT NOT NULL AUTO_INCREMENT,
 datos LONGTEXT NOT NULL,
 beneficiarios LONGTEXT NOT NULL,
 estado VARCHAR(12) NOT NULL DEFAULT 'activo',
 creado_por INT NOT NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6. Prueba local sin base de datos

Usa un router de vista previa en el scratchpad (`php -S` con `-t` en la raíz del proyecto) que defina `$_SESSION['user']`, `e()`, cargue `Kyc`/`AdjuntoKyc` y la config, y haga `require views/kyc.php` con `$editing`, `$public`, `$data` y `$catalogs` ficticios. Renderiza el PDF por CLI con `PdfKyc::renderRecord` y extrae el texto con `pdftotext`. Toma capturas con Playwright a 1440 px y a 390 px y comprueba que `scrollWidth` sea igual al ancho de la ventana.

### 7. Despliega

Con la skill **`despliegue-ftp-produccion`**: primero la migración y después `config/kyc_tipos.php`. Si la config sube antes que la tabla, la tarjeta aparece y el listado responde 503.

### 8. Verifica (con datos ficticios `PRUEBA - ...`)

- [ ] `php -l` en los PHP tocados.
- [ ] El tipo nuevo aparece en `kyc.php` con su icono, código y número de secciones.
- [ ] Alta, edición y nueva apertura: los valores persisten; los cálculos coinciden en pantalla y en el PDF.
- [ ] Conflicto de edición en dos pestañas: muestra "El registro cambió mientras lo editaba".
- [ ] Enlace externo de alta y de edición: un solo envío; solo se ofrecen las categorías de adjunto del tipo.
- [ ] PDF con expediente vacío y con datos: título, código, notas, firma y anexos del tipo.
- [ ] Correo: solo composición (`EmailKyc::html` y `Mailer::compose`). **Ningún envío real.**
- [ ] Socio o accionista sigue idéntico.

## Fuera de alcance (otras skills)

Matriz de riesgo → `matriz-nuevo-tipo`. Detección PEP/listas → `alertas-nuevo-detector` (añadir los campos PEP del tipo nuevo a `$camposPep`). Bitácora → `auditoria-instrumentar`. RESU → `reporteria-resu-adaptar`. Diseño → `diseno-dgl`.
