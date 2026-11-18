---
name: despliegue-ftp-produccion
description: Despliega código y corre migraciones de base de datos contra la producción de SUIAUTO (Hostinger, hosting compartido, sin SSH ni panel de migraciones). Úsala cada vez que haya que subir archivos PHP/JS/CSS modificados, crear o alterar una tabla en la base de datos real, o verificar que un despliegue no rompió nada — incluyendo como el paso final de cualquier otra skill de este proyecto (kyc-nuevo-tipo, matriz-nuevo-tipo, alertas-nuevo-detector, ia-nueva-herramienta, auditoria-instrumentar), que delegan aquí su sección de "Despliega". Este proyecto NO tiene acceso SSH ni conexión directa a MySQL desde la máquina de desarrollo: todo pasa por FTP y por scripts PHP temporales que se ejecutan una vez en el propio servidor.
---

# Desplegar y migrar contra producción por FTP

SUIAUTO corre en Hostinger (hosting compartido). No hay SSH, no hay panel de migraciones, no hay conexión MySQL directa desde tu máquina — `DB_HOST` en `app/config/config.php` es `localhost`, lo que significa "localhost del servidor de Hostinger", no el tuyo. Esto define cómo se hace *todo* en este proyecto:

- **Código** → sube por FTP.
- **Esquema de base de datos** (crear/alterar tablas) → se ejecuta subiendo un script PHP temporal al servidor y visitándolo una vez; el script usa la `Database`/config.php real del servidor para conectarse, porque solo el servidor puede alcanzar su propia base.
- **QA** → en producción, porque no existe un entorno de staging ni una base de datos local equivalente.

Si te encuentras buscando cómo conectarte a la base de datos desde tu terminal local, o un comando `ssh`, detente — no existe esa vía en este proyecto.

## 1. Dónde viven las credenciales FTP

`.vscode/sftp.json`, en la raíz del repo. Está en `.gitignore` (nunca se commitea) — ábrelo con el Read tool cuando necesites las credenciales, pero **nunca las copies dentro de un archivo que vaya a git** (ni siquiera dentro de otra skill o de un script que planees commitear). Si no está disponible o no tiene la contraseña, pídesela al usuario — no la adivines ni la reconstruyas de memoria de una sesión anterior.

```json
{
  "host": "...",
  "protocol": "ftp",
  "port": 21,
  "username": "...",
  "password": "...",
  "remotePath": "/"
}
```

`remotePath: "/"` significa que la ruta remota espeja la ruta del repo: `app/controllers/Foo.php` local sube a `/app/controllers/Foo.php` remoto.

⚠️ **`uploadOnSave: true`**: si el usuario tiene VSCode abierto con la extensión SFTP activa, cada vez que *él* guarda un archivo se sube solo. Esto es independiente de que tú subas por `curl` — pero si alguna vez le pides al usuario que guarde archivos para que se suban (en vez de subirlos tú con `curl`), recuerda el orden del punto 3.

## 2. Subir archivos de código (patrón de `curl`)

```bash
FTP_HOST="<host de sftp.json>"
FTP_USER="<username de sftp.json>"
FTP_PASS='<password de sftp.json>'   # comillas simples: evita que el shell interprete caracteres especiales

for f in "app/controllers/Foo.php" "app/models/Bar.php"; do
  curl -s --ftp-create-dirs -T "$f" "ftp://$FTP_HOST/$f" --user "$FTP_USER:$FTP_PASS" && echo "OK: $f" || echo "FALLÓ: $f"
done
```

`--ftp-create-dirs` crea las carpetas remotas que falten. Sube **solo los archivos que cambiaron** en esta tarea — no subas el repo completo "por si acaso".

## 3. La regla de oro: `index.php` siempre al final

`index.php` es el front controller y hace `require_once` de casi todos los `app/core/*.php`. Si subes un `index.php` que ya requiere un archivo nuevo (ej. `PdfGarante.php`) **antes** de que ese archivo nuevo exista en el servidor, el sitio completo queda con un error fatal hasta que subas el resto — esto ya pasó en este proyecto y es la razón de esta regla.

**Orden correcto**: todo lo demás primero (modelos, controladores, vistas, `app/core/*` nuevos), `index.php` al final, solo si de verdad lo tocaste. La mayoría de las tareas de este proyecto no tocan `index.php` en absoluto.

## 4. Crear o alterar tablas (migraciones)

No hay otra forma de correr SQL contra la base real que no sea desde el propio servidor. El patrón establecido:

1. Escribe la migración en `database/NN_descripcion.sql` (siguiente número — revisa `ls database/ | sort -V | tail -3`), con `CREATE TABLE IF NOT EXISTS` / `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` para que sea **idempotente**: si por algo se corre dos veces, no debe fallar ni duplicar nada.
2. Copia `references/plantilla-migrador.php`, reemplaza `TOKEN_SECRETO` por un token largo y aleatorio (ej. `bin2hex(random_bytes(16))` calculado una vez, pegado como string literal) y `ARCHIVO_SQL` por el nombre de tu migración.
3. Sube ese script temporal por FTP a una ruta que NO esté bajo `app/` (ej. `migrar_19.php` en la raíz, junto a `index.php`) — nunca lo dejes como parte del código versionado del proyecto.
4. Visítalo una vez con el token correcto: `curl -s "https://suilaft.tech/suiaut/migrar_19.php?token=<tu-token>"` (o pide al usuario que lo abra en el navegador si prefieres que él dispare el cambio). Lee la respuesta — debe confirmar qué se ejecutó, no solo un 200 vacío.
5. **Borra el script del servidor inmediatamente** (`curl -Q "-DELE migrar_19.php" ftp://...` o pide al usuario que lo borre por su cliente FTP habitual) — dejarlo ahí es un script que ejecuta SQL arbitrario contra producción protegido solo por un token en la URL; no debe sobrevivir ni un minuto más de lo necesario.
6. Confirma que el archivo `.sql` de la migración sí quedó en `database/` localmente (ese sí se versiona, es la documentación de qué cambió el esquema) y que el script PHP temporal NO se commitea (bórralo también de tu copia local después de usarlo, o dale un nombre que ya esté en `.gitignore`).

Ver `references/plantilla-migrador.php` para el script completo y `references/plantilla-migracion-sql.sql` para el formato esperado de una migración idempotente.

## 5. QA después de desplegar

Sin SSH y sin una herramienta de navegador siempre disponible en este entorno, el QA real combina dos niveles:

- **Verificación básica por `curl`** (siempre la puedes hacer tú mismo): confirma que las rutas clave devuelven el código esperado (302 a login si no hay sesión, no 500) y que el HTML/JSON de una ruta pública no tiene `Warning:`/`Fatal error:` de PHP filtrado en la respuesta — un error de PHP no autenticado a veces solo se nota así, no visualmente.

  ```bash
  curl -s "https://suilaft.tech/suiaut/" -o /dev/null -w "home: %{http_code}\n"
  curl -s "https://suilaft.tech/suiaut/tu-modulo" -o /dev/null -w "modulo: %{http_code}\n"
  ```

- **QA funcional real (flujo completo, con sesión)**: si no tienes un navegador ni credenciales de una cuenta real en esta sesión, **pídeselo al usuario** en vez de inventar que lo probaste — dile exactamente qué pasos probar y qué deberían ver, como una lista corta y concreta. No reportes una tarea como verificada en producción si no la verificaste de verdad.

## Checklist final de cualquier despliegue

- [ ] `php -l` sobre cada archivo PHP que cambiaste, sin errores.
- [ ] Archivos subidos en el orden correcto (`index.php`, si cambió, al final).
- [ ] Si hubo migración: el script temporal se ejecutó, confirmó éxito, y **ya no existe en el servidor**.
- [ ] `curl` a las rutas clave del módulo afectado no devuelve 500 ni `Warning`/`Fatal error` en el cuerpo.
- [ ] Si el repo usa git, hiciste commit + push (ver la memoria del proyecto: aquí siempre se hacen ambos, FTP y GitHub, nunca solo uno).
- [ ] Si no pudiste hacer QA funcional real tú mismo, se lo pediste explícitamente al usuario con pasos concretos — no lo diste por probado.
