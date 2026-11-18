/**
 * Logo de Alvarez Barba S.A.: logotipo tipográfico (serif) + "Fundada en 1932".
 *  - tono:  'claro' (sobre fondo oscuro) | 'oscuro' (sobre fondo claro)
 *  - forma: 'vertical' (login, centrado y grande) | 'horizontal' (barra superior, compacto)
 */
export default function LogoAB({ tono = 'claro', forma = 'vertical' }) {
  return (
    <div className={`ab-logo ab-logo--${tono} ab-logo--${forma}`} role="img" aria-label="Alvarez Barba S.A., fundada en 1932">
      <span className="ab-logo-nombre" aria-hidden="true">Alvarez Barba <small>S.A.</small></span>
      <span className="ab-logo-fundada" aria-hidden="true">Fundada en 1932</span>
    </div>
  );
}
