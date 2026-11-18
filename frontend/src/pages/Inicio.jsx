import { api } from '../api.js';
import { useAuth } from '../App.jsx';
import escudo from '../assets/escudo-color.png';

/** Placeholder del dashboard: aquí irán los módulos (KYC, matrices, alertas, RESU...). */
export default function Inicio() {
  const { usuario, refrescar } = useAuth();

  const salir = async () => {
    await api.post('/auth/logout').catch(() => {});
    refrescar();
  };

  return (
    <div className="ab-inicio">
      <header className="ab-barra">
        <div className="ab-barra-marca">
          <img src={escudo} alt="" />
          <span>SUIAUTO · Alvarez Barba S.A.</span>
        </div>
        <div className="ab-barra-usuario">
          <span>{usuario?.nombres} <small>({usuario?.rol})</small></span>
          <button type="button" className="au-btn-outline" onClick={salir}>Cerrar sesión</button>
        </div>
      </header>
      <main className="ab-contenido">
        <h1>Bienvenido/a, {usuario?.nombres}</h1>
        <p>Sesión iniciada con doble autenticación. Los módulos del sistema se irán habilitando aquí.</p>
        <p className="ab-modulos">Módulos de tu rol: {usuario?.modulos?.join(', ') || '—'}</p>
      </main>
    </div>
  );
}
