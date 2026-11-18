<?php
declare(strict_types=1);

/**
 * TOTP (RFC 6238) sin dependencias, para PHP 8.1+.
 *
 * Es la pieza mínima para la ruta B de la guía: cuando el sistema NO puede delegar el acceso a
 * Keycloak. Si puede, no uses esto: usa la ruta A.
 *
 * Probado contra los vectores de prueba del apéndice B del RFC 6238 (ver TotpTest.php).
 *
 * Lo que esta clase NO hace, y el sistema tiene que hacer:
 *  - guardar el secreto cifrado (nunca en claro) y asociado al usuario;
 *  - impedir reutilizar un código (guardar el último paso de tiempo aceptado);
 *  - limitar intentos por usuario y por IP;
 *  - regenerar la sesión al completar el segundo factor.
 */
final class Totp
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Secreto nuevo: 20 bytes aleatorios (160 bits, lo que recomienda el RFC para SHA-1),
     * devuelto en Base32, que es lo que entienden las aplicaciones autenticadoras.
     */
    public static function generarSecreto(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * La dirección otpauth:// que se convierte en código QR para escanear.
     *
     * SHA1, 6 dígitos y 30 segundos a propósito: es la única combinación que aceptan todas las
     * aplicaciones comunes. Microsoft Authenticator ignora otro algoritmo y genera códigos que no
     * coinciden. HMAC-SHA1 sigue siendo seguro para este uso; lo roto de SHA-1 son las colisiones,
     * que aquí no intervienen.
     */
    public static function uriProvision(string $secreto, string $cuenta, string $emisor): string
    {
        $etiqueta = rawurlencode($emisor) . ':' . rawurlencode($cuenta);
        return 'otpauth://totp/' . $etiqueta . '?' . http_build_query([
            'secret' => $secreto,
            'issuer' => $emisor,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Verifica un código y devuelve el paso de tiempo que casó, o null si no casa.
     *
     * Devuelve el paso y no un booleano para que el llamador pueda impedir la reutilización:
     * si el paso devuelto es menor o igual que el último aceptado para ese usuario, el código ya
     * se usó y hay que rechazarlo.
     *
     * @param int $ventana pasos de tolerancia a cada lado. 1 = ±30 s, suficiente para relojes
     *                     de móvil algo desajustados. Más amplitud facilita la fuerza bruta.
     */
    public static function verificar(string $secreto, string $codigo, int $ventana = 1,
            ?int $ahora = null, int $digitos = 6, int $periodo = 30, string $algoritmo = 'sha1'): ?int
    {
        $codigo = preg_replace('/\s+/', '', $codigo) ?? '';
        if (!preg_match('/^\d{' . $digitos . '}$/', $codigo)) {
            return null;
        }
        $clave = self::base32Decode($secreto);
        $paso = intdiv($ahora ?? time(), $periodo);
        for ($i = -$ventana; $i <= $ventana; $i++) {
            // hash_equals: comparación en tiempo constante, para no filtrar dígitos por tiempos.
            if (hash_equals(self::hotp($clave, $paso + $i, $digitos, $algoritmo), $codigo)) {
                return $paso + $i;
            }
        }
        return null;
    }

    /** HOTP del RFC 4226 sobre la clave ya decodificada. */
    public static function hotp(string $clave, int $contador, int $digitos = 6, string $algoritmo = 'sha1'): string
    {
        $mensaje = pack('J', $contador);                 // contador de 8 bytes, big-endian
        $hash = hash_hmac($algoritmo, $mensaje, $clave, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;  // truncamiento dinámico
        $binario = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        return str_pad((string) ($binario % (10 ** $digitos)), $digitos, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $datos): string
    {
        $bits = '';
        foreach (str_split($datos) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $salida = '';
        foreach (str_split($bits, 5) as $grupo) {
            $salida .= self::BASE32[bindec(str_pad($grupo, 5, '0'))];
        }
        return $salida;
    }

    public static function base32Decode(string $texto): string
    {
        $texto = strtoupper(preg_replace('/[\s=]/', '', $texto) ?? '');
        $bits = '';
        foreach (str_split($texto) as $caracter) {
            $valor = strpos(self::BASE32, $caracter);
            if ($valor === false) {
                throw new InvalidArgumentException('El secreto no es Base32 válido');
            }
            $bits .= str_pad(decbin($valor), 5, '0', STR_PAD_LEFT);
        }
        $salida = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $salida .= chr(bindec($byte));
            }
        }
        return $salida;
    }
}
