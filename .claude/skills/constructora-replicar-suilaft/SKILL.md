---
name: constructora-replicar-suilaft
description: Replica SUILAFT DGL para OTRA constructora o inmobiliaria (sujeto obligado del sector de inversión e intermediación inmobiliaria y construcción ante la UAFE). Guía de punta a punta para clonar el proyecto, cambiar la marca con un script, crear la base, cargar catálogos, aplicar las migraciones 02–15 en orden, configurar el RESU, crear el primer usuario y verificar en producción. Úsala cuando el usuario diga «replicar el SUILAFT para otra constructora», «nuevo cliente constructor», «clonar DGL para X», «montar otro SUILAFT de construcción», o pida adaptar este sistema a otra empresa del sector inmobiliario o de la construcción.
---

# Replicar SUILAFT para otra constructora

SUILAFT DGL ya resuelve lo que es propio del **sector construcción/inmobiliario** (RESU con plantillas UAFI CLI/OPR/TRA, registro de pagos por proyecto → operación → transacción, declaración de origen lícito, catálogos UAFE del sector). Para otra constructora casi todo se reutiliza; solo cambia lo **propio de la empresa**.

| Se reutiliza tal cual (sector) | Cambia por empresa |
|---|---|
| Motor RESU + validador SISLAFT (`resu-sector-construccion`) | Marca: nombre, razón social, RUC, logo, URL |
| Registro de pagos y licitud (`pagos-sector-construccion`) | Código de registro UAFE (CDR) y oficina → en pantalla, Generación RESU |
| Catálogos UAFE corregidos (`catalogos-uafe-construccion`) | Proyectos de la empresa → en pantalla, Catálogos › Proyectos |
| Alertas, auditoría, usuarios/roles, debida diligencia | Formularios KYC si la empresa tiene los suyos (`kyc-nuevo-tipo`) |
| Matriz institucional y matrices individuales (modelo) | Códigos de documento (DGL-FOR-…, DGL-MRI-…) y, si los hay, sus propios factores y pesos |

## Antes de empezar: pide al usuario

1. **Datos de la empresa**: nombre corto, razón social, RUC, sigla (2–8 letras: se usa en códigos y nombres de PDF) y logo PNG.
2. **Hosting**: carpeta/URL de producción (p. ej. `suilaft.tech/xyz_suilaft`), credenciales FTP y de base de datos. **Nunca** las escribas en archivos, skills ni en el chat: van en `.vscode/sftp.json` y `config/database.php` (fuera de Git).
3. **UAFE**: código de registro (CDR, 4–5 dígitos) y código de oficina (4 dígitos). Sin ellos el RESU no se puede generar (la pantalla lo bloquea).
4. **Formularios propios** (KYC, matrices): si la empresa usa los mismos de DGL, se reutilizan cambiando solo el código del documento; si tiene otros, se cargan con `kyc-nuevo-tipo` y `matriz-nuevo-tipo` DESPUÉS de replicar.

## Paso 1 · Copia limpia del proyecto

Copia la carpeta del proyecto a una nueva (p. ej. `~/Downloads/xyz_suilaft`). **No copies** `.vscode/` (credenciales de DGL) ni `config/database.php` / `config/mail.php` con los datos de DGL: créalos de nuevo para la empresa. `vendor/` sí se copia (FPDF).

## Paso 2 · Cambio de marca (script)

```bash
cd ~/Downloads/xyz_suilaft
cat > /tmp/empresa.json <<'EOF'
{"nombre_corto":"XYZ Constructora","razon_social":"Constructora XYZ S.A.","ruc":"1790000000001",
 "sigla":"XYZ","sigla_puntos":"X.Y.Z.","logo":"logo_xyz_web.png","url_base":"suilaft.tech/xyz_suilaft"}
EOF
python3 -I .claude/skills/constructora-replicar-suilaft/scripts/rebrand.py /tmp/empresa.json            # simulación
python3 -I .claude/skills/constructora-replicar-suilaft/scripts/rebrand.py /tmp/empresa.json --aplicar  # aplica
```

- El script reemplaza razón social, nombre, RUC de los textos legales (tratamiento de datos), «SUILAFT DGL», `D.G.L.`, logo, URL y el prefijo de códigos `DGL-` (DGL-FOR-002 → XYZ-FOR-002, nombres de PDF). En DGL hace ~118 reemplazos en ~45 archivos.
- Revisa a mano las líneas «DGL» que lista al final (comentarios y textos como «¿El escenario aplica a la actividad real de DGL?»).
- Copia el logo nuevo a la raíz con el nombre indicado.
- `php -l` en todo: `find . -name '*.php' -not -path './vendor/*' -exec php -l {} \; | grep -v '^No syntax'`.
- Los **textos legales** de `config/kyc_tipos.php` (`$DATOS_CLAUSULA`, `$DATOS_AUTORIZACION`, `$DECLARACION`) quedan con la razón social y el RUC nuevos, pero **confírmalos con el área legal de la empresa** antes de usarlos.

## Paso 3 · Base de datos

1. Crea la base vacía en el hosting (phpMyAdmin) y su usuario.
2. **Catálogos** (no están en `sql/`): exporta de la base de DGL, ya corregidos, solo estas tablas con datos: `actividad_economica`, `paises`, `provincias`, `canton`, `ciudad`, `parroquia`, `moneda`, `tipo_bien`, `tipo_transaccion`, `cat_forma_pago`, `cat_origen_fondos`; y `cat_proyectos` **solo con estructura** (vacía: la empresa registra los suyos). Detalle y verificación: skill `catalogos-uafe-construccion`.
3. Tabla `usuarios`: `sql/crear_usuarios.sql` (estructura). El primer usuario se crea en el Paso 5.
4. Migraciones en este orden, una por una, con el migrador temporal de `despliegue-ftp-produccion` (token, ejecutar, **borrar**, comprobar 404):

| # | Archivo | Crea |
|---|---|---|
| 02 | `02_kyc_socio_accionista.sql` | expedientes socio/accionista, invitaciones |
| 03–04 | `03_kyc_operaciones.sql`, `04_kyc_categoria_adjuntos.sql` | adjuntos KYC (03 y 04 **no** son idempotentes: solo en base nueva) |
| 05–06 | `05_kyc_proveedor.sql`, `06_kyc_clientes_empleado.sql` | proveedor, cliente PN/PJ, empleado |
| 07–08 | `07_matriz_riesgo.sql`, `08_matriz_institucional.sql` | matrices |
| 09–10 | `09_pagos_operaciones.sql`, `10_pagos_transacciones.sql` | registro de pagos |
| 11–12 | `11_resu.sql`, `12_resu_aplica.sql` | RESU (configuración, historial, «RESU aplica») |
| 13 | `13_catalogo_cantones_rpt.sql` | correcciones de catálogo (si los catálogos vienen de DGL ya corregidos, no cambia nada: es idempotente) |
| 14 | `14_usuarios_auditoria_alertas.sql` | roles, auditoría, alertas |
| 15 | `15_debida_diligencia.sql` | debida diligencia y permisos por usuario |

Partir las sentencias: quita los comentarios `--` **antes** de dividir por `;` (los comentarios contienen `;` y rompen el migrador).

## Paso 4 · Subir el código

Con `despliegue-ftp-produccion`: modelos → vistas → puntos de entrada → `config/modulos.php` al final. Nunca subas `.vscode/`, `sql/`, `.claude/`, `resu/` (plantillas de referencia) ni archivos de prueba.

## Paso 5 · Primer usuario y configuración inicial

1. Super Administrador: inserta UNA fila en `usuarios` con `rol = 'Super Administrador'`, `estado = 'A'` y `clave = password_hash(...)` (genera el hash con `php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' 'CLAVE'` y entrégale la clave al usuario por un canal seguro; no la escribas en ningún archivo).
2. Entra y, en pantalla:
   - **Usuarios y roles**: crea los usuarios reales (módulos y acciones por persona).
   - **Generación RESU › Configuración**: CDR, identificación, umbral (10.000) y oficina.
   - **Catálogos › Proyectos**: proyectos de la empresa (nombre y dirección del bien).
   - **Matriz institucional**: nuevo borrador y calificación real (la v1 de DGL es de DGL).

## Paso 6 · QA en producción (obligatoria)

Prueba con datos ficticios marcados `PRUEBA`, nunca reales, y desactiva después lo creado:
- [ ] Login correcto, fallido y logout quedan en Auditoría.
- [ ] Un KYC de cada tipo; PDF con la marca nueva (logo, razón social, códigos XYZ-FOR-…).
- [ ] Proyecto → operación → declaración de origen lícito → listados → PDF del lícito.
- [ ] Generación RESU de un mes con datos completos: 0 errores, descarga ZIP con CLI/OPR/TRA; y un mes con datos incompletos: descarga bloqueada con los enlaces «Corregir en».
- [ ] Validador SISLAFT con los 3 archivos generados: «Sin errores».
- [ ] Alertas: «Actualizar alertas» dos veces → la segunda, 0 nuevas.
- [ ] Usuario con «Solo ver»: aviso de solo lectura y 403 al intentar guardar.
- [ ] Ninguna página con `Warning`/`Fatal`; correos solo compuestos (no enviar a terceros).

## Skills relacionadas

`resu-sector-construccion` · `pagos-sector-construccion` · `catalogos-uafe-construccion` · `despliegue-ftp-produccion` · `kyc-nuevo-tipo` · `matriz-nuevo-tipo` · `institucional-nuevo-factor` · `usuarios-nuevo-rol` · `alertas-nuevo-detector` · `auditoria-instrumentar` · `diseno-dgl`.
