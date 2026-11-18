---
name: dgl-despliegue-ftp-produccion
description: Despliega código y corre migraciones de base de datos en la producción de SUILAFT DGL (https://suilaft.tech/dgl_suilaft/, hosting compartido por FTP, sin SSH ni ejecutor de migraciones). Úsala cada vez que haya que subir archivos PHP/vistas/modelos modificados, crear o alterar una tabla en la base real, o verificar que un despliegue no rompió nada, incluido el paso final de cualquier otra skill del proyecto (kyc-nuevo-tipo, matriz-nuevo-tipo, auditoria-instrumentar, alertas-nuevo-detector, etc.).
---

# Desplegar y migrar SUILAFT DGL por FTP

No hay SSH ni staging. `config/database.php` usa `localhost` **del servidor**, no tu máquina. Por eso:

- Código → FTP.
- Esquema → script PHP temporal que corre una vez en el servidor.
- QA → en producción, con datos ficticios.

## 0. Antes de nada: confirma con el usuario

Subir a producción y migrar afecta datos reales. Lista los archivos y la migración, y pide confirmación antes de la primera subida de la tarea, salvo que el usuario ya lo haya autorizado en esa conversación.

## 1. Credenciales

`.vscode/sftp.json` (excluido de Git; `protocol: ftp`, `remotePath: "/"`, **`uploadOnSave: true`**). Léelas con Read solo cuando vayas a subir. Nunca las copies a otro archivo, a una skill, a un log ni al chat. `uploadOnSave` significa que lo que el usuario guarda en VS Code ya se sube solo: avisa si vas a editar archivos que no deben llegar todavía a producción.

Antes de la primera subida, confirma que la raíz FTP corresponde a la carpeta de la app (lista la raíz y busca `index.php`, `kyc.php`, `config/`):

```bash
curl -s --list-only "ftp://$FTP_HOST/" --user "$FTP_USER:$FTP_PASS"
```

## 2. Subir archivos

```bash
for f in "models/Kyc.php" "views/kyc.php"; do
  curl -s --ftp-create-dirs -T "$f" "ftp://$FTP_HOST/$f" --user "$FTP_USER:$FTP_PASS" && echo "OK: $f" || echo "FALLÓ: $f"
done
```

Sube **solo** lo que cambió. Nunca subas `.vscode/`, `sql/`, `.claude/`, volcados, PDF de prueba ni archivos temporales. `config/database.php` y `config/mail.php` solo si el usuario lo pide.

**Orden**: modelos y archivos nuevos primero; vistas después; puntos de entrada (`index.php`, `kyc.php`, `publico.php`, `catalogos.php`, nuevos `*.php` de la raíz) y `config/*.php` al final. Un punto de entrada que hace `require` de un modelo que aún no existe tumba esa pantalla.

FTP no cifra. Si el proveedor ofrece FTPS, usa `--ssl-reqd`.

**Lecciones de despliegues anteriores:**
- La shell es **zsh**: `for f in $LISTA` no separa por espacios. Usa un arreglo, `FILES=(a b c); for f in "${FILES[@]}"`, o escribe la lista literal.
- Empieza los comandos de subida o migración con `set -e`, para que un `php -l` fallido detenga la subida.
- Antes de reemplazar archivos, descarga un respaldo de las versiones remotas al scratchpad. Después de subir, compara remoto y local con `cmp`; si sale distinto, repite la descarga antes de concluir nada: una vez fue un fallo momentáneo de la descarga.
- Si cambias `assets/dgl.css` o `dgl.js`, sube `$__assetV` en `views/partials/app_start.php`.

## 3. Migraciones

1. Escribe `sql/NN_descripcion.sql` (siguiente número: `ls sql/ | sort -V | tail -3`). Hazla **idempotente**: `CREATE TABLE IF NOT EXISTS`. Para columnas, MySQL no admite `ADD COLUMN IF NOT EXISTS` (solo MariaDB): comprueba primero con `information_schema.COLUMNS` dentro del migrador. Los scripts `03` y `04` existentes **no** son idempotentes: no los vuelvas a correr.
2. Respaldo: pide al usuario un export de las tablas afectadas (phpMyAdmin) antes de un `ALTER` o cambio de datos.
3. Crea el migrador desde `references/plantilla-migrador.php` en el **scratchpad**, no en el proyecto. Pega el SQL dentro del script (la carpeta `sql/` no se sube) como cadena PHP entre **comillas simples, escapando `\` y `'`**: el SQL lleva valores como `'activo'` y con comillas dobles el migrador no compila. Genera un token con `php -r 'echo bin2hex(random_bytes(24));'`. Ejecuta `php -l` sobre el migrador y **no lo subas si falla**.
4. Súbelo como `migrar_NN_<aleatorio>.php` en la raíz remota y ejecútalo una vez: `curl -s "https://suilaft.tech/dgl_suilaft/migrar_NN_xxx.php?token=..."`. Lee la salida: debe listar cada sentencia con OK.
5. **Bórralo inmediatamente**: `curl -s -Q "DELE migrar_NN_xxx.php" "ftp://$FTP_HOST/" --user "$FTP_USER:$FTP_PASS"`, y comprueba que la URL ya responde 404.
6. El `.sql` queda en `sql/` local como registro (recuerda que `sql/` está en `.gitignore`: entrégalo aparte si se comparte el proyecto).

Los DDL de MySQL hacen commit implícito: una transacción no los revierte.

## 4. QA después de desplegar

```bash
for u in "" "kyc.php" "catalogos.php"; do curl -s -o /dev/null -w "$u %{http_code}\n" "https://suilaft.tech/dgl_suilaft/$u"; done
curl -s "https://suilaft.tech/dgl_suilaft/" | grep -iE "warning|fatal|notice" && echo "HAY ERRORES PHP"
```

Sin sesión, `kyc.php` debe redirigir (302) y no dar 500. Para el flujo funcional con sesión, si no tienes navegador y cuenta de prueba, **pide al usuario** que lo pruebe con una lista concreta de pasos. No lo des por verificado si no lo verificaste.

**No envíes correos reales** como prueba (indicación expresa del usuario).

## Checklist

- [ ] Confirmación del usuario para producción.
- [ ] `find . -path './vendor' -prune -o -name '*.php' -type f -exec php -l {} \;` sin errores.
- [ ] Migración aplicada, salida revisada y script borrado (404).
- [ ] Archivos subidos en orden; ninguno sensible ni temporal.
- [ ] Rutas clave sin 500 ni `Warning`/`Fatal error`.
- [ ] README actualizado si cambió el comportamiento (sección "Estado funcional" y la línea de tiempo).

## Hora

Desde el 10-nov-2026 `models/Database.php` fija `SET time_zone = '-05:00'` (Ecuador) y `America/Guayaquil` en PHP: NOW(), CURDATE() y los DEFAULT CURRENT_TIMESTAMP ya son hora local. Compara vencimientos en SQL (`col > NOW()`), no con `strtotime()` contra `time()`. No vuelvas a desplazar fechas guardadas.
