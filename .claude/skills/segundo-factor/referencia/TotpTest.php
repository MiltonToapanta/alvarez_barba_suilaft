<?php
declare(strict_types=1);

// Ejecutar con: php TotpTest.php
// Vectores del apéndice B del RFC 6238 (8 dígitos, periodo de 30 s).
require __DIR__ . '/Totp.php';

$fallos = 0;
$ok = function (bool $condicion, string $nombre) use (&$fallos): void {
    echo ($condicion ? 'ok   ' : 'FALLA') . "  $nombre\n";
    if (!$condicion) $fallos++;
};

$claves = [
    'sha1'   => '12345678901234567890',
    'sha256' => '12345678901234567890123456789012',
    'sha512' => '1234567890123456789012345678901234567890123456789012345678901234',
];
$vectores = [
    [59,          '94287082', '46119246', '90693936'],
    [1111111109,  '07081804', '68084774', '25091201'],
    [1111111111,  '14050471', '67062674', '99943326'],
    [1234567890,  '89005924', '91819424', '93441116'],
    [2000000000,  '69279037', '90698825', '38618901'],
    [20000000000, '65353130', '77737706', '47863826'],
];
foreach ($vectores as [$t, $s1, $s256, $s512]) {
    foreach (['sha1' => $s1, 'sha256' => $s256, 'sha512' => $s512] as $alg => $esperado) {
        $obtenido = Totp::hotp($claves[$alg], intdiv($t, 30), 8, $alg);
        $ok($obtenido === $esperado, "RFC 6238 t=$t $alg => $esperado (obtenido $obtenido)");
    }
}

// Base32 de ida y vuelta, y verificación con ventana.
$secreto = Totp::generarSecreto();
$ok(strlen(Totp::base32Decode($secreto)) === 20, 'el secreto generado tiene 160 bits');
$ok(Totp::base32Decode(Totp::base32Encode('12345678901234567890')) === '12345678901234567890', 'Base32 de ida y vuelta');

$clave = Totp::base32Decode($secreto);
$ahora = 1_800_000_000;
$actual = Totp::hotp($clave, intdiv($ahora, 30));
$ok(Totp::verificar($secreto, $actual, 1, $ahora) === intdiv($ahora, 30), 'acepta el código del paso actual');
$previo = Totp::hotp($clave, intdiv($ahora, 30) - 1);
$ok(Totp::verificar($secreto, $previo, 1, $ahora) === intdiv($ahora, 30) - 1, 'acepta el paso anterior dentro de la ventana');
$viejo = Totp::hotp($clave, intdiv($ahora, 30) - 3);
$ok(Totp::verificar($secreto, $viejo, 1, $ahora) === null, 'rechaza un código fuera de la ventana');
$ok(Totp::verificar($secreto, '12345', 1, $ahora) === null, 'rechaza un código con dígitos de menos');
$ok(Totp::verificar($secreto, 'abcdef', 1, $ahora) === null, 'rechaza un código no numérico');
$ok(Totp::verificar($secreto, substr($actual, 0, 3) . ' ' . substr($actual, 3), 1, $ahora) !== null, 'tolera el espacio que ponen algunas apps');

$uri = Totp::uriProvision($secreto, 'ana@empresa.com', 'Mi Sistema');
$ok(str_starts_with($uri, 'otpauth://totp/Mi%20Sistema:ana%40empresa.com?secret='), 'la URI de aprovisionamiento es válida');

echo $fallos === 0 ? "\nTodo correcto.\n" : "\n$fallos fallos.\n";
exit($fallos === 0 ? 0 : 1);
