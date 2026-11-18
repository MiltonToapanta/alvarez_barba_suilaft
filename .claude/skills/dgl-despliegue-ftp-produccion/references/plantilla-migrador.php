<?php
/**
 * Migrador temporal de UN SOLO USO para SUILAFT DGL.
 * - Créalo en el scratchpad, no en el proyecto.
 * - Súbelo a la raíz remota con nombre aleatorio (migrar_NN_xxxx.php),
 *   ejecútalo una vez con el token y BÓRRALO del servidor de inmediato.
 * Uso: https://suilaft.tech/dgl_suilaft/migrar_NN_xxxx.php?token=TOKEN
 */
const TOKEN_ESPERADO = 'REEMPLAZAR_CON_TOKEN_ALEATORIO_DE_48_HEX';

if (!isset($_GET['token']) || !is_string($_GET['token']) || !hash_equals(TOKEN_ESPERADO, $_GET['token'])) {
    http_response_code(404);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/models/Database.php';

// Pega aquí las sentencias de sql/NN_descripcion.sql, una por elemento.
$sentencias = array(
    "CREATE TABLE IF NOT EXISTS ejemplo (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
);

// Columnas a agregar solo si no existen (MySQL no soporta ADD COLUMN IF NOT EXISTS).
// array(tabla, columna, definición)
$columnas = array(
    // array('kyc_adjuntos', 'nueva_columna', "VARCHAR(40) NOT NULL DEFAULT ''"),
);

try {
    $db = Database::connect();
    $fallo = false;
    foreach ($sentencias as $sql) {
        if ($db->query($sql)) {
            echo 'OK: ' . substr($sql, 0, 90) . "\n";
        } else {
            $fallo = true;
            echo 'ERROR: ' . $db->error . "\n   en: " . substr($sql, 0, 200) . "\n";
        }
    }
    foreach ($columnas as $c) {
        list($tabla, $columna, $definicion) = $c;
        $q = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $q->bind_param('ss', $tabla, $columna);
        $q->execute();
        $q->bind_result($existe);
        $q->fetch();
        $q->close();
        if ($existe) {
            echo "YA EXISTE: $tabla.$columna\n";
            continue;
        }
        if ($db->query('ALTER TABLE `' . $tabla . '` ADD COLUMN `' . $columna . '` ' . $definicion)) {
            echo "OK: columna $tabla.$columna\n";
        } else {
            $fallo = true;
            echo "ERROR columna $tabla.$columna: " . $db->error . "\n";
        }
    }
    $db->close();
    echo $fallo ? "\nTERMINADO CON ERRORES.\n" : "\nMIGRACIÓN COMPLETADA.\n";
} catch (Throwable $e) {
    error_log('Migrador: ' . $e->getMessage());
    echo "No se pudo conectar a la base de datos.\n";
}
