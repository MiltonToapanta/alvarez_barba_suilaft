-- Estructura replicada de SUIAUTO (sin datos). Generado por extraer_schema.py
SET NAMES utf8mb4;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `actividad_economica` (
  `id_actividad` int(11) NOT NULL,
  `codigo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `nivel` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_conversaciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(160) NOT NULL DEFAULT 'Nueva conversación',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `funcion` varchar(40) NOT NULL,
  `modelo` varchar(80) DEFAULT NULL,
  `duracion_ms` int(10) UNSIGNED DEFAULT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `tokens` int(10) UNSIGNED DEFAULT NULL,
  `error_codigo` varchar(40) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ai_mensajes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `conversacion_id` int(10) UNSIGNED NOT NULL,
  `rol` enum('usuario','asistente') NOT NULL,
  `contenido` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `alertas` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('riesgo','transaccion','lista','vencimiento','perfil') NOT NULL,
  `severidad` enum('baja','media','alta','critica') NOT NULL DEFAULT 'media',
  `origen_tipo` varchar(20) DEFAULT NULL,
  `origen_id` int(10) UNSIGNED DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('abierta','en_revision','cerrada','descartada') NOT NULL DEFAULT 'abierta',
  `asignado_a` varchar(150) DEFAULT NULL,
  `resolucion` text DEFAULT NULL,
  `clave_dedupe` varchar(190) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resuelto_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auditoria` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `usuario_nombre` varchar(150) DEFAULT NULL,
  `modulo` varchar(30) NOT NULL,
  `accion` varchar(30) NOT NULL,
  `entidad_tipo` varchar(30) DEFAULT NULL,
  `entidad_id` varchar(30) DEFAULT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `canton` (
  `id` bigint(20) NOT NULL,
  `cod_canton` varchar(10) DEFAULT NULL,
  `canton` text NOT NULL,
  `id_provincia` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

CREATE TABLE `casos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL DEFAULT '',
  `titulo` varchar(200) NOT NULL,
  `sujeto_tipo` varchar(20) DEFAULT NULL,
  `sujeto_id` int(11) DEFAULT NULL,
  `estado` enum('abierto','en_analisis','cerrado') NOT NULL DEFAULT 'abierto',
  `analista_asignado` varchar(150) DEFAULT NULL,
  `conclusion` text DEFAULT NULL,
  `abierto_por` varchar(150) DEFAULT NULL,
  `abierto_en` datetime NOT NULL DEFAULT current_timestamp(),
  `cerrado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `casos_vinculos` (
  `id` int(11) NOT NULL,
  `caso_id` int(11) NOT NULL,
  `tipo` enum('alerta','kyc','operacion') NOT NULL,
  `referencia_id` int(11) NOT NULL,
  `referencia_codigo` varchar(20) DEFAULT NULL,
  `agregado_por` varchar(150) DEFAULT NULL,
  `agregado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_condicion_bien` (
  `codigo` char(1) NOT NULL,
  `descripcion` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_dpa_canton` (
  `codigo` char(4) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `cod_provincia` char(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_dpa_parroquia` (
  `codigo` char(6) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `cod_canton` char(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_dpa_provincia` (
  `codigo` char(2) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_forma_pago` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_nivel_blindaje` (
  `codigo` varchar(2) NOT NULL,
  `descripcion` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_operacion_transaccion` (
  `tipo_operacion` char(3) NOT NULL,
  `tipo_transaccion` varchar(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_origen_fondos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_tipo_bien` (
  `codigo` char(3) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `clase` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_tipo_financiamiento` (
  `codigo` char(3) NOT NULL,
  `descripcion` varchar(40) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_tipo_identificacion` (
  `codigo` char(1) NOT NULL,
  `descripcion` varchar(40) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_tipo_identificacion_tercero` (
  `codigo` char(1) NOT NULL,
  `descripcion` varchar(40) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_tipo_operacion` (
  `codigo` char(3) NOT NULL,
  `descripcion` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_tipo_pago_cobro` (
  `codigo` char(3) NOT NULL,
  `descripcion` varchar(40) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_tipo_persona` (
  `codigo` char(1) NOT NULL,
  `descripcion` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cat_tipo_transaccion` (
  `codigo` varchar(3) NOT NULL,
  `descripcion` varchar(120) NOT NULL,
  `permite_debito` tinyint(1) NOT NULL DEFAULT 0,
  `permite_credito` tinyint(1) NOT NULL DEFAULT 0,
  `permite_efectivo` tinyint(1) NOT NULL DEFAULT 0,
  `permite_cheque` tinyint(1) NOT NULL DEFAULT 0,
  `permite_tarjeta` tinyint(1) NOT NULL DEFAULT 0,
  `permite_titulo` tinyint(1) NOT NULL DEFAULT 0,
  `permite_total` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ciudad` (
  `id` int(10) UNSIGNED NOT NULL,
  `cod_canton` varchar(50) DEFAULT NULL,
  `ciudad` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `debida_diligencia` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `kyc_registro_id` int(10) UNSIGNED NOT NULL,
  `estado` enum('pendiente','en_revision','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `resultado_listas` enum('sin_coincidencias','potencial','confirmada') DEFAULT NULL,
  `resultado_pep` enum('no_pep','pep','pendiente') DEFAULT 'pendiente',
  `validacion_documental` enum('completa','incompleta','no_validada') DEFAULT 'no_validada',
  `observaciones` text DEFAULT NULL,
  `conclusion` text DEFAULT NULL,
  `recomendacion` text DEFAULT NULL,
  `revisado_por` varchar(150) DEFAULT NULL,
  `revisado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `debida_diligencia_archivos` (
  `id` int(10) UNSIGNED NOT NULL,
  `debida_id` int(10) UNSIGNED NOT NULL,
  `categoria` enum('debida','informe') NOT NULL DEFAULT 'debida',
  `nombre_ruta` varchar(255) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamano` int(10) UNSIGNED NOT NULL,
  `subido_por` varchar(150) DEFAULT NULL,
  `subido_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `firma_consentimientos` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `metodo` varchar(10) NOT NULL,
  `version` varchar(20) NOT NULL,
  `huella_texto` char(64) NOT NULL,
  `aceptado_en` datetime NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `agente` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `firma_solicitudes` (
  `id` int(11) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `registro_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `sesion_hash` char(64) DEFAULT NULL,
  `estado` varchar(15) NOT NULL DEFAULT 'enviado',
  `minutos_validez` int(11) NOT NULL,
  `expira_en` datetime NOT NULL,
  `correo_destino` varchar(150) NOT NULL,
  `correo_enviado` tinyint(1) NOT NULL DEFAULT 0,
  `enviado_por` int(11) DEFAULT NULL,
  `enviado_por_nombre` varchar(150) DEFAULT NULL,
  `abierto_en` datetime DEFAULT NULL,
  `abierto_ip` varchar(45) DEFAULT NULL,
  `autorizado_en` datetime DEFAULT NULL,
  `autorizado_ip` varchar(45) DEFAULT NULL,
  `autorizado_agente` varchar(255) DEFAULT NULL,
  `intento` int(11) NOT NULL DEFAULT 0,
  `nexus_tramite` varchar(80) DEFAULT NULL,
  `nexus_enlace` varchar(500) DEFAULT NULL,
  `nexus_firmas` int(11) DEFAULT NULL,
  `nexus_horas` int(11) DEFAULT NULL,
  `estado_firma` varchar(30) DEFAULT NULL,
  `estado_consentimiento` varchar(30) DEFAULT NULL,
  `estado_dactilar` varchar(30) DEFAULT NULL,
  `estado_biometria` varchar(30) DEFAULT NULL,
  `callback_hash` char(64) DEFAULT NULL,
  `pdf_original` varchar(255) DEFAULT NULL,
  `pdf_original_sha256` char(64) DEFAULT NULL,
  `pdf_firmado` varchar(255) DEFAULT NULL,
  `pdf_firmado_sha256` char(64) DEFAULT NULL,
  `firmado_en` datetime DEFAULT NULL,
  `fallo_codigo` varchar(60) DEFAULT NULL,
  `fallo_motivo` varchar(500) DEFAULT NULL,
  `ultimo_error` varchar(500) DEFAULT NULL,
  `ultima_consulta` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL,
  `actualizado_en` datetime NOT NULL,
  `metodo` varchar(10) NOT NULL DEFAULT 'nexus',
  `suifirma_id` varchar(36) DEFAULT NULL,
  `certificado_titular` varchar(300) DEFAULT NULL,
  `certificado_emisor` varchar(300) DEFAULT NULL,
  `certificado_serie` varchar(80) DEFAULT NULL,
  `certificado_hasta` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kyc_adjuntos` (
  `id` int(11) NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `registro_id` int(11) NOT NULL,
  `documento_clave` varchar(60) NOT NULL,
  `archivo_ruta` varchar(255) DEFAULT NULL,
  `archivo_nombre_original` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','cargado') NOT NULL DEFAULT 'pendiente',
  `subido_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kyc_beneficiario_final` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `origen` enum('interno','externo') NOT NULL DEFAULT 'interno',
  `entidad_razon_social` varchar(255) DEFAULT NULL,
  `entidad_ruc` varchar(20) DEFAULT NULL,
  `tipo_relacion` varchar(30) DEFAULT NULL,
  `nombres_completos` varchar(255) DEFAULT NULL,
  `cedula_pasaporte` varchar(30) DEFAULT NULL,
  `nacionalidad` varchar(5) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `pais_residencia` varchar(5) DEFAULT NULL,
  `ciudad_residencia` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `ocupacion_actividad` varchar(150) DEFAULT NULL,
  `participacion_directa` varchar(20) DEFAULT NULL,
  `participacion_indirecta` varchar(20) DEFAULT NULL,
  `mecanismo_control` varchar(255) DEFAULT NULL,
  `cadena_propiedad_adjunta` varchar(5) DEFAULT NULL,
  `es_pep` varchar(5) DEFAULT NULL,
  `familiar_pep` varchar(5) DEFAULT NULL,
  `listas_restrictivas` varchar(20) DEFAULT NULL,
  `noticias_adversas` varchar(5) DEFAULT NULL,
  `fuente_riqueza` varchar(255) DEFAULT NULL,
  `patrimonio_aproximado` varchar(100) DEFAULT NULL,
  `origen_fondos_relacionados` text DEFAULT NULL,
  `soportes_estado` varchar(20) DEFAULT NULL,
  `doc_identidad_estado` varchar(20) DEFAULT NULL,
  `listas_pep_estado` varchar(20) DEFAULT NULL,
  `soportes_economicos_estado` varchar(20) DEFAULT NULL,
  `soportes_relacion_estado` varchar(20) DEFAULT NULL,
  `observaciones_analista` text DEFAULT NULL,
  `estado_expediente` varchar(20) DEFAULT NULL,
  `version_matriz_institucional` varchar(30) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `analista_responsable` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `apellidos` varchar(120) DEFAULT NULL,
  `nombres` varchar(120) DEFAULT NULL,
  `provincia_cod` varchar(2) DEFAULT NULL,
  `canton_cod` varchar(4) DEFAULT NULL,
  `celular` varchar(30) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kyc_cliente_pj` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `origen` enum('interno','externo') NOT NULL DEFAULT 'interno',
  `razon_social` varchar(255) DEFAULT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `nombre_comercial` varchar(255) DEFAULT NULL,
  `fecha_constitucion` date DEFAULT NULL,
  `pais_constitucion` varchar(5) DEFAULT NULL,
  `domicilio_principal` varchar(255) DEFAULT NULL,
  `actividad_economica_id` int(11) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `web` varchar(150) DEFAULT NULL,
  `rl_nombres` varchar(255) DEFAULT NULL,
  `rl_cedula` varchar(30) DEFAULT NULL,
  `rl_nacionalidad` varchar(5) DEFAULT NULL,
  `rl_cargo_fecha` varchar(150) DEFAULT NULL,
  `rl_es_pep` varchar(5) DEFAULT NULL,
  `rl_listas` varchar(20) DEFAULT NULL,
  `accionistas_socios` text DEFAULT NULL,
  `estructura_simple` varchar(30) DEFAULT NULL,
  `bf_identificados` varchar(5) DEFAULT NULL,
  `num_beneficiarios` varchar(10) DEFAULT NULL,
  `kyc_bf_anexos` varchar(20) DEFAULT NULL,
  `ingresos_ventas_anuales` varchar(100) DEFAULT NULL,
  `patrimonio_activos` varchar(100) DEFAULT NULL,
  `productos_servicios` text DEFAULT NULL,
  `mercados_clientes` text DEFAULT NULL,
  `paises_opera` varchar(255) DEFAULT NULL,
  `tipo_relacion` varchar(30) DEFAULT NULL,
  `volumen_esperado` varchar(150) DEFAULT NULL,
  `ticket_promedio` varchar(100) DEFAULT NULL,
  `frecuencia` varchar(150) DEFAULT NULL,
  `forma_pago_id` int(10) UNSIGNED DEFAULT NULL,
  `pagos_terceros` varchar(30) DEFAULT NULL,
  `banco_pais_origen` varchar(150) DEFAULT NULL,
  `doc_identidad_estado` varchar(20) DEFAULT NULL,
  `listas_pep_estado` varchar(20) DEFAULT NULL,
  `soportes_economicos_estado` varchar(20) DEFAULT NULL,
  `soportes_relacion_estado` varchar(20) DEFAULT NULL,
  `observaciones_analista` text DEFAULT NULL,
  `estado_expediente` varchar(20) DEFAULT NULL,
  `version_matriz_institucional` varchar(30) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `analista_responsable` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rl_apellidos` varchar(120) DEFAULT NULL,
  `rl_nombres_pila` varchar(120) DEFAULT NULL,
  `rl_correo` varchar(150) DEFAULT NULL,
  `rl_celular` varchar(30) DEFAULT NULL,
  `rl_provincia_cod` varchar(2) DEFAULT NULL,
  `rl_canton_cod` varchar(4) DEFAULT NULL,
  `rl_direccion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kyc_cliente_pj_sp` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `origen` enum('interno','externo') NOT NULL DEFAULT 'interno',
  `fecha_registro` date DEFAULT NULL,
  `ciudad_registro` varchar(150) DEFAULT NULL,
  `tipo_tramite` varchar(15) DEFAULT NULL,
  `numero_expediente` varchar(50) DEFAULT NULL,
  `denominacion_oficial` varchar(255) DEFAULT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `nombre_comercial` varchar(255) DEFAULT NULL,
  `tipo_entidad` varchar(30) DEFAULT NULL,
  `tipo_entidad_otro_detalle` varchar(255) DEFAULT NULL,
  `acto_creacion` varchar(255) DEFAULT NULL,
  `registro_publicacion` varchar(255) DEFAULT NULL,
  `entidad_adscripcion` varchar(255) DEFAULT NULL,
  `actividad_economica_id` int(11) DEFAULT NULL,
  `domicilio_institucional` varchar(255) DEFAULT NULL,
  `telefono_institucional` varchar(50) DEFAULT NULL,
  `correo_institucional` varchar(150) DEFAULT NULL,
  `sitio_web` varchar(255) DEFAULT NULL,
  `nombres_representante` varchar(255) DEFAULT NULL,
  `cedula_representante` varchar(30) DEFAULT NULL,
  `cargo_representante` varchar(150) DEFAULT NULL,
  `nacionalidad_representante` varchar(5) DEFAULT NULL,
  `nombramiento_vigencia` varchar(255) DEFAULT NULL,
  `delegacion_alcance` varchar(255) DEFAULT NULL,
  `contacto_representante` varchar(150) DEFAULT NULL,
  `titularidad_participacion` varchar(15) DEFAULT NULL,
  `detalle_participacion_mixta` varchar(150) DEFAULT NULL,
  `entidades_publicas_participantes` text DEFAULT NULL,
  `socios_accionistas_privados` varchar(10) DEFAULT NULL,
  `detalle_socios_privados` text DEFAULT NULL,
  `autoridades_organo_direccion` text DEFAULT NULL,
  `beneficiario_final_control` varchar(30) DEFAULT NULL,
  `criterio_identificacion_bf` text DEFAULT NULL,
  `presupuesto_anual` varchar(100) DEFAULT NULL,
  `anio_presupuesto` varchar(10) DEFAULT NULL,
  `activos_patrimonio` varchar(100) DEFAULT NULL,
  `fecha_patrimonio` date DEFAULT NULL,
  `fuente_financiamiento` varchar(25) DEFAULT NULL,
  `fuente_financiamiento_otro` varchar(150) DEFAULT NULL,
  `origen_especifico_fondos` text DEFAULT NULL,
  `banco_pais_titular_cuenta` text DEFAULT NULL,
  `tipo_finalidad_relacion` varchar(20) DEFAULT NULL,
  `uso_destino_institucional` varchar(255) DEFAULT NULL,
  `monto_relacion` varchar(100) DEFAULT NULL,
  `numero_vehiculos_operaciones` varchar(50) DEFAULT NULL,
  `frecuencia_relacion` varchar(100) DEFAULT NULL,
  `contrato_proceso_contratacion` varchar(255) DEFAULT NULL,
  `soporte_presupuestario` varchar(255) DEFAULT NULL,
  `forma_pago` varchar(15) DEFAULT NULL,
  `forma_pago_otro` varchar(100) DEFAULT NULL,
  `intervienen_terceros` varchar(5) DEFAULT NULL,
  `detalle_terceros` text DEFAULT NULL,
  `es_pep` varchar(15) DEFAULT NULL,
  `detalle_pep` text DEFAULT NULL,
  `personas_vinculadas_operacion` text DEFAULT NULL,
  `coincidencias_listas` varchar(30) DEFAULT NULL,
  `fuente_evidencia_listas` text DEFAULT NULL,
  `doc_identidad_estado` varchar(20) DEFAULT NULL,
  `listas_pep_estado` varchar(20) DEFAULT NULL,
  `soportes_economicos_estado` varchar(20) DEFAULT NULL,
  `soportes_relacion_estado` varchar(20) DEFAULT NULL,
  `observaciones_analista` text DEFAULT NULL,
  `estado_expediente` varchar(20) DEFAULT NULL,
  `version_matriz_institucional` varchar(30) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `analista_responsable` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rl_apellidos` varchar(120) DEFAULT NULL,
  `rl_nombres_pila` varchar(120) DEFAULT NULL,
  `rl_correo` varchar(150) DEFAULT NULL,
  `rl_celular` varchar(30) DEFAULT NULL,
  `rl_provincia_cod` varchar(2) DEFAULT NULL,
  `rl_canton_cod` varchar(4) DEFAULT NULL,
  `rl_direccion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kyc_cliente_pn` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `origen` enum('interno','externo') NOT NULL DEFAULT 'interno',
  `nombres_completos` varchar(255) DEFAULT NULL,
  `cedula_pasaporte` varchar(30) DEFAULT NULL,
  `nacionalidad` varchar(5) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `pais_residencia` varchar(5) DEFAULT NULL,
  `ciudad_residencia` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `actua_cuenta_propia` varchar(5) DEFAULT NULL,
  `tercero_mandante` varchar(255) DEFAULT NULL,
  `ocupacion_profesion` varchar(150) DEFAULT NULL,
  `empleador_negocio` varchar(150) DEFAULT NULL,
  `actividad_economica_id` int(11) DEFAULT NULL,
  `ingresos_mensuales` varchar(100) DEFAULT NULL,
  `patrimonio_aproximado` varchar(100) DEFAULT NULL,
  `fuente_ingresos_id` int(10) UNSIGNED DEFAULT NULL,
  `fuente_patrimonio_id` int(10) UNSIGNED DEFAULT NULL,
  `es_pep` varchar(5) DEFAULT NULL,
  `familiar_pep` varchar(5) DEFAULT NULL,
  `coincidencias_listas` varchar(20) DEFAULT NULL,
  `noticias_adversas` varchar(5) DEFAULT NULL,
  `noticias_adversas_detalle` text DEFAULT NULL,
  `observaciones_screening` text DEFAULT NULL,
  `motivo` varchar(30) DEFAULT NULL,
  `tipo_vehiculo` varchar(30) DEFAULT NULL,
  `valor_estimado` varchar(100) DEFAULT NULL,
  `frecuencia_esperada` varchar(150) DEFAULT NULL,
  `forma_pago_id` int(10) UNSIGNED DEFAULT NULL,
  `intervienen_terceros` varchar(5) DEFAULT NULL,
  `terceros_detalle` varchar(255) DEFAULT NULL,
  `banco_entidad_origen` varchar(150) DEFAULT NULL,
  `pais_origen_fondos` varchar(5) DEFAULT NULL,
  `origen_especifico_fondos` text DEFAULT NULL,
  `soporte` varchar(30) DEFAULT NULL,
  `coherencia_perfil` varchar(10) DEFAULT NULL,
  `justificacion_analista` text DEFAULT NULL,
  `doc_identidad_estado` varchar(20) DEFAULT NULL,
  `listas_pep_estado` varchar(20) DEFAULT NULL,
  `soportes_economicos_estado` varchar(20) DEFAULT NULL,
  `soportes_relacion_estado` varchar(20) DEFAULT NULL,
  `observaciones_analista` text DEFAULT NULL,
  `estado_expediente` varchar(20) DEFAULT NULL,
  `version_matriz_institucional` varchar(30) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `analista_responsable` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `apellidos` varchar(120) DEFAULT NULL,
  `nombres` varchar(120) DEFAULT NULL,
  `provincia_cod` varchar(2) DEFAULT NULL,
  `canton_cod` varchar(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kyc_cliente_pn_sp` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `origen` enum('interno','externo') NOT NULL DEFAULT 'interno',
  `fecha_registro` date DEFAULT NULL,
  `ciudad_registro` varchar(150) DEFAULT NULL,
  `numero_expediente` varchar(50) DEFAULT NULL,
  `tipo_tramite` varchar(15) DEFAULT NULL,
  `nombres_completos` varchar(255) DEFAULT NULL,
  `tipo_identificacion` varchar(15) DEFAULT NULL,
  `numero_identificacion` varchar(30) DEFAULT NULL,
  `nacionalidad` varchar(5) DEFAULT NULL,
  `lugar_nacimiento` varchar(150) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `estado_civil` varchar(50) DEFAULT NULL,
  `pais_residencia` varchar(5) DEFAULT NULL,
  `ruc_tributario` varchar(20) DEFAULT NULL,
  `domicilio_provincia_canton_parroquia` varchar(255) DEFAULT NULL,
  `direccion_referencia` varchar(255) DEFAULT NULL,
  `telefono_celular` varchar(50) DEFAULT NULL,
  `correo_personal_institucional` varchar(150) DEFAULT NULL,
  `conyuge_aplica` varchar(15) DEFAULT NULL,
  `conyuge_nombres` varchar(255) DEFAULT NULL,
  `conyuge_identificacion_actividad` varchar(255) DEFAULT NULL,
  `institucion` varchar(255) DEFAULT NULL,
  `ruc_institucion` varchar(20) DEFAULT NULL,
  `tipo_institucion` varchar(25) DEFAULT NULL,
  `tipo_institucion_otro` varchar(255) DEFAULT NULL,
  `cargo_area_funciones` varchar(255) DEFAULT NULL,
  `modalidad_vinculacion` varchar(20) DEFAULT NULL,
  `modalidad_vinculacion_otro` varchar(150) DEFAULT NULL,
  `fecha_ingreso_cargo` date DEFAULT NULL,
  `fecha_cese_cargo` date DEFAULT NULL,
  `direccion_telefono_laboral` varchar(255) DEFAULT NULL,
  `otras_actividades_economicas` varchar(5) DEFAULT NULL,
  `detalle_otras_actividades` text DEFAULT NULL,
  `remuneracion_mensual` varchar(100) DEFAULT NULL,
  `otros_ingresos_mensuales` varchar(100) DEFAULT NULL,
  `ingresos_totales_mes` varchar(100) DEFAULT NULL,
  `egresos_totales_mes` varchar(100) DEFAULT NULL,
  `activos` varchar(100) DEFAULT NULL,
  `pasivos` varchar(100) DEFAULT NULL,
  `patrimonio` varchar(100) DEFAULT NULL,
  `fuente_otros_ingresos` varchar(255) DEFAULT NULL,
  `origen_fondos_operacion` varchar(20) DEFAULT NULL,
  `origen_fondos_otro` varchar(150) DEFAULT NULL,
  `detalle_soporte_origen` text DEFAULT NULL,
  `banco_pais_titular_cuenta` text DEFAULT NULL,
  `tipo_finalidad_relacion` varchar(20) DEFAULT NULL,
  `vehiculo_interes` varchar(255) DEFAULT NULL,
  `monto_relacion` varchar(100) DEFAULT NULL,
  `frecuencia_esperada` varchar(100) DEFAULT NULL,
  `forma_pago` varchar(15) DEFAULT NULL,
  `forma_pago_otro` varchar(100) DEFAULT NULL,
  `actua_cuenta_tercero` varchar(5) DEFAULT NULL,
  `detalle_tercero_beneficiario` text DEFAULT NULL,
  `pagos_terceros` varchar(5) DEFAULT NULL,
  `detalle_pagos_terceros` text DEFAULT NULL,
  `es_pep` varchar(5) DEFAULT NULL,
  `vinculo_pep` varchar(5) DEFAULT NULL,
  `detalle_cargo_vinculo_pep` text DEFAULT NULL,
  `inicio_cese_cargo_pep` varchar(150) DEFAULT NULL,
  `evaluacion_interna_pep` varchar(30) DEFAULT NULL,
  `fuente_fecha_evaluacion_pep` varchar(150) DEFAULT NULL,
  `doc_identidad_estado` varchar(20) DEFAULT NULL,
  `listas_pep_estado` varchar(20) DEFAULT NULL,
  `soportes_economicos_estado` varchar(20) DEFAULT NULL,
  `soportes_relacion_estado` varchar(20) DEFAULT NULL,
  `observaciones_analista` text DEFAULT NULL,
  `estado_expediente` varchar(20) DEFAULT NULL,
  `version_matriz_institucional` varchar(30) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `analista_responsable` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `apellidos` varchar(120) DEFAULT NULL,
  `nombres` varchar(120) DEFAULT NULL,
  `provincia_cod` varchar(2) DEFAULT NULL,
  `canton_cod` varchar(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kyc_empleado` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `origen` enum('interno','externo') NOT NULL DEFAULT 'interno',
  `nombres_completos` varchar(255) DEFAULT NULL,
  `cedula_pasaporte` varchar(30) DEFAULT NULL,
  `nacionalidad` varchar(5) DEFAULT NULL,
  `residencia` varchar(255) DEFAULT NULL,
  `cargo_area` varchar(150) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `remuneracion` varchar(100) DEFAULT NULL,
  `jefe_inmediato` varchar(150) DEFAULT NULL,
  `maneja_caja_pagos` varchar(5) DEFAULT NULL,
  `acceso_datos_personales` varchar(5) DEFAULT NULL,
  `acceso_sistemas_criticos` varchar(5) DEFAULT NULL,
  `autoriza_descuentos` varchar(5) DEFAULT NULL,
  `interviene_compras` varchar(5) DEFAULT NULL,
  `gestiona_clientes` varchar(5) DEFAULT NULL,
  `es_pep_familiar` varchar(5) DEFAULT NULL,
  `listas_antecedentes` varchar(20) DEFAULT NULL,
  `conflicto_interes` varchar(5) DEFAULT NULL,
  `relacion_clientes_proveedores` varchar(5) DEFAULT NULL,
  `actividades_externas` text DEFAULT NULL,
  `ingresos_adicionales` varchar(100) DEFAULT NULL,
  `fuente_ingresos_adicionales` varchar(255) DEFAULT NULL,
  `patrimonio_aproximado` varchar(100) DEFAULT NULL,
  `cambio_patrimonial` text DEFAULT NULL,
  `doc_identidad_estado` varchar(20) DEFAULT NULL,
  `listas_pep_estado` varchar(20) DEFAULT NULL,
  `soportes_economicos_estado` varchar(20) DEFAULT NULL,
  `soportes_relacion_estado` varchar(20) DEFAULT NULL,
  `observaciones_analista` text DEFAULT NULL,
  `estado_expediente` varchar(20) DEFAULT NULL,
  `version_matriz_institucional` varchar(30) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `analista_responsable` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `apellidos` varchar(120) DEFAULT NULL,
  `nombres` varchar(120) DEFAULT NULL,
  `provincia_cod` varchar(2) DEFAULT NULL,
  `canton_cod` varchar(4) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `celular` varchar(30) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kyc_proveedor_pj` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `origen` enum('interno','externo') NOT NULL DEFAULT 'interno',
  `razon_social` varchar(255) DEFAULT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `pais_constitucion` varchar(5) DEFAULT NULL,
  `fecha_constitucion` date DEFAULT NULL,
  `domicilio` varchar(255) DEFAULT NULL,
  `actividad_economica_id` int(11) DEFAULT NULL,
  `representante_legal` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `web` varchar(150) DEFAULT NULL,
  `estructura_societaria_adjunta` varchar(5) DEFAULT NULL,
  `bf_identificados` varchar(5) DEFAULT NULL,
  `kyc_bf_anexos` varchar(20) DEFAULT NULL,
  `cambios_propiedad` text DEFAULT NULL,
  `bien_servicio` varchar(255) DEFAULT NULL,
  `monto_anual_estimado` varchar(100) DEFAULT NULL,
  `criticidad` varchar(20) DEFAULT NULL,
  `acceso_critico` varchar(5) DEFAULT NULL,
  `subcontrata_terceros` varchar(5) DEFAULT NULL,
  `paises_subcontratistas` varchar(255) DEFAULT NULL,
  `listas_sanciones` varchar(20) DEFAULT NULL,
  `pep_propiedad_admin` varchar(5) DEFAULT NULL,
  `conflicto_interes` varchar(5) DEFAULT NULL,
  `litigios_sanciones` text DEFAULT NULL,
  `programa_cumplimiento` varchar(20) DEFAULT NULL,
  `cuenta_bancaria_empresa` varchar(5) DEFAULT NULL,
  `banco_pais` varchar(150) DEFAULT NULL,
  `forma_pago_id` int(10) UNSIGNED DEFAULT NULL,
  `pagos_terceros` varchar(255) DEFAULT NULL,
  `doc_identidad_estado` varchar(20) DEFAULT NULL,
  `listas_pep_estado` varchar(20) DEFAULT NULL,
  `soportes_economicos_estado` varchar(20) DEFAULT NULL,
  `soportes_relacion_estado` varchar(20) DEFAULT NULL,
  `observaciones_analista` text DEFAULT NULL,
  `estado_expediente` varchar(20) DEFAULT NULL,
  `version_matriz_institucional` varchar(30) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `analista_responsable` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rl_apellidos` varchar(120) DEFAULT NULL,
  `rl_nombres_pila` varchar(120) DEFAULT NULL,
  `rl_correo` varchar(150) DEFAULT NULL,
  `rl_celular` varchar(30) DEFAULT NULL,
  `rl_provincia_cod` varchar(2) DEFAULT NULL,
  `rl_canton_cod` varchar(4) DEFAULT NULL,
  `rl_direccion` varchar(255) DEFAULT NULL,
  `rl_cedula` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kyc_proveedor_pn` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `origen` enum('interno','externo') NOT NULL DEFAULT 'interno',
  `nombres_completos` varchar(255) DEFAULT NULL,
  `cedula_pasaporte` varchar(30) DEFAULT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `actividad_oficio_id` int(11) DEFAULT NULL,
  `direccion_residencia` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `antiguedad_experiencia` varchar(150) DEFAULT NULL,
  `bien_servicio` varchar(255) DEFAULT NULL,
  `monto_anual_estimado` varchar(100) DEFAULT NULL,
  `frecuencia` varchar(150) DEFAULT NULL,
  `acceso_critico` varchar(255) DEFAULT NULL,
  `subcontrata_terceros` varchar(5) DEFAULT NULL,
  `intermediarios_gestores` varchar(5) DEFAULT NULL,
  `es_pep` varchar(5) DEFAULT NULL,
  `listas_restrictivas` varchar(20) DEFAULT NULL,
  `conflicto_interes` varchar(5) DEFAULT NULL,
  `relacion_empleados_clientes` varchar(5) DEFAULT NULL,
  `noticias_sanciones` text DEFAULT NULL,
  `cuenta_bancaria_propia` varchar(5) DEFAULT NULL,
  `banco_pais` varchar(150) DEFAULT NULL,
  `forma_pago_id` int(10) UNSIGNED DEFAULT NULL,
  `pago_terceros` varchar(255) DEFAULT NULL,
  `origen_cuentas_alternas` text DEFAULT NULL,
  `doc_identidad_estado` varchar(20) DEFAULT NULL,
  `listas_pep_estado` varchar(20) DEFAULT NULL,
  `soportes_economicos_estado` varchar(20) DEFAULT NULL,
  `soportes_relacion_estado` varchar(20) DEFAULT NULL,
  `observaciones_analista` text DEFAULT NULL,
  `estado_expediente` varchar(20) DEFAULT NULL,
  `version_matriz_institucional` varchar(30) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `analista_responsable` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `apellidos` varchar(120) DEFAULT NULL,
  `nombres` varchar(120) DEFAULT NULL,
  `provincia_cod` varchar(2) DEFAULT NULL,
  `canton_cod` varchar(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mapa_riesgos` (
  `id` int(11) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `kyc_registro_id` int(11) NOT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `puntaje_consolidado` int(11) DEFAULT NULL,
  `nivel_global` varchar(30) DEFAULT NULL,
  `observacion_general` text DEFAULT NULL,
  `elaboro_nombre` varchar(150) DEFAULT NULL,
  `elaboro_fecha` date DEFAULT NULL,
  `reviso_nombre` varchar(150) DEFAULT NULL,
  `reviso_fecha` date DEFAULT NULL,
  `aprobacion_superior_nombre` varchar(200) DEFAULT NULL,
  `aprobacion_referencia` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mapa_riesgos_detalle` (
  `id` int(11) NOT NULL,
  `mapa_riesgos_id` int(11) NOT NULL,
  `evento_codigo` varchar(10) NOT NULL,
  `probabilidad` varchar(20) DEFAULT NULL,
  `impacto` varchar(20) DEFAULT NULL,
  `forma_control` varchar(20) DEFAULT NULL,
  `tipo_control` varchar(20) DEFAULT NULL,
  `plan_accion` text DEFAULT NULL,
  `responsable` varchar(150) DEFAULT NULL,
  `fecha_objetivo` date DEFAULT NULL,
  `estado_plan` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `matriz_avanzada_controles` (
  `id` int(11) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `kyc_registro_id` int(11) NOT NULL,
  `control_key` varchar(20) NOT NULL,
  `efectividad` decimal(3,2) DEFAULT NULL,
  `responsable` varchar(150) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `matriz_avanzada_decision` (
  `id` int(11) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `kyc_registro_id` int(11) NOT NULL,
  `ri` decimal(6,2) DEFAULT NULL,
  `e` decimal(4,2) DEFAULT NULL,
  `rr` decimal(6,2) DEFAULT NULL,
  `pisos_seleccionados` varchar(255) DEFAULT NULL,
  `piso_maximo` decimal(6,2) DEFAULT NULL,
  `rf` decimal(6,2) DEFAULT NULL,
  `nivel` varchar(20) DEFAULT NULL,
  `diligencia_estado` varchar(20) DEFAULT NULL,
  `decision_competente` varchar(40) DEFAULT NULL,
  `sustento_restricciones` text DEFAULT NULL,
  `acciones_responsable_plazo` text DEFAULT NULL,
  `proxima_revision` date DEFAULT NULL,
  `elaboro_nombre` varchar(150) DEFAULT NULL,
  `elaboro_fecha` date DEFAULT NULL,
  `reviso_nombre` varchar(150) DEFAULT NULL,
  `reviso_fecha` date DEFAULT NULL,
  `aprobacion_superior_nombre` varchar(150) DEFAULT NULL,
  `aprobacion_referencia` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `matriz_institucional_factor` (
  `id` int(11) NOT NULL,
  `version_id` int(11) NOT NULL,
  `factor_key` varchar(60) NOT NULL,
  `inherente` tinyint(1) DEFAULT NULL,
  `eficacia_pct` decimal(5,2) DEFAULT NULL,
  `plan_accion` text DEFAULT NULL,
  `responsable` varchar(150) DEFAULT NULL,
  `fecha_plan` date DEFAULT NULL,
  `estado_plan` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `matriz_institucional_version` (
  `id` int(11) NOT NULL,
  `version_num` int(11) NOT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `total` decimal(5,2) DEFAULT NULL,
  `nivel` varchar(20) DEFAULT NULL,
  `estado` enum('borrador','aprobada') NOT NULL DEFAULT 'borrador',
  `aprobado_por` varchar(150) DEFAULT NULL,
  `fecha_aprobacion` date DEFAULT NULL,
  `proxima_revision` date DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `matriz_scores` (
  `id` int(11) NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `kyc_registro_id` int(11) NOT NULL,
  `factor_key` varchar(60) NOT NULL,
  `score` tinyint(1) DEFAULT NULL,
  `evidencia` text DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `moneda` (
  `codigo` char(3) NOT NULL,
  `nombre` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `operaciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_operacion` varchar(50) NOT NULL,
  `origen_tipo` varchar(20) NOT NULL,
  `origen_id` int(10) UNSIGNED NOT NULL,
  `tipo_operacion` char(3) NOT NULL,
  `tipo_financiamiento` char(3) NOT NULL DEFAULT 'NAP',
  `valor_financiamiento_directo` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `valor_financiamiento_externo` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `valor_total_operacion` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `fecha_operacion` date NOT NULL,
  `estado` enum('borrador','completo','anulado') NOT NULL DEFAULT 'borrador',
  `observaciones` text DEFAULT NULL,
  `creado_por` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `operacion_bienes` (
  `id` int(10) UNSIGNED NOT NULL,
  `operacion_id` int(10) UNSIGNED NOT NULL,
  `anio_fabricacion` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `tipo_bien` char(3) NOT NULL DEFAULT '',
  `modelo` varchar(50) NOT NULL DEFAULT '',
  `marca` varchar(50) NOT NULL DEFAULT '',
  `numero_chasis` varchar(50) NOT NULL DEFAULT '',
  `cilindraje` varchar(9) NOT NULL DEFAULT '',
  `nivel_blindaje` varchar(2) NOT NULL DEFAULT 'NO',
  `condicion_bien` char(1) NOT NULL DEFAULT 'N',
  `numero_placa` varchar(9) NOT NULL DEFAULT 'NO APLICA',
  `cod_provincia` char(2) NOT NULL DEFAULT '',
  `cod_canton` char(4) NOT NULL DEFAULT '',
  `cod_parroquia` char(6) NOT NULL DEFAULT '',
  `valor` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `operacion_transacciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `operacion_id` int(10) UNSIGNED NOT NULL,
  `fecha_transaccion` date NOT NULL,
  `numero_transaccion` varchar(40) NOT NULL,
  `tipo_transaccion` varchar(3) NOT NULL,
  `valor_debito` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `valor_credito` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `valor_efectivo` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `valor_cheque` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `valor_tarjeta` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `valor_titulo` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `valor_total` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `cod_moneda` char(3) NOT NULL DEFAULT 'USD',
  `cantidad_bad_50` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `monto_bad_50` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `cantidad_bad_100` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `monto_bad_100` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `cod_agencia` varchar(9) NOT NULL DEFAULT '',
  `pago_cobro` char(3) NOT NULL DEFAULT 'PRS',
  `tipo_id_tercero` char(1) NOT NULL DEFAULT 'N',
  `id_tercero` varchar(20) NOT NULL DEFAULT 'NO APLICA',
  `nombres_tercero` varchar(150) NOT NULL DEFAULT 'NO APLICA',
  `apellidos_tercero` varchar(150) NOT NULL DEFAULT 'NO APLICA',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `origen_fondos` (
  `id` int(10) UNSIGNED NOT NULL,
  `origen_tipo` varchar(20) NOT NULL,
  `origen_id` int(10) UNSIGNED NOT NULL,
  `operacion_id` int(10) UNSIGNED DEFAULT NULL,
  `fecha` date NOT NULL,
  `monto` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `cod_moneda` char(3) NOT NULL DEFAULT 'USD',
  `fuente_id` int(10) UNSIGNED DEFAULT NULL,
  `forma_pago_id` int(10) UNSIGNED DEFAULT NULL,
  `entidad_financiera` varchar(150) DEFAULT NULL,
  `cuenta` varchar(50) DEFAULT NULL,
  `cod_provincia` char(2) DEFAULT NULL,
  `cod_canton` char(4) DEFAULT NULL,
  `cod_parroquia` char(6) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `sustento` text DEFAULT NULL,
  `coherencia` enum('si','parcial','no') DEFAULT NULL,
  `declarante` varchar(150) DEFAULT NULL,
  `estado` enum('borrador','validado','observado') NOT NULL DEFAULT 'borrador',
  `creado_por` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `paises` (
  `id` int(11) NOT NULL,
  `cod` varchar(5) DEFAULT NULL,
  `pais` varchar(125) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `pais_origen_destino` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(8) NOT NULL,
  `descripcion` varchar(200) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `parroquia` (
  `id` bigint(20) NOT NULL,
  `parroquia` text NOT NULL,
  `id_canton` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

CREATE TABLE `provincias` (
  `id` bigint(20) NOT NULL,
  `provincia` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

CREATE TABLE `resu_config` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `cdr` varchar(5) NOT NULL DEFAULT '',
  `identificacion_reporte` varchar(50) NOT NULL DEFAULT 'RESU',
  `umbral_usd` int(10) UNSIGNED NOT NULL DEFAULT 10000,
  `usr` varchar(20) NOT NULL DEFAULT '',
  `cod_agencia` varchar(9) NOT NULL DEFAULT '',
  `direccion_default` varchar(150) NOT NULL DEFAULT '',
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `resu_historial` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo_reporte` varchar(12) NOT NULL,
  `periodo` char(7) NOT NULL,
  `archivo` varchar(150) NOT NULL DEFAULT '',
  `registros` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `validacion` varchar(255) DEFAULT NULL,
  `generado_por` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `modulos` varchar(500) NOT NULL DEFAULT '',
  `es_admin` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `segundo_factor_intentos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tipo_transaccion` (
  `codigo` smallint(6) NOT NULL,
  `descripcion` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id_usuarios` int(11) NOT NULL,
  `cedula` varchar(12) NOT NULL DEFAULT '',
  `usuario` varchar(50) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `estado` varchar(2) NOT NULL DEFAULT 'A',
  `cambio_clave` varchar(2) NOT NULL DEFAULT '0',
  `nombres` varchar(250) NOT NULL,
  `direccion` varchar(250) DEFAULT NULL,
  `celular` varchar(15) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `email` varchar(125) NOT NULL,
  `rol` varchar(45) NOT NULL,
  `rol_id` int(10) UNSIGNED DEFAULT NULL,
  `foto` varchar(125) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_codigo_recuperacion` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `codigo_hash` varchar(255) NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_invitaciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `anulado_en` datetime DEFAULT NULL,
  `creado_por` varchar(150) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_permisos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `modulo` varchar(30) NOT NULL,
  `nivel` enum('ver','editar') NOT NULL DEFAULT 'editar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_segundo_factor` (
  `usuario_id` int(11) NOT NULL,
  `secreto_cifrado` varbinary(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_paso_usado` bigint(20) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `activado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `actividad_economica`
  ADD PRIMARY KEY (`id_actividad`);

ALTER TABLE `ai_conversaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_conv_usuario` (`usuario_id`);

ALTER TABLE `ai_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_log_usuario` (`usuario_id`),
  ADD KEY `idx_ai_log_fecha` (`creado_en`);

ALTER TABLE `ai_mensajes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_msg_conv` (`conversacion_id`);

ALTER TABLE `alertas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave_dedupe` (`clave_dedupe`),
  ADD KEY `idx_alerta_tipo` (`tipo`),
  ADD KEY `idx_alerta_estado` (`estado`),
  ADD KEY `idx_alerta_severidad` (`severidad`),
  ADD KEY `idx_alerta_origen` (`origen_tipo`,`origen_id`);

ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aud_usuario` (`usuario_id`),
  ADD KEY `idx_aud_modulo` (`modulo`),
  ADD KEY `idx_aud_accion` (`accion`),
  ADD KEY `idx_aud_entidad` (`entidad_tipo`,`entidad_id`),
  ADD KEY `idx_aud_fecha` (`creado_en`);

ALTER TABLE `canton`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_canton_provincia_id` (`id_provincia`);

ALTER TABLE `casos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `ix_casos_sujeto` (`sujeto_tipo`,`sujeto_id`),
  ADD KEY `ix_casos_estado` (`estado`);

ALTER TABLE `casos_vinculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `caso_vinculo_unico` (`caso_id`,`tipo`,`referencia_id`,`referencia_codigo`),
  ADD KEY `fk_cv_caso` (`caso_id`);

ALTER TABLE `cat_condicion_bien`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_dpa_canton`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_dpa_parroquia`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_dpa_provincia`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_forma_pago`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_forma_pago_nombre` (`nombre`);

ALTER TABLE `cat_nivel_blindaje`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_operacion_transaccion`
  ADD PRIMARY KEY (`tipo_operacion`,`tipo_transaccion`);

ALTER TABLE `cat_origen_fondos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_origen_fondos_nombre` (`nombre`);

ALTER TABLE `cat_tipo_bien`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_tipo_financiamiento`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_tipo_identificacion`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_tipo_identificacion_tercero`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_tipo_operacion`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_tipo_pago_cobro`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_tipo_persona`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `cat_tipo_transaccion`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `ciudad`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `debida_diligencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dd_tipo_registro` (`tipo`,`kyc_registro_id`),
  ADD KEY `idx_dd_estado` (`estado`);

ALTER TABLE `debida_diligencia_archivos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dda_debida_categoria` (`debida_id`,`categoria`),
  ADD KEY `idx_dda_debida` (`debida_id`);

ALTER TABLE `firma_consentimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_consentimiento_solicitud` (`solicitud_id`);

ALTER TABLE `firma_solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_firma_token` (`token_hash`),
  ADD KEY `ix_firma_registro` (`tipo`,`registro_id`),
  ADD KEY `ix_firma_callback` (`callback_hash`);

ALTER TABLE `kyc_adjuntos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tipo_registro_doc` (`tipo`,`registro_id`,`documento_clave`);

ALTER TABLE `kyc_beneficiario_final`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

ALTER TABLE `kyc_cliente_pj`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

ALTER TABLE `kyc_cliente_pj_sp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

ALTER TABLE `kyc_cliente_pn`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

ALTER TABLE `kyc_cliente_pn_sp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

ALTER TABLE `kyc_empleado`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

ALTER TABLE `kyc_proveedor_pj`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

ALTER TABLE `kyc_proveedor_pn`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

ALTER TABLE `mapa_riesgos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tipo_registro` (`tipo`,`kyc_registro_id`);

ALTER TABLE `mapa_riesgos_detalle`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mapa_evento` (`mapa_riesgos_id`,`evento_codigo`),
  ADD KEY `fk_mrd_mapa` (`mapa_riesgos_id`);

ALTER TABLE `matriz_avanzada_controles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tipo_registro_control` (`tipo`,`kyc_registro_id`,`control_key`);

ALTER TABLE `matriz_avanzada_decision`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tipo_registro` (`tipo`,`kyc_registro_id`);

ALTER TABLE `matriz_institucional_factor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `version_factor` (`version_id`,`factor_key`),
  ADD KEY `fk_mif_version` (`version_id`);

ALTER TABLE `matriz_institucional_version`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `matriz_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tipo_registro_factor` (`tipo`,`kyc_registro_id`,`factor_key`);

ALTER TABLE `moneda`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `operaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_op_origen` (`origen_tipo`,`origen_id`),
  ADD KEY `idx_op_fecha` (`fecha_operacion`),
  ADD KEY `idx_op_numero` (`numero_operacion`),
  ADD KEY `idx_op_estado` (`estado`);

ALTER TABLE `operacion_bienes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bien_op` (`operacion_id`);

ALTER TABLE `operacion_transacciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tra_operacion` (`operacion_id`),
  ADD KEY `idx_tra_fecha` (`fecha_transaccion`);

ALTER TABLE `origen_fondos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_of_origen` (`origen_tipo`,`origen_id`),
  ADD KEY `idx_of_fecha` (`fecha`);

ALTER TABLE `paises`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pais_origen_destino`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

ALTER TABLE `parroquia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_parroquia_canton_id` (`id_canton`);

ALTER TABLE `provincias`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `resu_config`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `resu_historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resu_periodo` (`periodo`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

ALTER TABLE `segundo_factor_intentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sfi_usuario` (`usuario_id`,`creado_en`),
  ADD KEY `idx_sfi_ip` (`ip`,`creado_en`);

ALTER TABLE `tipo_transaccion`
  ADD PRIMARY KEY (`codigo`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuarios`),
  ADD UNIQUE KEY `usuario` (`usuario`);

ALTER TABLE `usuario_codigo_recuperacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ucr_usuario` (`usuario_id`);

ALTER TABLE `usuario_invitaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inv_token` (`token_hash`),
  ADD KEY `idx_inv_usuario` (`usuario_id`);

ALTER TABLE `usuario_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_modulo` (`usuario_id`,`modulo`),
  ADD KEY `fk_up_usuario` (`usuario_id`);

ALTER TABLE `usuario_segundo_factor`
  ADD PRIMARY KEY (`usuario_id`);

ALTER TABLE `actividad_economica`
  MODIFY `id_actividad` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ai_conversaciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ai_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ai_mensajes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `alertas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `auditoria`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `canton`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `casos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `casos_vinculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `cat_forma_pago`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `cat_origen_fondos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ciudad`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `debida_diligencia`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `debida_diligencia_archivos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `firma_consentimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `firma_solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kyc_adjuntos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kyc_beneficiario_final`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kyc_cliente_pj`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kyc_cliente_pj_sp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kyc_cliente_pn`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kyc_cliente_pn_sp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kyc_empleado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kyc_proveedor_pj`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `kyc_proveedor_pn`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `mapa_riesgos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `mapa_riesgos_detalle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `matriz_avanzada_controles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `matriz_avanzada_decision`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `matriz_institucional_factor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `matriz_institucional_version`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `matriz_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `operaciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `operacion_bienes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `operacion_transacciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `origen_fondos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `paises`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pais_origen_destino`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `parroquia`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `provincias`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `resu_historial`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `segundo_factor_intentos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `usuarios`
  MODIFY `id_usuarios` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `usuario_codigo_recuperacion`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `usuario_invitaciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `usuario_permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ai_mensajes`
  ADD CONSTRAINT `fk_ai_msg_conv` FOREIGN KEY (`conversacion_id`) REFERENCES `ai_conversaciones` (`id`) ON DELETE CASCADE;

ALTER TABLE `canton`
  ADD CONSTRAINT `FK_canton_provincia_id` FOREIGN KEY (`id_provincia`) REFERENCES `provincias` (`id`);

ALTER TABLE `casos_vinculos`
  ADD CONSTRAINT `fk_cv_caso` FOREIGN KEY (`caso_id`) REFERENCES `casos` (`id`) ON DELETE CASCADE;

ALTER TABLE `debida_diligencia_archivos`
  ADD CONSTRAINT `fk_dda_debida` FOREIGN KEY (`debida_id`) REFERENCES `debida_diligencia` (`id`) ON DELETE CASCADE;

ALTER TABLE `mapa_riesgos_detalle`
  ADD CONSTRAINT `fk_mrd_mapa` FOREIGN KEY (`mapa_riesgos_id`) REFERENCES `mapa_riesgos` (`id`) ON DELETE CASCADE;

ALTER TABLE `matriz_institucional_factor`
  ADD CONSTRAINT `fk_mif_version` FOREIGN KEY (`version_id`) REFERENCES `matriz_institucional_version` (`id`) ON DELETE CASCADE;

ALTER TABLE `operacion_bienes`
  ADD CONSTRAINT `fk_bien_operacion` FOREIGN KEY (`operacion_id`) REFERENCES `operaciones` (`id`) ON DELETE CASCADE;

ALTER TABLE `operacion_transacciones`
  ADD CONSTRAINT `fk_tra_operacion` FOREIGN KEY (`operacion_id`) REFERENCES `operaciones` (`id`) ON DELETE CASCADE;

ALTER TABLE `usuario_permisos`
  ADD CONSTRAINT `fk_up_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id_usuarios`) ON DELETE CASCADE;

CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
