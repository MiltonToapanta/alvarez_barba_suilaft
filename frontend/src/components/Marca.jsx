import escudo from '../assets/escudo-color.png';
import logoCliente from '../assets/logo-alvarez-barba.png';

/** Panel de marca (izquierda), igual al de SUIAUTO + sello del cliente. */
export default function Marca() {
  return (
    <aside className="au-marca" aria-label="SUIAUTO · Alvarez Barba S.A.">
      <div className="au-orbes" aria-hidden="true">
        <span className="au-orbe au-orbe-1" />
        <span className="au-orbe au-orbe-2" />
        <span className="au-orbe au-orbe-3" />
      </div>

      <span className="au-escudo-wrap">
        <span className="au-escudo-halo" aria-hidden="true" />
        <span className="au-escudo-plate" aria-hidden="true" />
        <img src={escudo} alt="" className="au-escudo" />
      </span>
      <div className="au-nombre">SUIAUTO</div>
      <div className="au-lema">Sistema integral de cumplimiento automotriz</div>

      <img src={logoCliente} alt="Alvarez Barba S.A. — Fundada en 1932" className="au-cliente" />

      <div className="au-brillo" aria-hidden="true" />

      <h2>Prevención de lavado de activos <span>y cumplimiento</span></h2>
      <div className="au-subrayado" aria-hidden="true" />
      <p className="au-intro">
        Plataforma de cumplimiento LA/FT para el sector vehicular: conocimiento del cliente, matrices de
        riesgo, debida diligencia, alertas y reportería, con trazabilidad completa.
      </p>

      <ul className="au-features">
        <li>
          <span className="au-feat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /><path d="M8 13h8M8 17h5" /></svg>
          </span>
          <div>
            <strong>Formularios KYC</strong>
            <p>Clientes, proveedores, empleados y beneficiarios finales, con enlace seguro para el llenado externo.</p>
          </div>
        </li>
        <li>
          <span className="au-feat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 3v18h18" /><path d="M7 15l4-4 3 3 5-6" /></svg>
          </span>
          <div>
            <strong>Riesgo y debida diligencia</strong>
            <p>Matriz individual e institucional, con evidencia documental conservada.</p>
          </div>
        </li>
        <li>
          <span className="au-feat-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
          </span>
          <div>
            <strong>Alertas y reportería</strong>
            <p>Motor de alertas, bitácora de auditoría y archivos RESU, con acceso protegido por doble autenticación.</p>
          </div>
        </li>
      </ul>

      <div className="au-via" aria-hidden="true">
        <span className="au-via-linea" />
        <span className="au-via-auto">
          <svg viewBox="0 0 64 28" xmlns="http://www.w3.org/2000/svg">
            <path fill="currentColor" d="M4 20h2a4 4 0 0 1 8 0h20a4 4 0 0 1 8 0h4a4 4 0 0 0 4-4v-3a3 3 0 0 0-1.2-2.4l-7-5.2A6 6 0 0 0 38.2 4H18a5 5 0 0 0-3.9 1.9L9 13H6a4 4 0 0 0-4 4v1a2 2 0 0 0 2 2Z" />
            <circle cx="14" cy="20" r="3.4" fill="#08111f" />
            <circle cx="42" cy="20" r="3.4" fill="#08111f" />
          </svg>
        </span>
      </div>
    </aside>
  );
}
