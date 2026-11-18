import { useEffect } from 'react';
import Marca from './Marca.jsx';

export default function LayoutAcceso({ titulo, children }) {
  useEffect(() => {
    document.title = `Alvarez Barba | ${titulo}`;
  }, [titulo]);

  return (
    <div className="au-layout">
      <Marca />
      <main className="au-acceso">
        <div className="au-card">
          {children}
        </div>
        <p className="au-copy">
          © {new Date().getFullYear()} Alvarez Barba S.A. · Plataforma SUIAUTO · SUILAFT. Todos los derechos reservados.
        </p>
      </main>
    </div>
  );
}

export function Alerta({ tipo = 'error', children }) {
  if (!children) return null;
  return (
    <div className={`au-alert au-alert--${tipo}`} role={tipo === 'error' ? 'alert' : 'status'}>
      {children}
    </div>
  );
}

export function Volver({ onClick }) {
  return (
    <button type="button" className="au-volver" onClick={onClick}>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M19 12H5" /><path d="m12 19-7-7 7-7" /></svg>
      Volver
    </button>
  );
}

export function Chevron() {
  return (
    <svg className="au-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
  );
}
