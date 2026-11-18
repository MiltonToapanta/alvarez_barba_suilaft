import LogoAB from './LogoAB.jsx';

/** Panel de marca (izquierda) con la identidad de Alvarez Barba S.A. */
export default function Marca() {
  return (
    <aside className="au-marca ab-marca" aria-label="Alvarez Barba S.A.">
      <span className="ab-marco" aria-hidden="true" />
      <span className="ab-destello" aria-hidden="true" />
      <span className="ab-polvo" aria-hidden="true">
        {Array.from({ length: 14 }, (_, i) => <i key={i} />)}
      </span>

      <LogoAB tono="claro" forma="vertical" />

      <div className="ab-filete" aria-hidden="true"><span /></div>

      <h2>Cumplimiento y prevención <span>de lavado de activos</span></h2>
      <p className="au-intro">
        Portal corporativo de cumplimiento LA/FT: conocimiento del cliente, matrices de riesgo,
        debida diligencia, alertas y reportería a la UAFE, con trazabilidad completa.
      </p>

      <ul className="au-features">
        <li>
          <span className="au-feat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /><path d="M8 13h8M8 17h5" /></svg>
          </span>
          <div>
            <strong>Conozca a su cliente</strong>
            <p>Clientes, proveedores, empleados y beneficiarios finales, con enlace seguro para el llenado externo.</p>
          </div>
        </li>
        <li>
          <span className="au-feat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M3 3v18h18" /><path d="M7 15l4-4 3 3 5-6" /></svg>
          </span>
          <div>
            <strong>Riesgo y debida diligencia</strong>
            <p>Matriz individual e institucional, con evidencia documental conservada.</p>
          </div>
        </li>
        <li>
          <span className="au-feat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
          </span>
          <div>
            <strong>Alertas y reportería</strong>
            <p>Motor de alertas, bitácora de auditoría y archivos RESU, con acceso por doble autenticación.</p>
          </div>
        </li>
      </ul>

      <p className="ab-plataforma">Plataforma SUIAUTO · SUILAFT</p>
    </aside>
  );
}
