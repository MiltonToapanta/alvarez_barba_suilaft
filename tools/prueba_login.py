"""Prueba de punta a punta del acceso (contraseña + Authenticator) contra http://localhost:8080.
Uso: python3 -I tools/prueba_login.py <usuario> <clave>
Valida los invariantes de la skill autenticacion-modificar-login.
"""
import base64, hashlib, hmac, http.cookiejar, json, struct, sys, time, urllib.parse, urllib.request

BASE = 'http://localhost:8080'
USUARIO, CLAVE = sys.argv[1], sys.argv[2]


class Cliente:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def xsrf(self):
        for c in self.jar:
            if c.name == 'XSRF-TOKEN':
                return urllib.parse.unquote(c.value)
        return ''

    def req(self, metodo, ruta, datos=None):
        h = {'Accept': 'application/json', 'Referer': BASE + '/', 'Origin': BASE, 'X-XSRF-TOKEN': self.xsrf()}
        cuerpo = None
        if datos is not None:
            cuerpo = json.dumps(datos).encode()
            h['Content-Type'] = 'application/json'
        r = urllib.request.Request(BASE + ruta, data=cuerpo, headers=h, method=metodo)
        try:
            with self.op.open(r) as resp:
                txt = resp.read().decode()
                return resp.status, (json.loads(txt) if txt else {})
        except urllib.error.HTTPError as e:
            txt = e.read().decode()
            try:
                return e.code, json.loads(txt)
            except ValueError:
                return e.code, {'raw': txt[:200]}

    def csrf(self):
        self.req('GET', '/sanctum/csrf-cookie')


def totp(secreto, t=None):
    clave = base64.b32decode(secreto + '=' * (-len(secreto) % 8))
    paso = int((t or time.time()) // 30)
    h = hmac.new(clave, struct.pack('>Q', paso), hashlib.sha1).digest()
    o = h[-1] & 0x0F
    return '%06d' % ((struct.unpack('>I', h[o:o + 4])[0] & 0x7FFFFFFF) % 1000000)


ok_total = True
def check(nombre, cond, extra=''):
    global ok_total
    ok_total &= bool(cond)
    print(('  OK   ' if cond else '  FALLA') + f' {nombre} {extra}')


c = Cliente()
c.csrf()
s, d = c.req('POST', '/api/auth/login', {'usuario': USUARIO, 'clave': 'mala-clave-123'})
check('contraseña incorrecta -> 422', s == 422, d.get('message', ''))

c.csrf()
s, d = c.req('POST', '/api/auth/login', {'usuario': USUARIO, 'clave': CLAVE})
check('contraseña correcta -> pide 2FA', s == 200 and d.get('estado') in ('configurar', 'codigo'), str(d))
estado = d.get('estado')

s, _ = c.req('GET', '/api/yo')
check('sin 2FA NO hay sesión (/api/yo -> 401)', s == 401, f'({s})')

if estado == 'configurar':
    s, d = c.req('GET', '/api/auth/configurar')
    secreto = d.get('secreto', '')
    check('QR / secreto entregado', s == 200 and len(secreto) >= 16 and d.get('uri', '').startswith('otpauth://'))
    s2, d2 = c.req('GET', '/api/auth/configurar')
    check('recargar no cambia el secreto', d2.get('secreto') == secreto)

    s, d = c.req('POST', '/api/auth/configurar', {'codigo': '000000'})
    check('código de activación incorrecto -> 422', s == 422)

    s, d = c.req('POST', '/api/auth/configurar', {'codigo': totp(secreto)})
    codigos = d.get('codigos_recuperacion', [])
    check('activación correcta + 10 códigos de recuperación', s == 200 and len(codigos) == 10, f'({s})')
    usado = totp(secreto)

    s, d = c.req('GET', '/api/yo')
    check('sesión completa (/api/yo -> 200)', s == 200 and d.get('usuario') == USUARIO)

    c.csrf()
    s, _ = c.req('POST', '/api/auth/logout')
    s, _ = c.req('GET', '/api/yo')
    check('logout destruye la sesión', s == 401)

    # Segundo acceso: pide código, rechaza el mismo TOTP ya usado, acepta recuperación una sola vez.
    c.csrf()
    s, d = c.req('POST', '/api/auth/login', {'usuario': USUARIO, 'clave': CLAVE})
    check('segundo acceso pide código', d.get('estado') == 'codigo')
    s, d = c.req('POST', '/api/auth/codigo', {'codigo': usado})
    check('el mismo código TOTP no se puede reutilizar', s == 422, f'({s})')
    s, d = c.req('POST', '/api/auth/codigo', {'codigo': codigos[0]})
    check('código de recuperación funciona', s == 200, f'({s} {d.get("message", "")})')

    c.csrf(); c.req('POST', '/api/auth/logout'); c.csrf()
    c.req('POST', '/api/auth/login', {'usuario': USUARIO, 'clave': CLAVE})
    s, d = c.req('POST', '/api/auth/codigo', {'codigo': codigos[0]})
    check('código de recuperación usado NO funciona dos veces', s == 422, f'({s})')

    # Bloqueo por intentos (5 fallos en la ventana)
    estados = [c.req('POST', '/api/auth/codigo', {'codigo': '111111'})[0] for _ in range(6)]
    check('bloqueo tras varios intentos fallidos (429)', 429 in estados, str(estados))
else:
    print('  (el usuario ya tenía 2FA activo; este script prueba el primer registro)')

print('\nRESULTADO:', 'TODO OK' if ok_total else 'HAY FALLAS')
sys.exit(0 if ok_total else 1)
