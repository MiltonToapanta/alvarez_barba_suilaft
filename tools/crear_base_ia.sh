#!/bin/sh
# Crea la base `suilaft_ai` del módulo de IA, da permisos al usuario de la app e importa
# roles/planes. Las demás tablas las crea la app de IA en su primer acceso (includes/schema.php).
# Uso: sh tools/crear_base_ia.sh   (con los contenedores levantados)
set -e
cd "$(dirname "$0")/.."
docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "
  CREATE DATABASE IF NOT EXISTS suilaft_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  GRANT ALL PRIVILEGES ON suilaft_ai.* TO \`$MARIADB_USER\`@\`%\`;
  FLUSH PRIVILEGES;"'
docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" suilaft_ai' < ia/database.sql
echo "Base suilaft_ai lista."
