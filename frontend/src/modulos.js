/** Módulos del portal (igual que SUIAUTO app/config/modulos.php).
 *  `ruta` coincide con la clave de roles.modulos / usuario_permisos. */
export const MODULOS = [
  { icon: 'chart', titulo: 'Dashboards', desc: 'KPIs, indicadores y visión ejecutiva del cumplimiento', ruta: 'dashboards' },
  { icon: 'users', titulo: 'Usuarios', desc: 'Gestión de cuentas y roles', ruta: 'usuarios' },
  { icon: 'id', titulo: 'Formularios KYC', desc: 'Alta y gestión de KYC: clientes, proveedores, empleados y beneficiarios finales', ruta: 'kyc' },
  { icon: 'car', titulo: 'Vehículos y Operaciones', desc: 'Compra, venta, financiamiento y pagos', ruta: 'operaciones' },
  { icon: 'check', titulo: 'Debida Diligencia', desc: 'Listas restrictivas, PEP y validación documental', ruta: 'debida' },
  { icon: 'chart', titulo: 'Matriz de Riesgo Individual', desc: 'Perfilamiento, scoring y clasificación de riesgo', ruta: 'matriz' },
  { icon: 'map', titulo: 'Mapa de Riesgos LA/FT', desc: '16 eventos de riesgo, probabilidad/impacto, controles y mapa de calor', ruta: 'mapariesgos' },
  { icon: 'bank', titulo: 'Matriz Institucional', desc: 'Factores, ponderación, umbrales y políticas', ruta: 'institucional' },
  { icon: 'bell', titulo: 'Motor de Alertas', desc: 'Riesgo, transacciones, coincidencias en listas y vencimientos', ruta: 'alertas' },
  { icon: 'folder', titulo: 'Gestión de Casos', desc: 'Investigaciones, seguimiento y cierre formal de contrapartes de mayor riesgo', ruta: 'casos' },
  { icon: 'brain', titulo: 'Inteligencia Artificial', desc: 'SUILAFT AI: asistente de cumplimiento, proyectos, normativa y documentos', ruta: 'ia' },
  { icon: 'report', titulo: 'Reportería', desc: 'RESU, ROS, RIA · historial y exportación', ruta: 'reporteria' },
  { icon: 'audit', titulo: 'Auditoría', desc: 'Trazabilidad y registros', ruta: 'auditoria' },
];

/** Admin ve todo; los demás solo los módulos de su rol/permisos (como SUIAUTO). */
export function modulosVisibles(usuario) {
  if (!usuario) return [];
  if (usuario.es_admin) return MODULOS;
  const permitidos = usuario.modulos ?? [];
  return MODULOS.filter((m) => permitidos.includes(m.ruta));
}
