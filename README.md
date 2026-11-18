# SUILAFT · Alvarez Barba S.A.

Sistema de cumplimiento LA/FT (sector vehicular, base SUIAUTO).
Laravel 12 (API) + React 18 / Vite + MariaDB 11.8, todo en Docker.

## Levantar

```bash
cp .env.example .env               # contraseñas de la base (ya existe en local)
docker compose up -d --build
docker compose exec app php artisan key:generate      # solo la primera vez
docker compose exec app php artisan migrate --seed    # esquema SUIAUTO + catálogos + admin
```

Abrir http://localhost:8080. El seeder muestra **una sola vez** la clave del usuario `admin`.
El primer acceso pide registrar Google/Microsoft Authenticator.

MariaDB queda expuesta solo en `127.0.0.1:3317` (para DBeaver/TablePlus).

## Estructura

| Ruta | Qué es |
|---|---|
| `docker-compose.yml` | db (MariaDB), app (PHP 8.3-FPM), frontend (Vite), web (Nginx :8080) |
| `docker/nginx/default.conf` | `/api` y `/sanctum` → Laravel; el resto → React (mismo origen) |
| `backend/database/schema/mariadb-schema.sql` | Estructura de SUIAUTO (65 tablas), cargada por `migrate` |
| `backend/database/seeders/sql/catalogos.sql` | Catálogos UAFE / DPA / países / monedas / roles |
| `backend/database/migrations/` | Cambios propios **sobre** el esquema base |
| `backend/app/Http/Controllers/Auth/AccesoController.php` | Login en dos pasos (contraseña + TOTP) |
| `backend/app/Services/SegundoFactor.php` | TOTP, bloqueo por intentos, códigos de recuperación |
| `frontend/src/` | Pantallas de acceso (mismo diseño y CSS que SUIAUTO) |
| `branding/` | Logo de Alvarez Barba en SVG y PNG (versión oscura y clara) |
| `frontend/src/assets/alvarez-barba.css` | Identidad visual: grafito + dorado, títulos serif (Cormorant Garamond) |
| `tools/extraer_schema.py` | Regenera schema + catálogos desde un volcado de SUIAUTO |
| `tools/prueba_login.py` | Prueba de punta a punta de los invariantes de seguridad del login |

## Seguridad del acceso

La sesión real (`Auth::login`) solo se crea después del segundo factor. Antes de eso
solo existe `sf_pendiente` (5 minutos). Hay bloqueo por intentos (contraseña y código),
rechazo de códigos TOTP reutilizados y códigos de recuperación de un solo uso.
Todo queda en la tabla `auditoria` con `modulo = 'auth'`.
Ver la skill `.claude/skills/autenticacion-modificar-login` antes de tocar este flujo.

```bash
python3 -I tools/prueba_login.py <usuario> <clave>   # con un usuario SIN 2FA activado
```
