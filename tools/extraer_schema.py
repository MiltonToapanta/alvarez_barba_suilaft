"""Separa el volcado phpMyAdmin de SUIAUTO en:
  - schema.sql     -> CREATE TABLE + índices + FK (sin AUTO_INCREMENT=N ni datos)
  - catalogos.sql  -> INSERT solo de tablas catálogo (sin datos de clientes/usuarios)
Uso: python3 -I extraer_schema.py <dump.sql> <dir_salida>
"""
import re, sys, os

CATALOGOS = {
    'actividad_economica', 'canton', 'ciudad', 'moneda', 'paises',
    'pais_origen_destino', 'parroquia', 'provincias', 'roles', 'tipo_transaccion',
    'cat_condicion_bien', 'cat_dpa_canton', 'cat_dpa_parroquia', 'cat_dpa_provincia',
    'cat_forma_pago', 'cat_nivel_blindaje', 'cat_operacion_transaccion',
    'cat_origen_fondos', 'cat_tipo_bien', 'cat_tipo_financiamiento',
    'cat_tipo_identificacion', 'cat_tipo_identificacion_tercero', 'cat_tipo_operacion',
    'cat_tipo_pago_cobro', 'cat_tipo_persona', 'cat_tipo_transaccion',
}

dump, salida = sys.argv[1], sys.argv[2]
with open(dump, encoding='utf-8') as f:
    lineas = f.read().splitlines()

# Agrupa en sentencias: una sentencia termina en una línea que acaba con ';'
sentencias, actual = [], []
for ln in lineas:
    s = ln.strip()
    if not actual and (s == '' or s.startswith('--') or s.startswith('/*!')):
        continue
    actual.append(ln)
    if s.endswith(';'):
        sentencias.append('\n'.join(actual))
        actual = []

schema, datos, tablas = [], [], []
for st in sentencias:
    m = re.match(r'\s*(CREATE TABLE|INSERT INTO|ALTER TABLE)\s+`([^`]+)`', st)
    if not m:
        continue  # SET, START TRANSACTION, COMMIT...
    tipo, tabla = m.groups()
    if tipo == 'CREATE TABLE':
        tablas.append(tabla)
        schema.append(st)
    elif tipo == 'ALTER TABLE':
        st = re.sub(r',\s*AUTO_INCREMENT=\d+', '', st)
        schema.append(st)
    elif tabla in CATALOGOS:
        datos.append(st)

os.makedirs(salida, exist_ok=True)
cab = "SET NAMES utf8mb4;\nSET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSET FOREIGN_KEY_CHECKS = 0;\n\n"
with open(os.path.join(salida, 'schema.sql'), 'w', encoding='utf-8') as f:
    f.write("-- Estructura replicada de SUIAUTO (sin datos). Generado por extraer_schema.py\n")
    f.write(cab + '\n\n'.join(schema))
    # Laravel borra `migrations` antes de cargar el schema y espera que el archivo la cree.
    f.write("\n\nCREATE TABLE `migrations` (\n"
            "  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n"
            "  `migration` varchar(255) NOT NULL,\n"
            "  `batch` int(11) NOT NULL,\n"
            "  PRIMARY KEY (`id`)\n"
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n")
    f.write("\nSET FOREIGN_KEY_CHECKS = 1;\n")
with open(os.path.join(salida, 'catalogos.sql'), 'w', encoding='utf-8') as f:
    f.write("-- Catálogos de SUIAUTO (UAFE / DPA / países / monedas / roles)\n")
    f.write(cab + '\n\n'.join(datos) + "\n\nSET FOREIGN_KEY_CHECKS = 1;\n")

print(f"tablas: {len(tablas)}  sentencias schema: {len(schema)}  inserts catálogo: {len(datos)}")
print("tablas con datos:", sorted({re.match(r'\s*INSERT INTO `([^`]+)`', d).group(1) for d in datos}))
print("faltan catálogos:", sorted(CATALOGOS - set(tablas)))
