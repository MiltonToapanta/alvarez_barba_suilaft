<?php
/** Aviso cuando no hay sesión del portal, el rol no tiene el módulo o falla la base. */
$textos = [
    'sesion' => ['Tu sesión del portal expiró', 'Vuelve a iniciar sesión en el portal para usar la Inteligencia Artificial.', 'Ir al inicio de sesión', '/login'],
    'permiso' => ['Sin acceso a este módulo', 'Tu rol no tiene habilitada la Inteligencia Artificial. Pide acceso al administrador.', 'Volver al portal', '/'],
    'error' => ['No pude abrir la Inteligencia Artificial', 'Falló la conexión con la base de datos del módulo. Avisa a soporte.', 'Volver al portal', '/'],
];
[$titulo, $texto, $boton, $destino] = $textos[$motivo] ?? $textos['error'];
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($titulo); ?> · SUILAFT AI</title>
    <style>
      body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #faf8f4; color: #1c1b19;
             font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
      .caja { max-width: 420px; text-align: center; padding: 2rem; }
      h1 { font-family: Georgia, serif; font-weight: 600; font-size: 1.6rem; margin: 0 0 .6rem; }
      p { color: #5b564d; line-height: 1.5; margin: 0 0 1.4rem; }
      a { display: inline-block; background: #212123; color: #fff; text-decoration: none; padding: .65rem 1.2rem; border-radius: 10px; font-weight: 600; }
      a:hover { background: #3a3732; }
    </style>
  </head>
  <body>
    <div class="caja">
      <h1><?php echo h($titulo); ?></h1>
      <p><?php echo h($texto); ?></p>
      <a href="<?php echo h($destino); ?>" target="_top"><?php echo h($boton); ?></a>
    </div>
  </body>
</html>
