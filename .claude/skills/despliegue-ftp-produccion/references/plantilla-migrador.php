<?php
/**
 * Script temporal de un solo uso para correr una migracion SQL contra la
 * base de datos real de produccion. Subelo por FTP, visitalo UNA vez con
 * el token correcto, y BORRALO del servidor de inmediato. No lo dejes
 * como parte del codigo del proyecto ni lo commitees a git: es un script
 * que ejecuta SQL arbitrario, protegido solo por el token de esta URL.
 *
 * Uso: https://tu-dominio/ruta/migrar_NN.php?token=TOKEN_SECRETO
 */

// Reemplaza por un token largo y aleatorio generado una sola vez
// (ej. bin2hex(random_bytes(16))), pegado aqui como string literal.
// No uses algo adivinable como "migrar123".
define('TOKEN_ESPERADO', 'TOKEN_SECRETO');

if (($_GET['token'] ?? '') !== TOKEN_ESPERADO) {
    http_response_code(403);
    exit('No autorizado.');
}

// Ajusta esta ruta si el script no queda en la raiz del proyecto.
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/Database.php';

header('Content-Type: text/plain; charset=utf-8');

// Reemplaza por el nombre real de tu migracion en database/.
$archivoSql = __DIR__ . '/database/ARCHIVO_SQL.sql';

if (!file_exists($archivoSql)) {
    exit("No se encontro el archivo: $archivoSql\n");
}

$sql = file_get_contents($archivoSql);
$db = Database::getInstance()->getConnection();

// OJO: CREATE TABLE/ALTER TABLE son DDL y MySQL las confirma solas (commit
// implicito) sin importar transacciones de PDO - por eso NO se envuelve
// esto en beginTransaction()/rollBack(): seria una falsa sensacion de
// seguridad. La unica red de seguridad real es que cada sentencia use
// IF NOT EXISTS / IF EXISTS, para que correr el script dos veces (por
// error, o tras uno fallido a medias) nunca falle ni duplique nada.
$sentencias = array_filter(array_map('trim', explode(';', $sql)));
$fallo = false;

foreach ($sentencias as $sentencia) {
    if ($sentencia === '') {
        continue;
    }
    try {
        $db->exec($sentencia);
        echo "OK: " . substr($sentencia, 0, 80) . "...\n";
    } catch (\Throwable $e) {
        $fallo = true;
        echo "ERROR en esta sentencia: " . $e->getMessage() . "\n";
        echo "Sentencia: " . substr($sentencia, 0, 200) . "\n\n";
    }
}

echo $fallo
    ? "\nTerminado CON errores - revisa arriba cuales sentencias fallaron.\n"
    : "\nMigracion completada sin errores.\n";
