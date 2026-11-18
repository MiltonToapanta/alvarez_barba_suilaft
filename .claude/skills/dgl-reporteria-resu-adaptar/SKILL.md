---
name: dgl-reporteria-resu-adaptar
description: Construye el módulo "Generación RESU" de SUILAFT DGL — el Reporte de operaciones que igualan o superan el umbral para la UAFE en el sector inmobiliario/constructor — tomando como base el generador RESU de SUIAUTO (vehicular) y el manual oficial de la UAFE del sector. Úsala cuando el usuario entregue el manual o las plantillas RESU (Excel/XML/XSD) del sector inmobiliario/construcción, pida generar o adaptar el RESU, o pida exportar operaciones a la UAFE.
---

> **Para el día a día y para replicar, usa `resu-sector-construccion`** (estado real del módulo, reglas, errores típicos del SISLAFT y cómo cambiar columnas). Esta skill queda como historia del diseño original adaptado de SUIAUTO.


# RESU inmobiliario en DGL

## Estado y dependencias

**Existe desde el 6-nov-2026** (`resu.php`, `models/Resu.php`, `models/ResuValidador.php`, `models/Xlsx.php`): genera las plantillas UAFI CLI/OPR/TRA y las valida. Para cambiar columnas o reglas, edita `ResuValidador::COLS` y `Resu::armar()` juntos y vuelve a validar con las plantillas de `resu/`. Lo siguiente describe el diseño original. El RESU necesita **operaciones** (ventas o promesas de inmuebles, pagos y transacciones) que DGL todavía no registra. Orden obligatorio:

1. Tipos KYC de los sujetos que reporta el manual, por ejemplo cliente persona natural y jurídica (`kyc-nuevo-tipo`).
2. Módulo **Registro de pagos** (tarjeta `pagos`, "Origen lícito PN / PJ"): operación (proyecto de `cat_proyectos`, inmueble, valor y fecha) y sus transacciones (`cat_forma_pago`, `cat_origen_fondos`, `moneda`, `tipo_transaccion`).
3. Recién entonces, el RESU.

No inventes campos regulatorios. **Sin el manual oficial del sector no se escribe código**: pídeselo al usuario (estructuras de CABECERA y DETALLE, plantillas Excel, XSD y catálogo oficial).

## Qué se reutiliza de SUIAUTO (`~/Downloads/suiaut`)

- `app/core/ResuExcel.php` y `app/core/ResuXml.php`: generadores genéricos de Excel y XML. Adáptalos a PHP 7.4 sin dependencias nuevas y a la estructura de DGL (`models/`).
- El flujo de "generar → historial → descargar" de `ReporteriaController` y las funciones de normalización (`resu_texto()`, `resu_entero()`: mayúsculas, sin tildes y sin caracteres especiales).
- La lógica de umbral y estados `RESU_APLICA` / `NO_ALCANZA` / `INCOMPLETA`.

## Qué se escribe nuevo para DGL

- `config/resu_columnas.php`: encabezados **literales y en el mismo orden** que las plantillas oficiales del sector inmobiliario. Una columna de más, de menos o fuera de orden hace que el portal UAFE rechace el archivo.
- `models/Resu.php`: `filaCabecera()`, `filaCliente()`, `filaOperacion()` (proyecto, inmueble, valor, forma de pago) y `filaTransaccion()`. Cada valor faltante se añade a `$faltantes` y el sujeto queda `INCOMPLETA`: nunca se envía vacío en silencio. Los datos del sujeto salen del JSON `datos` de su tabla `kyc_*` (claves de `config/kyc_tipos.php`).
- Catálogos oficiales del sector: los que ya existen (`tipo_bien`, `tipo_transaccion`, `cat_forma_pago`, `moneda`) se **contrastan** con el catálogo del manual. Si no coinciden, se crea una migración nueva; nunca se pisan los existentes sin respaldo.
- `resu.php` + `views/resu.php` + historial (tabla `resu_generaciones`). Acceso: `oficial de cumplimiento` y `super administrador`. Auditoría `generar_reporte`, módulo `resu`.

## Verificación (lo más importante)

- [ ] Abre cada archivo generado y cuenta las columnas contra la plantilla oficial, en el mismo orden.
- [ ] Los XML validan contra los XSD **del sector inmobiliario** (`xmllint --schema`).
- [ ] Prueba un sujeto que supera el umbral, uno que no lo alcanza y uno con datos faltantes.
- [ ] Textos en mayúsculas y sin tildes; identificaciones sin caracteres especiales.
- [ ] Los totales de la cabecera cuadran con la suma del detalle.
- [ ] Usa solo datos `PRUEBA` en las pruebas. Nunca subas un RESU de prueba al portal UAFE.
