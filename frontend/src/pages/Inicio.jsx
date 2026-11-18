import { Link } from 'react-router-dom';
import { useAuth } from '../App.jsx';
import { modulosVisibles } from '../modulos.js';
import Icono from '../components/Icono.jsx';

/** Panel de inicio (SUIAUTO app/views/home/dashboard.php). */
export default function Inicio() {
  const { usuario } = useAuth();
  const modulos = modulosVisibles(usuario);

  return (
    <section className="hero">
      <span className="badge"><Icono nombre="bank" /> PANEL CORPORATIVO ALVAREZ BARBA</span>
      <h1>Hola, <span className="accent">{usuario?.nombres || usuario?.usuario}</span></h1>
      <p>Elige una opción para continuar con la gestión de cumplimiento vehicular</p>

      {modulos.length === 0 ? (
        <p className="vacio">Tu rol todavía no tiene módulos asignados. Pide acceso al administrador.</p>
      ) : (
        <div className="modules-grid">
          {modulos.map((m) => (
            <Link key={m.ruta} to={'/' + m.ruta} className="module-card">
              <span className="module-icon"><Icono nombre={m.icon} /></span>
              <strong>{m.titulo}</strong>
              <p>{m.desc}</p>
              <span className="module-bar" />
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}
