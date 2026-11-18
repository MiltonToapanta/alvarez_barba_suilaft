# Guía: segundo factor de autenticación con aplicación autenticadora

Esta guía explica cómo SUILAFT v2.0 exige un segundo factor —un código de seis dígitos generado
por una aplicación en el móvil— y cómo llevar esa misma capa de seguridad a otros sistemas de la
empresa, incluidos monolitos en Spring Boot y en **PHP puro, sin framework**.

Está escrita para dos lectores a la vez: la persona que va a implantarlo y la IA que la ayude.
Si eres una IA, lee primero la sección 9.

---

## 1. En dos minutos

- **Qué es.** Además de la contraseña, el usuario escribe un código que cambia cada 30 segundos y
  que genera una aplicación en su móvil: Google Authenticator, Microsoft Authenticator o FreeOTP.
  El estándar se llama **TOTP** (RFC 6238). Robar la contraseña ya no basta para entrar.
- **Cómo lo hace SUILAFT.** No lo programa: lo **delega en Keycloak**, un servidor de identidad
  de código abierto. La aplicación nunca ve la contraseña ni el código; Keycloak los verifica y le
  devuelve a la aplicación una identidad ya comprobada.
- **La recomendación para los demás sistemas** es la misma: conectarlos al Keycloak que ya existe
  (ruta A). Así el segundo factor, el bloqueo por intentos, la política de contraseñas y el cierre
  de sesión se configuran una vez para todos, y un usuario se da de alta una sola vez.
- **Si un sistema no puede conectarse a Keycloak**, hay una ruta B: implementar TOTP dentro de la
  aplicación. Funciona, pero cada sistema carga con su propia seguridad. En `referencia/` hay una
  implementación en PHP sin dependencias, probada contra los vectores oficiales del RFC.

---

## 2. Cómo está hecho en SUILAFT

Todo lo de esta sección existe en el repositorio de SUILAFT v2.0 y se puede consultar.

### 2.1 Las piezas

```
Navegador ──► Frontend (Angular)  ──►  Backend Spring Boot (cliente OIDC "BFF")
                                              │
                                              ├──► Keycloak 26  (contraseña + TOTP + política)
                                              └──► Valkey       (sesión del servidor)
```

- **Keycloak 26.7.3** con un realm propio, `suilaft`. Es el único que conoce contraseñas y
  secretos TOTP.
- **Backend como BFF** (*Backend For Frontend*): el backend es el cliente OIDC. Hace el flujo con
  Keycloak, guarda los tokens **en su sesión** y al navegador sólo le da una cookie `HttpOnly`.
  El navegador nunca recibe un token, así que un script inyectado en la página no tiene nada que
  robar.
- **Valkey** guarda esa sesión en el servidor.

### 2.2 El segundo factor, obligatorio para todos

El segundo factor no depende de roles de la aplicación: lo exige Keycloak a **todo** usuario
interactivo. Si algo falla, falla cerrado.

Se configura en `infra/keycloak/init-production-client.sh`:

| Ajuste | Valor en producción | Para qué |
|---|---|---|
| Acción requerida `CONFIGURE_TOTP` | habilitada y **por defecto** | Todo usuario nuevo tiene que registrar su autenticador en el primer acceso. |
| `otpPolicyType` | `totp` | Códigos por tiempo, no por contador. |
| `otpPolicyAlgorithm` | `HmacSHA256` | Ver el hallazgo 3.1: conviene `HmacSHA1`. |
| `otpPolicyDigits` / `otpPolicyPeriod` | `6` / `30` | Lo que esperan todas las aplicaciones. |
| `bruteForceProtected` | `true` | Bloqueo temporal tras intentos fallidos. |
| `failureFactor` | `5` | Intentos antes de bloquear. |
| `waitIncrementSeconds` / `maxFailureWaitSeconds` | `60` / `900` | Espera creciente, hasta 15 minutos. |
| `permanentLockout` | `false` | Nunca bloqueo permanente: evita que un atacante deje fuera a un usuario a propósito. |
| `ssoSessionIdleTimeout` / `ssoSessionMaxLifespan` | 30 min / 8 h | Sesión que caduca por inactividad y por duración. |
| `accessTokenLifespan` | 5 min | Tokens cortos; el backend los renueva. |
| `passwordPolicy` | 12 caracteres, mayúscula, minúscula, dígito, símbolo, distinta del usuario, caduca a 180 días | |

El cliente del backend (`suilaft-bff`) usa **código de autorización con PKCE (S256)**, tiene
desactivados el flujo implícito y el de contraseña directa (`directAccessGrantsEnabled=false`), y
sus direcciones de retorno son exactas, sin comodines.

### 2.3 El primer acceso de un usuario

1. El administrador crea al usuario. En SUILAFT se hace desde la aplicación, que llama a la API de
   administración de Keycloak y le manda una invitación por correo; el primer administrador lo
   crea `scripts/seed-production.py`.
2. Keycloak le asigna las acciones requeridas `UPDATE_PASSWORD` y `CONFIGURE_TOTP`.
3. Al entrar, Keycloak le obliga a cambiar la contraseña temporal y le muestra un **código QR**.
   Lo escanea con su aplicación y escribe el primer código para confirmar que la configuró bien.
4. Desde entonces, cada acceso pide contraseña y código.

Las pantallas de Keycloak llevan la marca de SUILAFT: `infra/keycloak/themes/suilaft/login/`,
en particular `login-config-totp.ftl` (registro del autenticador) y `login-otp.ftl` (pedir el
código).

### 2.4 Qué recibe la aplicación

La aplicación recibe de Keycloak un token de identidad firmado con el correo, el identificador del
usuario (`sub`) y la hora a la que se autenticó (`auth_time`). Enlaza ese `sub` con su propio
usuario local. **Nunca guarda contraseñas.**

La configuración de Spring está en
`backend/src/main/java/com/suilaft/identity/infrastructure/security/SecurityConfiguration.java`.
Dos detalles que conviene copiar:

- **CSRF guardado en la sesión**, no en una cookie legible por JavaScript
  (`HttpSessionCsrfTokenRepository`, cabecera `X-XSRF-TOKEN`).
- **Cierre de sesión en los dos lados**: invalida la sesión local y redirige al cierre de sesión de
  Keycloak (`OidcClientInitiatedLogoutSuccessHandler`). Sin lo segundo, el usuario «sale» de la
  aplicación pero sigue con sesión en Keycloak y vuelve a entrar sin que le pidan nada.

### 2.5 Pedir el factor otra vez para una acción sensible

Para firmar un documento, SUILAFT no se conforma con que haya sesión: exige que la autenticación
sea **reciente** (menos de 15 minutos) y, si se activa, que haya habido segundo factor. Está en
`backend/src/main/java/com/suilaft/signature/infrastructure/security/RecentAuthenticationPolicy.java`:
lee `auth_time` y la reclamación `amr` del token. Es el patrón a seguir para operaciones como
cambiar datos bancarios, aprobar pagos o descargar información masiva.

---

## 3. Hallazgos al revisar la configuración actual

Se comprobaron contra el propio Keycloak de SUILAFT. Afectan a SUILAFT y a quien copie su
configuración.

### 3.1 Con `HmacSHA256`, Microsoft Authenticator no funciona

Keycloak informa de qué aplicaciones son compatibles con la política configurada. Resultado real:

| Algoritmo | Aplicaciones compatibles según Keycloak |
|---|---|
| `HmacSHA1` | FreeOTP, Google Authenticator, **Microsoft Authenticator** |
| `HmacSHA256` (producción hoy) | FreeOTP, Google Authenticator |

Microsoft Authenticator ignora el algoritmo del QR y genera los códigos con SHA-1: el usuario los
escribe y Keycloak los rechaza, sin ningún mensaje que explique por qué.

**Recomendación: `HmacSHA1`.** No es una rebaja de seguridad. Lo que está roto de SHA-1 son las
colisiones, y HMAC-SHA1 —que es lo que usa TOTP— no depende de ellas; es además el valor por
defecto del RFC 6238. Cada credencial registrada guarda su propio algoritmo, así que cambiar la
política afecta a los registros nuevos. Conviene comprobarlo con un usuario de prueba antes de
hacerlo en producción.

### 3.2 La comprobación de segundo factor para firmar depende de una reclamación que no llega

`RecentAuthenticationPolicy` exige, cuando `SIGNATURE_REQUIRE_MFA=true`, que el token traiga la
reclamación `amr` con `otp` o `mfa`. El realm tiene el mapeador de `acr` pero **no el de `amr`**, y
sin él Keycloak normalmente no incluye `amr` en el token. En producción esa variable está a `true`
(`infra/vps/env.prod.example`).

Si se confirma, la firma interna quedaría siempre denegada. **No está verificado de extremo a
extremo**: hay que decodificar el token de identidad de un usuario real tras entrar con código y
ver si trae `amr`. Si no lo trae, hay que añadir el mapeador «Authentication Method Reference» al
realm y configurar la referencia en el autenticador OTP del flujo de acceso.

### 3.3 Volver a exigir `CONFIGURE_TOTP` a quien ya lo tiene rompe su acceso

Asignar la acción requerida a un usuario que ya tiene autenticador lo mete en un bucle de acceso.
`infra/keycloak/init-local-users.sh` comprueba antes si el usuario ya tiene una credencial `otp`.
Cualquier script de alta masiva tiene que hacer lo mismo.

---

## 4. Qué ruta elegir

| Situación del sistema | Ruta |
|---|---|
| Spring Boot 2.7+ o 3.x | **A.** Soporte nativo de OIDC en Spring Security. |
| PHP con Composer (con o sin framework) | **A**, con librería OIDC. |
| PHP puro, sin Composer ni forma de añadir librerías, detrás de Apache o nginx | **A**, con un proxy de autenticación delante (sección 5.3, opción 1). Casi sin tocar código. |
| Sin red hacia Keycloak, o que no puede depender de él | **B.** TOTP dentro de la aplicación. |
| Aplicaciones de escritorio o APIs para máquinas | No aplica tal cual: una máquina no tiene móvil. Usar credenciales de cliente o certificados. |

Ante la duda, **A**. La ruta B obliga a cada sistema a guardar secretos, limitar intentos, gestionar
códigos de recuperación y atender a quien perdió el móvil. Con A todo eso lo hace Keycloak una vez.

---

## 5. Ruta A: conectar el sistema a Keycloak

### 5.1 Preparar Keycloak

Se puede reutilizar el Keycloak existente con un realm por organización, o uno por sistema si deben
estar aislados. Un realm compartido da **inicio de sesión único**: quien ya entró en un sistema no
vuelve a escribir contraseña ni código en otro.

1. **Política OTP del realm:** `totp`, `HmacSHA1`, 6 dígitos, 30 segundos (ver 3.1).
2. **Acción requerida** `CONFIGURE_TOTP`: habilitada y por defecto.
3. **Protección de fuerza bruta** con los valores de la tabla 2.2.
4. **Un cliente por sistema**, con estos ajustes:
   - tipo confidencial (con secreto), porque el que habla con Keycloak es el servidor;
   - flujo estándar activado; implícito y contraseña directa **desactivados**;
   - PKCE `S256`;
   - direcciones de retorno exactas, por ejemplo `https://sistema.empresa.com/callback`;
   - dirección de cierre de sesión de vuelta al sistema.
5. **Mapeador `amr`** si el sistema va a exigir segundo factor para acciones concretas (ver 3.2).

Con `kcadm.sh` quedaría así; el script de SUILAFT es el ejemplo completo:

```bash
kcadm.sh update realms/MI_REALM \
  -s otpPolicyType=totp -s otpPolicyAlgorithm=HmacSHA1 \
  -s otpPolicyDigits=6 -s otpPolicyPeriod=30 \
  -s bruteForceProtected=true -s failureFactor=5 \
  -s waitIncrementSeconds=60 -s maxFailureWaitSeconds=900 -s permanentLockout=false
kcadm.sh update authentication/required-actions/CONFIGURE_TOTP -r MI_REALM \
  -s enabled=true -s defaultAction=true
```

### 5.2 Spring Boot

**Dependencias:**

```xml
<dependency>
  <groupId>org.springframework.boot</groupId>
  <artifactId>spring-boot-starter-oauth2-client</artifactId>
</dependency>
<!-- Sólo si la sesión debe sobrevivir a reinicios o repartirse entre varias instancias: -->
<dependency>
  <groupId>org.springframework.session</groupId>
  <artifactId>spring-session-data-redis</artifactId>
</dependency>
```

**`application.yml`:**

```yaml
spring:
  security:
    oauth2:
      client:
        registration:
          keycloak:
            client-id: ${OIDC_CLIENT_ID}
            client-secret: ${OIDC_CLIENT_SECRET}   # desde variable o archivo, nunca en el repositorio
            scope: openid, profile, email
            authorization-grant-type: authorization_code
        provider:
          keycloak:
            issuer-uri: https://login.empresa.com/realms/MI_REALM
```

**Cadena de seguridad** (resumen de la de SUILAFT):

```java
@Bean
SecurityFilterChain seguridad(HttpSecurity http, ClientRegistrationRepository registros) throws Exception {
    var resolver = new DefaultOAuth2AuthorizationRequestResolver(registros, "/oauth2/authorization");
    resolver.setAuthorizationRequestCustomizer(OAuth2AuthorizationRequestCustomizers.withPkce());

    var salida = new OidcClientInitiatedLogoutSuccessHandler(registros);
    salida.setPostLogoutRedirectUri("{baseUrl}/");

    var csrf = new HttpSessionCsrfTokenRepository();
    csrf.setHeaderName("X-XSRF-TOKEN");

    return http
        .authorizeHttpRequests(a -> a.requestMatchers("/publico/**").permitAll().anyRequest().authenticated())
        .oauth2Login(o -> o.authorizationEndpoint(e -> e.authorizationRequestResolver(resolver)))
        .csrf(c -> c.csrfTokenRepository(csrf))
        .sessionManagement(s -> s.sessionFixation(f -> f.changeSessionId()))
        .logout(l -> l.logoutUrl("/salir").invalidateHttpSession(true).logoutSuccessHandler(salida))
        .build();
}
```

**Enlazar el usuario local.** Registrar un `OidcUserService` propio que busque al usuario por el
`sub` de Keycloak (o por correo la primera vez) y le cargue los roles de la aplicación.

**Si la aplicación corre en Docker** y el navegador llega a Keycloak por una dirección pública pero
el backend por una interna, `issuer-uri` no basta: SUILAFT construye el registro a mano con el
emisor público y los extremos internos de token, claves y datos de usuario (`OidcClientProperties`).

**Spring Boot 2.x con Java 8** usa la misma idea con `spring-security-oauth2-client` 5.x; cambian
algunos nombres de la configuración, no el flujo.

### 5.3 PHP puro, sin framework

#### Opción 1: un proxy de autenticación delante (casi sin tocar el código)

Para monolitos antiguos es lo más rápido y lo más seguro. El servidor web hace todo el flujo OIDC
con Keycloak antes de que la petición llegue a PHP, y a PHP le pasa la identidad ya verificada.

**Apache con `mod_auth_openidc`:**

```apache
OIDCProviderMetadataURL https://login.empresa.com/realms/MI_REALM/.well-known/openid-configuration
OIDCClientID            sistema-php
OIDCClientSecret        ${OIDC_CLIENT_SECRET}
OIDCRedirectURI         https://sistema.empresa.com/oidc/callback
OIDCCryptoPassphrase    ${OIDC_CRYPTO_PASSPHRASE}
OIDCPKCEMethod          S256
OIDCScope               "openid email profile"
OIDCRemoteUserClaim     email
OIDCSessionInactivityTimeout 1800

<Location />
  AuthType openid-connect
  Require valid-user
</Location>
```

**nginx con `oauth2-proxy`:** `oauth2-proxy` con `--provider=keycloak-oidc`,
`--code-challenge-method=S256` y `--set-xauthrequest`, y nginx con `auth_request` hacia él; la
identidad llega en la cabecera `X-Auth-Request-Email`.

En PHP basta con leer quién es:

```php
<?php
// Con mod_auth_openidc:
$correo = $_SERVER['REMOTE_USER'] ?? null;
// Con oauth2-proxy:
$correo = $_SERVER['HTTP_X_AUTH_REQUEST_EMAIL'] ?? null;

if ($correo === null) { http_response_code(401); exit; }
$usuario = buscarUsuarioPorCorreo($correo);  // la tabla de usuarios del sistema, como hoy
```

> **Imprescindible.** PHP no debe ser alcanzable salvo a través del proxy. Si alguien llega
> directamente al PHP, puede inventarse la cabecera y entrar como quien quiera. Bloquéalo en la red
> o en el propio servidor web, y **borra en el proxy esas cabeceras si llegan del cliente**.

Con esta opción el formulario de acceso actual del sistema **se retira**: el acceso lo hace
Keycloak. El resto del código —permisos, pantallas, consultas— no cambia.

*Las configuraciones de esta opción son plantillas. No se han probado en esta guía; ajústalas y
pruébalas con el sistema concreto.*

#### Opción 2: una librería OIDC en PHP

Si el sistema puede usar Composer —aunque no tenga framework—, `jumbojett/openid-connect-php`
hace el flujo completo, incluida la verificación de la firma del token.

```php
<?php
require __DIR__ . '/vendor/autoload.php';
use Jumbojett\OpenIDConnectClient;

session_start();

$oidc = new OpenIDConnectClient(
    'https://login.empresa.com/realms/MI_REALM',
    'sistema-php',
    getenv('OIDC_CLIENT_SECRET')
);
$oidc->setRedirectURL('https://sistema.empresa.com/callback.php');
$oidc->setCodeChallengeMethod('S256');
$oidc->addScope(['openid', 'email', 'profile']);
$oidc->authenticate();          // redirige a Keycloak y, a la vuelta, valida el token

session_regenerate_id(true);    // sesión nueva al autenticarse: evita la fijación de sesión
$_SESSION['usuario'] = [
    'sub'    => $oidc->requestUserInfo('sub'),
    'correo' => $oidc->requestUserInfo('email'),
];
$_SESSION['id_token'] = $oidc->getIdToken();   // hace falta para cerrar sesión en Keycloak
```

Para salir: `session_destroy()` y `$oidc->signOut($idToken, 'https://sistema.empresa.com/')`.

> **No escribas a mano la verificación del token.** Comprobar la firma, el emisor, la audiencia y
> la caducidad de un JWT es exactamente donde se cuelan los fallos graves. Usa la librería o el proxy.

*Plantilla no probada en esta guía; comprobar la API de la versión de la librería que se instale.*

#### La sesión PHP, en cualquiera de las dos opciones

```php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');      // sólo por HTTPS
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
```

### 5.4 Pasar los usuarios existentes a Keycloak

- **Crear los usuarios** en Keycloak con su correo, con la API de administración o un archivo de
  importación. Enlazar cada uno con su usuario del sistema por correo o por un identificador.
- **Contraseñas.** Lo más limpio es **no migrarlas**: se crean sin contraseña y con las acciones
  `UPDATE_PASSWORD` y `CONFIGURE_TOTP`, y cada uno fija la suya en el primer acceso. Migrar los
  hashes sólo es posible si el algoritmo está entre los que Keycloak sabe leer; si no, requiere una
  extensión propia de Keycloak y no compensa.
- **Ver el hallazgo 3.3** antes de asignar `CONFIGURE_TOTP` en bloque.

---

## 6. Ruta B: TOTP dentro de la aplicación

Sólo cuando la ruta A no es posible.

### 6.1 Qué hay que guardar

```sql
CREATE TABLE usuario_segundo_factor (
    usuario_id          BIGINT PRIMARY KEY REFERENCES usuario(id),
    secreto_cifrado     VARBINARY(255) NOT NULL,   -- el secreto TOTP, CIFRADO
    activo              BOOLEAN NOT NULL DEFAULT FALSE,
    ultimo_paso_usado   BIGINT NULL,               -- impide reutilizar un código
    creado_en           TIMESTAMP NOT NULL,
    activado_en         TIMESTAMP NULL
);

CREATE TABLE usuario_codigo_recuperacion (
    id          BIGINT PRIMARY KEY AUTO_INCREMENT,
    usuario_id  BIGINT NOT NULL REFERENCES usuario(id),
    codigo_hash VARCHAR(255) NOT NULL,             -- password_hash(), nunca en claro
    usado_en    TIMESTAMP NULL
);
```

### 6.2 Los flujos

**Registro del autenticador**

1. Generar el secreto (`Totp::generarSecreto()`) y guardarlo **cifrado** con `activo = false`.
2. Mostrar el QR de `Totp::uriProvision($secreto, $correo, 'Nombre del sistema')`, generado en el
   servidor con una librería de QR. **No mandes el secreto a un servicio externo para pintar el QR.**
3. Pedir el primer código. Sólo si es correcto, marcar `activo = true`. Así nadie se queda activado
   con un autenticador mal configurado y fuera del sistema.
4. Generar 8 a 10 códigos de recuperación de un solo uso, mostrarlos **una vez** y guardarlos
   con `password_hash()`.

**Acceso**

1. Contraseña correcta ⇒ guardar en sesión `pendiente_2fa = usuario_id`. **Todavía no está dentro.**
2. Pantalla del código. `Totp::verificar()` devuelve el paso de tiempo que casó.
3. Si el paso es menor o igual que `ultimo_paso_usado`, rechazar: ese código ya se usó.
4. Si es válido: guardar el paso, `session_regenerate_id(true)`, dar la sesión por completa.
5. Toda página protegida comprueba la sesión completa, **no** `pendiente_2fa`.

**Móvil perdido.** El usuario entra con un código de recuperación y registra de nuevo su
autenticador. Si tampoco los tiene, un administrador desactiva su segundo factor tras verificar su
identidad por otro canal, y el sistema lo obliga a registrarlo otra vez al entrar. Deja constancia
en auditoría.

### 6.3 Requisitos que no se negocian

- **Secreto cifrado** con una clave que no esté en la base de datos ni en el repositorio.
- **Límite de intentos** del código: por ejemplo, 5 por usuario en 15 minutos, además de por IP.
  Seis dígitos son un millón de combinaciones: sin límite se adivinan.
- **Sin reutilización** del mismo código (paso 3 del acceso).
- **Ventana de ±1 paso** (30 segundos a cada lado). Más amplitud facilita la fuerza bruta.
- **Hora del servidor sincronizada** por NTP. Un servidor que se atrasa dos minutos rechaza todos
  los códigos.
- **Comparación en tiempo constante** (`hash_equals` en PHP, `MessageDigest.isEqual` en Java).
- **Nunca** registrar en logs el secreto, el código ni los códigos de recuperación.

### 6.4 Implementaciones

- **PHP:** `referencia/Totp.php`, sin dependencias, probada con `php referencia/TotpTest.php`
  contra los 18 vectores del apéndice B del RFC 6238 en SHA-1, SHA-256 y SHA-512. Si el sistema
  usa Composer, `spomky-labs/otphp` y `robthree/twofactorauth` son alternativas mantenidas.
- **Spring Boot:** `dev.samstevens.totp:totp` para generar, verificar y pintar el QR, o el mismo
  algoritmo de `Totp.php` con `javax.crypto.Mac`.

---

## 7. Lista de verificación antes de salir a producción

- [ ] Todo usuario interactivo tiene que registrar el autenticador; no hay forma de saltárselo.
- [ ] Algoritmo `SHA1`, 6 dígitos, 30 segundos, y probado con Google **y** Microsoft Authenticator.
- [ ] Bloqueo tras intentos fallidos, temporal y no permanente.
- [ ] Sesión nueva tras autenticarse (fijación de sesión).
- [ ] Cookies `HttpOnly`, `Secure` y `SameSite`.
- [ ] Cerrar sesión cierra también en Keycloak (ruta A).
- [ ] Los secretos del cliente están fuera del repositorio.
- [ ] Hay un procedimiento escrito para quien pierde el móvil, y alguien que lo atiende.
- [ ] Hora del servidor sincronizada.
- [ ] Nada sensible en los logs.
- [ ] En la opción del proxy, PHP no es alcanzable sin pasar por él.
- [ ] Probado entero con un usuario real: alta, registro del autenticador, acceso, código
      equivocado, código repetido, bloqueo, salida y móvil perdido.

---

## 8. Cómo implantarlo sin dejar a nadie fuera

1. **Piloto.** Un sistema y pocos usuarios técnicos. Se ajustan pantallas, correos y el
   procedimiento de soporte.
2. **Administradores.** Todas las cuentas con privilegios de todos los sistemas. Son las que más
   importa proteger y las de gente que puede ayudar a los demás.
3. **Aviso a los usuarios** con una semana de margen: qué aplicación instalar, cómo se ve el QR,
   qué hacer si cambian de móvil.
4. **Resto de usuarios**, sistema por sistema.
5. **Revisión** a las dos semanas: bloqueos, llamadas a soporte, quién no ha registrado aún.

---

## 9. Para la IA que lea esta guía

Tu trabajo es ayudar a implantar el segundo factor en **un sistema concreto**. Antes de escribir
código:

1. **Inspecciona el sistema.** Lenguaje y versión, si usa framework o Composer, dónde está hoy el
   formulario de acceso, cómo se guarda la sesión, qué servidor web tiene delante, si llega por red
   al Keycloak de la empresa.
2. **Elige la ruta con la tabla de la sección 4** y di por qué. Si eliges B, explica qué impide A.
3. **Propón el plan** con los archivos que vas a tocar, antes de tocarlos.
4. **Cumple la sección 6.3** si vas por B y la advertencia del proxy si vas por A-opción 1. No las
   rebajes para simplificar.
5. **No escribas a mano la verificación de tokens JWT**, ni la criptografía de TOTP si hay una
   librería mantenida o puedes usar `referencia/Totp.php`.
6. **No guardes secretos en el código** ni en archivos del repositorio.
7. **Verifica con la sección 7** y di qué comprobaste de verdad y qué no.

**Para: pregunta antes de seguir** si te piden guardar el secreto TOTP sin cifrar, quitar el límite
de intentos, desactivar el segundo factor para «facilitar las pruebas» en producción, o dar por
válido el acceso sólo con la contraseña «de momento». Explica el riesgo en una frase y ofrece la
alternativa segura.
