---
name: segundo-factor
description: Implantar el segundo factor de autenticación con aplicación autenticadora (TOTP: Google Authenticator, Microsoft Authenticator, FreeOTP) en un sistema, siguiendo cómo lo hace SUILAFT con Keycloak. Sirve para Spring Boot y para PHP, incluido PHP puro sin framework. Úsalo cuando pidan «segundo factor», «2FA», «MFA», «doble autenticación», «TOTP», «código del autenticador», «Google Authenticator», «conectar a Keycloak», «inicio de sesión único» o «proteger el acceso» de un sistema.
---

# Segundo factor de autenticación

La guía completa está en `GUIA.md`, en esta misma carpeta. **Léela entera antes de proponer nada**:
explica cómo lo hace SUILAFT, qué ruta elegir, el código de cada una y los fallos ya conocidos.

`referencia/Totp.php` es una implementación de TOTP en PHP sin dependencias, probada contra los
vectores oficiales del RFC 6238. Se comprueba con `php referencia/TotpTest.php`.

## Cómo trabajar

1. **Inspecciona el sistema antes de escribir nada.** Lenguaje y versión; framework y Composer;
   dónde está el formulario de acceso actual; cómo se guarda la sesión; qué servidor web tiene
   delante; si llega por red al Keycloak de la empresa.
2. **Elige la ruta** con la tabla de la sección 4 de la guía y di por qué:
   - **A — delegar en Keycloak.** La preferida. En PHP puro sin Composer, con un proxy de
     autenticación delante (`mod_auth_openidc` o `oauth2-proxy`).
   - **B — TOTP dentro de la aplicación.** Sólo si A no es posible; explica qué lo impide.
3. **Presenta el plan** con los archivos que vas a tocar y espera conformidad si el cambio retira el
   formulario de acceso actual o toca la tabla de usuarios.
4. **Implanta** siguiendo la guía.
5. **Verifica** con la lista de la sección 7 y di qué comprobaste de verdad y qué quedó sin probar.

## Lo que no se negocia

- No escribir a mano la verificación de tokens JWT: librería OIDC o proxy.
- En la ruta B: secreto TOTP **cifrado**, límite de intentos, sin reutilizar códigos, ventana de
  ±1 paso, sesión regenerada al completar el segundo factor, códigos de recuperación con hash.
- Con proxy delante: PHP inalcanzable salvo a través del proxy, y cabeceras de identidad borradas
  si vienen del cliente.
- Algoritmo `SHA1`, 6 dígitos y 30 segundos, para que funcione Microsoft Authenticator.
- Ningún secreto en el código, en el repositorio ni en los logs.

## Cuándo parar y preguntar

Si piden guardar el secreto sin cifrar, quitar el límite de intentos, desactivar el segundo factor
en producción «para probar», o aceptar sólo la contraseña «de momento»: explica el riesgo en una
frase, ofrece la alternativa segura y espera respuesta.

## Para llevarla a otro proyecto

Copia esta carpeta entera a `.claude/skills/segundo-factor/` del otro repositorio. Van juntos
`SKILL.md`, `GUIA.md` y `referencia/`.
