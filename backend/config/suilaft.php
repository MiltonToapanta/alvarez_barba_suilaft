<?php

return [
    'sf_emisor' => env('SF_EMISOR', 'SUIAUTO Alvarez Barba'),
    // Tiempo máximo entre la contraseña correcta y el código del Authenticator.
    'sf_pendiente_segundos' => (int) env('SF_PENDIENTE_SEGUNDOS', 300),
    'sf_max_intentos' => (int) env('SF_MAX_INTENTOS', 5),
    'sf_ventana_minutos' => (int) env('SF_VENTANA_MINUTOS', 15),
];
