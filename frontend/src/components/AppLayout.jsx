import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { api } from '../api.js';
import { useAuth } from '../App.jsx';
import { modulosVisibles } from '../modulos.js';
import Icono from './Icono.jsx';
import LogoAB from './LogoAB.jsx';

function ahora() {
  const d = new Date();
  return d.toLocaleTimeString('es-EC', { hour12: false }) + ' · ' +
    d.toLocaleDateString('es-EC', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
}

function Reloj() {
  const [txt, setTxt] = useState(ahora);
  useEffect(() => {
    const t = setInterval(() => setTxt(ahora()), 1000);
    return () => clearInterval(t);
  }, []);
  return <div className="pill clock">{txt}</div>;
}

/** Marco del portal: barra lateral de módulos + barra superior (como SUIAUTO app_header.php). */
export default function AppLayout() {
  const { usuario, refrescar } = useAuth();
  const [abierto, setAbierto] = useState(false);
  const { pathname } = useLocation();
  const modulos = modulosVisibles(usuario);

  useEffect(() => { setAbierto(false); }, [pathname]);

  const salir = async () => {
    await api.post('/auth/logout').catch(() => {});
    refrescar();
  };

  return (
    <div className="app-shell">
      <aside className={'sidebar' + (abierto ? ' open' : '')} aria-label="Módulos">
        <NavLink to="/" end className={({ isActive }) => 'side-item' + (isActive ? ' active' : '')} title="Inicio" aria-label="Inicio">
          <Icono nombre="home" />
        </NavLink>
        {modulos.map((m) => (
          <NavLink key={m.ruta} to={'/' + m.ruta} className={({ isActive }) => 'side-item' + (isActive ? ' active' : '')} title={m.titulo} aria-label={m.titulo}>
            <Icono nombre={m.icon} />
          </NavLink>
        ))}
      </aside>
      <div className="sidebar-velo" onClick={() => setAbierto(false)} />

      <div className="app-main">
        <header className="topbar">
          <button type="button" className="icon-btn" aria-label="Menú" onClick={() => setAbierto((v) => !v)}>
            <Icono nombre="menu" />
          </button>

          <Link to="/" className="brand" aria-label="Inicio · Alvarez Barba S.A.">
            <LogoAB tono="oscuro" forma="horizontal" />
            <span className="brand-sistema">Portal de cumplimiento<b>SUIAUTO · SUILAFT</b></span>
          </Link>

          <div className="topbar-right">
            <Reloj />
            <div className="pill user"><Icono nombre="users" /> {usuario?.nombres || usuario?.usuario}</div>
            <div className="pill role"><Icono nombre="shield" /> {usuario?.rol}</div>
            <button type="button" className="btn-logout" onClick={salir}><Icono nombre="logout" /> Salir</button>
          </div>
        </header>

        <main className="content">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
